<?php

namespace App\Policies;

use App\Models\Template;
use App\Models\User;

class TemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Template $template): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, Template $template): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, Template $template): bool
    {
        return $user->hasRole('Admin');
    }
}
