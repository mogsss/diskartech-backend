<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationPhoneTest extends TestCase
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
        foreach (['students', 'employers', 'households'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                foreach (['student_name', 'student_school_name', 'course', 'student_schedule', 'year_level',
                    'contact_number', 'employer_name', 'hirer_name', 'business_type', 'household_name',
                    'cp_number', 'gender', 'location', 'detailed_address', 'avatar'] as $field) {
                    $table->string($field)->nullable();
                }
                $table->integer('age')->nullable();
                $table->double('latitude')->nullable();
                $table->double('longitude')->nullable();
                $table->boolean('isVerified')->default(false);
                $table->boolean('isSubscribed')->default(false);
                $table->timestamps();
            });
        }
    }

    public static function roles(): array
    {
        return [['student', 'students', 'contact_number'], ['employer', 'employers', 'contact_number'],
            ['household', 'households', 'cp_number']];
    }

    private function payload(): array
    {
        return ['first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@example.com',
            'password' => 'Password123!', 'age' => 18, 'school_name' => 'Test School',
            'course' => 'BSIT', 'year_level' => '4th Year', 'business_name' => 'Test Business',
            'business_type' => 'Retail'];
    }

    #[DataProvider('roles')]
    public function test_invalid_numbers_cannot_create_accounts_or_tokens(string $role, string $table, string $column): void
    {
        $invalid = [[], ['phone' => null], ['phone' => ''], ['phone' => 9123456789],
            ['phone' => ['09123456789']], ['phone' => '0912345678'], ['phone' => '091234567890'],
            ['phone' => '08123456789'], ['phone' => '+639123456789'], ['phone' => '09abc456789'],
            ['phone' => '09123 56789'], ['phone' => "09123\n56789"], ['phone' => str_repeat('9', 1000)]];
        foreach ([false, true] as $google) {
            foreach ($invalid as $phone) {
                $this->postJson('/api/register/'.$role, [...$this->payload(),
                    'registered_with_google' => $google, ...$phone])
                    ->assertUnprocessable()->assertJsonValidationErrors('phone');
            }
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount($table, 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[DataProvider('roles')]
    public function test_valid_numbers_keep_the_leading_zero_for_password_and_google_registration(string $role, string $table, string $column): void
    {
        foreach (['09123456789', '09987654321'] as $index => $phone) {
            $this->postJson('/api/register/'.$role, [...$this->payload(),
                'email' => "user{$index}@example.com", 'phone' => $phone, 'registered_with_google' => $index === 1])
                ->assertCreated()->assertJsonPath('status', 'success');
            $this->assertDatabaseHas($table, [$column => $phone]);
        }
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }
}
