<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\JobMatchingController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobMatchingProviderTest extends TestCase
{
    private array $providerLogs = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Isolate all fixtures and caches from the configured application database.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
            'cache.default' => 'array',
            'services.gemini.key' => 'gemini-test-secret',
            'services.gemini.model' => 'gemini-3.5-flash-lite',
            'services.gemini.matching_timeout' => 15,
            'services.gemini.matching_connect_timeout' => 5,
            'services.openai.key' => 'openai-test-secret',
            'services.openai.matching_enabled' => true,
            'services.openai.matching_quota_cooldown' => 1800,
            'services.job_matching.fallback_cache_seconds' => 60,
        ]);
        DB::purge('sqlite');
        Cache::flush();
        Http::preventStrayRequests();

        // The production distance scope uses MySQL trigonometric functions.
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('radians', fn ($v) => deg2rad($v));
        $pdo->sqliteCreateFunction('sin', fn ($v) => sin($v));
        $pdo->sqliteCreateFunction('cos', fn ($v) => cos($v));
        $pdo->sqliteCreateFunction('acos', fn ($v) => acos(max(-1, min(1, $v))));

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('role');
        });
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('skillset');
            $table->text('available_days');
            $table->string('time_slot');
            $table->double('latitude');
            $table->double('longitude');
        });
        foreach (['employers', 'households'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
            });
        }
        Schema::create('available_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->string('category');
            $table->text('skills');
            $table->text('available_days');
            $table->string('time_slot');
            $table->string('status');
            $table->double('latitude');
            $table->double('longitude');
        });
        DB::table('users')->insert([
            ['id' => 1, 'email' => 'student@example.test', 'role' => 'student'],
            ['id' => 2, 'email' => 'hirer@example.test', 'role' => 'employer'],
        ]);
        DB::table('students')->insert([
            'id' => 1, 'user_id' => 1, 'skillset' => '["Customer Service"]',
            'available_days' => '["Monday","Friday"]', 'time_slot' => 'Morning',
            'latitude' => 14.5, 'longitude' => 121,
        ]);
        DB::table('available_jobs')->insert([
            'id' => 10, 'user_id' => 2, 'title' => 'Shop Assistant', 'category' => 'Retail',
            'skills' => '["Customer Service"]', 'available_days' => '["Monday","Wednesday"]',
            'time_slot' => 'Morning', 'status' => 'active', 'latitude' => 14.51, 'longitude' => 121.01,
        ]);

        foreach (['warning', 'error'] as $level) {
            Log::shouldReceive($level)->andReturnUsing(function ($message, $context = []) {
                $this->providerLogs[] = [$message, $context];
            });
        }
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function matchedJobs(): array
    {
        $request = Request::create('/api/student/matched-jobs', 'GET');
        $request->setUserResolver(fn () => User::findOrFail(1));
        $response = (new JobMatchingController)->getMatchedJobs($request);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        return $response->getData(true);
    }

    private function geminiScores(int $score): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode([
            ['id' => 10, 'match_percentage' => $score],
        ])]]]]]];
    }

    public function test_gemini_uses_header_authentication_and_configured_timeouts(): void
    {
        Http::fake(function (ClientRequest $request, array $options) {
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent', $request->url());
            $this->assertTrue($request->hasHeader('x-goog-api-key', 'gemini-test-secret'));
            $this->assertSame(15, $options['timeout']);
            $this->assertSame(5, $options['connect_timeout']);

            return Http::response($this->geminiScores(88));
        });

        $result = $this->matchedJobs();
        $this->assertSame('ai_skills_schedule', $result['matched_by']);
        $this->assertSame(88, $result['matched_jobs'][0]['match_percentage']);
        Http::assertSentCount(1);
    }

    public function test_timeout_and_exhausted_credits_use_math_without_leaking_keys_or_repeating_openai(): void
    {
        $geminiCalls = $openaiCalls = 0;
        Http::fake(function (ClientRequest $request) use (&$geminiCalls, &$openaiCalls) {
            if (str_contains($request->url(), 'generativelanguage.googleapis.com')) {
                $geminiCalls++;
                throw new ConnectionException('Timed out at https://example.test/?key=gemini-test-secret');
            }
            $openaiCalls++;

            return Http::response(['error' => ['code' => 'credit_balance_exhausted', 'type' => 'insufficient_quota']], 429);
        });

        $result = $this->matchedJobs();
        $this->assertSame('fallback_math', $result['matched_by']);
        $this->assertSame(50, $result['matched_jobs'][0]['match_percentage']);
        $this->matchedJobs(); // Same profile receives the cached result.
        $this->assertSame(1, $geminiCalls);

        $this->travel(61)->seconds();
        $this->matchedJobs(); // Gemini retries; the exhausted OpenAI key stays in cooldown.
        $this->assertSame(2, $geminiCalls);
        $this->assertSame(1, $openaiCalls);
        $this->assertStringNotContainsString('gemini-test-secret', json_encode($this->providerLogs));
        $this->assertStringNotContainsString('openai-test-secret', json_encode($this->providerLogs));
    }

    public function test_invalid_provider_output_falls_back_and_primary_can_recover_after_one_minute(): void
    {
        $geminiCalls = 0;
        Http::fake(function (ClientRequest $request) use (&$geminiCalls) {
            if (str_contains($request->url(), 'generativelanguage.googleapis.com')) {
                $geminiCalls++;

                return Http::response($geminiCalls > 1 ? $this->geminiScores(95)
                    : ['candidates' => [['content' => ['parts' => [['text' => 'not valid JSON']]]]]]);
            }

            return Http::response(['choices' => [['message' => ['content' => '[]']]]]);
        });

        $this->assertSame('fallback_math', $this->matchedJobs()['matched_by']);
        $this->travel(61)->seconds();
        $result = $this->matchedJobs();
        $this->assertSame('ai_skills_schedule', $result['matched_by']);
        $this->assertSame(95, $result['matched_jobs'][0]['match_percentage']);
    }

    public function test_valid_zero_scores_are_not_treated_as_provider_failures(): void
    {
        Http::fake(fn () => Http::response($this->geminiScores(0)));

        $result = $this->matchedJobs();
        $this->assertSame('ai_skills_schedule', $result['matched_by']);
        $this->assertSame([], $result['matched_jobs']);
        Http::assertSentCount(1);
    }

    public static function backupScores(): array
    {
        return ['positive match' => [82], 'valid zero match' => [0]];
    }

    #[DataProvider('backupScores')]
    public function test_valid_openai_scores_are_used_when_gemini_output_is_invalid(int $score): void
    {
        Http::fake(function (ClientRequest $request) use ($score) {
            if (str_contains($request->url(), 'generativelanguage.googleapis.com')) {
                return Http::response(['candidates' => [['content' => ['parts' => [['text' => 'not JSON']]]]]]);
            }

            return Http::response(['choices' => [['message' => ['content' => json_encode([
                ['id' => 10, 'match_percentage' => $score],
            ])]]]]);
        });

        $result = $this->matchedJobs();
        $this->assertSame('ai_openai', $result['matched_by']);
        if ($score === 0) {
            $this->assertSame([], $result['matched_jobs']);
        } else {
            $this->assertSame($score, $result['matched_jobs'][0]['match_percentage']);
        }
        Http::assertSentCount(2);
    }

    public function test_normal_rate_limit_does_not_trigger_the_credit_cooldown(): void
    {
        $openaiCalls = 0;
        Http::fake(function (ClientRequest $request) use (&$openaiCalls) {
            if (str_contains($request->url(), 'generativelanguage.googleapis.com')) {
                return Http::response([], 503);
            }
            $openaiCalls++;

            return Http::response(['error' => ['code' => 'rate_limit_exceeded', 'type' => 'rate_limit_error']], 429);
        });

        $this->assertSame('fallback_math', $this->matchedJobs()['matched_by']);
        $this->travel(61)->seconds();
        $this->matchedJobs();
        $this->assertSame(2, $openaiCalls);
    }

    public function test_openai_backup_can_be_disabled_without_disabling_schedule_matching(): void
    {
        config(['services.openai.matching_enabled' => false]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);

        $this->assertSame('fallback_math', $this->matchedJobs()['matched_by']);
        Http::assertSentCount(1);
    }
}
