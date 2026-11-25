<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymStatus;
use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GymTest extends TestCase
{
    use RefreshDatabase;

    public function test_gym_defaults_to_active_and_manila_timezone()
    {
        $gym = Gym::create(['name' => 'DM Fitness']);

        $gym->refresh();
        $this->assertSame(GymStatus::Active, $gym->status);
        $this->assertSame('Asia/Manila', $gym->timezone);
    }

    public function test_slug_is_generated_from_name()
    {
        $gym = Gym::factory()->create(['name' => 'Iron House Fitness']);

        $this->assertSame('iron-house-fitness', $gym->slug);
    }

    public function test_duplicate_names_get_unique_slugs()
    {
        $first = Gym::factory()->create(['name' => 'Iron House']);
        $second = Gym::factory()->create(['name' => 'Iron House']);

        $this->assertSame('iron-house', $first->slug);
        $this->assertSame('iron-house-2', $second->slug);
    }

    public function test_reserved_subdomains_cannot_be_used_as_slug()
    {
        $gym = Gym::factory()->create(['name' => 'Admin']);

        $this->assertSame('admin-2', $gym->slug);
    }

    public function test_timezone_cannot_be_mass_assigned()
    {
        $gym = Gym::create(['name' => 'Flex Zone', 'timezone' => 'Asia/Tokyo']);

        $this->assertSame('Asia/Manila', $gym->refresh()->timezone);
    }

    public function test_status_cannot_be_mass_assigned()
    {
        $gym = Gym::create(['name' => 'Flex Zone', 'status' => 'suspended']);

        $this->assertSame(GymStatus::Active, $gym->refresh()->status);
    }

    public function test_branding_is_cast_to_array()
    {
        $gym = Gym::factory()->create(['branding' => ['primary_color' => '#dc2626']]);

        $this->assertSame('#dc2626', $gym->refresh()->branding['primary_color']);
    }
}
