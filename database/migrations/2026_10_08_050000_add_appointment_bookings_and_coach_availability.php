<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_schedules', function (Blueprint $table) {
            $table->string('kind', 20)->default('block');
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 255)->nullable();
            $table->string('service', 40)->nullable();
            $table->string('location', 100)->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->dateTime('status_changed_at')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['gym_id', 'kind', 'starts_at']);
        });
        DB::table('coach_schedules')->where('is_cancelled', true)->update(['status' => 'cancelled']);
        Schema::table('gym_user', function (Blueprint $table) {
            $table->json('availability')->nullable();
            $table->unsignedInteger('availability_revision')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('gym_user', fn (Blueprint $table) => $table->dropColumn(['availability', 'availability_revision']));
        Schema::table('coach_schedules', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->dropForeign(['status_changed_by']);
            $table->dropIndex(['gym_id', 'kind', 'starts_at']);
            $table->dropColumn(['kind', 'member_id', 'customer_name', 'service', 'location', 'status', 'status_changed_at', 'status_changed_by']);
        });
    }
};
