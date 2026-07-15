<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionEnded extends Notification implements ShouldQueue
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
            ->subject(__('Your :app Pro subscription has ended', ['app' => config('app.name')]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('Your :app Pro subscription has ended and your account has been moved back to the Free plan.', ['app' => config('app.name')]))
            ->line(__("You'll keep access to puzzles you've already created, but Pro features like AI autofill and unlimited exports are no longer available."))
            ->action(__('Resubscribe'), route('billing.index'))
            ->line(__('Thanks for being a :app Pro subscriber.', ['app' => config('app.name')]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'subscription.ended',
            'title' => __('Subscription ended'),
            'body' => __('Your Pro subscription has ended. Resubscribe any time.'),
            'url' => route('billing.index'),
        ];
    }
}
