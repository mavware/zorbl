<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPaymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?string $hostedInvoiceUrl = null) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('Payment failed for your Zorbl subscription'))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('We were unable to charge your payment method for your Zorbl Pro subscription. To avoid losing access, please update your payment details.'));

        if ($this->hostedInvoiceUrl !== null) {
            $message->action(__('View invoice'), $this->hostedInvoiceUrl);
        } else {
            $message->action(__('Update payment method'), route('billing.index'));
        }

        return $message->line(__('Stripe will retry the charge automatically over the next few days.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'subscription.payment_failed',
            'title' => __('Payment failed'),
            'body' => __('Update your payment method to keep your Pro subscription active.'),
            'url' => route('billing.index'),
            'hosted_invoice_url' => $this->hostedInvoiceUrl,
        ];
    }
}
