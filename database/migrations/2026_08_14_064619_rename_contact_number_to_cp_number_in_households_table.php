<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            if (Schema::hasColumn('households', 'contact_number')) {
                $table->renameColumn('contact_number', 'cp_number');
            } else if (!Schema::hasColumn('households', 'cp_number')) {
                $table->string('cp_number')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->renameColumn('cp_number', 'contact_number');
        });
    }
};