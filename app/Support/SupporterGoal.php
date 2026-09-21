<?php

namespace App\Support;

use Laravel\Cashier\Subscription;

/**
 * Progress towards the monthly funding goal shown on the billing page.
 *
 * Revenue is estimated as a flat amount per contributor, where contributors
 * are the site's active subscribers plus the people who support the project
 * outside the subscription flow (configured via
 * `crosswordbuilder.external_contributors`). The goal itself comes from
 * `crosswordbuilder.funding_goal_dollars`.
 */
class SupporterGoal
{
    public const DOLLARS_PER_SUPPORTER = 5;

    private ?int $supporterCount = null;

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

    /**
     * Supporters who contribute outside the site's subscription flow.
     */
    public function externalContributorCount(): int
    {
        return max(0, (int) config('crosswordbuilder.external_contributors', 0));
    }

    /**
     * Everyone helping to fund the site: subscribers plus external contributors.
     */
    public function contributorCount(): int
    {
        return $this->supporterCount() + $this->externalContributorCount();
    }

    public function currentDollars(): int
    {
        return $this->contributorCount() * self::DOLLARS_PER_SUPPORTER;
    }

    /**
     * The monthly funding target, configured via
     * `crosswordbuilder.funding_goal_dollars`. Never below one dollar so the
     * percentage calculation stays well defined.
     */
    public function goalDollars(): int
    {
        return max(1, (int) config('crosswordbuilder.funding_goal_dollars', 500));
    }

    /**
     * Whole-number percentage of the goal reached, capped at 100.
     */
    public function percent(): int
    {
        return (int) min(100, floor($this->currentDollars() / $this->goalDollars() * 100));
    }

    public function isReached(): bool
    {
        return $this->currentDollars() >= $this->goalDollars();
    }
}
