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
        return $user->id === $crossword->user_id
            || $crossword->is_published
            || $this->isTeamMember($user, $crossword);
    }

    public function solve(User $user, Crossword $crossword): bool
    {
        return $user->id === $crossword->user_id
            || $crossword->is_published
            || $this->isTeamMember($user, $crossword);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Crossword $crossword): bool
    {
        return $user->id === $crossword->user_id
            || $this->isTeamMember($user, $crossword);
    }

    public function delete(User $user, Crossword $crossword): bool
    {
        return $user->id === $crossword->user_id;
    }

    private function isTeamMember(User $user, Crossword $crossword): bool
    {
        if ($crossword->team_id === null) {
            return false;
        }

        return $crossword->team->hasMember($user);
    }
}
