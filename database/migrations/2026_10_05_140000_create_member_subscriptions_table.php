<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->restrictOnDelete();
            $table->string('plan_name');
            $table->unsignedInteger('duration_amount');
            $table->string('duration_unit', 10);
            $table->unsignedInteger('price_centavos');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 12)->default('active');
            $table->string('operation', 10)->default('assign');
            $table->uuid('request_key');
            $table->timestamps();
            $table->unique(['gym_id', 'request_key']);
            $table->index(['gym_id', 'member_id', 'status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_subscriptions');
    }
};
