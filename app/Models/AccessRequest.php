<?php

namespace App\Models;

use App\Enums\Plan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A gym owner asking for access via the website form. Platform-level (no gym yet).
 * Pruned automatically (`model:prune`, daily) so personal data isn't kept longer than needed.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $gym_name
 * @property string|null $location
 * @property Plan $plan
 * @property string|null $message
 * @property string $status new | invited | dismissed
 * @property string|null $ip_address
 * @property Carbon $created_at
 */
class AccessRequest extends Model
{
    use MassPrunable;

    protected $fillable = ['name', 'email', 'phone', 'gym_name', 'location', 'plan', 'message'];

    /**
     * Handled requests after 90 days; never-handled ones after 180 days.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::query()->where(fn (Builder $query) => $query
            ->where(fn (Builder $q) => $q->where('status', '!=', 'new')->where('updated_at', '<', now()->subDays(90)))
            ->orWhere(fn (Builder $q) => $q->where('status', 'new')->where('created_at', '<', now()->subDays(180))));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
        ];
    }
}
