<?php

namespace App\Services;

use App\Models\Crossword;
use CrosswordBuilder\CrosswordIO\GridNumberer;

/**
 * Tries to fill a template from the word list, as proof that it can be used.
 */
class TemplateFillChecker
{
    public function __construct(
        private GridFiller $filler,
        private GridNumberer $numberer,
    ) {}

    /**
     * @param  array<int, array<int, int|string|null>>  $grid
     * @param  array<string, array{bars?: list<string>}>  $styles
     * @return array{filled: bool, seconds: float, message: string}
     */
    public function check(array $grid, int $width, int $height, array $styles = [], int $minLength = 3, int $timeout = 20): array
    {
        $numbered = $this->numberer->number($grid, $width, $height, $styles, $minLength);
        $solution = Crossword::emptySolution($width, $height);

        foreach ($numbered['grid'] as $r => $row) {
            foreach ($row as $c => $cell) {
                if ($cell === '#' || $cell === null) {
                    $solution[$r][$c] = $cell;
                }
            }
        }

        $started = microtime(true);
        $result = $this->filler->fill($numbered['grid'], $solution, $width, $height, $styles, $minLength, $timeout);
        $seconds = microtime(true) - $started;

        return [
            'filled' => $result['success'],
            'seconds' => $seconds,
            'message' => $result['success']
                ? sprintf('Filled from the word list in %.1f seconds.', $seconds)
                : sprintf('Not filled within %d seconds. %s', $timeout, $result['message']),
        ];
    }
}
