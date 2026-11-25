<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('duration_amount');
            $table->string('duration_unit', 10);
            $table->unsignedInteger('price_centavos');
            $table->text('inclusions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['gym_id', 'is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
