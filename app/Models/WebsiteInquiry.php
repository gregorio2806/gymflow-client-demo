<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use App\Support\WebsitePrivacy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class WebsiteInquiry extends Model
{
    use BelongsToGym, MassPrunable;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['consented_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    public function prunable(): Builder
    {
        // Scheduler has no tenant; only this retention operation deliberately crosses gyms.
        return static::withoutGlobalScope('gym')->where('created_at', '<=', now()->utc()->subDays(WebsitePrivacy::RETENTION_DAYS));
    }
}
