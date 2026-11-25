<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('original_price_centavos')->nullable();
            $table->unsignedSmallInteger('discount_basis_points')->default(0);
            $table->string('discount_type', 20)->default('regular');
            $table->string('discount_other', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('member_subscriptions', fn (Blueprint $table) => $table->dropColumn(['original_price_centavos', 'discount_basis_points', 'discount_type', 'discount_other']));
    }
};
