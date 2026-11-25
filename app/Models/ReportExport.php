<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class ReportExport extends Model
{
    use BelongsToGym, MassPrunable;

    protected $guarded = ['*'];

    protected $hidden = ['csv'];

    protected static function booted(): void
    {
        static::creating(function (ReportExport $export) {
            if (($export->branch_id !== null && ! Branch::withoutGlobalScopes()->where('gym_id', $export->gym_id)->whereKey($export->branch_id)->exists()) || ! User::whereHas('gyms', fn ($q) => $q->where('gyms.id', $export->gym_id)->where('gym_user.is_active', true))->whereKey($export->user_id)->exists()) {
                throw new \LogicException('Export actor and branch must belong to its gym.');
            }
        });
        static::updating(function (ReportExport $export) {
            if ($export->isDirty(['user_id', 'branch_id', 'requested_role', 'from', 'through', 'request_key', 'expires_at'])) {
                throw new \LogicException('Export request identity is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['csv' => 'encrypted', 'expires_at' => 'immutable_datetime'];
    }

    public function prunable()
    {
        return static::withoutGlobalScope('gym')->where('expires_at', '<=', now()->utc());
    }
}
