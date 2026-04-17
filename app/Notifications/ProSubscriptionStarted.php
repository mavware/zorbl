<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProSubscriptionStarted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Welcome to Zorbl Pro'))
            ->greeting(__('Welcome to Pro, :name!', ['name' => $notifiable->name]))
            ->line(__('Your subscription is active. You now have access to AI autofill, AI clue generation, unlimited puzzles, and all export formats.'))
            ->action(__('Start creating'), route('crosswords.index'))
            ->line(__('Thanks for supporting Zorbl.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'subscription.started',
            'title' => __('Welcome to Zorbl Pro'),
            'body' => __('Your Pro subscription is now active.'),
            'url' => route('billing.index'),
        ];
    }
}
