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
        Schema::table('job_applications', function (Blueprint $table) {
            $table->string('start_date')->nullable()->after('interview_type');
            $table->string('work_schedule')->nullable()->after('start_date');
            $table->string('agreed_rate')->nullable()->after('work_schedule');
            $table->text('special_instructions')->nullable()->after('agreed_rate');
            $table->timestamp('hired_at')->nullable()->after('special_instructions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'work_schedule', 'agreed_rate', 'special_instructions', 'hired_at']);
        });
    }
};
