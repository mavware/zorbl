<?php

namespace App\Policies;

use App\Models\Crossword;
use App\Models\User;

class CrosswordPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Crossword $crossword): bool
    {
        return $user->id === $crossword->user_id || $crossword->is_published;
    }

    /**
     * Determine if the user can solve the crossword.
     * Owners can always solve; others can solve published puzzles.
     */
    public function solve(User $user, Crossword $crossword): bool
    {
        return $user->id === $crossword->user_id || $crossword->is_published;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Crossword $crossword): bool
    {
        return $user->id === $crossword->user_id;
    }

    public function delete(User $user, Crossword $crossword): bool
    {
        return $user->id === $crossword->user_id;
    }
}
