<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_websites', function (Blueprint $table) {
            $table->json('draft_content')->nullable();
            $table->unsignedInteger('draft_revision')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('gym_websites', fn (Blueprint $table) => $table->dropColumn(['draft_content', 'draft_revision']));
    }
};
