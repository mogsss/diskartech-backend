<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::table('employers', function (Blueprint $table) {
        // Idadagdag natin ang column na 'valid_id_path' na pwedeng maging null
        $table->string('valid_id_path')->nullable()->after('employer_certificate_path');
    });
}

    /**
     * Reverse the migrations.
     */
public function down()
{
    Schema::table('employers', function (Blueprint $table) {
        $table->dropColumn('valid_id_path');
    });
}
};
