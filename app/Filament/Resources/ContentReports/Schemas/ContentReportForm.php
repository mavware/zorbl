<?php

namespace App\Filament\Resources\ContentReports\Schemas;

use App\Models\ContentReport;
use App\Models\Crossword;
use App\Models\PuzzleComment;
use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ContentReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Report')
                    ->description('Filed by a user — review the content and decide whether to take action.')
                    ->schema([
                        Placeholder::make('reporter')
                            ->content(fn (ContentReport $record) => $record->reporter?->name.' <'.$record->reporter?->email.'>'),
                        Placeholder::make('reportable_summary')
                            ->label('Reported content')
                            ->content(function (ContentReport $record): HtmlString {
                                $type = ContentReport::REPORTABLE_TYPES[$record->reportable_type] ?? $record->reportable_type;
                                $entity = $record->reportable;
                                $label = match (true) {
                                    $entity instanceof Crossword => ($entity->title ?: 'Untitled puzzle').' (#'.$entity->id.')',
                                    $entity instanceof PuzzleComment => 'Comment by '.$entity->user?->name.' on puzzle #'.$entity->crossword_id,
                                    $entity instanceof User => $entity->name.' <'.$entity->email.'>',
                                    default => 'Deleted content',
                                };

                                return new HtmlString('<strong>'.e(ucfirst($type)).':</strong> '.e($label));
                            }),
                        Placeholder::make('reason')
                            ->content(fn (ContentReport $record) => ContentReport::REASONS[$record->reason] ?? $record->reason),
                        Placeholder::make('details')
                            ->content(fn (ContentReport $record) => $record->details ?: '—')
                            ->columnSpanFull(),
                        Placeholder::make('filed_at')
                            ->label('Filed at')
                            ->content(fn (ContentReport $record) => $record->created_at?->format('Y-m-d H:i')),
                    ])
                    ->columns(2),

                Section::make('Resolution')
                    ->schema([
                        Select::make('status')
                            ->options([
                                ContentReport::STATUS_PENDING => 'Pending',
                                ContentReport::STATUS_REVIEWING => 'Reviewing',
                                ContentReport::STATUS_ACTIONED => 'Actioned',
                                ContentReport::STATUS_DISMISSED => 'Dismissed',
                            ])
                            ->required(),
                        Textarea::make('resolution_note')
                            ->rows(3)
                            ->placeholder('Internal note about what was done (visible only to admins).')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
