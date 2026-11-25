<?php

namespace App\Models;

use App\Enums\GymRole;
use App\Enums\MemberQrPurpose;
use App\Models\Concerns\BelongsToGym;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MemberQrToken extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    protected $hidden = ['token_hash', 'encrypted_payload'];

    protected static function booted(): void
    {
        static::addGlobalScope('branch', function (Builder $query) {
            if (app(CurrentGym::class)->role() === GymRole::BranchManager) {
                $query->whereHas('member');
            }
        });

        static::creating(function (MemberQrToken $token) {
            if (! Member::withoutGlobalScope('gym')->where('gym_id', $token->gym_id)->whereKey($token->member_id)->exists()) {
                throw new LogicException('A QR token must belong to a member in its gym.');
            }
        });

        static::saving(function (MemberQrToken $token) {
            if (! preg_match('/\A[a-f0-9]{64}\z/', $token->token_hash ?? '')
                || ! MemberQrPurpose::tryFrom($token->getAttributes()['purpose'] ?? '')) {
                throw new LogicException('QR tokens require a SHA-256 hash and a supported purpose.');
            }
            if ($token->exists) {
                foreach (['member_id', 'purpose', 'token_hash'] as $field) {
                    if ($token->isDirty($field)) {
                        throw new LogicException('QR token identity is immutable; reissue a new token instead.');
                    }
                }
                if ($token->getRawOriginal('revoked_at') !== null && $token->isDirty('revoked_at')) {
                    throw new LogicException('A revoked QR token cannot be restored or changed.');
                }
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function scopeUsable(Builder $query, MemberQrPurpose $purpose, ?CarbonImmutable $at = null): void
    {
        $at ??= CarbonImmutable::now();
        $query->where('purpose', $purpose->value)->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $at));
    }

    protected function casts(): array
    {
        return ['purpose' => MemberQrPurpose::class, 'revoked_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
