<?php

namespace App\Filament\Resources\Templates\Pages;

use App\Enums\TemplateGeneratorStyle;
use App\Enums\TemplateStyle;
use App\Filament\Resources\Templates\TemplateResource;
use App\Jobs\GenerateTemplateDrafts;
use App\Services\ProceduralTemplateGenerator;
use App\Services\TemplateDraftSaver;
use App\Services\TemplateGeneratorService;
use App\Services\TemplateSlotPlanner;
use App\Support\GenerationSpec;
use App\Support\TemplateScoringWeights;
use App\Support\TemplateSearchOptions;
use App\Support\TemplateTargets;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use InvalidArgumentException;

class ListTemplates extends ListRecords
{
    protected static string $resource = TemplateResource::class;

    /** Whole-number shape levers: form field => TemplateTargets property. */
    private const array TARGET_LEVERS = [
        'min_words' => 'minWords',
        'max_words' => 'maxWords',
        'min_entry_length' => 'minEntryLength',
        'max_entry_length' => 'maxEntryLength',
        'max_black_run' => 'maxBlackRun',
        'max_black_shape' => 'maxBlackShape',
        'open_window_size' => 'openWindowSize',
        'max_full_width_entries' => 'maxFullWidthEntries',
        'max_cheaters' => 'maxCheaters',
        'min_touching_blocks' => 'minTouchingBlocks',
    ];

    /** Form field => TemplateScoringWeights property. */
    private const array WEIGHT_LEVERS = [
        'weight_short_entry' => 'shortEntry',
        'weight_long_entry' => 'longEntry',
        'weight_block_band' => 'blockBand',
        'weight_word_band' => 'wordBand',
        'weight_length_mix' => 'lengthMix',
        'weight_open_area' => 'openArea',
        'weight_cheater' => 'cheater',
        'weight_black_shape' => 'blackShape',
        'weight_chokepoint' => 'chokepoint',
        'weight_full_width' => 'fullWidth',
        'weight_border' => 'border',
        'weight_long_black_run' => 'longBlackRun',
    ];

    /** Form field => TemplateSearchOptions property. */
    private const array SEARCH_LEVERS = [
        'search_iterations_per_cell' => 'iterationsPerCell',
        'search_attempts_per_template' => 'attemptsPerTemplate',
        'search_start_temperature' => 'startTemperature',
        'search_end_temperature' => 'endTemperature',
        'search_max_shared_blocks' => 'maxSharedBlocks',
        'search_flip_weight' => 'flipWeight',
        'search_paint_weight' => 'paintWeight',
        'search_erase_weight' => 'eraseWeight',
        'search_slide_weight' => 'slideWeight',
    ];

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->generateProceduralAction(),
        ];
    }

    private function generateProceduralAction(): Action
    {
        return Action::make('generateProcedural')
            ->label('Generate procedurally')
            ->icon('heroicon-o-squares-2x2')
            ->color('gray')
            ->modalWidth('3xl')
            ->modalHeading('Generate templates procedurally')
            ->modalDescription(sprintf('Builds symmetric grids without AI, scored against published word-count, block-density and entry-length standards. Each result is saved as an inactive draft for you to review. Grids larger than %d×%d are generated in the background and you are notified when they are ready.', GenerateTemplateDrafts::QUEUE_ABOVE_SIZE, GenerateTemplateDrafts::QUEUE_ABOVE_SIZE))
            ->modalSubmitActionLabel('Generate')
            ->schema([
                Select::make('size')
                    ->label('Size')
                    ->options(collect(range(TemplateTargets::MIN_SIZE, TemplateTargets::MAX_SIZE))->mapWithKeys(
                        fn (int $size) => [$size => "{$size}×{$size}"]
                    )->all())
                    ->default(13)
                    ->live()
                    ->required(),
                Select::make('style')
                    ->label('Style')
                    ->options(collect(TemplateGeneratorStyle::cases())->mapWithKeys(
                        fn (TemplateGeneratorStyle $style) => [$style->value => $style->label()]
                    )->all())
                    ->default(TemplateGeneratorStyle::Standard->value)
                    ->helperText('Themed and themeless need at least 11×11.')
                    ->live()
                    ->required(),
                TextInput::make('theme_lengths')
                    ->label('Theme slot lengths')
                    ->placeholder('13, 17')
                    ->helperText('Comma-separated lengths from the top row down: row 4, row 8 on 19×19 and larger, then the centre row on odd sizes. Leave blank for defaults. Ignored when words to fit are given.')
                    ->visible(fn (Get $get): bool => $get('style') === TemplateGeneratorStyle::Themed->value),
                TextInput::make('candidate_count')
                    ->label('Templates to generate')
                    ->numeric()
                    ->default(3)
                    ->minValue(1)
                    ->maxValue(5)
                    ->required(),
                TextInput::make('seed')
                    ->label('Seed')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('The same size, style, seed and settings always produce the same grids. Optional.'),
                Repeater::make('seed_words')
                    ->label('Words to fit')
                    ->helperText('The grid gets a slot for each word, placed symmetrically: two words of the same length share a slot pair, longer words sit nearer the centre. Spaces and punctuation are ignored.')
                    ->schema([
                        TextInput::make('word')
                            ->label('Word')
                            ->required()
                            ->regex('/^[A-Za-z][A-Za-z \\-]*$/')
                            ->minLength(3)
                            ->maxLength(TemplateTargets::MAX_SIZE)
                            ->validationMessages(['regex' => 'Use letters only.']),
                        Select::make('direction')
                            ->label('Direction')
                            ->options(['across' => 'Across', 'down' => 'Down'])
                            ->default('across')
                            ->selectablePlaceholder(false)
                            ->required(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add a word')
                    ->reorderable(false),
                Select::make('min_touching_blocks')
                    ->label('Minimum touching blocks')
                    ->options([
                        0 => 'No minimum (blocks may stand alone)',
                        1 => 'Every block touches at least 1 other block',
                        2 => 'Every block touches at least 2 other blocks',
                    ])
                    ->default(1)
                    ->selectablePlaceholder(false)
                    ->helperText('Counts blocks in the eight surrounding squares, diagonals included; the grid edge does not count. At 2 the grids become sparse and blocky.'),
                Grid::make(2)->schema([
                    $this->lever('min_entry_length', 'Shortest entry', $this->defaultLever(fn (TemplateTargets $t) => $t->minEntryLength), 'Shorter entries are penalised, not forbidden. Single unchecked letters are never allowed.')
                        ->minValue(2),
                    $this->lever('max_entry_length', 'Longest entry', $this->defaultLever(fn (TemplateTargets $t) => $t->maxEntryLength), 'Longer entries are penalised, not forbidden. The word list has nothing longer than '.TemplateTargets::MAX_ENTRY_LENGTH.' letters.')
                        ->minValue(2),
                ]),
                $this->shapeSection(),
                $this->lengthMixSection(),
                $this->weightsSection(),
                $this->searchSection(),
            ])
            ->action(function (array $data): void {
                $size = (int) $data['size'];
                $style = TemplateGeneratorStyle::from($data['style']);
                $count = (int) $data['candidate_count'];
                $seed = filled($data['seed'] ?? null) ? (int) $data['seed'] : null;
                $themeLengths = array_map('intval', $this->parseSeedEntries($data['theme_lengths'] ?? ''));
                $seedWords = array_values(array_filter(
                    $data['seed_words'] ?? [],
                    fn (array $entry): bool => filled($entry['word'] ?? null),
                ));

                try {
                    $targetOverrides = $this->targetOverrides($data);
                    $search = new TemplateSearchOptions(...$this->filledLevers($data, self::SEARCH_LEVERS));

                    if ($size > GenerateTemplateDrafts::QUEUE_ABOVE_SIZE) {
                        // Check the settings now so mistakes surface here, not in the queue.
                        TemplateTargets::for($size, $style)->with($targetOverrides);
                        if ($seedWords !== []) {
                            app(TemplateSlotPlanner::class)->forWords($size, $seedWords);
                        } elseif ($style === TemplateGeneratorStyle::Themed) {
                            app(TemplateSlotPlanner::class)->forThemeLengths($size, $themeLengths);
                        }

                        GenerateTemplateDrafts::dispatch(auth()->id(), $size, $style, $count, $seed, $themeLengths, $targetOverrides, $search, $seedWords);

                        Notification::make()
                            ->title('Generation queued')
                            ->body(sprintf('%d %d×%d template(s) are being generated in the background. You will be notified when the drafts are ready.', $count, $size, $size))
                            ->info()
                            ->send();

                        return;
                    }

                    // Annealing a large grid takes several seconds per candidate.
                    set_time_limit(360);

                    $candidates = app(ProceduralTemplateGenerator::class)->generate(
                        size: $size,
                        style: $style,
                        count: $count,
                        seed: $seed,
                        themeLengths: $themeLengths,
                        targetOverrides: $targetOverrides,
                        search: $search,
                        seedWords: $seedWords,
                    );
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Generation failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $savedIds = app(TemplateDraftSaver::class)->saveAsDrafts($candidates);

                if ($savedIds === []) {
                    Notification::make()
                        ->title('No templates generated')
                        ->body('No valid new grid was found. Try a different seed or loosen the settings.')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Generation complete')
                    ->body(sprintf('Saved %d template(s) as inactive drafts.', count($savedIds)))
                    ->success()
                    ->send();
            });
    }

    /**
     * Placeholder closure that reads one value from the default targets for the selected size and style.
     */
    private function defaultLever(Closure $read): Closure
    {
        return function (Get $get) use ($read): ?string {
            $targets = $this->defaultTargets($get);

            return $targets === null ? null : (string) $read($targets);
        };
    }

    private function shapeSection(): Section
    {
        $default = fn (Closure $read): Closure => $this->defaultLever($read);

        return Section::make('Shape targets')
            ->description('What the grid should look like. Blank fields use the default shown for the chosen size and style.')
            ->collapsible()
            ->collapsed()
            ->compact()
            ->columns(2)
            ->schema([
                $this->lever('min_density', 'Minimum block density (%)', $default(fn (TemplateTargets $t) => round(100 * $t->minBlocks / $t->size ** 2, 1)))
                    ->maxValue(100),
                $this->lever('max_density', 'Maximum block density (%)', $default(fn (TemplateTargets $t) => round(100 * $t->maxBlocks / $t->size ** 2, 1)))
                    ->maxValue(100),
                $this->lever('min_words', 'Minimum word count', $default(fn (TemplateTargets $t) => $t->minWords)),
                $this->lever('max_words', 'Maximum word count', $default(fn (TemplateTargets $t) => $t->maxWords)),
                $this->lever('max_black_run', 'Longest run of blocks', $default(fn (TemplateTargets $t) => $t->maxBlackRun), 'Hard rule. Runs at this length are allowed but penalised.')
                    ->minValue(1),
                $this->lever('max_black_shape', 'Largest connected block shape', $default(fn (TemplateTargets $t) => $t->maxBlackShape), 'Cells in one shape before the block-shape penalty applies.')
                    ->minValue(1),
                $this->lever('open_window_size', 'Open-area limit', $default(fn (TemplateTargets $t) => $t->openWindowSize), 'All-white squares this many cells wide or wider are penalised.')
                    ->minValue(2),
                $this->lever('max_full_width_entries', 'Full-width entries allowed', $default(fn (TemplateTargets $t) => $t->maxFullWidthEntries === PHP_INT_MAX ? 'No limit' : $t->maxFullWidthEntries), 'Themed grids also allow their reserved full-width theme slots.'),
                $this->lever('max_cheaters', 'Cheater squares allowed', $default(fn (TemplateTargets $t) => $t->maxCheaters), 'Blocks that separate no entries.'),
                $this->lever('max_border_share', 'Blocks on the border (%)', $default(fn (TemplateTargets $t) => round(100 * $t->maxBorderShare, 1)), 'Largest share of blocks that may sit on the outer edge before a penalty.')
                    ->maxValue(100),
            ]);
    }

    private function lengthMixSection(): Section
    {
        $share = fn (int $length): Closure => function (Get $get) use ($length): ?string {
            $targets = $this->defaultTargets($get);

            return $targets === null ? null : (string) round(100 * $targets->lengthShares[$length], 1);
        };

        return Section::make('Word-length mix')
            ->description('Target share of entries at each length, in percent. Change any of them and the six are rescaled to total 100.')
            ->collapsible()
            ->collapsed()
            ->compact()
            ->columns(3)
            ->schema([
                $this->lever('share_3', '3 letters (%)', $share(3)),
                $this->lever('share_4', '4 letters (%)', $share(4)),
                $this->lever('share_5', '5 letters (%)', $share(5)),
                $this->lever('share_6', '6 letters (%)', $share(6)),
                $this->lever('share_7', '7 letters (%)', $share(7)),
                $this->lever('share_8', '8 or more (%)', $share(8)),
            ]);
    }

    private function weightsSection(): Section
    {
        $defaults = new TemplateScoringWeights;
        $labels = [
            'weight_short_entry' => ['Short entries', 'Per letter an entry falls short of the shortest entry.'],
            'weight_long_entry' => ['Long entries', 'Per letter an entry exceeds the longest entry.'],
            'weight_block_band' => ['Block density', 'Per block outside the density range.'],
            'weight_word_band' => ['Word count', 'Per word outside the word-count range.'],
            'weight_length_mix' => ['Word-length mix', 'Multiplies the distance (0–2) from the target mix.'],
            'weight_open_area' => ['Open areas', 'Per all-white square at or above the open-area limit.'],
            'weight_cheater' => ['Cheater squares', 'Per cheater above the allowance.'],
            'weight_black_shape' => ['Block shapes', 'Per cell above the largest allowed shape.'],
            'weight_chokepoint' => ['Chokepoints', 'Per white square that alone links two sections.'],
            'weight_full_width' => ['Full-width entries', 'Per full-width entry above the allowance.'],
            'weight_border' => ['Border blocks', 'Per border block above the allowed share.'],
            'weight_long_black_run' => ['Long block runs', 'Per run of blocks at the maximum length.'],
        ];

        return Section::make('Scoring weights')
            ->description('How hard the generator works to avoid each flaw. Raise a weight to prioritise it, or set 0 to ignore it.')
            ->collapsible()
            ->collapsed()
            ->compact()
            ->columns(2)
            ->schema(array_map(
                fn (string $name): TextInput => $this->lever($name, $labels[$name][0], (string) $defaults->{self::WEIGHT_LEVERS[$name]}, $labels[$name][1]),
                array_keys($labels),
            ));
    }

    private function searchSection(): Section
    {
        $defaults = new TemplateSearchOptions;

        return Section::make('Search')
            ->description('How the generator explores block placements. More iterations and attempts take proportionally longer.')
            ->collapsible()
            ->collapsed()
            ->compact()
            ->columns(2)
            ->schema([
                $this->lever('search_iterations_per_cell', 'Iterations per cell', (string) $defaults->iterationsPerCell, 'Total steps are this times the number of cells.')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(1000),
                $this->lever('search_attempts_per_template', 'Attempts per template', (string) $defaults->attemptsPerTemplate, 'Independent runs per requested template; the best are kept.')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(5),
                $this->lever('search_start_temperature', 'Start temperature', (string) $defaults->startTemperature, 'Higher accepts more bad moves early on.')
                    ->minValue(0.01),
                $this->lever('search_end_temperature', 'End temperature', (string) $defaults->endTemperature, 'Lower settles more strictly at the end.')
                    ->minValue(0.01),
                $this->lever('search_max_shared_blocks', 'Near-duplicate threshold (%)', (string) (100 * $defaults->maxSharedBlocks), 'Results sharing more than this share of blocks with a better one are dropped.')
                    ->minValue(1)
                    ->maxValue(100),
                $this->lever('search_flip_weight', 'Move: flip one cell', (string) $defaults->flipWeight, 'Relative frequency of each move type.'),
                $this->lever('search_paint_weight', 'Move: add a 2–3 block run', (string) $defaults->paintWeight),
                $this->lever('search_erase_weight', 'Move: clear a 2–3 cell run', (string) $defaults->eraseWeight),
                $this->lever('search_slide_weight', 'Move: slide a block', (string) $defaults->slideWeight),
            ]);
    }

    /**
     * An optional numeric setting whose placeholder shows the value used when it is left blank.
     */
    private function lever(string $name, string $label, Closure|string $default, ?string $help = null): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->placeholder($default)
            ->helperText($help);
    }

    private function defaultTargets(Get $get): ?TemplateTargets
    {
        $style = TemplateGeneratorStyle::tryFrom((string) $get('style'));

        if ($style === null) {
            return null;
        }

        try {
            return TemplateTargets::for((int) $get('size'), $style);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function targetOverrides(array $data): array
    {
        $cells = ((int) $data['size']) ** 2;
        $overrides = array_map('intval', $this->filledLevers($data, self::TARGET_LEVERS));

        if (filled($data['min_density'] ?? null)) {
            $overrides['minBlocks'] = (int) ceil((float) $data['min_density'] / 100 * $cells);
        }

        if (filled($data['max_density'] ?? null)) {
            $overrides['maxBlocks'] = (int) floor((float) $data['max_density'] / 100 * $cells);
        }

        if (filled($data['max_border_share'] ?? null)) {
            $overrides['maxBorderShare'] = (float) $data['max_border_share'] / 100;
        }

        $shares = [];
        foreach ([3, 4, 5, 6, 7, 8] as $length) {
            if (filled($data["share_{$length}"] ?? null)) {
                $shares[$length] = (float) $data["share_{$length}"] / 100;
            }
        }

        if ($shares !== []) {
            $overrides['lengthShares'] = $shares;
        }

        $weights = $this->filledLevers($data, self::WEIGHT_LEVERS);

        if ($weights !== []) {
            $overrides['weights'] = new TemplateScoringWeights(...$weights);
        }

        return $overrides;
    }

    /**
     * Collect the levers that were filled in, keyed by the setting they control.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $levers  Form field name => setting name
     * @return array<string, int|float>
     */
    private function filledLevers(array $data, array $levers): array
    {
        $values = [];

        foreach ($levers as $field => $setting) {
            if (! filled($data[$field] ?? null)) {
                continue;
            }

            $value = (float) $data[$field];

            $values[$setting] = match (true) {
                $field === 'search_max_shared_blocks' => $value / 100,
                in_array($setting, ['iterationsPerCell', 'attemptsPerTemplate'], true) => (int) $value,
                default => $value,
            };
        }

        return $values;
    }

    private function generateAction(): Action
    {
        return Action::make('generate')
            ->label('Generate with Claude')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->modalWidth('xl')
            ->modalHeading('Generate new templates')
            ->modalDescription('Claude Opus 4.7 will produce candidate grids using the existing 81 templates as in-context examples. Each candidate is saved as an inactive draft for you to review.')
            ->modalSubmitActionLabel('Generate')
            ->schema([
                Select::make('size')
                    ->label('Size')
                    ->options([
                        '5x5' => '5×5 (mini)',
                        '7x7' => '7×7',
                        '9x9' => '9×9',
                        '11x11' => '11×11',
                        '15x15' => '15×15 (standard)',
                    ])
                    ->default('15x15')
                    ->required(),
                Select::make('style_tags')
                    ->label('Target style tags')
                    ->multiple()
                    ->options(collect(TemplateStyle::cases())->mapWithKeys(
                        fn (TemplateStyle $t) => [$t->value => $t->label()]
                    )->all())
                    ->helperText('Tags Claude should aim to satisfy. Optional.'),
                Textarea::make('philosophy_hint')
                    ->label('Philosophy hint')
                    ->placeholder('e.g. "lattice corners with a roomy middle band" or "wide-open without going full themeless"')
                    ->helperText('1-2 sentences describing the design intent. Optional.')
                    ->rows(2),
                Textarea::make('seed_entries')
                    ->label('Seed entries (one per line)')
                    ->placeholder("MARQUEEFILL\nANOTHERLONGENTRY")
                    ->helperText('Long answers the constructor wants featured. Their lengths constrain block placement. Optional.')
                    ->rows(3),
                TextInput::make('candidate_count')
                    ->label('Candidates to generate')
                    ->numeric()
                    ->default(3)
                    ->minValue(1)
                    ->maxValue(5),
            ])
            ->action(function (array $data): void {
                // Generation can run 30-90s end-to-end (Opus 4.7 with tool use,
                // up to 5 candidates, 16K max_tokens). Default php max_execution_time
                // is 30s and would kill the request mid-stream — extend it for
                // this action only.
                set_time_limit(360);

                [$width, $height] = explode('x', $data['size']);
                $spec = new GenerationSpec(
                    width: (int) $width,
                    height: (int) $height,
                    styleTags: array_map(
                        fn (string $value) => TemplateStyle::from($value),
                        $data['style_tags'] ?? [],
                    ),
                    philosophyHint: filled($data['philosophy_hint'] ?? null) ? trim($data['philosophy_hint']) : null,
                    seedEntries: $this->parseSeedEntries($data['seed_entries'] ?? ''),
                    candidateCount: (int) ($data['candidate_count'] ?? 3),
                );

                try {
                    $candidates = app(TemplateGeneratorService::class)->generate($spec);
                    $savedIds = app(TemplateDraftSaver::class)->saveAsDrafts($candidates);
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Generation failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $invalid = collect($candidates)->filter(fn ($c) => ! $c->isValid());
                $body = sprintf(
                    'Saved %d valid candidate(s) as inactive drafts. %d failed validation.',
                    count($savedIds),
                    $invalid->count(),
                );

                if ($invalid->isNotEmpty()) {
                    $body .= ' Errors: '.$invalid->flatMap(fn ($c) => $c->validationErrors)->take(3)->implode('; ');
                }

                Notification::make()
                    ->title('Generation complete')
                    ->body($body)
                    ->success()
                    ->send();
            });
    }

    /**
     * @return list<string>
     */
    private function parseSeedEntries(string $raw): array
    {
        return collect(preg_split('/[\r\n,]+/', $raw))
            ->map(fn (string $s) => trim($s))
            ->filter()
            ->values()
            ->all();
    }
}
