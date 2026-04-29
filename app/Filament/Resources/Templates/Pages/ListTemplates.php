<?php

namespace App\Filament\Resources\Templates\Pages;

use App\Enums\TemplateStyle;
use App\Filament\Resources\Templates\TemplateResource;
use App\Services\TemplateGeneratorService;
use App\Support\GenerationSpec;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTemplates extends ListRecords
{
    protected static string $resource = TemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->generateAction(),
        ];
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
                    $service = app(TemplateGeneratorService::class);
                    $candidates = $service->generate($spec);
                    $savedIds = $service->saveAsDrafts($candidates);
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
