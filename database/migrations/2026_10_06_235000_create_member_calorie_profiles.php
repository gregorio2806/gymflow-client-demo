<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_calorie_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->text('details');
            $table->string('consent_version', 40);
            $table->timestamp('consented_at');
            $table->timestamps();
            $table->unique(['gym_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_calorie_profiles');
    }
};
