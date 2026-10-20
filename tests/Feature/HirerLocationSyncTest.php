<?php

namespace Tests\Feature;

use App\Models\Jobs;
use App\Models\User;
use App\Services\EmployerJobQuota;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HirerLocationSyncTest extends TestCase
{
    private const OLD_LAT = 12.999;
    private const OLD_LNG = 121.4801563;
    private const NEW_LAT = 13.04;
    private const NEW_LNG = 121.48;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:']);
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('role'); $table->string('email')->nullable(); $table->timestamps();
        });
        foreach (['students', 'employers', 'households'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('user_id');
                foreach (['student_name', 'employer_name', 'household_name', 'location', 'detailed_address'] as $field) $table->string($field)->nullable();
                $table->double('latitude')->nullable(); $table->double('longitude')->nullable();
                $table->boolean('isSubscribed')->default(false); $table->timestamp('subscription_expires_at')->nullable();
                $table->timestamps();
            });
        }
        Schema::create('available_jobs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('title')->default('Existing job');
            $table->string('status')->default('active');
            $table->double('latitude')->nullable(); $table->double('longitude')->nullable(); $table->timestamps();
        });
        Schema::create('reports', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('reporter_id'); $table->unsignedBigInteger('reported_user_id')->nullable();
            $table->unsignedBigInteger('job_id')->nullable();
        });
        foreach (['radians' => 'deg2rad', 'cos' => 'cos', 'sin' => 'sin', 'acos' => 'acos'] as $sql => $php) {
            DB::connection()->getPdo()->sqliteCreateFunction($sql, $php, 1);
        }
    }

    public static function hirers(): array
    {
        return [['employer', 'employers'], ['household', 'households']];
    }

    private function account(string $role): User
    {
        $id = DB::table('users')->insertGetId(['role' => $role, 'email' => uniqid().'@example.com']);
        DB::table($role.'s')->insert(['user_id' => $id, $role.'_name' => 'Original name', 'location' => 'Old location',
            'latitude' => self::OLD_LAT, 'longitude' => self::OLD_LNG]);
        return User::findOrFail($id);
    }

    private function job(User $owner, string $status = 'active', bool $withoutPin = false): int
    {
        return DB::table('available_jobs')->insertGetId(['user_id' => $owner->id, 'status' => $status,
            'latitude' => $withoutPin ? null : self::OLD_LAT, 'longitude' => $withoutPin ? null : self::OLD_LNG,
            'created_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-10-01 00:00:00']);
    }

    private function location(array $overrides = []): array
    {
        return array_replace(['latitude' => self::NEW_LAT, 'longitude' => self::NEW_LNG,
            'location' => 'Updated Pinamalayan location', 'detailed_address' => 'New street / landmark'], $overrides);
    }

    #[DataProvider('hirers')]
    public function test_saving_a_pin_updates_all_owned_posts_without_changing_their_status_or_other_owners(string $role, string $table): void
    {
        $owner = $this->account($role); $other = $this->account($role);
        foreach (['active', 'hidden', 'closed', 'archived'] as $status) $this->job($owner, $status);
        $this->job($owner, 'active', true);
        $otherJob = $this->job($other);
        Sanctum::actingAs($owner);
        $this->postJson('/api/user/update-profile', $this->location(['user_id' => $other->id]))
            ->assertOk()->assertJsonPath('profile.location', 'Updated Pinamalayan location');
        $this->assertDatabaseHas($table, ['user_id' => $owner->id, ...$this->location()]);
        $this->assertSame(5, DB::table('available_jobs')->where('user_id', $owner->id)
            ->where('latitude', self::NEW_LAT)->where('longitude', self::NEW_LNG)->count());
        $this->assertSame(['active', 'hidden', 'closed', 'archived', 'active'], DB::table('available_jobs')->where('user_id', $owner->id)->orderBy('id')->pluck('status')->all());
        $this->assertSame(5, DB::table('available_jobs')->where('user_id', $owner->id)->where('created_at', '2026-10-01 00:00:00')->count());
        $this->assertDatabaseHas('available_jobs', ['id' => $otherJob, 'latitude' => self::OLD_LAT, 'longitude' => self::OLD_LNG]);
        $this->assertDatabaseHas($table, ['user_id' => $other->id, 'location' => 'Old location']);
    }

    #[DataProvider('hirers')]
    public function test_students_receive_the_updated_job_pin_and_distance_after_profile_save(string $role, string $table): void
    {
        $owner = $this->account($role); $job = $this->job($owner); $student = $this->account('student');
        $before = Jobs::withDistance(13.0386691, 121.4801563)->findOrFail($job)->distance;
        $this->assertGreaterThan(3, (float) $before);
        Sanctum::actingAs($owner);
        $this->postJson('/api/user/update-profile', $this->location())->assertOk();
        Sanctum::actingAs($student);
        $this->getJson('/api/jobs/'.$job)->assertOk()
            ->assertJsonPath('job.latitude', self::NEW_LAT)->assertJsonPath('job.longitude', self::NEW_LNG)
            ->assertJsonPath('job.'.$role.'.location', 'Updated Pinamalayan location');
        $after = Jobs::withDistance(13.0386691, 121.4801563)->findOrFail($job)->distance;
        $this->assertLessThan(1, (float) $after);
    }

    #[DataProvider('hirers')]
    public function test_resaving_the_same_profile_pin_repairs_posts_from_before_the_fix(string $role, string $table): void
    {
        $owner = $this->account($role); $job = $this->job($owner);
        DB::table($table)->where('user_id', $owner->id)->update($this->location());
        Sanctum::actingAs($owner);
        $this->postJson('/api/user/update-profile', $this->location())->assertOk();
        $this->assertDatabaseHas('available_jobs', ['id' => $job, 'latitude' => self::NEW_LAT, 'longitude' => self::NEW_LNG]);
        DB::table('available_jobs')->where('id', $job)->update(['updated_at' => '2026-10-01 00:00:00']);
        $this->postJson('/api/user/update-profile', $this->location())->assertOk();
        $this->assertDatabaseHas('available_jobs', ['id' => $job, 'updated_at' => '2026-10-01 00:00:00']);
    }

    #[DataProvider('hirers')]
    public function test_text_only_profile_edits_preserve_job_coordinates(string $role, string $table): void
    {
        $owner = $this->account($role); $job = $this->job($owner);
        DB::table($table)->where('user_id', $owner->id)->update(['latitude' => self::NEW_LAT, 'longitude' => self::NEW_LNG]);
        Sanctum::actingAs($owner);
        $this->postJson('/api/user/update-profile', ['name' => 'Updated name', 'detailed_address' => 'New landmark'])->assertOk();
        $this->assertDatabaseHas('available_jobs', ['id' => $job, 'latitude' => self::OLD_LAT, 'longitude' => self::OLD_LNG,
            'updated_at' => '2026-10-01 00:00:00']);
    }

    #[DataProvider('hirers')]
    public function test_invalid_pins_cannot_modify_either_profiles_or_posts(string $role, string $table): void
    {
        $owner = $this->account($role); $job = $this->job($owner);
        Sanctum::actingAs($owner);
        foreach ([['latitude' => 13.4115, 'longitude' => 121.1803], ['latitude' => self::NEW_LAT]] as $coordinates) {
            $this->postJson('/api/user/update-profile', ['location' => 'Must not save', ...$coordinates])->assertUnprocessable();
        }
        $this->assertDatabaseHas($table, ['user_id' => $owner->id, 'location' => 'Old location', 'latitude' => self::OLD_LAT]);
        $this->assertDatabaseHas('available_jobs', ['id' => $job, 'latitude' => self::OLD_LAT, 'longitude' => self::OLD_LNG]);
    }

    #[DataProvider('hirers')]
    public function test_a_failed_job_sync_rolls_back_the_profile_update(string $role, string $table): void
    {
        $owner = $this->account($role); $job = $this->job($owner);
        DB::unprepared("CREATE TRIGGER fail_location_sync BEFORE UPDATE ON available_jobs BEGIN SELECT RAISE(ABORT, 'Simulated job update failure'); END");
        Sanctum::actingAs($owner);
        $this->postJson('/api/user/update-profile', $this->location())->assertStatus(500);
        $this->assertDatabaseHas($table, ['user_id' => $owner->id, 'location' => 'Old location', 'latitude' => self::OLD_LAT]);
        $this->assertDatabaseHas('available_jobs', ['id' => $job, 'latitude' => self::OLD_LAT, 'longitude' => self::OLD_LNG]);
    }

    #[DataProvider('hirers')]
    public function test_a_new_post_uses_the_current_pin_even_if_creation_started_with_stale_coordinates(string $role, string $table): void
    {
        $owner = $this->account($role);
        $staleAttributes = ['user_id' => $owner->id, 'latitude' => self::OLD_LAT, 'longitude' => self::OLD_LNG];
        Sanctum::actingAs($owner);
        $this->postJson('/api/user/update-profile', $this->location())->assertOk();
        $job = app(EmployerJobQuota::class)->create($owner, $staleAttributes);
        $this->assertDatabaseHas('available_jobs', ['id' => $job->id, 'latitude' => self::NEW_LAT, 'longitude' => self::NEW_LNG]);
    }

    public function test_student_profile_edits_do_not_sync_job_posts(): void
    {
        $student = $this->account('student'); $job = $this->job($student);
        Sanctum::actingAs($student);
        $this->postJson('/api/user/update-profile', $this->location())->assertOk();
        $this->assertDatabaseHas('available_jobs', ['id' => $job, 'latitude' => self::OLD_LAT, 'longitude' => self::OLD_LNG]);
    }
}
