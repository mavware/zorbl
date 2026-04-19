<?php

namespace App\Policies;

use App\Models\PuzzleAttempt;
use App\Models\User;

class PuzzleAttemptPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PuzzleAttempt $attempt): bool
    {
        return $user->id === $attempt->user_id;
    }

    public function update(User $user, PuzzleAttempt $attempt): bool
    {
        return $user->id === $attempt->user_id;
    }

    public function delete(User $user, PuzzleAttempt $attempt): bool
    {
        return $user->id === $attempt->user_id;
    }
}
