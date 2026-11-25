<?php

namespace App\Enums;

/**
 * Subscription plans. Names, prices, and limits live in config/plans.php.
 */
enum Plan: string
{
    case Starter = 'starter';
    case Pro = 'pro';
    case MultiBranch = 'multi_branch';

    public function label(): string
    {
        return config("plans.plans.{$this->value}.name");
    }

    public function monthlyPrice(): int
    {
        return config("plans.plans.{$this->value}.monthly_price");
    }

    /**
     * @return array<string, int|null> null = unlimited
     */
    public function limits(): array
    {
        return config("plans.plans.{$this->value}.limits");
    }
}
