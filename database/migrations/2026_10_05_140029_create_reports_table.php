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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('reported_user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('job_id')->nullable()->constrained('available_jobs')->onDelete('cascade');
            $table->string('report_type')->default('other'); // scam, abuse, fake_job, underpaid, unsafe, no_show, other
            $table->string('subject')->nullable();
            $table->text('description');
            $table->string('evidence_path')->nullable();
            $table->string('status')->default('pending'); // pending, investigating, resolved, dismissed
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
