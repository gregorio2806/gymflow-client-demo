<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_schedules', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coach_schedules', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn(['revision', 'cancelled_at', 'cancelled_by']);
        });
    }
};
