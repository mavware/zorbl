<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SupportTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subject')
                    ->disabled(),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->disabled()
                    ->label('Submitted By'),
                Textarea::make('description')
                    ->disabled()
                    ->columnSpanFull(),
                Select::make('category')
                    ->options([
                        'bug_report' => 'Bug Report',
                        'feature_request' => 'Feature Request',
                        'account_issue' => 'Account Issue',
                        'puzzle_issue' => 'Puzzle Issue',
                        'general' => 'General',
                    ])
                    ->required(),
                Select::make('status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ])
                    ->required(),
                Select::make('priority')
                    ->options([
                        'low' => 'Low',
                        'normal' => 'Normal',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ])
                    ->required(),
                Select::make('assigned_to')
                    ->relationship('assignee', 'name')
                    ->label('Assigned To')
                    ->searchable()
                    ->preload(),
                DateTimePicker::make('closed_at')
                    ->disabled(),
            ]);
    }
}
