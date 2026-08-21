<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            // Tanggalin ang lumang schedules column kung meron man
            if (Schema::hasColumn('job_postings', 'schedules')) {
                $table->dropColumn('schedules');
            }

            // Idagdag ang bagong columns para sa AI matching
            $table->json('available_days')->nullable()->after('salary');
            $table->string('time_slot')->nullable()->after('available_days');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn(['available_days', 'time_slot']);
            $table->text('schedules')->nullable();
        });
    }
};