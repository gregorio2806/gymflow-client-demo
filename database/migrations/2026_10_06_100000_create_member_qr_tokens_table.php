<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 10);
            $table->char('token_hash', 64);
            $table->dateTime('revoked_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['gym_id', 'token_hash']);
            $table->index(['gym_id', 'member_id', 'purpose', 'revoked_at'], 'member_qr_tokens_member_purpose_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_qr_tokens');
    }
};
