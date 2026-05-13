<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            ->subject(__('Welcome to :app', ['app' => $appName]))
            ->greeting(__('Welcome, :name!', ['name' => $notifiable->name]))
            ->line(__('Thanks for signing up. :app is a free platform for building and solving crosswords from independent constructors.', ['app' => $appName]))
            ->line(__('Here are two good ways to get started:'))
            ->line('• '.__('Browse community puzzles and try a few solves to get a feel for the editor and solver.'))
            ->line('• '.__('Build your first puzzle — the visual editor handles symmetry, numbering, and exports for you.'))
            ->action(__('Browse puzzles'), route('puzzles.index'))
            ->line(__('Stuck on anything? The :help has guides for building, solving, contests, and more.', ['help' => '['.__('Help Center').']('.route('help.index').')']))
            ->salutation(__('Happy puzzling,').'  '.$appName);
    }

    public function toArray(User $notifiable): array
    {
        return [];
    }
}
