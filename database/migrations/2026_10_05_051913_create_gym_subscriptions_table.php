<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each gym's current plan. One row per gym.
     */
    public function up(): void
    {
        Schema::create('gym_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan', 20)->default('starter'); // App\Enums\Plan
            $table->string('billing_cycle', 10)->default('monthly'); // App\Enums\BillingCycle
            $table->unsignedSmallInteger('extra_branches')->default(0); // Multi-Branch only
            $table->string('status', 20)->default('trialing'); // App\Enums\SubscriptionStatus
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamps();
        });

        // Existing gyms: Starter; demo gyms: Multi-Branch (so visitors can explore everything).
        DB::table('gyms')->orderBy('id')->each(function (object $gym) {
            DB::table('gym_subscriptions')->insert([
                'gym_id' => $gym->id,
                'plan' => $gym->demo_expires_at ? 'multi_branch' : 'starter',
                'billing_cycle' => 'monthly',
                'extra_branches' => 0,
                'status' => 'trialing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_subscriptions');
    }
};
