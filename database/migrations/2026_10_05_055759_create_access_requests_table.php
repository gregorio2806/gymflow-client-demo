<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Request access" form submissions from gym owners (sign-up is invite-only).
     * Contains personal data: keep only what's needed, delete dismissed/old requests (see legal checklist).
     */
    public function up(): void
    {
        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('gym_name', 100);
            $table->string('location')->nullable();
            $table->string('plan', 20); // App\Enums\Plan
            $table->text('message')->nullable();
            $table->string('status', 20)->default('new')->index(); // new | invited | dismissed
            $table->string('ip_address', 45)->nullable(); // abuse checks
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_requests');
    }
};
