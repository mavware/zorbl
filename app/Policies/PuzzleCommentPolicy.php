<?php

namespace App\Policies;

use App\Models\PuzzleComment;
use App\Models\User;

class PuzzleCommentPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, PuzzleComment $puzzleComment): bool
    {
        return $user->id === $puzzleComment->user_id;
    }
}
