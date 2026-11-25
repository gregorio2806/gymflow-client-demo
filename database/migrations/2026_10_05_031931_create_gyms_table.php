<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gyms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 63)->unique(); // used as the subdomain: {slug}.<platform-domain>
            $table->string('timezone')->default('Asia/Manila');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->json('branding')->nullable(); // logo path, colors
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gyms');
    }
};
