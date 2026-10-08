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
        if (Schema::hasTable('students') && !Schema::hasColumn('students', 'avatar')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('avatar')->nullable()->after('detailed_address');
            });
        }

        if (Schema::hasTable('employers') && !Schema::hasColumn('employers', 'avatar')) {
            Schema::table('employers', function (Blueprint $table) {
                $table->string('avatar')->nullable()->after('hirer_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('students') && Schema::hasColumn('students', 'avatar')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('avatar');
            });
        }

        if (Schema::hasTable('employers') && Schema::hasColumn('employers', 'avatar')) {
            Schema::table('employers', function (Blueprint $table) {
                $table->dropColumn('avatar');
            });
        }
    }
};
