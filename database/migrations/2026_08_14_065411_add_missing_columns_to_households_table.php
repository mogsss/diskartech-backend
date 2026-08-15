<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            if (!Schema::hasColumn('households', 'detailed_address')) {
                $table->string('detailed_address')->nullable();
            }
            if (!Schema::hasColumn('households', 'age')) {
                $table->integer('age')->nullable();
            }
            if (!Schema::hasColumn('households', 'gender')) {
                $table->string('gender')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn(['detailed_address', 'age', 'gender']);
        });
    }
};