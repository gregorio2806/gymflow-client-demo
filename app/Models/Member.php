<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use App\Tenancy\CurrentGym;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use LogicException;

class Member extends Authenticatable
{
    /** @use HasFactory<MemberFactory> */
    use BelongsToGym, HasFactory;

    protected $fillable = ['name', 'mobile', 'email', 'home_branch_id'];

    protected $hidden = ['portal_password', 'status_code_hash'];

    public function getAuthPasswordName(): string
    {
        return 'portal_password';
    }

    protected $attributes = ['is_active' => true];

    protected static function booted(): void
    {
        static::addGlobalScope('branch', fn (Builder $query) => app(CurrentGym::class)->restrictToBranch($query, 'home_branch_id'));

        static::creating(function (Member $member) {
            $member->home_branch_id ??= Branch::withoutGlobalScope('gym')->where('gym_id', $member->gym_id)->where('is_main', true)->value('id');
            $member->validateHomeBranch();
        });

        static::updating(function (Member $member) {
            if ($member->isDirty('home_branch_id')) {
                $member->validateHomeBranch();
            }
        });
    }

    private function validateHomeBranch(): void
    {
        if (! Branch::withoutGlobalScope('gym')->where('gym_id', $this->gym_id)->whereKey($this->home_branch_id)->exists()) {
            throw new LogicException('A member must have a home branch in their gym.');
        }
    }

    /** @return BelongsTo<Branch, $this> */
    public function homeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'home_branch_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(MemberPurchase::class)->withoutGlobalScope('branch');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MemberSubscription::class);
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(MemberQrToken::class);
    }

    public function currentMembership(): HasOne
    {
        $at = now();

        return $this->hasOne(MemberSubscription::class)->ofMany(['starts_at' => 'max', 'id' => 'max'], fn ($query) => $query->where('status', '!=', 'cancelled')->where('starts_at', '<=', $at)->where('ends_at', '>', $at));
    }

    public function nextMembership(): HasOne
    {
        $at = now();

        return $this->hasOne(MemberSubscription::class)->ofMany(['starts_at' => 'min', 'id' => 'min'], fn ($query) => $query->where('status', '!=', 'cancelled')->where('starts_at', '>', $at));
    }

    public function latestMembership(): HasOne
    {
        return $this->hasOne(MemberSubscription::class)->ofMany(['starts_at' => 'max', 'id' => 'max']);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'privacy_consented_at' => 'datetime', 'terms_accepted_at' => 'immutable_datetime'];
    }
}
