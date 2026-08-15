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
        // Halimbawa: Idadagdag natin ang business_type pagkatapos ng employer_name
        $table->string('business_type')->nullable()->after('employer_name');
    });
}

public function down(): void
{
    Schema::table('employers', function (Blueprint $table) {
        $table->dropColumn('business_type');
    });
}
};
