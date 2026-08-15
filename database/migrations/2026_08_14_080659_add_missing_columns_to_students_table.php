<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'age')) {
                $table->integer('age')->nullable();
            }
            if (!Schema::hasColumn('students', 'gender')) {
                $table->string('gender')->nullable();
            }
            if (!Schema::hasColumn('students', 'location')) {
                $table->string('location')->nullable();
            }
            if (!Schema::hasColumn('students', 'detailed_address')) {
                $table->string('detailed_address')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['age', 'gender', 'location', 'detailed_address']);
        });
    }
};