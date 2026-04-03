<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSupportTicket extends EditRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
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
