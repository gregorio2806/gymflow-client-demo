<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A gym (the paying account) has one or more branches (physical locations).
     * Every gym has exactly one main branch.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_main')->default(false);
            $table->timestamps();

            $table->index(['gym_id', 'is_main']);
        });

        // Existing gyms get their main branch (copying the gym's contact details).
        DB::table('gyms')->orderBy('id')->each(function (object $gym) {
            DB::table('branches')->insert([
                'gym_id' => $gym->id,
                'name' => 'Main branch',
                'address' => $gym->address,
                'phone' => $gym->phone,
                'is_main' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
