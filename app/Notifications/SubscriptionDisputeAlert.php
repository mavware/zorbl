<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionDisputeAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $disputeId,
        public int $amount,
        public string $currency,
        public ?string $reason,
        public ?int $customerUserId,
        public ?string $customerEmail,
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
        $formatted = number_format($this->amount / 100, 2).' '.strtoupper($this->currency);

        $message = (new MailMessage)
            ->subject(__('Stripe dispute opened (:amount)', ['amount' => $formatted]))
            ->greeting(__('Heads up,'))
            ->line(__('A Stripe dispute was just opened for :amount.', ['amount' => $formatted]))
            ->line(__('Dispute ID: :id', ['id' => $this->disputeId]))
            ->line(__('Reason: :reason', ['reason' => $this->reason ?? 'unspecified']));

        if ($this->customerEmail !== null) {
            $message->line(__('Customer email: :email', ['email' => $this->customerEmail]));
        }

        return $message
            ->action(__('Open Stripe dashboard'), 'https://dashboard.stripe.com/disputes/'.$this->disputeId)
            ->line(__('Respond in the Stripe dashboard before the evidence deadline.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'admin.dispute_opened',
            'title' => __('Stripe dispute opened'),
            'body' => __('A chargeback was opened. Respond in the Stripe dashboard.'),
            'url' => 'https://dashboard.stripe.com/disputes/'.$this->disputeId,
            'dispute_id' => $this->disputeId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'customer_user_id' => $this->customerUserId,
            'customer_email' => $this->customerEmail,
        ];
    }
}
