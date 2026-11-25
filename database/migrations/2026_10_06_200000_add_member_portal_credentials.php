<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('portal_login')->nullable();
            $table->string('portal_password')->nullable();
            $table->unique(['gym_id', 'portal_login']);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['gym_id', 'portal_login']);
            $table->dropColumn(['portal_login', 'portal_password']);
        });
    }
};
