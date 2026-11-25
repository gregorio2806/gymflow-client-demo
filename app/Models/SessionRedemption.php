<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionRedemption extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(CoachSchedule::class, 'coach_schedule_id');
    }
}
