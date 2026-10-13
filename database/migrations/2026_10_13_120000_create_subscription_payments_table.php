<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['subscription_plan', 'subscription_started_at', 'subscription_expires_at', 'isSubscribed'] as $column) {
            if (!Schema::hasColumn('employers', $column)) {
                Schema::table('employers', function (Blueprint $table) use ($column) {
                    if ($column === 'isSubscribed') $table->boolean($column)->default(false);
                    elseif ($column === 'subscription_plan') $table->string($column)->nullable();
                    else $table->timestamp($column)->nullable();
                });
            }
        }
        if (Schema::hasTable('subscription_payments')) return;
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->uuid('request_id');
            $table->string('billing_cycle', 10);
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('PHP');
            $table->string('status', 10)->default('creating');
            $table->json('payment_methods');
            $table->string('billing_email');
            $table->string('checkout_session_id', 100)->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->string('payment_id', 100)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('granted_until')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
