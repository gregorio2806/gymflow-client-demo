<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('sku', 80)->nullable();
            $table->string('category', 100)->nullable();
            $table->unsignedBigInteger('cost_centavos')->default(0);
            $table->unsignedBigInteger('price_centavos')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['gym_id', 'sku']);
            $table->index(['gym_id', 'is_active', 'id']);
            $table->index(['gym_id', 'name']);
        });

        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'branch_id']);
            $table->index(['gym_id', 'branch_id']);
        });

        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 12);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->string('reason', 500);
            $table->timestamps();
            $table->index(['gym_id', 'branch_id', 'created_at']);
            $table->index(['gym_id', 'product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_movements');
        Schema::dropIfExists('product_stocks');
        Schema::dropIfExists('products');
    }
};
