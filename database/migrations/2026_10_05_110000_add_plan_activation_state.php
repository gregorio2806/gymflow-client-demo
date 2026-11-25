<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_user', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->index(['gym_id', 'is_active']);
        });
        Schema::table('branches', fn (Blueprint $table) => $table->boolean('is_active')->default(true));
        Schema::table('gyms', function (Blueprint $table) {
            $table->boolean('needs_staff_selection')->default(false);
            $table->boolean('needs_branch_selection')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('gym_user', function (Blueprint $table) {
            $table->dropIndex(['gym_id', 'is_active']);
            $table->dropColumn('is_active');
        });
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('is_active'));
        Schema::table('gyms', fn (Blueprint $table) => $table->dropColumn(['needs_staff_selection', 'needs_branch_selection']));
    }
};
