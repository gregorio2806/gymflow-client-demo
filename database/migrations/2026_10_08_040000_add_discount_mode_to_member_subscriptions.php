<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_subscriptions', fn (Blueprint $table) => $table->string('discount_mode', 20)->default('percentage'));
    }

    public function down(): void
    {
        Schema::table('member_subscriptions', fn (Blueprint $table) => $table->dropColumn('discount_mode'));
    }
};
