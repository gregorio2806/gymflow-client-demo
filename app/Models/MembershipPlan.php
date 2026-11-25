<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Database\Factories\MembershipPlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class MembershipPlan extends Model
{
    /** @use HasFactory<MembershipPlanFactory> */
    use BelongsToGym, HasFactory;

    protected $fillable = ['name', 'duration_amount', 'duration_unit', 'inclusions'];

    protected $attributes = ['is_active' => true, 'kind' => 'subscription'];

    protected static function booted(): void
    {
        static::addGlobalScope('access-products', fn (Builder $query) => $query->where($query->qualifyColumn('kind'), 'subscription'));
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)->withoutGlobalScope('access-products');
    }

    public function creationAudit(): MorphOne
    {
        return $this->morphOne(AuditLog::class, 'subject')->ofMany(['id' => 'min'], fn (Builder $query) => $query->where('action', 'membership_plan.created'));
    }

    public function updateAudit(): MorphOne
    {
        return $this->morphOne(AuditLog::class, 'subject')->ofMany(['id' => 'max'], fn (Builder $query) => $query->whereIn('action', ['membership_plan.updated', 'membership_plan.activated', 'membership_plan.archived']));
    }

    protected function casts(): array
    {
        return ['duration_amount' => 'integer', 'price_centavos' => 'integer', 'is_active' => 'boolean', 'branch_ids' => 'array', 'configuration' => 'array'];
    }
}
