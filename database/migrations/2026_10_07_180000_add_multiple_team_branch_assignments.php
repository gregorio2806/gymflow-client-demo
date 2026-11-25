<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['gym_user', 'gym_invitations'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->json('branch_ids')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['gym_user', 'gym_invitations'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('branch_ids'));
        }
    }
};
