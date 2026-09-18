<?php

namespace App\Support;

use Laravel\Cashier\Subscription;

/**
 * Progress towards the monthly funding goal shown on the billing page.
 *
 * Revenue is estimated as a flat amount per active supporter plus a constant
 * that stands in for income earned outside the subscription model.
 */
class SupporterGoal
{
    public const GOAL_DOLLARS = 500;

    private ?int $supporterCount = null;

    public const DOLLARS_PER_SUPPORTER = 5;

    public const NON_SUBSCRIPTION_DOLLARS = 215;

    /**
     * Distinct users with an active (or grace-period) default subscription.
     */
    public function supporterCount(): int
    {
        return $this->supporterCount ??= Subscription::query()
            ->where('type', 'default')
            ->active()
            ->distinct()
            ->count('user_id');
    }

    public function currentDollars(): int
    {
        return ($this->supporterCount() * self::DOLLARS_PER_SUPPORTER) + self::NON_SUBSCRIPTION_DOLLARS;
    }

    public function goalDollars(): int
    {
        return self::GOAL_DOLLARS;
    }

    /**
     * Whole-number percentage of the goal reached, capped at 100.
     */
    public function percent(): int
    {
        return (int) min(100, floor($this->currentDollars() / self::GOAL_DOLLARS * 100));
    }

    public function isReached(): bool
    {
        return $this->currentDollars() >= self::GOAL_DOLLARS;
    }
}
