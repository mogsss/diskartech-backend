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
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Sino ang nag-post
            $table->string('title');
            $table->text('description');
            $table->decimal('salary', 10, 2);
            $table->text('schedules'); // Sasalo sa schedule options (Flexible, Weekdays, etc.)
            $table->text('requirements')->nullable(); // Sasalo sa requirements list
            $table->text('skills')->nullable(); // Sasalo sa skills needed list
            $table->string('status')->default('active'); // active, closed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};