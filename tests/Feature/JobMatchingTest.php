<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\JobMatchingController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobMatchingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'services.gemini.key' => 'test-gemini-key',
            'services.gemini.model' => 'gemini-3.5-flash-lite',
            'services.gemini.matching_timeout' => 15,
            'services.gemini.matching_connect_timeout' => 5,
            'services.openai.key' => 'test-openai-key',
            'services.openai.matching_enabled' => true,
            'services.openai.matching_timeout' => 5,
            'services.openai.matching_quota_cooldown' => 1800,
            'services.job_matching.fallback_cache_seconds' => 60,
        ]);

        Http::preventStrayRequests();
        Log::spy();

        // Exercise real model queries without running unrelated, MySQL-specific migrations.
        $pdo = DB::connection()->getPdo();
        foreach (['radians' => 'deg2rad', 'cos' => 'cos', 'sin' => 'sin', 'acos' => 'acos'] as $sqlFunction => $phpFunction) {
            $pdo->sqliteCreateFunction($sqlFunction, $phpFunction, 1);
        }

        foreach (['users', 'households', 'employers'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
            });
        }

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('skillset');
            $table->text('available_days');
            $table->string('time_slot');
            $table->double('latitude');
            $table->double('longitude');
        });

        Schema::create('available_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title');
            $table->string('category');
            $table->string('status');
            $table->text('skills');
            $table->text('available_days');
            $table->string('time_slot');
            $table->double('latitude');
            $table->double('longitude');
        });

        DB::table('students')->insert([
            'id' => 1,
            'user_id' => 1,
            'skillset' => json_encode(['Cooking']),
            'available_days' => json_encode(['Monday']),
            'time_slot' => 'Whole Day',
            'latitude' => 14.5,
            'longitude' => 121.0,
        ]);

        DB::table('available_jobs')->insert([
            'id' => 1,
            'title' => 'Cook',
            'category' => 'Food',
            'status' => 'active',
            'skills' => json_encode(['Cooking']),
            'available_days' => json_encode(['Monday', 'Tuesday']),
            'time_slot' => 'Whole Day',
            'latitude' => 14.5,
            'longitude' => 121.0,
        ]);
    }

    public function test_gemini_uses_bounded_timeouts_and_header_authentication_and_caches_results(): void
    {
        Http::fake(function (ClientRequest $request, array $options) {
            $this->assertSame(15, $options['timeout']);
            $this->assertSame(5, $options['connect_timeout']);
            $this->assertTrue($request->hasHeader('x-goog-api-key', 'test-gemini-key'));
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent', $request->url());

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => '[{"id":1,"match_percentage":85}]']]]]]]);
        });

        $result = $this->match();
        $this->assertSame('ai_skills_schedule', $result['matched_by']);
        $this->assertEquals(85, $result['matched_jobs'][0]['match_percentage']);
        $this->assertSame($result, $this->match());
        Http::assertSentCount(1);
    }

    public function test_gemini_timeout_uses_openai_without_logging_the_exception_secret(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::failedConnection('Timed out for https://example.test?key=test-gemini-key'),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '[{"id":1,"match_percentage":75}]']]]]),
        ]);

        $result = $this->match();
        $this->assertSame('ai_openai', $result['matched_by']);
        $this->assertEquals(75, $result['matched_jobs'][0]['match_percentage']);
        Http::assertSent(fn (ClientRequest $request) => $request->hasHeader('Authorization', 'Bearer test-openai-key'));
        Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context) {
            $this->assertStringNotContainsString('test-gemini-key', $message.json_encode($context));

            return $context['timeout_seconds'] === 15;
        });
        Log::shouldNotHaveReceived('error');
    }

    #[DataProvider('quotaErrors')]
    public function test_quota_failures_return_local_matches_and_skip_openai_until_cooldown_expires(string $code, string $type): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 503),
            'api.openai.com/*' => Http::response(['error' => ['code' => $code, 'type' => $type]], 429),
        ]);

        $result = $this->match();
        $this->assertSame('fallback_math', $result['matched_by']);
        $this->assertEquals(50, $result['matched_jobs'][0]['match_percentage']);
        $this->assertSame($result, $this->match());
        Http::assertSentCount(2);

        $this->travel(61)->seconds();
        $this->assertSame('fallback_math', $this->match()['matched_by']);
        Http::assertSentCount(3);

        $this->travel(1800)->seconds();
        $this->assertSame('fallback_math', $this->match()['matched_by']);
        Http::assertSentCount(5);
        Log::shouldNotHaveReceived('error');
    }

    public static function quotaErrors(): array
    {
        return [
            'credits exhausted' => ['credit_balance_exhausted', 'insufficient_quota'],
            'legacy quota code' => ['insufficient_quota', 'insufficient_quota'],
            'quota type' => ['unknown', 'insufficient_quota'],
            'organization spend limit' => ['organization_spend_limit_exceeded', 'billing_error'],
            'organization usage limit' => ['organization_usage_limit_exceeded', 'billing_error'],
        ];
    }

    public function test_temporary_rate_limit_does_not_disable_openai_for_the_quota_cooldown(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 503),
            'api.openai.com/*' => Http::response(['error' => ['code' => 'rate_limit_exceeded', 'type' => 'rate_limit_error']], 429),
        ]);

        $this->assertSame('fallback_math', $this->match()['matched_by']);
        $this->travel(61)->seconds();
        $this->assertSame('fallback_math', $this->match()['matched_by']);
        Http::assertSentCount(4);
    }

    public function test_invalid_provider_json_still_returns_schedule_matches(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'not JSON']]]]]]),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '[{"id":1,"match_percentage":150}]']]]]),
        ]);

        $result = $this->match();
        $this->assertSame('fallback_math', $result['matched_by']);
        $this->assertEquals(50, $result['matched_jobs'][0]['match_percentage']);
        Http::assertSentCount(2);
        Log::shouldNotHaveReceived('error');
    }

    public function test_valid_zero_openai_scores_remain_an_empty_ai_result(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 503),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '[{"id":1,"match_percentage":0}]']]]]),
        ]);

        $result = $this->match();
        $this->assertSame('ai_openai', $result['matched_by']);
        $this->assertSame([], $result['matched_jobs']);
        Http::assertSentCount(2);
    }

    public function test_disabled_openai_backup_returns_schedule_matches(): void
    {
        config(['services.openai.matching_enabled' => false]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);

        $this->assertSame('fallback_math', $this->match()['matched_by']);
        Http::assertSentCount(1);
    }

    private function match(): array
    {
        $request = Request::create('/api/job-matching', 'GET');
        $user = new User;
        $user->id = 1;
        $request->setUserResolver(fn () => $user);

        $response = app(JobMatchingController::class)->getMatchedJobs($request);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        return $response->getData(true);
    }
}
