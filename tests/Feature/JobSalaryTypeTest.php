<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobSalaryTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
        ]);
        Http::preventStrayRequests();

        // The existing nearby-student query uses MySQL's non-aggregate HAVING.
        // SQLite accepts the same computed alias in WHERE; adapt only the test connection.
        DB::connection()->setQueryGrammar(new class(DB::connection()) extends SQLiteGrammar
        {
            protected function compileHavings(Builder $query)
            {
                $sql = parent::compileHavings($query);
                return $query->from === 'students' && empty($query->groups)
                    ? preg_replace('/^having /', 'and ', $sql)
                    : $sql;
            }
        });

        $pdo = DB::connection()->getPdo();
        foreach (['radians' => 'deg2rad', 'cos' => 'cos', 'sin' => 'sin', 'acos' => 'acos'] as $sql => $php) {
            $pdo->sqliteCreateFunction($sql, $php, 1);
        }

        Schema::create('users', fn (Blueprint $table) => $table->id());
        foreach (['employers', 'households'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->boolean('isVerified');
                $table->double('latitude');
                $table->double('longitude');
            });
        }
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('student_name');
            $table->double('latitude');
            $table->double('longitude');
        });
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reporter_id');
            $table->unsignedBigInteger('job_id')->nullable();
            $table->unsignedBigInteger('reported_user_id')->nullable();
        });
        Schema::create('available_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('description');
            $table->decimal('salary', 10, 2);
            $table->string('category');
            $table->text('available_days');
            $table->string('time_slot');
            $table->text('requirements')->nullable();
            $table->text('skills')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
        $this->migration()->up();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_10_120000_add_salary_type_to_available_jobs_table.php');
    }

    private function login(string $role, bool $verified = true): void
    {
        $user = new User;
        $user->id = 1;
        $user->role = $role;
        Sanctum::actingAs($user);
        DB::table($role === 'household' ? 'households' : 'employers')->insert([
            'user_id' => 1, 'isVerified' => $verified,
            'latitude' => 13.04, 'longitude' => 121.49,
        ]);
    }

    private function payload(): array
    {
        return [
            'title' => 'Student helper', 'description' => 'Assist with tasks.',
            'salary' => '75.50', 'category' => 'General',
            'available_days' => ['Monday'], 'time_slot' => 'Morning (6AM - 12PM)',
        ];
    }

    public static function rolesAndUnits(): array
    {
        return [
            ['employer', 'day'], ['employer', 'hour'],
            ['household', 'day'], ['household', 'hour'],
        ];
    }

    #[DataProvider('rolesAndUnits')]
    public function test_create_read_and_change_rate_unit(string $role, string $unit): void
    {
        $this->login($role);
        $response = $this->postJson('/api/jobs', $this->payload() + ['salary_type' => $unit])
            ->assertCreated()->assertJsonPath('job.salary_type', $unit);
        $id = $response->json('job.id');
        $this->assertDatabaseHas('available_jobs', ['id' => $id, 'salary_type' => $unit, 'salary' => 75.50]);
        $this->getJson("/api/jobs/{$id}")->assertOk()->assertJsonPath('job.salary_type', $unit);
        $changed = $unit === 'day' ? 'hour' : 'day';
        $this->putJson("/api/jobs/{$id}", $this->payload() + ['salary_type' => $changed])
            ->assertOk()->assertJsonPath('job.salary_type', $changed);
        $this->assertDatabaseHas('available_jobs', ['id' => $id, 'salary_type' => $changed]);
    }

    public function test_legacy_clients_default_to_day_and_edits_preserve_existing_unit(): void
    {
        $this->login('employer');
        $id = $this->postJson('/api/jobs', $this->payload())->assertCreated()
            ->assertJsonPath('job.salary_type', 'day')->json('job.id');
        $this->putJson("/api/jobs/{$id}", $this->payload() + ['salary_type' => 'hour'])->assertOk();
        $this->putJson("/api/jobs/{$id}", $this->payload())->assertOk()->assertJsonPath('job.salary_type', 'hour');
    }

    public function test_invalid_units_are_rejected_without_creating_or_changing_jobs(): void
    {
        $this->login('employer');
        $id = $this->postJson('/api/jobs', $this->payload() + ['salary_type' => 'hour'])->assertCreated()->json('job.id');
        foreach (['week', '', null] as $invalid) {
            $payload = $this->payload() + ['salary_type' => $invalid];
            $this->postJson('/api/jobs', $payload)->assertUnprocessable()->assertJsonValidationErrors('salary_type');
            $this->putJson("/api/jobs/{$id}", $payload)->assertUnprocessable()->assertJsonValidationErrors('salary_type');
        }
        $this->assertDatabaseCount('available_jobs', 1);
        $this->assertDatabaseHas('available_jobs', ['id' => $id, 'salary_type' => 'hour']);
    }

    public function test_hourly_jobs_still_require_verified_accounts_and_owner_permissions(): void
    {
        $this->login('household', false);
        $this->postJson('/api/jobs', $this->payload() + ['salary_type' => 'hour'])
            ->assertForbidden()->assertJsonPath('needs_verification', true);
        $this->assertDatabaseCount('available_jobs', 0);
        DB::table('households')->update(['isVerified' => true]);
        $id = $this->postJson('/api/jobs', $this->payload() + ['salary_type' => 'hour'])->assertCreated()->json('job.id');
        DB::table('available_jobs')->where('id', $id)->update(['user_id' => 2]);
        $this->putJson("/api/jobs/{$id}", $this->payload() + ['salary_type' => 'day'])->assertNotFound();
        $this->assertDatabaseHas('available_jobs', ['id' => $id, 'salary_type' => 'hour']);
    }

    public function test_migration_defaults_existing_jobs_to_day_and_can_roll_back(): void
    {
        $this->migration()->down();
        $legacy = $this->payload();
        $legacy['available_days'] = json_encode($legacy['available_days']);
        DB::table('available_jobs')->insert($legacy + ['user_id' => 1]);
        $this->migration()->up();
        $this->assertDatabaseHas('available_jobs', ['salary_type' => 'day']);
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('available_jobs', 'salary_type'));
    }
}
