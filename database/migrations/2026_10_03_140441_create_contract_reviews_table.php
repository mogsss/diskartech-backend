<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contract_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('job_applications')->onDelete('cascade');
            $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewee_id')->constrained('users')->onDelete('cascade');
            $table->string('reviewer_role'); // 'employer' or 'student'
            $table->unsignedTinyInteger('rating'); // 1 to 5
            $table->text('feedback')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'reviewer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_reviews');
    }
};
