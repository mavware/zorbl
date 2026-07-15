<?php

namespace App\Filament\Pages;

use App\Services\AiThemeBuilder;
use App\Services\GridFiller;
use App\Services\ThemeWordPlacer;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 * @property-read Schema $wordsForm
 */
class Prompt extends Page
{
    protected string $view = 'filament.pages.prompt';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Sparkles;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $wordsData = [];

    /**
     * @var array{success: bool, entries: list<array{entry: string, length: int, explanation: string}>, assumptions: string, message: string}|null
     */
    public ?array $result = null;

    public function mount(): void
    {
        $this->form->fill();
        $this->wordsForm->fill();
    }

    /**
     * @return array<int, string>
     */
    protected function getForms(): array
    {
        return ['form', 'wordsForm'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('prompt')
                    ->label('Prompt')
                    ->placeholder('Describe a theme, wordplay angle, and concept...')
                    ->rows(6)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function wordsForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('words')
                    ->label('Words to place')
                    ->helperText('These words will be fitted into a 15×15 template. Edit the list to your liking.')
                    ->simple(
                        TextInput::make('word')
                            ->placeholder('WORD OR PHRASE')
                            ->required(),
                    )
                    ->addActionLabel('Add word')
                    ->defaultItems(0),
            ])
            ->statePath('wordsData');
    }

    public function submit(AiThemeBuilder $builder): void
    {
        $data = $this->form->getState();

        $this->result = $builder->build((string) ($data['prompt'] ?? ''));

        if ($this->result['success']) {
            $this->wordsForm->fill([
                'words' => array_map(fn (array $entry): string => $entry['entry'], $this->result['entries']),
            ]);
        }
    }

    public function buildPuzzle(ThemeWordPlacer $placer, GridFiller $filler): void
    {
        /** @var list<string> $words */
        $words = collect($this->wordsData['words'] ?? [])
            ->map(fn (mixed $item): mixed => is_array($item) ? ($item['word'] ?? '') : $item)
            ->map(fn (mixed $word): string => strtoupper((string) preg_replace('/[^A-Za-z]/', '', (string) $word)))
            ->filter()
            ->values()
            ->all();

        if ($words === []) {
            Notification::make()->title('Add at least one word to place.')->warning()->send();

            return;
        }

        $placement = $placer->place($words);

        if ($placement === null) {
            Notification::make()
                ->title('No 15×15 template fits all of those words. Try removing or shortening some.')
                ->danger()
                ->send();

            return;
        }

        $grid = $placement['grid'];
        $styles = $placement['styles'];
        $solution = $placement['solution'];

        // Fill the remaining empty cells with the heuristic board filler,
        // keeping the theme words we already placed as fixed constraints.
        $result = $filler->fill($grid, $solution, 15, 15, $styles ?? [], 3, 10, mt_rand(1, mt_getrandmax()));
        $solution = $this->applyFills($solution, $result['fills'], $placement['across'], $placement['down']);

        $user = auth()->user();

        $crossword = $user->crosswords()->create([
            'title' => null,
            'author' => $user->name,
            'copyright' => copyright($user->copyright_name ?? $user->name ?? ''),
            'width' => 15,
            'height' => 15,
            'grid' => $grid,
            'solution' => $solution,
            'styles' => $styles,
            'clues_across' => array_map(fn (array $slot): array => ['number' => $slot['number'], 'clue' => ''], $placement['across']),
            'clues_down' => array_map(fn (array $slot): array => ['number' => $slot['number'], 'clue' => ''], $placement['down']),
        ]);

        $this->redirect(route('crosswords.editor', $crossword), navigate: true);
    }

    /**
     * Apply the filler's per-slot words back into the solution grid.
     *
     * @param  array<int, array<int, string|null>>  $solution
     * @param  list<array{direction: string, number: int, word: string}>  $fills
     * @param  array<int, array{number: int, row: int, col: int, length: int}>  $across
     * @param  array<int, array{number: int, row: int, col: int, length: int}>  $down
     * @return array<int, array<int, string|null>>
     */
    private function applyFills(array $solution, array $fills, array $across, array $down): array
    {
        $slots = [
            'across' => collect($across)->keyBy('number'),
            'down' => collect($down)->keyBy('number'),
        ];

        foreach ($fills as $fill) {
            $slot = $slots[$fill['direction']]->get($fill['number']);

            if ($slot === null) {
                continue;
            }

            for ($i = 0; $i < $slot['length']; $i++) {
                $r = $fill['direction'] === 'across' ? $slot['row'] : $slot['row'] + $i;
                $c = $fill['direction'] === 'across' ? $slot['col'] + $i : $slot['col'];
                $solution[$r][$c] = $fill['word'][$i];
            }
        }

        return $solution;
    }
}
