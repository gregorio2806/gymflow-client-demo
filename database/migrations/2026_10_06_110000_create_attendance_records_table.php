<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('checked_in_at');
            $table->string('source', 10);
            $table->timestamps();
            $table->index(['gym_id', 'branch_id', 'checked_in_at']);
            $table->index(['gym_id', 'member_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
