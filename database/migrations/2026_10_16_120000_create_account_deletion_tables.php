<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_deletion_codes')) {
            Schema::create('account_deletion_codes', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->primary();
                $table->string('session_hash', 64);
                $table->string('code_hash', 64);
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('sent_at');
            });
        }
        if (!Schema::hasTable('account_deletion_files')) {
            Schema::create('account_deletion_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->text('path');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_codes');
        Schema::dropIfExists('account_deletion_files');
    }
};
