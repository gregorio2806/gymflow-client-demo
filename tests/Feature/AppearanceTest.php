<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_light_theme_is_the_default()
    {
        $response = $this->actingAs(User::factory()->memberOf(Gym::factory()->create())->create())->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('appearance', 'light');
        $response->assertDontSee('<html lang="en"  class="dark">', false);
    }

    public function test_dark_theme_is_used_when_chosen()
    {
        $response = $this->actingAs(User::factory()->memberOf(Gym::factory()->create())->create())
            ->withUnencryptedCookie('appearance', 'dark')
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('appearance', 'dark');
        $response->assertSee('class="dark"', false);
    }
}
