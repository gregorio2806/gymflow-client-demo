<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;

class PricingDiscount extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['product_kinds' => 'array', 'plan_ids' => 'array', 'value' => 'integer', 'is_active' => 'boolean', 'requires_verification' => 'boolean'];
    }
}
