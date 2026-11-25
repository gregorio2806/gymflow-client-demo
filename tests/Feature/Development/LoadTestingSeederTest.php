<?php

namespace Tests\Feature\Development;

use App\Models\Gym;
use App\Models\Member;
use App\Tenancy\CurrentGym;
use Database\Seeders\LoadTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoadTestingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixture_is_scoped_idempotent_and_preserves_manual_edits_without_claiming_consent(): void
    {
        $other = Gym::factory()->create();
        $foreign = app(CurrentGym::class)->runAs($other, fn () => Member::factory()->create(['name' => 'Unrelated sample']));
        $this->seed(LoadTestingSeeder::class);
        $gym = Gym::where('slug', LoadTestingSeeder::SLUG)->sole();
        app(CurrentGym::class)->set($gym);
        $this->assertSame(1000, Member::count());
        $this->assertSame(5000, DB::table('attendance_records')->where('gym_id', $gym->id)->count());
        $member = Member::where('email', 'member0001@loadtest.invalid')->sole();
        $member->name = 'Fictional manually edited member';
        $member->save();
        $this->seed(LoadTestingSeeder::class);
        $this->assertSame(1000, Member::count());
        $this->assertSame('Fictional manually edited member', $member->refresh()->name);
        $this->assertNull($member->terms_accepted_at);
        $this->assertNull($member->privacy_consented_at);
        $this->assertSame('Unrelated sample', $foreign->refresh()->name);
    }

    public function test_fixture_is_blocked_in_production(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        app(LoadTestingSeeder::class)->run();
    }
}
