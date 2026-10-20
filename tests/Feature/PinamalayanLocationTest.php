<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PinamalayanLocation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PinamalayanLocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:']);
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('email')->nullable(); });
        Schema::create('available_jobs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id');
            $table->double('latitude')->nullable(); $table->double('longitude')->nullable(); $table->timestamps();
        });
        foreach (['students', 'employers', 'households'] as $table) {
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->id(); $blueprint->unsignedBigInteger('user_id');
                $blueprint->string('location')->nullable(); $blueprint->string('detailed_address')->nullable();
                $blueprint->double('latitude')->nullable(); $blueprint->double('longitude')->nullable(); $blueprint->timestamps();
            });
            DB::table($table)->insert(['user_id' => 1, 'latitude' => 13.0386691, 'longitude' => 121.4801563, 'location' => 'Pinamalayan']);
        }
    }

    public static function roles(): array
    {
        return [['student', 'students'], ['employer', 'employers'], ['household', 'households']];
    }

    private function authenticate(string $role): void
    {
        $user = new User; $user->id = 1; $user->role = $role;
        Sanctum::actingAs($user);
    }

    #[DataProvider('roles')]
    public function test_valid_profile_locations_save_for_all_picker_roles(string $role, string $table): void
    {
        $this->authenticate($role);
        $this->postJson('/api/user/update-profile', ['latitude' => '13.0386691', 'longitude' => '121.4801563',
            'location' => "  Pinamalayan\x00  ", 'detailed_address' => "  Street\x00\u{202E}  "])
            ->assertOk()->assertJsonPath('profile.location', 'Pinamalayan')->assertJsonPath('profile.detailed_address', 'Street');
        $this->assertDatabaseHas($table, ['user_id' => 1, 'latitude' => 13.0386691, 'longitude' => 121.4801563]);
    }

    #[DataProvider('roles')]
    public function test_outside_locations_cannot_overwrite_saved_profiles(string $role, string $table): void
    {
        $this->authenticate($role);
        $this->postJson('/api/user/update-profile', ['latitude' => 13.059, 'longitude' => 121.397, 'location' => 'Pinamalayan'])
            ->assertUnprocessable()->assertJsonValidationErrors('longitude');
        $this->assertDatabaseHas($table, ['user_id' => 1, 'latitude' => 13.0386691, 'longitude' => 121.4801563]);
    }

    #[DataProvider('roles')]
    public function test_registration_rejects_outside_coordinates_before_creating_accounts(string $role, string $table): void
    {
        $this->postJson('/api/register/'.$role, ['first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@example.com',
            'password' => 'Password123', 'phone' => '09123456789', 'school_name' => 'School', 'course' => 'Course',
            'year_level' => '4th Year', 'business_name' => 'Business', 'business_type' => 'Retail',
            'latitude' => 13.4115, 'longitude' => 121.1803])->assertUnprocessable()->assertJsonValidationErrors('longitude');
        $this->assertDatabaseCount('users', 0);
    }

    public static function invalidCoordinates(): array
    {
        return [
            [['latitude' => 13.0386691], 'longitude'],
            [['longitude' => 121.4801563], 'latitude'],
            [['latitude' => 'invalid', 'longitude' => 121.4801563], 'latitude'],
            [['latitude' => [], 'longitude' => 121.4801563], 'latitude'],
            [['latitude' => 91, 'longitude' => 121.4801563], 'latitude'],
            [['latitude' => 13.0386691, 'longitude' => 181], 'longitude'],
            [['latitude' => 13.11, 'longitude' => 121.25], 'longitude'],
            [['location' => '<script>bad</script>'], 'location'],
            [['detailed_address' => '&lt;b&gt;bad&lt;/b&gt;'], 'detailed_address'],
        ];
    }

    #[DataProvider('invalidCoordinates')]
    public function test_malformed_inputs_are_rejected(array $input, string $field): void
    {
        $this->authenticate('student');
        $this->postJson('/api/user/update-profile', $input)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_profile_edits_without_coordinates_leave_existing_location_intact(): void
    {
        $this->authenticate('student');
        DB::table('students')->where('user_id', 1)->update(['latitude' => 13.4115, 'longitude' => 121.1803]);
        $this->postJson('/api/user/update-profile', ['detailed_address' => 'Unit 1'])->assertOk();
        $this->assertDatabaseHas('students', ['user_id' => 1, 'latitude' => 13.4115, 'longitude' => 121.1803]);
    }

    public function test_boundary_points_and_numeric_types(): void
    {
        $boundary = json_decode(file_get_contents(resource_path('data/pinamalayan-boundary.json')), true);
        [$lng, $lat] = $boundary['geometry']['coordinates'][0][0];
        $this->assertTrue(PinamalayanLocation::contains($lat, $lng));
        foreach ([[13.145, 121.441], [12.9197, 121.487], [null, null], [true, 121.4801563], [NAN, 121.4801563], [13.0386691, INF]] as [$lat, $lng]) {
            $this->assertFalse(PinamalayanLocation::contains($lat, $lng));
        }
        $request = Request::create('/', 'POST', ['latitude' => null, 'longitude' => null]);
        $rules = PinamalayanLocation::rules($request);
        $this->assertTrue(Validator::make($request->all(), $rules)->passes());
    }
}
