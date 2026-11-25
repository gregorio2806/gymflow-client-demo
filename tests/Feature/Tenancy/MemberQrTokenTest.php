<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
use App\Enums\MemberQrPurpose;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberQrToken;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;
use ValueError;

class MemberQrTokenTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-06 04:00:00', 'UTC'));
        $this->gym = Gym::factory()->create();
        app(CurrentGym::class)->set($this->gym);
        $this->member = Member::factory()->create();
    }

    private function token(array $attributes = []): MemberQrToken
    {
        $token = new MemberQrToken;
        $token->forceFill(array_replace([
            'member_id' => $this->member->id,
            'purpose' => MemberQrPurpose::Attendance,
            'token_hash' => hash('sha256', Str::random(64)),
        ], $attributes))->save();

        return $token;
    }

    public function test_tokens_store_only_hashes_and_hide_them_from_serialization(): void
    {
        $raw = Str::random(64);
        $token = $this->token(['token_hash' => hash('sha256', $raw)]);
        $this->assertSame($this->gym->id, $token->gym_id);
        $this->assertSame(MemberQrPurpose::Attendance, $token->purpose);
        $this->assertSame(hash('sha256', $raw), $token->fresh()->token_hash);
        $this->assertArrayNotHasKey('token_hash', $token->toArray());
        $this->assertStringNotContainsString($raw, $token->toJson());
        $this->assertTrue($token->member->is($this->member));
        $this->assertTrue($this->member->qrTokens()->sole()->is($token));
        $this->assertNull($token->revoked_at);
        $this->assertNull($token->expires_at);
    }

    public function test_tokens_are_tenant_scoped_and_fail_closed_without_context(): void
    {
        $tokenA = $this->token();
        $gymB = Gym::factory()->create();
        $context = app(CurrentGym::class);
        $context->set($gymB);
        $memberB = Member::factory()->create();
        $tokenB = $this->token(['member_id' => $memberB->id, 'gym_id' => $this->gym->id]);
        $this->assertSame($gymB->id, $tokenB->gym_id);
        $this->assertNull(MemberQrToken::find($tokenA->id));
        $this->assertSame(0, MemberQrToken::whereKey($tokenA->id)->update(['revoked_at' => now()]));
        $this->assertSame([$tokenB->id], MemberQrToken::pluck('id')->all());
        $context->forget();
        $this->assertSame(0, MemberQrToken::count());
        $this->expectException(LogicException::class);
        $this->token();
    }

    public function test_foreign_members_are_rejected(): void
    {
        $foreign = app(CurrentGym::class)->runAs(Gym::factory()->create(), fn () => Member::factory()->create());
        $this->expectException(LogicException::class);
        $this->token(['member_id' => $foreign->id]);
    }

    public function test_managers_only_see_tokens_for_their_members_home_branch(): void
    {
        $mainToken = $this->token();
        $branch = Branch::factory()->create();
        $other = Member::factory()->create(['home_branch_id' => $branch->id]);
        $otherToken = $this->token(['member_id' => $other->id]);
        app(CurrentGym::class)->set($this->gym, GymRole::BranchManager, $branch->id);
        $this->assertSame([$otherToken->id], MemberQrToken::pluck('id')->all());
        $this->assertNull(MemberQrToken::find($mainToken->id));
        $this->assertSame(0, MemberQrToken::whereKey($mainToken->id)->update(['revoked_at' => now()]));
        app(CurrentGym::class)->set($this->gym, GymRole::BranchManager);
        $this->assertSame(0, MemberQrToken::count());
    }

    public function test_usable_tokens_respect_purpose_revocation_and_exclusive_expiry(): void
    {
        $attendance = $this->token();
        $portal = $this->token(['purpose' => MemberQrPurpose::Portal]);
        $future = $this->token(['expires_at' => now()->addSecond()]);
        $this->token(['expires_at' => now()]);
        $this->token(['expires_at' => now()->subSecond()]);
        $this->token(['revoked_at' => now()]);
        $this->assertSame([$attendance->id, $future->id], MemberQrToken::usable(MemberQrPurpose::Attendance)->orderBy('id')->pluck('id')->all());
        $this->assertSame([$portal->id], MemberQrToken::usable(MemberQrPurpose::Portal)->pluck('id')->all());
        $this->travel(1)->seconds();
        $this->assertSame([$attendance->id], MemberQrToken::usable(MemberQrPurpose::Attendance)->pluck('id')->all());
    }

    public function test_token_fields_are_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);
        new MemberQrToken(['gym_id' => $this->gym->id, 'member_id' => $this->member->id, 'purpose' => 'attendance', 'token_hash' => hash('sha256', 'secret'), 'revoked_at' => now()]);
    }

    public function test_plaintext_and_invalid_hashes_are_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->token(['token_hash' => 'plaintext-token']);
    }

    public function test_invalid_purposes_are_rejected(): void
    {
        $this->expectException(ValueError::class);
        $this->token(['purpose' => 'unknown']);
    }

    public function test_token_identity_and_gym_cannot_be_changed(): void
    {
        $token = $this->token();
        foreach (['member_id' => Member::factory()->create()->id, 'purpose' => MemberQrPurpose::Portal, 'token_hash' => hash('sha256', 'new'), 'gym_id' => Gym::factory()->create()->id] as $field => $value) {
            try {
                $token->forceFill([$field => $value])->save();
                $this->fail('Token identity changes must be rejected.');
            } catch (LogicException) {
                $token->refresh();
            }
        }
        $this->assertSame($this->member->id, $token->member_id);
    }

    public function test_revocation_is_terminal(): void
    {
        $token = $this->token();
        $token->revoked_at = now();
        $token->save();
        $this->assertInstanceOf(CarbonImmutable::class, $token->fresh()->revoked_at);
        $this->expectException(LogicException::class);
        $token->revoked_at = null;
        $token->save();
    }

    public function test_hashes_are_unique_within_the_gym(): void
    {
        $hash = $this->token()->token_hash;
        $this->expectException(QueryException::class);
        $this->token(['token_hash' => $hash]);
    }

    public function test_gym_deletion_cleans_tokens_and_preserves_other_tenants(): void
    {
        $token = $this->token();
        $gymB = Gym::factory()->create();
        app(CurrentGym::class)->set($gymB);
        $other = $this->token(['member_id' => Member::factory()->create()->id]);
        $this->gym->delete();
        $this->assertDatabaseMissing('member_qr_tokens', ['id' => $token->id]);
        $this->assertModelExists($other);
        $this->assertSame($gymB->id, app(CurrentGym::class)->id());
    }
}
