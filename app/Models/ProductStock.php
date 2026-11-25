<?php

namespace App\Models;

use App\Enums\GymRole;
use App\Models\Concerns\BelongsToGym;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStock extends Model
{
    use BelongsToGym;

    protected $fillable = ['quantity'];

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
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
