<?php

namespace App\Policies;

use App\Models\Contest;
use App\Models\User;

class ContestPolicy
{
    /**
     * Anyone can view the contest listing.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Contest is viewable if it's not draft or archived.
     */
    public function view(?User $user, Contest $contest): bool
    {
        return $contest->isPublished();
    }

    /**
     * Users can register for upcoming or active contests.
     */
    public function register(User $user, Contest $contest): bool
    {
        return in_array($contest->status, ['upcoming', 'active']);
    }

    /**
     * Users can solve contest puzzles only when active and registered.
     */
    public function solve(User $user, Contest $contest): bool
    {
        if (! $contest->isActive()) {
            return false;
        }

        return $contest->entries()->where('user_id', $user->id)->exists();
    }

    /**
     * Users can submit meta answers when active, registered, and have attempts remaining.
     */
    public function submitMeta(User $user, Contest $contest): bool
    {
        if (! $contest->isActive()) {
            return false;
        }

        $entry = $contest->entries()->where('user_id', $user->id)->first();

        if (! $entry) {
            return false;
        }

        if ($entry->meta_solved) {
            return false;
        }

        if ($contest->max_meta_attempts > 0 && $entry->meta_attempts_count >= $contest->max_meta_attempts) {
            return false;
        }

        return true;
    }

    /**
     * Leaderboard is visible for any non-draft contest.
     */
    public function viewLeaderboard(?User $user, Contest $contest): bool
    {
        return $contest->status !== 'draft';
    }
}
