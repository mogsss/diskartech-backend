<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HirerNameProfileUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
        ]);
        Schema::create('employers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('employer_name');
            $table->string('hirer_name')->nullable();
            $table->string('business_type')->nullable();
            $table->boolean('isVerified')->default(true);
            $table->timestamps();
        });
        DB::table('employers')->insert([
            ['user_id' => 1, 'employer_name' => 'Example Business', 'hirer_name' => 'Original Hirer'],
            ['user_id' => 2, 'employer_name' => 'Other Business', 'hirer_name' => 'Other Hirer'],
        ]);
    }

    private function login(string $role = 'employer'): void
    {
        $user = new User;
        $user->id = 1;
        $user->role = $role;
        Sanctum::actingAs($user);
    }

    public function test_hirer_name_changes_independently_of_business_name_and_other_accounts(): void
    {
        $this->login();
        $this->postJson('/api/user/update-profile', ['hirer_name' => '  Updated Hirer  '])
            ->assertOk()->assertJsonPath('profile.hirer_name', 'Updated Hirer')
            ->assertJsonPath('profile.employer_name', 'Example Business');
        $this->assertDatabaseHas('employers', ['user_id' => 1, 'hirer_name' => 'Updated Hirer', 'isVerified' => true]);
        $this->assertDatabaseHas('employers', ['user_id' => 2, 'hirer_name' => 'Other Hirer']);
    }

    public function test_omitting_hirer_name_preserves_it_for_older_clients(): void
    {
        $this->login();
        $this->postJson('/api/user/update-profile', ['employer_name' => 'New Business'])
            ->assertOk()->assertJsonPath('profile.hirer_name', 'Original Hirer')
            ->assertJsonPath('profile.employer_name', 'New Business');
    }

    public static function invalidNames(): array
    {
        return [[''], ['   '], [null], [42], [['Name']], [str_repeat('x', 256)]];
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_hirer_name_does_not_save_any_changes(mixed $name): void
    {
        $this->login();
        $this->postJson('/api/user/update-profile', ['hirer_name' => $name, 'employer_name' => 'Must not save'])
            ->assertUnprocessable()->assertJsonValidationErrors('hirer_name');
        $this->assertDatabaseHas('employers', ['user_id' => 1, 'hirer_name' => 'Original Hirer', 'employer_name' => 'Example Business']);
    }

    public function test_household_representative_uses_its_existing_name_field(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('household_name');
            $table->timestamps();
        });
        DB::table('households')->insert(['user_id' => 1, 'household_name' => 'Original Representative']);
        $this->login('household');
        $this->postJson('/api/user/update-profile', ['household_name' => 'Updated Representative', 'hirer_name' => 'Ignore this'])
            ->assertOk()->assertJsonPath('profile.household_name', 'Updated Representative');
        $this->assertDatabaseHas('employers', ['user_id' => 1, 'hirer_name' => 'Original Hirer']);
    }

    public function test_missing_employer_profile_does_not_update_another_account(): void
    {
        DB::table('employers')->where('user_id', 1)->delete();
        $this->login();
        $this->postJson('/api/user/update-profile', ['hirer_name' => 'New Hirer'])->assertNotFound();
        $this->assertDatabaseHas('employers', ['user_id' => 2, 'hirer_name' => 'Other Hirer']);
    }
}
