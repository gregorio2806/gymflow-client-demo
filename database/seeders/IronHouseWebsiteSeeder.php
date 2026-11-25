<?php

namespace Database\Seeders;

use App\Models\Gym;
use App\Models\GymWebsite;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Seeder;
use RuntimeException;

class IronHouseWebsiteSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('IronHouseWebsiteSeeder is local/testing only.');
        }
        $gym = Gym::where('slug', 'iron-house-fitness')->firstOrFail();
        app(CurrentGym::class)->runAs($gym, function () use ($gym) {
            if (GymWebsite::exists()) {
                return;
            }
            (new GymWebsite)->forceFill(['published_at' => now()->utc(), 'published_content' => [
                'description' => 'A fictional neighborhood gym for testing this website.',
                'about' => 'Iron House Fitness is a fictional development gym. Explore these sample pages to test the website. Facilities, prices, and announcements below are examples, not an offer from a real business.',
                'plans' => [
                    ['name' => 'Monthly access', 'price_centavos' => 120000, 'duration' => '1 calendar month', 'description' => 'Sample gym access. Ask the gym about current terms and inclusions.'],
                    ['name' => 'Day pass', 'price_centavos' => 15000, 'duration' => '1 day', 'description' => 'A fictional single-day access option.'],
                ],
                'facilities' => [['name' => 'Strength area', 'description' => 'Sample free-weight and resistance-training area.'], ['name' => 'Cardio area', 'description' => 'Sample space for cardio equipment.']],
                'announcements' => [['title' => 'Welcome to the sample website', 'body' => 'This announcement is fictional development content.', 'date' => now()->setTimezone($gym->timezone)->toDateString()]],
                'contact' => ['address' => 'Quezon City (fictional sample)', 'hours' => 'Sample hours: Monday–Saturday, 6 AM–9 PM'],
            ]])->save();
        });
    }
}
