<?php

namespace App\Models;

use App\Enums\GymRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A user's membership in a gym, with their role there.
 *
 * @property int $gym_id
 * @property int $user_id
 * @property GymRole $role
 */
class GymUser extends Pivot
{
    public $incrementing = true;

    protected static function booted(): void
    {
        static::creating(function (GymUser $membership) {
            if ($membership->role !== GymRole::Owner && $membership->branch_id === null) {
                $membership->branch_id = Gym::findOrFail($membership->gym_id)->mainBranch()->id;
            }
        });
        static::updating(function (GymUser $membership) {
            if ($membership->role === GymRole::Owner && ! $membership->is_active) {
                throw new \LogicException('The owner cannot be deactivated.');
            }
        });
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
            'branch_id' => 'integer',
            'branch_ids' => 'array',
            'availability' => 'array',
            'availability_revision' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
