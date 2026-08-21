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
    Schema::table('employers', function (Blueprint $table) {
        $table->boolean('cert_ai_is_valid')->nullable();
        $table->text('cert_ai_remarks')->nullable();
    });
}

public function down(): void
{
    Schema::table('employers', function (Blueprint $table) {
        $table->dropColumn(['cert_ai_is_valid', 'cert_ai_remarks']);
    });
}
};
