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
            $table->string('end_date')->nullable()->after('start_date');
            $table->text('contract_terms')->nullable()->after('special_instructions');
            $table->timestamp('terminated_at')->nullable()->after('hired_at');
            $table->text('termination_reason')->nullable()->after('terminated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn(['end_date', 'contract_terms', 'terminated_at', 'termination_reason']);
        });
    }
};
