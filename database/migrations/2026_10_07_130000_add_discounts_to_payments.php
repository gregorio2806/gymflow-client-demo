<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedSmallInteger('discount_basis_points')->default(0);
            $table->unsignedBigInteger('discount_centavos')->default(0);
            $table->unsignedBigInteger('discount_base_centavos')->nullable();
            $table->string('discount_scope', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['discount_basis_points', 'discount_centavos', 'discount_base_centavos', 'discount_scope']));
    }
};
