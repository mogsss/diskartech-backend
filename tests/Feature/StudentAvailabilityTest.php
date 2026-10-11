<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentAvailabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:']);
        Schema::create('students', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id');
            $table->text('available_days')->nullable(); $table->string('time_slot')->nullable(); $table->timestamps();
        });
        DB::table('students')->insert(['user_id' => 1, 'available_days' => '["Mon"]', 'time_slot' => 'Whole Day']);
        $user = new User;
        $user->id = 1; $user->role = 'student';
        Sanctum::actingAs($user);
    }

    public function test_two_slots_save_and_reload_in_canonical_order(): void
    {
        $this->postJson('/api/student/update-availability', [
            'days' => ['Mon', 'Wed'],
            'time_slot' => "  Evening (6PM - 10PM) & Morning (6AM - 12PM)\x00  ",
        ])->assertOk()->assertJsonPath('profile.time_slot', 'Morning (6AM - 12PM) & Evening (6PM - 10PM)');
        $this->getJson('/api/student/profile')->assertOk()
            ->assertJsonPath('profile.available_days', ['Mon', 'Wed'])
            ->assertJsonPath('profile.time_slot', 'Morning (6AM - 12PM) & Evening (6PM - 10PM)');
    }

    public function test_single_slots_and_whole_day_still_save(): void
    {
        foreach (['Morning (6AM - 12PM)', 'Afternoon (1PM - 5PM)', 'Evening (6PM - 10PM)', 'Whole Day'] as $slot) {
            $this->postJson('/api/student/update-availability', ['days' => ['Tue'], 'time_slot' => $slot])
                ->assertOk()->assertJsonPath('profile.time_slot', $slot);
        }
    }

    public static function invalidSchedules(): array
    {
        return [
            'three slots' => ['time_slot', 'Morning (6AM - 12PM) & Afternoon (1PM - 5PM) & Evening (6PM - 10PM)', 'time_slot'],
            'whole day combined' => ['time_slot', 'Whole Day & Morning (6AM - 12PM)', 'time_slot'],
            'duplicate slots' => ['time_slot', 'Morning (6AM - 12PM) & Morning (6AM - 12PM)', 'time_slot'],
            'markup' => ['time_slot', '<script>alert(1)</script>', 'time_slot'],
            'unknown slot' => ['time_slot', 'Midnight', 'time_slot'],
            'trailing delimiter' => ['time_slot', 'Whole Day & ', 'time_slot'],
            'array slot' => ['time_slot', ['Whole Day'], 'time_slot'],
            'empty days' => ['days', [], 'days'],
            'unknown day' => ['days', ['Mon', '<script>'], 'days.1'],
            'duplicate days' => ['days', ['Mon', 'Mon'], 'days.0'],
        ];
    }

    #[DataProvider('invalidSchedules')]
    public function test_invalid_availability_is_not_saved(string $field, mixed $value, string $error): void
    {
        $this->postJson('/api/student/update-availability', array_replace(['days' => ['Mon'], 'time_slot' => 'Whole Day'], [$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->assertDatabaseHas('students', ['user_id' => 1, 'available_days' => '["Mon"]', 'time_slot' => 'Whole Day']);
    }

    public function test_profile_updates_remain_scoped_to_the_signed_in_student(): void
    {
        $user = new User;
        $user->id = 2; $user->role = 'student';
        Sanctum::actingAs($user);
        $this->postJson('/api/student/update-availability', ['days' => ['Fri'], 'time_slot' => 'Whole Day', 'user_id' => 1])->assertNotFound();
        $this->assertDatabaseHas('students', ['user_id' => 1, 'available_days' => '["Mon"]']);
    }
}
