<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $amount,
        public string $currency,
        public ?string $hostedInvoiceUrl = null,
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

        $message = (new MailMessage)
            ->subject(__('Your Zorbl Pro subscription renewed'))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('Your Zorbl Pro subscription renewed for :amount.', ['amount' => $formatted]));

        if ($this->hostedInvoiceUrl !== null) {
            $message->action(__('View invoice'), $this->hostedInvoiceUrl);
        }

        return $message->line(__('Thanks for sticking with Zorbl.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'subscription.renewed',
            'title' => __('Subscription renewed'),
            'body' => __('Your Pro subscription renewed for :amount.', ['amount' => $this->formatAmount()]),
            'url' => route('billing.index'),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'hosted_invoice_url' => $this->hostedInvoiceUrl,
        ];
    }

    private function formatAmount(): string
    {
        return number_format($this->amount / 100, 2).' '.strtoupper($this->currency);
    }
}
