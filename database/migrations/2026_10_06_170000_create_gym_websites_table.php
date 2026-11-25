<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('published_content')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_websites');
    }
};
