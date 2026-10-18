<?php

namespace App\Filament\Pages;

use App\Enums\ClueSource;
use App\Enums\PuzzlePublication;
use App\Enums\TemplateGeneratorStyle;
use App\Jobs\GenerateTemplateDrafts;
use App\Support\TemplateTargets;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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

    /**
     * Most 500-entry Wiktionary pages one import run may read; each is a separate request.
     */
    public const int MAX_IMPORT_PAGES = 20;

    /**
     * Most puzzles per publication one scrape run may read; each is a separate request.
     */
    public const int MAX_SCRAPE_PUZZLES = 5;

    /**
     * Most templates one generation run may build; annealing takes several seconds each at the larger sizes.
     */
    public const int MAX_GENERATE_TEMPLATES = 3;

    /**
     * Largest side a grid generated inside the request may have; the Templates page queues anything bigger.
     */
    public const int MAX_GENERATE_SIZE = GenerateTemplateDrafts::QUEUE_ABOVE_SIZE;

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
            ->modalDescription('Write clues for catalog words that have none. The AI source spends API credits.')
            ->modalSubmitActionLabel('Run')
            ->fillForm(['source' => ClueSource::Ai->value, 'words' => 1, 'limit' => 20, 'approve' => false])
            ->schema([
                Select::make('source')
                    ->options(collect(ClueSource::cases())
                        ->mapWithKeys(fn (ClueSource $source): array => [$source->value => $source->label()])
                        ->all())
                    ->required(),
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
                '--source' => (string) $data['source'],
                '--words' => (int) $data['words'],
                '--limit' => (int) $data['limit'],
                '--approve' => (bool) $data['approve'],
            ]));
    }

    public function importSpreadWordlistAction(): Action
    {
        return Action::make('importSpreadWordlist')
            ->label('Run words:import-spread-wordlist')
            ->icon(Heroicon::OutlinedStar)
            ->requiresConfirmation()
            ->modalDescription('Rescore every word from Spread the Wordlist. Words it does not list are kept but scored as unvetted, so Quick Fill only reaches for them when nothing better fits. This rewrites every row in the words table and can take a minute.')
            ->action(function (): void {
                set_time_limit(600);

                $this->runCommand('words:import-spread-wordlist');
            });
    }

    public function importWordsAction(): Action
    {
        return Action::make('importWords')
            ->label('Run words:import-wiktionary')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->modalDescription('Add words and phrases from Wiktionary that are not in the word list yet, continuing from where the last run stopped.')
            ->modalSubmitActionLabel('Run')
            ->fillForm(['pages' => 5, 'restart' => false])
            ->schema([
                TextInput::make('pages')
                    ->label('Pages to read (500 entries each)')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(self::MAX_IMPORT_PAGES)
                    ->required(),
                Toggle::make('restart')
                    ->label('Start from the beginning'),
            ])
            ->action(fn (array $data) => $this->runCommand('words:import-wiktionary', [
                '--pages' => (int) $data['pages'],
                '--restart' => (bool) $data['restart'],
            ]));
    }

    public function scrapePublishedCluesAction(): Action
    {
        return Action::make('scrapePublishedClues')
            ->label('Run clues:scrape-published')
            ->icon(Heroicon::OutlinedNewspaper)
            ->modalDescription('Read completed crosswords from trusted publications and file their words and clues, continuing from where the last run stopped.')
            ->modalSubmitActionLabel('Run')
            ->fillForm(['publication' => 'all', 'puzzles' => 1, 'approve' => false])
            ->schema([
                Select::make('publication')
                    ->options(collect(PuzzlePublication::cases())
                        ->mapWithKeys(fn (PuzzlePublication $publication): array => [$publication->value => $publication->label()])
                        ->prepend('All publications', 'all')
                        ->all())
                    ->required(),
                TextInput::make('puzzles')
                    ->label('Puzzles per publication')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(self::MAX_SCRAPE_PUZZLES)
                    ->required(),
                Toggle::make('approve')
                    ->label('Approve clues immediately')
                    ->helperText('Off stores them as pending review.'),
            ])
            ->action(fn (array $data) => $this->runCommand('clues:scrape-published', array_filter([
                '--publication' => $data['publication'] === 'all' ? null : (string) $data['publication'],
                '--puzzles' => (int) $data['puzzles'],
                '--approve' => (bool) $data['approve'],
            ], fn (mixed $value): bool => $value !== null)));
    }

    public function generateTemplatesAction(): Action
    {
        $side = fn (string $name): TextInput => TextInput::make($name)
            ->numeric()
            ->integer()
            ->minValue(TemplateTargets::MIN_SIZE)
            ->maxValue(self::MAX_GENERATE_SIZE);

        return Action::make('generateTemplates')
            ->label('Run templates:generate')
            ->icon(Heroicon::OutlinedSquares2x2)
            ->modalDescription('Build symmetric grid templates procedurally, square or rectangular, and save them as inactive drafts or activate them straight away. The Templates page has the full set of levers.')
            ->modalSubmitActionLabel('Run')
            ->fillForm(['width' => 15, 'height' => null, 'style' => TemplateGeneratorStyle::Standard->value, 'count' => 1, 'seed' => null, 'activate' => false])
            ->schema([
                $side('width')
                    ->label('Width')
                    ->required(),
                $side('height')
                    ->label('Height')
                    ->placeholder('Same as width')
                    ->helperText('Leave blank for a square grid.'),
                Select::make('style')
                    ->options(collect(TemplateGeneratorStyle::cases())
                        ->mapWithKeys(fn (TemplateGeneratorStyle $style): array => [$style->value => $style->label()])
                        ->all())
                    ->helperText('Themed and themeless need at least 11 on each side.')
                    ->required(),
                TextInput::make('count')
                    ->label('Templates to generate')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(self::MAX_GENERATE_TEMPLATES)
                    ->required(),
                TextInput::make('seed')
                    ->label('Seed')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->helperText('Optional. The same size, style and seed always produce the same grids.'),
                Toggle::make('activate')
                    ->label('Activate immediately')
                    ->helperText('Off saves them as inactive drafts to review on the Templates page.'),
            ])
            ->action(function (array $data): void {
                $width = (int) $data['width'];
                $height = filled($data['height'] ?? null) ? (int) $data['height'] : $width;

                // Annealing a 21-wide grid takes around half a minute per template.
                set_time_limit(360);

                $this->runCommand('templates:generate', array_filter([
                    'size' => $width === $height ? (string) $width : "{$width}x{$height}",
                    '--style' => (string) $data['style'],
                    '--count' => (int) $data['count'],
                    '--seed' => filled($data['seed'] ?? null) ? (int) $data['seed'] : null,
                    '--activate' => (bool) $data['activate'],
                ], fn (mixed $value): bool => $value !== null));
            });
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
