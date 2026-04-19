<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRefunded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $amountRefunded,
        public string $currency,
        public bool $fullRefund,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $formatted = $this->formatAmount();

        return (new MailMessage)
            ->subject(__('A refund was issued for your Zorbl subscription'))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line($this->fullRefund
                ? __('We have refunded :amount to your original payment method.', ['amount' => $formatted])
                : __('We have issued a partial refund of :amount to your original payment method.', ['amount' => $formatted])
            )
            ->line(__('It may take a few business days for the refund to appear on your statement.'))
            ->action(__('View billing'), route('billing.index'))
            ->line(__('If you have any questions, reply to this email and we will help.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'subscription.refunded',
            'title' => $this->fullRefund ? __('Refund issued') : __('Partial refund issued'),
            'body' => __('We refunded :amount to your original payment method.', ['amount' => $this->formatAmount()]),
            'url' => route('billing.index'),
            'amount_refunded' => $this->amountRefunded,
            'currency' => $this->currency,
            'full_refund' => $this->fullRefund,
        ];
    }

    private function formatAmount(): string
    {
        return number_format($this->amountRefunded / 100, 2).' '.strtoupper($this->currency);
    }
}
