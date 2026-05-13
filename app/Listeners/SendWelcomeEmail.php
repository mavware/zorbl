<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\WelcomeEmail;
use Illuminate\Auth\Events\Registered;

class SendWelcomeEmail
{
    public function handle(Registered $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        $user->notify(new WelcomeEmail);
    }
}
