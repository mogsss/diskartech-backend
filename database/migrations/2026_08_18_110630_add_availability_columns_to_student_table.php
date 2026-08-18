<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'available_days')) {
                $table->json('available_days')->nullable(); // JSON type para magkasya ang array ng mga araw
            }
            if (!Schema::hasColumn('students', 'time_slot')) {
                $table->string('time_slot')->nullable(); // String para sa time slot
            }
        });
    }

    public function down()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['available_days', 'time_slot']);
        });
    }
};