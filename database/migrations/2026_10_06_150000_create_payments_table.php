<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_centavos');
            $table->string('currency', 3)->default('PHP');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at');
            $table->string('status', 20)->default('recorded');
            $table->string('void_reason', 500)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->uuid('request_key');
            $table->timestamps();
            $table->unique(['gym_id', 'request_key']);
            $table->index(['gym_id', 'branch_id', 'paid_at']);
            $table->index(['gym_id', 'member_id', 'paid_at']);
            $table->index(['gym_id', 'member_subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
