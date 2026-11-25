<?php

namespace App\Models;

use App\Enums\GymRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property bool $is_platform_admin SaaS operator (not a gym role). Never fillable.
 * @property bool $is_demo Temporary "Try demo" account. Never fillable.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Gyms this user works at, with their role in each.
     *
     * @return BelongsToMany<Gym, $this, GymUser>
     */
    public function gyms(): BelongsToMany
    {
        return $this->belongsToMany(Gym::class)
            ->using(GymUser::class)
            ->withPivot('role', 'branch_id', 'branch_ids', 'is_active', 'availability', 'availability_revision')
            ->withTimestamps();
    }

    /**
     * The user's role in the given gym, or null if they don't belong to it.
     */
    public function roleIn(Gym $gym): ?GymRole
    {
        $role = $this->gyms()->wherePivot('is_active', true)->whereKey($gym->getKey())->first()?->pivot?->role;

        return $role instanceof GymRole ? $role : null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'terms_accepted_at' => 'immutable_datetime',
            'is_platform_admin' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }
}
