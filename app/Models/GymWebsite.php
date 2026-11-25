<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use App\Support\WebsitePrivacy;
use Illuminate\Database\Eloquent\Model;

class GymWebsite extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::saving(function (GymWebsite $website) {
            if ($website->isDirty(['published_content', 'published_at'])) {
                $website->published_revision = ($website->published_revision ?? 0) + 1;
                $website->contact_form_enabled = $website->published_at !== null && WebsitePrivacy::enabled($website->published_content ?? []);
            }
        });
    }

    protected function casts(): array
    {
        return ['contact_form_enabled' => 'boolean', 'draft_content' => 'array', 'draft_revision' => 'integer', 'published_revision' => 'integer', 'published_content' => 'array', 'published_at' => 'immutable_datetime'];
    }
}
