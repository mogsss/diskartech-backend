<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employers', function (Blueprint $table) {
            // Kung may dati kang subscription_plan, maaari nating i-drop o hayaan muna
            if (!Schema::hasColumn('employers', 'isSubscribed')) {
                $table->boolean('isSubscribed')->default(false);
            }
            if (!Schema::hasColumn('employers', 'subscription_started_at')) {
                $table->timestamp('subscription_started_at')->nullable();
            }
            if (!Schema::hasColumn('employers', 'subscription_expires_at')) {
                $table->timestamp('subscription_expires_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('employers', function (Blueprint $table) {
            $table->dropColumn(['isSubscribed', 'subscription_started_at', 'subscription_expires_at']);
        });
    }
};