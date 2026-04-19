<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPlanChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $fromPlan, public string $toPlan) {}

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
            ->subject(__('Your Zorbl Pro plan has changed'))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('Your subscription switched from the :from plan to the :to plan.', [
                'from' => $this->fromPlan,
                'to' => $this->toPlan,
            ]))
            ->action(__('View billing'), route('billing.index'))
            ->line(__('If you did not make this change, please contact support.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'subscription.plan_changed',
            'title' => __('Plan changed'),
            'body' => __('Your subscription is now on the :to plan.', ['to' => $this->toPlan]),
            'url' => route('billing.index'),
            'from_plan' => $this->fromPlan,
            'to_plan' => $this->toPlan,
        ];
    }
}
