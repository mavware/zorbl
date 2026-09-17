<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;

class EditSupportTicket extends EditRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * A reply from the conversation panel can change the ticket's status, so
     * refill those fields to keep the form from overwriting them on save.
     */
    #[On('support-ticket-replied')]
    public function refreshTicketStatus(): void
    {
        $this->refreshFormData(['status', 'closed_at']);
    }

    protected function afterSave(): void
    {
        $ticket = $this->record;

        if ($ticket->status === 'closed' && $ticket->closed_at === null) {
            $ticket->update(['closed_at' => now()]);
        } elseif ($ticket->status !== 'closed' && $ticket->closed_at !== null) {
            $ticket->update(['closed_at' => null]);
        }
    }
}
