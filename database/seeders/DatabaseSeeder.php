<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Model events stay ON: gyms generate their slug, gym-owned models get their gym_id.
     */
    public function run(): void
    {
        if (! app()->isProduction()) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
