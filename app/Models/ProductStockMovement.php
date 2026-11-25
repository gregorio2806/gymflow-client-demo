<?php

namespace App\Models;

use App\Enums\GymRole;
use App\Models\Concerns\BelongsToGym;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProductStockMovement extends Model
{
    use BelongsToGym;

    protected $fillable = ['type', 'quantity', 'stock_before', 'stock_after', 'reason', 'user_id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'stock_before' => 'integer', 'stock_after' => 'integer'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('branch', function (Builder $query) {
            $current = app(CurrentGym::class);
            if ($current->role() === GymRole::BranchManager) {
                $current->branchId()
                    ? $query->where($query->qualifyColumn('branch_id'), $current->branchId())
                    : $query->whereRaw('1 = 0');
            }
        });
        static::updating(fn () => throw new LogicException('Product stock movements are immutable.'));
        static::deleting(fn () => throw new LogicException('Product stock movements are immutable.'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
