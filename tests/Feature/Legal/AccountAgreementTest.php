<?php

namespace Tests\Feature\Legal;

use App\Enums\Plan;
use App\Models\AuditLog;
use App\Models\Gym;
use App\Models\Member;
use App\Models\OwnerInvitation;
use App\Models\User;
use App\Support\LegalDocuments;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class AccountAgreementTest extends TestCase
{
    use RefreshDatabase;

    private function agreement(array $changes = []): array
    {
        return array_replace(['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version']], $changes);
    }

    private function invitation(): string
    {
        $invite = new OwnerInvitation(['email' => 'fictional-owner@example.invalid', 'plan' => Plan::Pro]);
        $token = $invite->issueToken();
        $invite->save();

        return $token;
    }

    private function registration(array $changes = []): array
    {
        return array_replace(['name' => 'Fictional owner', 'password' => 'password', 'password_confirmation' => 'password', ...$this->agreement()], $changes);
    }

    private function portal(): array
    {
        $gym = Gym::factory()->create();
        app(CurrentGym::class)->set($gym);
        Feature::for($gym)->activate('member-auth');
        $member = Member::factory()->create();
        $member->forceFill(['portal_login' => 'fictional-member', 'portal_password' => Hash::make('password')])->save();
        $this->post(route('portal.authenticate', ['gym' => $gym->slug]), ['login' => 'fictional-member', 'password' => 'password'])->assertRedirect(route('portal.home', ['gym' => $gym->slug]));
        $this->withCookie(config('session.cookie'), session()->getId());

        return [$gym, $member];
    }

    public function test_all_policy_pages_share_escaped_content_and_draft_status(): void
    {
        config(['legal.business_name' => '<script>alert(1)</script>']);
        foreach (LegalDocuments::documents() as $document) {
            $this->get(route('legal.show', ['document' => $document['slug']]))->assertOk()->assertSee($document['title'])->assertSee('Draft for local review')->assertDontSee('<script>alert(1)</script>', false);
        }
        $this->get('/')->assertSee(route('legal.show', ['document' => 'privacy']), false);
    }

    public function test_missing_or_unchecked_agreement_cannot_create_owner_or_consume_invite(): void
    {
        $token = $this->invitation();
        foreach ([null, false] as $value) {
            $this->post(route('owner-invitations.register', $token), $this->registration(['terms_accepted' => $value]))->assertSessionHasErrors('terms_accepted');
        }
        $this->assertDatabaseMissing('users', ['email' => 'fictional-owner@example.invalid']);
        $this->assertNull(OwnerInvitation::sole()->accepted_at);
    }

    public function test_stale_policies_require_refresh_and_server_owns_timestamp(): void
    {
        $token = $this->invitation();
        $this->post(route('owner-invitations.register', $token), $this->registration(['policy_version' => str_repeat('a', 64)]))->assertSessionHasErrors('policy_version');
        $this->post(route('owner-invitations.register', $token), $this->registration(['terms_accepted_at' => '2000-01-01']))->assertRedirect(route('gyms.create'));
        $user = User::where('email', 'fictional-owner@example.invalid')->sole();
        $this->assertSame(LegalDocuments::bundle()['version'], $user->terms_version);
        $this->assertTrue($user->terms_accepted_at->isToday());
    }

    public function test_configuration_changes_rotate_agreement_fingerprint(): void
    {
        $original = LegalDocuments::bundle()['version'];
        config(['legal.refunds' => 'Fictional reviewed refund rules.']);
        $this->assertNotSame($original, LegalDocuments::bundle()['version']);
        $this->assertFalse(LegalDocuments::ready());
    }

    public function test_member_must_review_terms_before_private_portal_and_agreement_is_idempotent(): void
    {
        [$gym, $member] = $this->portal();
        $agreement = route('portal.agreement', ['gym' => $gym->slug]);
        $home = route('portal.home', ['gym' => $gym->slug]);
        $save = route('portal.accept-agreement', ['gym' => $gym->slug]);
        $this->get($home)->assertRedirect($agreement);
        $this->get($agreement)->assertInertia(fn (Assert $p) => $p->component('portal/Agreement')->where('auth.user', null)->where('legal.version', LegalDocuments::bundle()['version']));
        $this->post($save, $this->agreement(['terms_accepted' => false]))->assertSessionHasErrors('terms_accepted');
        $this->post($save, $this->agreement(['member_id' => 999, 'gym_id' => 999]))->assertRedirect($home);
        $this->post($save, $this->agreement())->assertRedirect($home);
        $this->assertSame(LegalDocuments::bundle()['version'], $member->refresh()->terms_version);
        $this->assertTrue($member->terms_accepted_at->isToday());
        app(CurrentGym::class)->set($gym);
        $this->assertSame(1, AuditLog::where('action', 'member.account-agreement.accepted')->count());
        $this->assertNull(AuditLog::sole()->user_id);
        $this->get($home)->assertOk();
        config(['legal.version' => 'next-review']);
        $this->get($home)->assertRedirect($agreement);
    }

    public function test_member_agreement_is_tenant_proof_scoped_and_cannot_use_staff_login(): void
    {
        [$gym, $member] = $this->portal();
        $other = Gym::factory()->create();
        Feature::for($other)->activate('member-auth');
        $this->post(route('portal.accept-agreement', ['gym' => $other->slug]), $this->agreement())->assertRedirect(route('portal.login', ['gym' => $other->slug]));
        $this->assertNull($member->refresh()->terms_accepted_at);
        $this->get(route('portal.agreement', ['gym' => $gym->slug]))->assertRedirect(route('portal.login', ['gym' => $gym->slug]));
    }

    public function test_failed_agreement_audit_rolls_back_acceptance(): void
    {
        [$gym, $member] = $this->portal();
        DB::statement('DROP TABLE audit_logs');
        $this->post(route('portal.accept-agreement', ['gym' => $gym->slug]), $this->agreement())->assertServerError();
        $this->assertNull($member->refresh()->terms_accepted_at);
    }
}
