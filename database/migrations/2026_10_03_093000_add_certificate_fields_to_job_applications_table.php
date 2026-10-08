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
            $table->string('certificate_status')->nullable()->after('termination_reason'); // requested, approved, rejected
            $table->timestamp('certificate_requested_at')->nullable()->after('certificate_status');
            $table->timestamp('certificate_issued_at')->nullable()->after('certificate_requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn([
                'certificate_status',
                'certificate_requested_at',
                'certificate_issued_at',
            ]);
        });
    }
};
