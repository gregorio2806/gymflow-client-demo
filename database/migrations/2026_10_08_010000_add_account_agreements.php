<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'members'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('terms_version', 64)->nullable();
                $table->timestamp('terms_accepted_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'members'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['terms_version', 'terms_accepted_at']));
        }
    }
};
