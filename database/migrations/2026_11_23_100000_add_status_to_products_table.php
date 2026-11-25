<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('is_active');
            $table->index(['gym_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['products_gym_id_status_id_index']);
            $table->dropColumn('status');
        });
    }
};
