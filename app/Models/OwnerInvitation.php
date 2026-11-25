<?php

namespace App\Models;

use App\Enums\Plan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Platform-level invitation for a new gym owner (sign-up is invite-only).
 * Not gym-scoped: it exists before the gym does.
 *
 * @property int $id
 * @property string $email
 * @property Plan $plan
 * @property string|null $gym_name
 * @property string $token_hash
 * @property int|null $invited_by
 * @property int|null $user_id
 * @property int|null $gym_id
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 */
class OwnerInvitation extends Model
{
    public const VALID_FOR_DAYS = 7;

    protected $fillable = ['email', 'plan', 'gym_name'];

    protected $hidden = ['token_hash'];

    /**
     * Returns the plain token for the email link; only its hash is stored.
     */
    public function issueToken(): string
    {
        $token = Str::random(48);

        $this->token_hash = hash('sha256', $token);
        $this->expires_at = now()->addDays(self::VALID_FOR_DAYS);

        return $token;
    }

    public static function findPendingByToken(string $token): ?self
    {
        return static::pending()->where('token_hash', hash('sha256', $token))->first();
    }

    /**
     * An accepted invitation this user hasn't used to create a gym yet.
     */
    public static function unusedFor(User $user): ?self
    {
        return static::where('user_id', $user->id)->whereNotNull('accepted_at')->whereNull('gym_id')->oldest()->first();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
