<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sign-up is invite-only: the platform admin invites a gym owner by email.
     * One invitation = one gym the owner may create, on the given plan.
     */
    public function up(): void
    {
        Schema::create('owner_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('plan', 20); // App\Enums\Plan
            $table->string('gym_name', 100)->nullable(); // pre-fills "Create your gym"
            $table->char('token_hash', 64)->unique(); // sha256 of the emailed token
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // who accepted
            $table->foreignId('gym_id')->nullable()->constrained()->nullOnDelete(); // the gym created with it
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owner_invitations');
    }
};
