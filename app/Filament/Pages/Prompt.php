<?php

namespace App\Filament\Pages;

use App\Services\AiThemeBuilder;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
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
     * @var array{success: bool, entries: list<array{entry: string, length: int, explanation: string}>, assumptions: string, message: string}|null
     */
    public ?array $result = null;

    public bool $submitting = false;

    public function mount(): void
    {
        $this->form->fill();
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

    public function submit(AiThemeBuilder $builder): void
    {
        $this->submitting = true;

        $data = $this->form->getState();

        $this->result = $builder->build((string) ($data['prompt'] ?? ''));

        $this->submitting = false;
    }
}
