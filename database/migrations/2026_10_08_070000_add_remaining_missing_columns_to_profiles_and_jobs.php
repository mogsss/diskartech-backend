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
        // 1. employers.rejection_reason
        if (Schema::hasTable('employers') && !Schema::hasColumn('employers', 'rejection_reason')) {
            Schema::table('employers', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable()->after('ai_remarks');
            });
        }

        // 2. households.rejection_reason and avatar
        if (Schema::hasTable('households')) {
            Schema::table('households', function (Blueprint $table) {
                if (!Schema::hasColumn('households', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('isVerified');
                }
                if (!Schema::hasColumn('households', 'avatar')) {
                    $table->string('avatar')->nullable()->after('detailed_address');
                }
            });
        }

        // 3. job_postings.latitude and longitude
        if (Schema::hasTable('job_postings')) {
            Schema::table('job_postings', function (Blueprint $table) {
                if (!Schema::hasColumn('job_postings', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable();
                }
                if (!Schema::hasColumn('job_postings', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('employers') && Schema::hasColumn('employers', 'rejection_reason')) {
            Schema::table('employers', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }

        if (Schema::hasTable('households')) {
            Schema::table('households', function (Blueprint $table) {
                if (Schema::hasColumn('households', 'rejection_reason')) {
                    $table->dropColumn('rejection_reason');
                }
                if (Schema::hasColumn('households', 'avatar')) {
                    $table->dropColumn('avatar');
                }
            });
        }

        if (Schema::hasTable('job_postings')) {
            Schema::table('job_postings', function (Blueprint $table) {
                if (Schema::hasColumn('job_postings', 'latitude')) {
                    $table->dropColumn('latitude');
                }
                if (Schema::hasColumn('job_postings', 'longitude')) {
                    $table->dropColumn('longitude');
                }
            });
        }
    }
};
