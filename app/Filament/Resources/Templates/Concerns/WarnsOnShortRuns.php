<?php

namespace App\Filament\Resources\Templates\Concerns;

use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

/**
 * Wraps the Save button on Template edit/create pages with a confirmation
 * modal that fires only when the grid contains runs of open cells shorter
 * than the configured `min_word_length`. The user can either go back and
 * fix the grid, or save anyway.
 *
 * Bars in `styles` are intentionally ignored here — short runs are detected
 * by black-square boundaries only, matching the legacy `validateMinWordLength`
 * behavior. If bar-aware checking is wanted later, switch to GridNumberer.
 */
trait WarnsOnShortRuns
{
    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->submit(null)
            ->action(fn () => $this->save())
            ->requiresConfirmation()
            ->modalHidden(fn (): bool => count($this->shortRuns()) === 0)
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalHeading('Short words detected')
            ->modalDescription(fn () => $this->shortRunsDescription())
            ->modalSubmitActionLabel('Save anyway')
            ->modalCancelActionLabel('Go back and fix');
    }

    /**
     * @return list<array{direction: 'across'|'down', row: int, col: int, length: int}>
     */
    private function shortRuns(): array
    {
        $data = $this->data ?? [];
        $grid = $data['grid'] ?? null;
        $width = (int) ($data['width'] ?? 0);
        $height = (int) ($data['height'] ?? 0);
        $minLength = (int) ($data['min_word_length'] ?? 3);

        if ($minLength < 2 || ! is_array($grid) || $width < 1 || $height < 1) {
            return [];
        }

        $runs = [];

        for ($r = 0; $r < $height; $r++) {
            $start = null;
            for ($c = 0; $c <= $width; $c++) {
                $isOpen = $c < $width && (($grid[$r][$c] ?? '#') !== '#');
                if ($isOpen && $start === null) {
                    $start = $c;
                } elseif (! $isOpen && $start !== null) {
                    $length = $c - $start;
                    if ($length < $minLength) {
                        $runs[] = ['direction' => 'across', 'row' => $r, 'col' => $start, 'length' => $length];
                    }
                    $start = null;
                }
            }
        }

        for ($c = 0; $c < $width; $c++) {
            $start = null;
            for ($r = 0; $r <= $height; $r++) {
                $isOpen = $r < $height && (($grid[$r][$c] ?? '#') !== '#');
                if ($isOpen && $start === null) {
                    $start = $r;
                } elseif (! $isOpen && $start !== null) {
                    $length = $r - $start;
                    if ($length < $minLength) {
                        $runs[] = ['direction' => 'down', 'row' => $start, 'col' => $c, 'length' => $length];
                    }
                    $start = null;
                }
            }
        }

        return $runs;
    }

    private function shortRunsDescription(): HtmlString
    {
        $runs = $this->shortRuns();
        $minLength = (int) ($this->data['min_word_length'] ?? 3);

        $items = array_map(
            fn (array $run) => sprintf(
                '<li><span class="font-mono">%s</span> at row %d, col %d &mdash; length %d</li>',
                $run['direction'],
                $run['row'] + 1,
                $run['col'] + 1,
                $run['length'],
            ),
            array_slice($runs, 0, 10),
        );

        $more = count($runs) > 10 ? sprintf('<li>&hellip; and %d more</li>', count($runs) - 10) : '';

        return new HtmlString(sprintf(
            '<p class="mb-2">This grid contains %d run(s) shorter than the minimum word length of %d:</p>'
            .'<ul class="list-disc pl-5 space-y-0.5 text-sm">%s%s</ul>',
            count($runs),
            $minLength,
            implode('', $items),
            $more,
        ));
    }
}
