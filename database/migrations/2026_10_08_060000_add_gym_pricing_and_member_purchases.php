<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', fn (Blueprint $t) => $t->json('pricing_settings')->nullable());
        Schema::table('membership_plans', function (Blueprint $t) {
            $t->string('kind', 20)->default('subscription');
            $t->json('branch_ids')->nullable();
            $t->json('configuration')->nullable();
            $t->index(['gym_id', 'kind', 'is_active']);
        });
        Schema::create('pricing_discounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->string('category', 20);
            $t->string('rule_type', 20)->default('promotional');
            $t->string('unit', 20)->default('percentage');
            $t->unsignedInteger('value')->default(0);
            $t->boolean('requires_verification')->default(false);
            $t->boolean('is_active')->default(true);
            $t->json('product_kinds');
            $t->json('plan_ids')->nullable();
            $t->date('effective_from')->nullable();
            $t->date('effective_through')->nullable();
            $t->timestamps();
            $t->unique(['gym_id', 'category']);
        });
        Schema::table('members', function (Blueprint $t) {
            $t->foreignId('pricing_discount_id')->nullable()->constrained('pricing_discounts')->nullOnDelete();
            $t->dateTime('eligibility_verified_at')->nullable();
            $t->foreignId('eligibility_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->date('eligibility_from')->nullable();
            $t->date('eligibility_through')->nullable();
        });
        Schema::table('member_subscriptions', fn (Blueprint $t) => $t->json('pricing_snapshot')->nullable());
        Schema::create('member_purchases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('member_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_plan_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('kind', 20);
            $t->string('plan_name');
            $t->unsignedInteger('price_centavos');
            $t->unsignedInteger('original_price_centavos');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at')->nullable();
            $t->string('status', 20)->default('active');
            $t->unsignedInteger('sessions')->default(0);
            $t->unsignedInteger('used_sessions')->default(0);
            $t->integer('session_adjustment')->default(0);
            $t->json('snapshot');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->uuid('request_key');
            $t->timestamps();
            $t->unique(['gym_id', 'request_key']);
            $t->index(['gym_id', 'member_id', 'kind', 'status']);
        });
        Schema::create('pricing_checkouts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('member_id')->constrained()->cascadeOnDelete();
            $t->uuid('request_key');
            $t->char('fingerprint', 64);
            $t->json('summary');
            $t->timestamps();
            $t->unique(['gym_id', 'request_key']);
        });
        Schema::table('payments', function (Blueprint $t) {
            $t->foreignId('member_subscription_id')->nullable()->change();
            $t->foreignId('member_purchase_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('coach_schedules', fn (Blueprint $t) => $t->foreignId('member_purchase_id')->nullable()->constrained()->nullOnDelete());
        Schema::create('session_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('member_purchase_id')->constrained()->cascadeOnDelete();
            $t->foreignId('coach_schedule_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['gym_id', 'coach_schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_redemptions');
        Schema::table('coach_schedules', fn (Blueprint $t) => $t->dropConstrainedForeignId('member_purchase_id'));
        Schema::table('payments', fn (Blueprint $t) => $t->dropConstrainedForeignId('member_purchase_id'));
        Schema::dropIfExists('pricing_checkouts');
        Schema::dropIfExists('member_purchases');
        Schema::table('member_subscriptions', fn (Blueprint $t) => $t->dropColumn('pricing_snapshot'));
        Schema::table('members', function (Blueprint $t) {
            $t->dropConstrainedForeignId('pricing_discount_id');
            $t->dropConstrainedForeignId('eligibility_verified_by');
            $t->dropColumn(['eligibility_verified_at', 'eligibility_from', 'eligibility_through']);
        });
        Schema::dropIfExists('pricing_discounts');
        Schema::table('membership_plans', function (Blueprint $t) {
            $t->dropIndex(['gym_id', 'kind', 'is_active']);
            $t->dropColumn(['kind', 'branch_ids', 'configuration']);
        });
        Schema::table('gyms', fn (Blueprint $t) => $t->dropColumn('pricing_settings'));
        // Existing payments remain compatible. Nullable subscription IDs cannot be safely tightened if new purchase payments exist.
    }
};
