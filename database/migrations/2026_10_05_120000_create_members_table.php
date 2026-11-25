<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('home_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name');
            $table->string('mobile', 20);
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('privacy_consented_at')->nullable();
            $table->foreignId('privacy_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['gym_id', 'is_active']);
            $table->index(['gym_id', 'name']);
            $table->index(['gym_id', 'home_branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
