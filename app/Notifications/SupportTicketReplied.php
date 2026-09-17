<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Models\TicketResponse;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent to the ticket owner when a staff member replies to their support ticket.
 * Support replies are transactional, so they bypass notification preferences.
 */
class SupportTicketReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SupportTicket $ticket,
        public TicketResponse $response,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'support.replied',
            'title' => __('Support replied to ":subject"', ['subject' => $this->ticket->subject]),
            'body' => $this->response->body,
            'url' => route('support.show', $this->ticket),
            'ticket_id' => $this->ticket->id,
            'response_id' => $this->response->id,
        ];
    }
}
