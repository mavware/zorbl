<?php

namespace App\Filament\Pages;

use App\Enums\WordplayType;
use App\Models\WordplayEntry;
use App\Services\Wordplay\BeheadmentChainsFinder;
use App\Services\Wordplay\CharadePairsFinder;
use App\Services\Wordplay\SemordnilapsFinder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class GenerateWordplay extends Page
{
    protected string $view = 'filament.pages.generate-wordplay';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Wordplay';

    protected static ?string $navigationLabel = 'Generate';

    protected static ?int $navigationSort = -10;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $results = [];

    /**
     * @var list<int>
     */
    public array $selected = [];

    public ?string $resultsType = null;

    public function mount(): void
    {
        $this->form->fill([
            'type' => WordplayType::CharadePair->value,
            'charade_size' => 200,
            'charade_min_length' => 4,
            'semordnilap_min_length' => 5,
            'beheadment_top' => 25,
            'beheadment_min_length' => 3,
            'beheadment_mode' => BeheadmentChainsFinder::MODE_ANY,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Parameters')
                    ->schema([
                        Select::make('type')
                            ->label('Wordplay type')
                            ->options(collect(WordplayType::cases())
                                ->mapWithKeys(fn (WordplayType $type): array => [$type->value => $type->label()])
                                ->all())
                            ->required()
                            ->live(),

                        TextInput::make('charade_size')
                            ->label('Sample size (pair-loop seed words)')
                            ->numeric()
                            ->minValue(50)
                            ->maxValue(5000)
                            ->required()
                            ->visible(fn (Get $get): bool => $get('type') === WordplayType::CharadePair->value),
                        TextInput::make('charade_min_length')
                            ->label('Minimum word length on each side of a split')
                            ->numeric()
                            ->minValue(2)
                            ->maxValue(15)
                            ->required()
                            ->visible(fn (Get $get): bool => $get('type') === WordplayType::CharadePair->value),

                        TextInput::make('semordnilap_min_length')
                            ->label('Minimum word length')
                            ->numeric()
                            ->minValue(2)
                            ->maxValue(15)
                            ->required()
                            ->visible(fn (Get $get): bool => $get('type') === WordplayType::Semordnilap->value),

                        TextInput::make('beheadment_top')
                            ->label('How many longest chains to return')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(200)
                            ->required()
                            ->visible(fn (Get $get): bool => $get('type') === WordplayType::BeheadmentChain->value),
                        TextInput::make('beheadment_min_length')
                            ->label('Shortest word allowed in a chain')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(15)
                            ->required()
                            ->visible(fn (Get $get): bool => $get('type') === WordplayType::BeheadmentChain->value),
                        Select::make('beheadment_mode')
                            ->label('Deletion mode')
                            ->options([
                                BeheadmentChainsFinder::MODE_ANY => 'Any position (STARTLING-style)',
                                BeheadmentChainsFinder::MODE_FRONT => 'Front only (true beheadment)',
                                BeheadmentChainsFinder::MODE_BACK => 'Back only (curtailment)',
                            ])
                            ->required()
                            ->visible(fn (Get $get): bool => $get('type') === WordplayType::BeheadmentChain->value),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate')
                ->icon(Heroicon::OutlinedPlay)
                ->color('primary')
                ->action('generate'),
        ];
    }

    public function generate(): void
    {
        ini_set('memory_limit', '512M');

        $data = $this->form->getState();
        $type = WordplayType::from($data['type']);

        $this->results = match ($type) {
            WordplayType::CharadePair => app(CharadePairsFinder::class)->find(
                (int) ($data['charade_size'] ?? 500),
                (int) ($data['charade_min_length'] ?? 4),
            ),
            WordplayType::Semordnilap => app(SemordnilapsFinder::class)->find(
                (int) ($data['semordnilap_min_length'] ?? 5),
            ),
            WordplayType::BeheadmentChain => app(BeheadmentChainsFinder::class)->find(
                (int) ($data['beheadment_min_length'] ?? 3),
                (string) ($data['beheadment_mode'] ?? BeheadmentChainsFinder::MODE_ANY),
                (int) ($data['beheadment_top'] ?? 25),
            ),
        };

        $this->resultsType = $type->value;
        $this->selected = [];

        Notification::make()
            ->title(sprintf('Generated %d %s candidates', count($this->results), $type->label()))
            ->success()
            ->send();
    }

    public function saveSelected(): void
    {
        if ($this->resultsType === null || $this->selected === []) {
            Notification::make()
                ->title('Nothing selected')
                ->warning()
                ->send();

            return;
        }

        $type = WordplayType::from($this->resultsType);

        $created = 0;
        $existing = 0;
        foreach ($this->selected as $index) {
            $result = $this->results[(int) $index] ?? null;
            if ($result === null) {
                continue;
            }

            $notes = match ($type) {
                WordplayType::CharadePair => ['splits' => $result['splits'] ?? []],
                WordplayType::Semordnilap => ['reverse' => $result['reverse'] ?? ''],
                WordplayType::BeheadmentChain => ['chain' => $result['chain'] ?? []],
            };

            $entry = WordplayEntry::firstOrCreate(
                ['word' => $result['word'], 'type' => $type],
                ['notes' => $notes, 'status' => 'saved'],
            );

            if ($entry->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        $this->selected = [];

        Notification::make()
            ->title("Saved {$created} new entries"
                .($existing > 0 ? " ({$existing} already existed)" : ''))
            ->success()
            ->send();
    }

    public function getResultsTypeEnum(): ?WordplayType
    {
        return $this->resultsType !== null ? WordplayType::tryFrom($this->resultsType) : null;
    }
}
