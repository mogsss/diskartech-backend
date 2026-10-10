<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApplicantWorkplaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
        ]);
        Schema::create('students', function (Blueprint $table) { $table->id(); });
        Schema::create('available_jobs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('title');
        });
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('job_id'); $table->unsignedBigInteger('student_id');
            $table->string('status'); $table->timestamps();
        });
        foreach (['employers', 'households'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('user_id');
                $table->string('location')->nullable(); $table->string('detailed_address')->nullable();
                $table->string('valid_id_path')->nullable();
            });
        }
        DB::table('students')->insert(['id' => 10]);
        DB::table('available_jobs')->insert(['id' => 5, 'user_id' => 1, 'title' => 'Student helper']);
        DB::table('job_applications')->insert(['id' => 27, 'job_id' => 5, 'student_id' => 10, 'status' => 'pending']);
    }

    public static function owners(): array
    {
        return [['employer', 'employers', 'household'], ['household', 'households', 'employer']];
    }

    #[DataProvider('owners')]
    public function test_details_include_the_job_owners_address_without_verification_documents(string $role, string $table, string $other): void
    {
        // Profile IDs differ from user IDs, as they do in the deployed database.
        DB::table($table)->insert([
            'id' => 42, 'user_id' => 1, 'location' => 'Pinamalayan, Mimaropa',
            'detailed_address' => 'Example Street', 'valid_id_path' => 'private-document.jpg',
        ]);
        $user = new User;
        $user->id = 1;
        $user->role = $role;
        Sanctum::actingAs($user);
        $response = $this->getJson('/api/employer/applications/27')->assertOk()
            ->assertJsonPath("application.job.$role.location", 'Pinamalayan, Mimaropa')
            ->assertJsonPath("application.job.$role.detailed_address", 'Example Street')
            ->assertJsonPath("application.job.$other", null)
            ->assertJsonPath('application.status', 'viewed');
        $this->assertArrayNotHasKey('valid_id_path', $response->json("application.job.$role"));
    }
}
