<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Para sa Households table
        Schema::table('households', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('ai_remarks');
        });

        // Para sa Employers table
        Schema::table('employers', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('ai_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
        
        Schema::table('employers', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
