<?php

namespace App\Models;

use App\Actions\ReconcilePlanLimits;
use App\Enums\BillingCycle;
use App\Enums\GymFeature;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Laravel\Pennant\Feature;

/**
 * A gym's current plan. Written only by system code and the platform admin — never
 * from gym owners' form input.
 *
 * @property int $id
 * @property int $gym_id
 * @property Plan $plan
 * @property BillingCycle $billing_cycle
 * @property int $extra_branches
 * @property SubscriptionStatus $status
 * @property Carbon|null $current_period_ends_at
 */
class GymSubscription extends Model
{
    use BelongsToGym;

    protected $fillable = ['plan', 'billing_cycle', 'extra_branches', 'status', 'current_period_ends_at'];

    protected $attributes = [
        'plan' => 'starter',
        'billing_cycle' => 'monthly',
        'extra_branches' => 0,
        'status' => 'trialing',
    ];

    protected static function booted(): void
    {
        static::updated(function (GymSubscription $subscription) {
            if (! $subscription->wasChanged(['plan', 'extra_branches'])) {
                return;
            }
            $gym = Gym::findOrFail($subscription->gym_id);
            $overrides = $gym->feature_overrides ?? [];
            foreach (GymFeature::cases() as $feature) {
                if ($subscription->wasChanged('plan') && $feature->dependsOnPlan() && ! array_key_exists($feature->value, $overrides)) {
                    Feature::for($gym)->forget($feature->value);
                }
            }
            app(ReconcilePlanLimits::class)->handle($gym);
        });
    }

    /**
     * Branches this subscription pays for (included + extra).
     */
    public function branchLimit(): int
    {
        return $this->plan->limits()['branches'] + ($this->plan === Plan::MultiBranch ? $this->extra_branches : 0);
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
            'billing_cycle' => BillingCycle::class,
            'status' => SubscriptionStatus::class,
            'extra_branches' => 'integer',
            'current_period_ends_at' => 'datetime',
        ];
    }
}
