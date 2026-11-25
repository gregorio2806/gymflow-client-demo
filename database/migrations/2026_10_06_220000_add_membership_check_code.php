<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('status_code_hash', 64)->nullable();
            $table->unique(['gym_id', 'status_code_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['gym_id', 'status_code_hash']);
            $table->dropColumn('status_code_hash');
        });
    }
};
