<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
use App\Models\AuditLog;
use App\Models\Gym;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create(['name' => 'Old Name']);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();

        $this->actingAs($this->owner);
        app(CurrentGym::class)->set($this->gym, GymRole::Owner);
    }

    public function test_it_records_who_did_what_to_which_record()
    {
        $log = Audit::log('gym.viewed', $this->gym);

        $this->assertSame($this->gym->id, $log->gym_id);
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertSame('gym.viewed', $log->action);
        $this->assertTrue($log->subject->is($this->gym));
        $this->assertSame('127.0.0.1', $log->ip_address);
    }

    public function test_it_records_before_and_after_values_of_a_save()
    {
        $this->gym->update(['name' => 'New Name']);

        $log = Audit::log('gym.updated', $this->gym);

        $this->assertSame(['before' => ['name' => 'Old Name'], 'after' => ['name' => 'New Name']], $log->refresh()->changes);
    }

    public function test_hidden_attributes_are_never_recorded()
    {
        $this->owner->update(['name' => 'Renamed', 'password' => 'new-password']);

        $log = Audit::log('user.updated', $this->owner);

        $this->assertArrayHasKey('name', $log->changes['after']);
        $this->assertArrayNotHasKey('password', $log->changes['after']);
        $this->assertArrayNotHasKey('password', $log->changes['before']);
    }

    public function test_audit_logs_cannot_be_changed()
    {
        $log = Audit::log('gym.updated', $this->gym);

        $this->expectException(LogicException::class);

        $log->update(['action' => 'nothing.happened']);
    }

    public function test_audit_logs_cannot_be_deleted()
    {
        $log = Audit::log('gym.updated', $this->gym);

        $this->expectException(LogicException::class);

        $log->delete();
    }

    public function test_gyms_only_see_their_own_audit_logs()
    {
        Audit::log('gym.updated', $this->gym);

        $otherGym = Gym::factory()->create();
        app(CurrentGym::class)->set($otherGym);
        Audit::log('gym.updated', $otherGym);

        $this->assertSame(1, AuditLog::count());
        $this->assertSame($otherGym->id, AuditLog::first()->gym_id);
    }
}
