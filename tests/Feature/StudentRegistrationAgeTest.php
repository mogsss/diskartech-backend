<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentRegistrationAgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:']);
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('registered_with_google')->default(false);
            $table->boolean('isEmailVerified')->default(false);
        });
        (require database_path('migrations/2026_08_14_025124_create_personal_access_tokens_table.php'))->up();
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            foreach (['student_name', 'student_school_name', 'course', 'student_schedule', 'year_level',
                'contact_number', 'gender', 'location', 'detailed_address', 'avatar'] as $field) {
                $table->string($field)->nullable();
            }
            $table->integer('age');
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->boolean('isVerified')->default(false);
            $table->timestamps();
        });
    }

    private function payload(): array
    {
        return ['first_name' => 'Test', 'last_name' => 'Student', 'email' => 'student@example.com',
            'password' => 'Password123!', 'phone' => '09123456789', 'school_name' => 'Test School',
            'course' => 'BSIT', 'year_level' => '4th Year'];
    }

    public function test_invalid_or_missing_age_cannot_create_accounts_or_issue_tokens(): void
    {
        $invalid = [[], ['age' => null], ['age' => ''], ['age' => 17], ['age' => 0],
            ['age' => -18], ['age' => 'abc'], ['age' => '18years'], ['age' => '18.5']];
        foreach ([false, true] as $google) {
            foreach ($invalid as $age) {
                $this->postJson('/api/register/student', [...$this->payload(),
                    'registered_with_google' => $google, ...$age])
                    ->assertUnprocessable()->assertJsonValidationErrors('age');
            }
        }
        foreach (['users', 'students', 'personal_access_tokens'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
    }

    public function test_eighteen_year_old_and_older_students_can_register(): void
    {
        foreach ([18, '19', 65] as $i => $age) {
            $this->postJson('/api/register/student', [...$this->payload(), 'email' => "student{$i}@example.com",
                'age' => $age, 'registered_with_google' => $i === 1])
                ->assertCreated()->assertJsonPath('status', 'success');
            $this->assertDatabaseHas('students', ['age' => (int) $age]);
        }
        $this->assertSame(3, DB::table('users')->count());
        $this->assertSame(3, DB::table('personal_access_tokens')->count());
    }
}
