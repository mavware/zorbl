<?php

namespace App\Filament\Resources\Templates\Actions;

use App\Models\Template;
use App\Services\TemplateFillChecker;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Tries to fill a template from the word list and reports whether it worked.
 */
class CheckFillAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'checkFill';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Check fill')
            ->icon('heroicon-o-beaker')
            ->color('gray')
            ->modalHeading('Check that this template can be filled')
            ->modalDescription('Runs the word-list filler against the empty template. Filling is the real test of a template; a grid that will not fill in the time allowed is not necessarily bad, but one that fills quickly is usable.')
            ->modalSubmitActionLabel('Run fill')
            ->schema([
                TextInput::make('timeout')
                    ->label('Seconds to allow')
                    ->numeric()
                    ->integer()
                    ->default(20)
                    ->minValue(5)
                    ->maxValue(120)
                    ->required(),
            ])
            ->action(function (array $data, Template $record): void {
                $timeout = (int) $data['timeout'];
                set_time_limit($timeout + 30);

                $result = app(TemplateFillChecker::class)->check(
                    $record->grid,
                    $record->width,
                    $record->height,
                    $record->styles ?? [],
                    $record->min_word_length,
                    $timeout,
                );

                Notification::make()
                    ->title($result['filled'] ? "{$record->name} filled" : "{$record->name} did not fill")
                    ->body($result['message'])
                    ->status($result['filled'] ? 'success' : 'warning')
                    ->persistent()
                    ->send();
            });
    }
}
