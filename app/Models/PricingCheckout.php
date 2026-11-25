<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;

class PricingCheckout extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['summary' => 'array'];
    }
}
