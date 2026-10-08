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
            $table->string('interview_date')->nullable()->after('status');
            $table->string('interview_time')->nullable()->after('interview_date');
            $table->string('interview_location')->nullable()->after('interview_time');
            $table->string('interview_type')->nullable()->default('walk-in')->after('interview_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn(['interview_date', 'interview_time', 'interview_location', 'interview_type']);
        });
    }
};
