<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->index(['gym_id', 'member_id', 'starts_at', 'id', 'status', 'ends_at'], 'member_subscriptions_summary_index');
        });
    }

    public function down(): void
    {
        Schema::table('member_subscriptions', fn (Blueprint $table) => $table->dropIndex('member_subscriptions_summary_index'));
    }
};
