<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('available_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->nullable()->constrained('employers')->onDelete('cascade');
            $table->foreignId('household_id')->nullable()->constrained('households')->onDelete('cascade');
            $table->string('job_name');
            $table->text('job_description');
            $table->string('job_location');
            $table->string('job_schedule');
            $table->decimal('rate', 10, 2);
            $table->string('span'); // hal. Part-time, Weekly, etc.
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('available_jobs');
    }
};