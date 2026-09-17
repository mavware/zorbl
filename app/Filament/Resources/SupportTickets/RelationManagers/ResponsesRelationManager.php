<?php

namespace App\Filament\Resources\SupportTickets\RelationManagers;

use App\Models\SupportTicket;
use App\Models\TicketResponse;
use App\Notifications\SupportTicketReplied;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ResponsesRelationManager extends RelationManager
{
    protected static string $relationship = 'responses';

    protected static ?string $title = 'Conversation';

    protected static bool $isLazy = false;

    /** @var array<string, string> */
    private const STATUS_OPTIONS = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        $responseCount = $ownerRecord instanceof SupportTicket ? $ownerRecord->responses()->count() : 0;

        return Tab::make('Conversation')
            ->badge($responseCount)
            ->icon(Heroicon::ChatBubbleLeftRight);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->label('Message')
                    ->required()
                    ->minLength(5)
                    ->maxLength(5000)
                    ->rows(6)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('created_at', 'asc')
            ->paginated(false)
            ->emptyStateHeading('No responses yet')
            ->emptyStateDescription('Reply to start the conversation with the ticket submitter.')
            ->columns([
                TextColumn::make('user.name')
                    ->label('From')
                    ->description(fn (TicketResponse $record): string => $record->is_admin_response ? 'Staff' : 'Submitter')
                    ->weight('semibold'),
                IconColumn::make('is_admin_response')
                    ->label('Staff')
                    ->boolean()
                    ->trueIcon(Heroicon::ShieldCheck)
                    ->falseIcon(Heroicon::User)
                    ->trueColor('primary')
                    ->falseColor('gray'),
                TextColumn::make('body')
                    ->label('Message')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime()
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->headerActions([
                $this->replyAction(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (TicketResponse $record): bool => $record->user_id === Auth::id()),
                DeleteAction::make(),
            ]);
    }

    private function replyAction(): Action
    {
        return Action::make('reply')
            ->label('Reply')
            ->icon(Heroicon::PaperAirplane)
            ->modalHeading('Reply to ticket')
            ->modalSubmitActionLabel('Send reply')
            ->schema([
                Textarea::make('body')
                    ->label('Message')
                    ->placeholder('Write your reply to the submitter...')
                    ->required()
                    ->minLength(5)
                    ->maxLength(5000)
                    ->rows(8)
                    ->autofocus()
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Set ticket status')
                    ->options(self::STATUS_OPTIONS)
                    ->default(fn (): string => $this->defaultStatusAfterReply())
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data): void {
                $this->sendReply($data['body'], $data['status']);
            });
    }

    /**
     * Replying to a fresh ticket moves it into progress; otherwise keep the
     * current status so the admin can confirm or change it explicitly.
     */
    private function defaultStatusAfterReply(): string
    {
        $ticket = $this->getTicket();

        return $ticket->status === 'open' ? 'in_progress' : $ticket->status;
    }

    private function sendReply(string $body, string $status): void
    {
        $ticket = $this->getTicket();

        /** @var TicketResponse $response */
        $response = $ticket->responses()->create([
            'user_id' => Auth::id(),
            'body' => $body,
            'is_admin_response' => true,
        ]);

        $ticket->update([
            'status' => $status,
            'closed_at' => $status === 'closed' ? ($ticket->closed_at ?? now()) : null,
        ]);

        if ($ticket->user_id !== Auth::id()) {
            $ticket->user->notify(new SupportTicketReplied($ticket, $response));
        }

        Notification::make()
            ->title('Reply sent')
            ->body('The submitter has been notified.')
            ->success()
            ->send();

        $this->dispatch('support-ticket-replied');
    }

    private function getTicket(): SupportTicket
    {
        /** @var SupportTicket $ticket */
        $ticket = $this->getOwnerRecord();

        return $ticket;
    }
}
