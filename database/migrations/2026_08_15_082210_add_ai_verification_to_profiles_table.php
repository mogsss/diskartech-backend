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
        Schema::table('households', function (Blueprint $table) {
            $table->boolean('ai_is_valid')->nullable()->after('valid_id_path');
            $table->text('ai_remarks')->nullable()->after('ai_is_valid');
        });

        Schema::table('employers', function (Blueprint $table) {
            $table->boolean('ai_is_valid')->nullable()->after('valid_id_path');
            $table->text('ai_remarks')->nullable()->after('ai_is_valid');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn(['ai_is_valid', 'ai_remarks']);
        });

        Schema::table('employers', function (Blueprint $table) {
            $table->dropColumn(['ai_is_valid', 'ai_remarks']);
        });
    }
};
