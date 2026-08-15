<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employers', function (Blueprint $table) {
            if (!Schema::hasColumn('employers', 'subscription_plan')) {
                $table->string('subscription_plan')->nullable(); // Halimbawa: Free, Premium, etc.
            }
            if (!Schema::hasColumn('employers', 'subscription_expires_at')) {
                $table->timestamp('subscription_expires_at')->nullable(); // Petsa kung kailan magtatapos ang subscription
            }
        });
    }

    public function down(): void
    {
        Schema::table('employers', function (Blueprint $table) {
            $table->dropColumn(['subscription_plan', 'subscription_expires_at']);
        });
    }
};