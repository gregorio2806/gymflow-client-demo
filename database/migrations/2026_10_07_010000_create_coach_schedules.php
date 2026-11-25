<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->text('notes')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
            $table->index(['gym_id', 'coach_id', 'starts_at'], 'coach_schedules_calendar_index');
            $table->index(['gym_id', 'branch_id', 'starts_at'], 'coach_schedules_branch_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_schedules');
    }
};
