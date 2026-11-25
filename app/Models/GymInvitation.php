<?php

namespace App\Models;

use App\Enums\GymRole;
use App\Models\Concerns\BelongsToGym;
use App\Tenancy\CurrentGym;
use Database\Factories\GymInvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An emailed invitation for someone to join a gym as staff or coach.
 *
 * @property int $id
 * @property int $gym_id
 * @property string $email
 * @property GymRole $role
 * @property string $token_hash
 * @property int|null $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 */
class GymInvitation extends Model
{
    /** @use HasFactory<GymInvitationFactory> */
    use BelongsToGym, HasFactory;

    public const VALID_FOR_DAYS = 7;

    protected $fillable = ['name', 'email', 'role'];

    protected $hidden = ['token_hash'];

    protected static function booted(): void
    {
        static::addGlobalScope('branch', fn (Builder $query) => app(CurrentGym::class)->restrictToBranch($query));
        static::creating(function (GymInvitation $invitation) {
            $gymId = $invitation->gym_id ?? app(CurrentGym::class)->id();
            if ($invitation->branch_id === null) {
                $invitation->branch_id = Gym::findOrFail($gymId)->mainBranch()->id;
            }
        });
    }

    /**
     * Create a token for this invitation. Returns the plain token (for the email link only);
     * only its hash is stored.
     */
    public function issueToken(): string
    {
        $token = Str::random(48);

        $this->token_hash = self::hashToken($token);
        $this->expires_at = now()->addDays(self::VALID_FOR_DAYS);

        return $token;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Find a usable invitation from an emailed token. The token is the secret, so this
     * deliberately looks across all gyms (the invitee has no current gym yet).
     */
    public static function findPendingByToken(string $token): ?self
    {
        return static::withoutGlobalScopes(['gym', 'branch'])
            ->pending()
            ->where('token_hash', self::hashToken($token))
            ->first();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => GymRole::class,
            'branch_ids' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
