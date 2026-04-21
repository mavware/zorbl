<?php

namespace App\Policies;

use App\Models\RoadmapItem;
use App\Models\User;

class RoadmapItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RoadmapItem $roadmapItem): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, RoadmapItem $roadmapItem): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, RoadmapItem $roadmapItem): bool
    {
        return $user->hasRole('Admin');
    }
}
