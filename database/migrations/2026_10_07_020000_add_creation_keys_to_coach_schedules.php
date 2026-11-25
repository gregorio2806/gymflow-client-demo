<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_schedules', function (Blueprint $table) {
            $table->uuid('request_key')->nullable();
            $table->string('creation_hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['gym_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::table('coach_schedules', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropUnique(['gym_id', 'request_key']);
            $table->dropColumn(['request_key', 'creation_hash', 'created_by']);
        });
    }
};
