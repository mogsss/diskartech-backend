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
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('school_id_ai_is_valid')->nullable()->after('school_id');
            $table->text('school_id_ai_remarks')->nullable()->after('school_id_ai_is_valid');
            $table->boolean('coe_ai_is_valid')->nullable()->after('coe');
            $table->text('coe_ai_remarks')->nullable()->after('coe_ai_is_valid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'school_id_ai_is_valid',
                'school_id_ai_remarks',
                'coe_ai_is_valid',
                'coe_ai_remarks',
            ]);
        });
    }
};
