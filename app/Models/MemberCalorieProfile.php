<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;

class MemberCalorieProfile extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    protected $hidden = ['details'];

    protected static function booted(): void
    {
        static::creating(function (MemberCalorieProfile $profile) {
            if (! Member::withoutGlobalScope('gym')->withoutGlobalScope('branch')->where('gym_id', $profile->gym_id)->whereKey($profile->member_id)->exists()) {
                throw new \LogicException('Calorie profile member must belong to its gym.');
            }
        });
        static::updating(function (MemberCalorieProfile $profile) {
            if ($profile->isDirty('member_id')) {
                throw new \LogicException('Calorie profile ownership is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['details' => 'encrypted:array', 'consented_at' => 'immutable_datetime'];
    }
}
