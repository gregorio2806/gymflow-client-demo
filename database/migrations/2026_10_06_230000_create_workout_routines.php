<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 100);
            $table->text('notes')->nullable();
            $table->json('days');
            $table->unsignedInteger('revision')->default(1);
            $table->uuid('request_key');
            $table->timestamps();
            $table->unique(['gym_id', 'request_key']);
            $table->index(['gym_id', 'branch_id', 'created_by']);
        });
        Schema::create('member_routine_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_routine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('routine_revision');
            $table->json('snapshot');
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'member_id', 'ended_at']);
            $table->index(['gym_id', 'workout_routine_id', 'ended_at'], 'routine_assignment_current_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_routine_assignments');
        Schema::dropIfExists('workout_routines');
    }
};
