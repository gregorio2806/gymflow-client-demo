<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Enums\MemberQrPurpose;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberQrToken;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use RuntimeException;
use Tests\TestCase;

class AttendanceQrTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        app(CurrentGym::class)->set($this->gym);
        $this->member = Member::factory()->create();
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Owner)->create());
    }

    private function manage(string $operation)
    {
        return $this->postJson(route('member-qr.store', $this->member), ['operation' => $operation]);
    }

    public function test_generation_returns_only_a_random_payload_once_and_audits_without_secrets(): void
    {
        $response = $this->manage('generate')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $payload = $response->json('payload');
        $this->assertMatchesRegularExpression('/\Agym-attendance:[A-Za-z0-9]{64}\z/', $payload);
        $hash = hash('sha256', substr($payload, strlen('gym-attendance:')));
        $this->assertSame($hash, MemberQrToken::sole()->token_hash);
        $this->get(route('member-qr.show', $this->member))->assertInertia(fn (Assert $page) => $page->where('hasQr', true)->missing('payload')->missing('member.mobile')->missing('token_hash'));
        $this->assertStringNotContainsString($hash, AuditLog::sole()->toJson());
        $this->assertStringNotContainsString($payload, AuditLog::sole()->toJson());
        $this->assertNull(session('payload'));
        $this->assertNotSame($payload, MemberQrToken::sole()->encrypted_payload);
        $this->manage('view')->assertOk()->assertJson(['payload' => $payload, 'hasQr' => true]);
        $this->manage('generate')->assertUnprocessable()->assertJsonValidationErrors('operation');
        $this->assertSame(1, MemberQrToken::count());
    }

    public function test_reissue_revokes_old_attendance_qr_and_preserves_portal_token(): void
    {
        $oldPayload = $this->manage('generate')->json('payload');
        $old = MemberQrToken::sole();
        $portal = new MemberQrToken;
        $portal->forceFill(['member_id' => $this->member->id, 'purpose' => MemberQrPurpose::Portal, 'token_hash' => hash('sha256', 'portal-secret')])->save();
        $new = $this->manage('reissue')->assertOk()->json('payload');
        $this->assertNotSame($oldPayload, $new);
        $this->assertNotNull($old->refresh()->revoked_at);
        $this->assertNull($portal->refresh()->revoked_at);
        $this->assertSame(1, MemberQrToken::usable(MemberQrPurpose::Attendance)->count());
        $this->assertSame(1, AuditLog::where('action', 'attendance-qr.reissued')->count());
        $this->manage('revoke')->assertOk()->assertJson(['payload' => null, 'hasQr' => false]);
        $this->manage('revoke')->assertOk();
        $this->assertSame(0, MemberQrToken::usable(MemberQrPurpose::Attendance)->count());
        $this->assertNull($portal->refresh()->revoked_at);
        $this->assertSame(2, AuditLog::where('action', 'attendance-qr.revoked')->count());
    }

    public function test_staff_can_manage_qr_but_coaches_and_disabled_feature_cannot(): void
    {
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($staff);
        $this->manage('generate')->assertOk();
        $coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->actingAs($coach)->get(route('member-qr.show', $this->member))->assertForbidden();
        $this->manage('revoke')->assertForbidden();
        Feature::for($this->gym)->deactivate('qr-attendance');
        $this->actingAs($staff)->get(route('member-qr.show', $this->member))->assertNotFound();
        $this->manage('reissue')->assertNotFound();
    }

    public function test_manager_is_limited_to_members_home_branch_and_read_only_branch_blocks_changes(): void
    {
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $main = $this->gym->mainBranch();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $main->id]);
        $other = Member::factory()->create(['home_branch_id' => Branch::factory()->create()->id]);
        $this->actingAs($manager);
        $this->manage('generate')->assertOk();
        $this->get(route('member-qr.show', $other))->assertNotFound();
        $this->postJson(route('member-qr.store', $other), ['operation' => 'generate'])->assertNotFound();
        $main->forceFill(['is_active' => false])->save();
        $this->get(route('member-qr.show', $this->member))->assertInertia(fn (Assert $page) => $page->where('canManage', false));
        $this->manage('reissue')->assertUnprocessable()->assertJsonValidationErrors('operation');
    }

    public function test_expired_token_can_be_reissued_and_only_one_unrevoked_attendance_token_remains(): void
    {
        $this->manage('generate')->assertOk();
        MemberQrToken::sole()->forceFill(['expires_at' => now()])->save();
        $this->get(route('member-qr.show', $this->member))->assertInertia(fn (Assert $page) => $page->where('hasQr', true));
        $this->manage('reissue')->assertOk();
        $this->assertSame(1, MemberQrToken::whereNull('revoked_at')->where('purpose', 'attendance')->count());
    }

    public function test_audit_failure_rolls_back_reissue_and_preserves_previous_qr(): void
    {
        $this->manage('generate')->assertOk();
        $old = MemberQrToken::sole();
        AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
        $this->withoutExceptionHandling();
        try {
            $this->manage('reissue');
            $this->fail('Expected failed audit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
            $this->assertNull($old->refresh()->revoked_at);
            $this->assertSame(1, MemberQrToken::count());
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_qr_operation_is_validated(): void
    {
        $this->manage('portal')->assertUnprocessable()->assertJsonValidationErrors('operation');
    }

    public function test_legacy_qr_without_encrypted_payload_requires_reissue_for_recovery(): void
    {
        $this->manage('generate');
        MemberQrToken::sole()->forceFill(['encrypted_payload' => null])->saveQuietly();

        $this->manage('view')->assertUnprocessable()->assertJsonValidationErrors('operation');
    }
}
