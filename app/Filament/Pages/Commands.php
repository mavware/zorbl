<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs maintenance Artisan commands from the admin panel and shows their output.
 *
 * Commands run inside the request, so clue backfill is capped at a few words
 * per run to stay well within the request timeout.
 */
class Commands extends Page
{
    /**
     * Most words one backfill run may process; each word is a separate AI call.
     */
    public const int MAX_BACKFILL_WORDS = 5;

    protected string $view = 'filament.pages.commands';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?int $navigationSort = 4;

    public ?string $lastCommand = null;

    public ?string $output = null;

    public function exportWordsAction(): Action
    {
        return Action::make('exportWords')
            ->label('Run words:export-json')
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->modalDescription('Rebuild the word list JSON and warm the cache the API serves it from.')
            ->action(fn () => $this->runCommand('words:export-json'));
    }

    public function backfillCluesAction(): Action
    {
        return Action::make('backfillClues')
            ->label('Run clues:backfill')
            ->icon(Heroicon::OutlinedSparkles)
            ->modalDescription('Write clues with AI for catalog words that have none. This spends API credits.')
            ->modalSubmitActionLabel('Run')
            ->fillForm(['words' => 1, 'limit' => 20, 'approve' => false])
            ->schema([
                TextInput::make('words')
                    ->label('Words to process')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(self::MAX_BACKFILL_WORDS)
                    ->required(),
                TextInput::make('limit')
                    ->label('Maximum clues per word')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(50)
                    ->required(),
                Toggle::make('approve')
                    ->label('Approve clues immediately')
                    ->helperText('Off stores them as pending review.'),
            ])
            ->action(fn (array $data) => $this->runCommand('clues:backfill', [
                '--words' => (int) $data['words'],
                '--limit' => (int) $data['limit'],
                '--approve' => (bool) $data['approve'],
            ]));
    }

    /**
     * @param  array<string, bool|int|string>  $parameters
     */
    private function runCommand(string $command, array $parameters = []): void
    {
        $exitCode = Artisan::call($command, $parameters);

        $this->lastCommand = $command;
        $this->output = trim(Artisan::output());

        $notification = Notification::make()->title($exitCode === 0 ? "{$command} finished" : "{$command} failed");

        ($exitCode === 0 ? $notification->success() : $notification->danger())->send();
    }
}
