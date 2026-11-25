<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToGym;

    protected $fillable = ['name', 'sku', 'category', 'cost_centavos', 'price_centavos', 'low_stock_threshold', 'is_active', 'status'];

    protected function casts(): array
    {
        return ['cost_centavos' => 'integer', 'price_centavos' => 'integer', 'low_stock_threshold' => 'integer', 'is_active' => 'boolean'];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(ProductStockMovement::class);
    }
}
