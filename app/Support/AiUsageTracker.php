<?php

namespace App\Support;

use App\Models\AiUsage;
use App\Models\User;

class AiUsageTracker
{
    /**
     * Get the number of AI usages for a user this month.
     */
    public function monthlyCount(User $user, string $type): int
    {
        return AiUsage::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    /**
     * Get the monthly limit for a given AI feature type.
     */
    public function monthlyLimit(User $user, string $type): int
    {
        $limits = $user->planLimits();

        return match ($type) {
            'grid_fill' => $limits->monthlyAiFills(),
            'clue_generation' => $limits->monthlyAiClues(),
            default => 0,
        };
    }

    /**
     * Check if the user can use the given AI feature.
     */
    public function canUse(User $user, string $type): bool
    {
        $limit = $this->monthlyLimit($user, $type);

        if ($limit === 0) {
            return false;
        }

        return $this->monthlyCount($user, $type) < $limit;
    }

    /**
     * Record an AI usage.
     */
    public function record(User $user, string $type): void
    {
        AiUsage::create([
            'user_id' => $user->id,
            'type' => $type,
        ]);
    }

    /**
     * Get remaining uses for this month.
     */
    public function remaining(User $user, string $type): int
    {
        return max(0, $this->monthlyLimit($user, $type) - $this->monthlyCount($user, $type));
    }
}
