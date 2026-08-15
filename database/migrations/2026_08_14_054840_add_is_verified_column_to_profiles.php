<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idadagdag ang isVerified column sa tatlong table na ito
        foreach (['students', 'employers', 'households'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'status')) {
                    $table->dropColumn('status');
                }
                if (!Schema::hasColumn($tableName, 'isVerified')) {
                    $table->boolean('isVerified')->default(false); // false = unverified, true = verified
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['students', 'employers', 'households'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('isVerified');
                $table->string('status')->default('Pending');
            });
        }
    }
};