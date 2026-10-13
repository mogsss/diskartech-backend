<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_attachments')) return;
        Schema::create('chat_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('chat_id', 100)->index();
            $table->unsignedBigInteger('sender_id')->index();
            $table->unsignedBigInteger('recipient_id')->index();
            $table->string('disk', 20);
            $table->string('storage_path');
            $table->string('filename', 180);
            $table->string('mime_type', 100);
            $table->string('kind', 10);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('chat_attachments'); }
};
