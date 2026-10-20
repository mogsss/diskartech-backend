<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_user_deletions')) {
            Schema::create('admin_user_deletions', function (Blueprint $table) {
                $table->id();
                // Historical identifiers survive removal of an administrator.
                // Do not copy the deleted user's name, email, or credentials.
                $table->unsignedBigInteger('admin_id')->index();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('user_role', 20);
                $table->text('reason');
                $table->timestamp('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_deletions');
    }
};
