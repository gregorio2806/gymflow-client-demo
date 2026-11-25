<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['gym_user', 'gym_invitations'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->index(['gym_id', 'branch_id']);
            });
        }
        DB::table('branches')->where('is_main', true)->orderBy('id')->chunkById(100, function ($branches) {
            foreach ($branches as $branch) {
                DB::table('gym_user')->where('gym_id', $branch->gym_id)->where('role', '!=', 'owner')->update(['branch_id' => $branch->id]);
                DB::table('gym_invitations')->where('gym_id', $branch->gym_id)->update(['branch_id' => $branch->id]);
            }
        });
    }

    public function down(): void
    {
        foreach (['gym_user', 'gym_invitations'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['gym_id', 'branch_id']);
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
