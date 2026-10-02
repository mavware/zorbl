<?php

namespace App\Services;

use App\Enums\TemplateGeneratorStyle;
use App\Models\Template;
use App\Support\GenerationCandidate;
use App\Support\SlotPlan;
use App\Support\TemplateScore;
use App\Support\TemplateScoringWeights;
use App\Support\TemplateSearchOptions;
use App\Support\TemplateTargets;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Generates crossword templates without AI: simulated annealing over
 * rotationally symmetric block placements, scored by TemplateGridScorer.
 */
class ProceduralTemplateGenerator
{
    public function __construct(
        private TemplateGridScorer $scorer,
        private TemplateStatsService $stats,
        private TemplateSlotPlanner $planner,
    ) {}

    /**
     * @param  list<int>  $themeLengths  Themed style only: slot lengths from the top row down (row 4, row 8 on 19+ grids, then the centre row on odd sizes)
     * @param  list<array{word: string, direction?: string}>  $seedWords  Words the grid must have slots for; these replace the themed slots
     * @param  array<string, mixed>  $targetOverrides  Replacements for the size's default targets, keyed by TemplateTargets property name
     * @return list<GenerationCandidate> Best candidates first; may be fewer than $count
     */
    public function generate(
        int $size,
        TemplateGeneratorStyle $style = TemplateGeneratorStyle::Standard,
        int $count = 3,
        ?int $seed = null,
        array $themeLengths = [],
        array $targetOverrides = [],
        ?TemplateSearchOptions $search = null,
        array $seedWords = [],
    ): array {
        $search ??= new TemplateSearchOptions;
        $slots = match (true) {
            $seedWords !== [] => $this->planner->forWords($size, $seedWords),
            $style === TemplateGeneratorStyle::Themed => $this->planner->forThemeLengths($size, $themeLengths),
            default => new SlotPlan,
        };

        $defaults = TemplateTargets::for($size, $style, $slots->fullWidthEntries);
        $targets = $defaults->with($targetOverrides);
        $customSettings = $this->customSettings($defaults, $targets, $search);
        $seed ??= random_int(1, 2_000_000_000);
        $taken = $this->existingFingerprints($size);
        $found = [];

        for ($attempt = 0; $attempt < $search->attemptsPerTemplate * $count; $attempt++) {
            $runSeed = $seed + $attempt;
            $cells = $this->anneal($targets, $search, $slots->locked, $runSeed);

            $measured = $this->scorer->measure($cells, $targets);

            // An empty grid breaks no rule but is not a template.
            if ($measured['hard'] > 0 || ($measured['blocks'] === 0 && $targets->minBlocks > 0)) {
                continue;
            }

            $grid = TemplateGridScorer::toGrid($cells, $size);
            $fingerprint = self::fingerprint($grid);

            if (isset($taken[$fingerprint])) {
                continue;
            }

            $taken[$fingerprint] = true;
            $found[] = [
                'grid' => $grid,
                'seed' => $runSeed,
                'score' => $this->scorer->score($grid, $targets),
                'blocks' => array_keys($cells, TemplateGridScorer::BLOCK, true),
            ];
        }

        usort($found, fn (array $a, array $b): int => $a['score']->energy <=> $b['score']->energy);

        $kept = [];

        foreach ($found as $result) {
            foreach ($kept as $better) {
                $shared = count(array_intersect($result['blocks'], $better['blocks']));

                if ($shared > $search->maxSharedBlocks * max(count($result['blocks']), count($better['blocks']))) {
                    continue 2;
                }
            }

            $kept[] = $result;

            if (count($kept) === $count) {
                break;
            }
        }

        return array_map(
            fn (array $result): GenerationCandidate => $this->toCandidate($result['grid'], $result['score'], $targets, $result['seed'], $slots, $customSettings),
            $kept,
        );
    }

    /**
     * @param  array<int, int>  $locked  Padded cell index => fixed cell value
     * @return array<int, int> Best padded grid found
     */
    private function anneal(TemplateTargets $targets, TemplateSearchOptions $search, array $locked, int $seed): array
    {
        $size = $targets->size;
        $stride = $size + 2;
        $last = $stride * $stride - 1;
        $random = new Randomizer(new Mt19937($seed));

        $cells = TemplateGridScorer::toCells(array_fill(0, $size, array_fill(0, $size, 0)), $size);
        foreach ($locked as $index => $value) {
            $cells[$index] = $value;
        }

        $energy = $this->scorer->measure($cells, $targets)['energy'];
        $best = $cells;
        $bestEnergy = $energy;
        $iterations = $search->iterationsPerCell * $size * $size;
        $cooling = $search->endTemperature / $search->startTemperature;
        $moveTotal = $search->flipWeight + $search->paintWeight + $search->eraseWeight + $search->slideWeight;
        $flipBelow = $search->flipWeight / $moveTotal;
        $paintBelow = $flipBelow + $search->paintWeight / $moveTotal;
        $eraseBelow = $paintBelow + $search->eraseWeight / $moveTotal;

        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            $previous = $cells;
            $index = $random->getInt(1, $size) * $stride + $random->getInt(1, $size);
            $move = $random->nextFloat();

            if ($move < $flipBelow) {
                // Flip one cell and its rotational twin.
                if (isset($locked[$index])) {
                    continue;
                }
                $value = $cells[$index] === TemplateGridScorer::WHITE ? TemplateGridScorer::BLOCK : TemplateGridScorer::WHITE;
                $cells[$index] = $value;
                $cells[$last - $index] = $value;
            } elseif ($move < $eraseBelow) {
                // Paint a straight 2-3 cell segment: a block near the edge is
                // only legal as part of a run that reaches the edge.
                $value = $move < $paintBelow ? TemplateGridScorer::BLOCK : TemplateGridScorer::WHITE;
                $step = $random->getInt(0, 1) === 0 ? 1 : $stride;
                $length = $random->getInt(2, 3);

                for ($k = 1; $k < $length; $k++) {
                    if ($cells[$index + $k * $step] === TemplateGridScorer::BORDER) {
                        continue 2;
                    }
                }

                for ($k = 0; $k < $length; $k++) {
                    $target = $index + $k * $step;
                    if (! isset($locked[$target])) {
                        $cells[$target] = $value;
                        $cells[$last - $target] = $value;
                    }
                }
            } else {
                // Slide an existing block one cell.
                for ($tries = 0; $tries < 20 && $cells[$index] !== TemplateGridScorer::BLOCK; $tries++) {
                    $index = $random->getInt(1, $size) * $stride + $random->getInt(1, $size);
                }

                $target = $index + [-1, 1, -$stride, $stride][$random->getInt(0, 3)];

                if ($cells[$index] !== TemplateGridScorer::BLOCK || $cells[$target] !== TemplateGridScorer::WHITE
                    || isset($locked[$index]) || isset($locked[$target])) {
                    continue;
                }

                $cells[$index] = TemplateGridScorer::WHITE;
                $cells[$last - $index] = TemplateGridScorer::WHITE;
                $cells[$target] = TemplateGridScorer::BLOCK;
                $cells[$last - $target] = TemplateGridScorer::BLOCK;
            }

            $candidate = $this->scorer->measure($cells, $targets)['energy'];
            $temperature = $search->startTemperature * $cooling ** ($iteration / $iterations);

            if ($candidate <= $energy || $random->nextFloat() < exp(($energy - $candidate) / $temperature)) {
                $energy = $candidate;

                if ($candidate < $bestEnergy) {
                    $bestEnergy = $candidate;
                    $best = $cells;
                }
            } else {
                $cells = $previous;
            }
        }

        return $best;
    }

    /**
     * Block patterns already stored for this size, including soft-deleted
     * rows, since the templates table enforces unique grids.
     *
     * @return array<string, true>
     */
    private function existingFingerprints(int $size): array
    {
        $fingerprints = [];

        Template::withTrashed()
            ->where('width', $size)
            ->where('height', $size)
            ->pluck('grid')
            ->each(function (array $grid) use (&$fingerprints): void {
                $fingerprints[self::fingerprint($grid)] = true;
            });

        return $fingerprints;
    }

    /**
     * @param  array<int, array<int, int|string|null>>  $grid
     */
    private static function fingerprint(array $grid): string
    {
        return implode('/', array_map(
            fn (array $row): string => implode('', array_map(fn (mixed $cell): string => $cell === '#' ? '#' : '.', $row)),
            $grid,
        ));
    }

    /**
     * Settings that differ from the defaults, so a draft records how to reproduce it.
     *
     * @return list<string>
     */
    private function customSettings(TemplateTargets $defaults, TemplateTargets $targets, TemplateSearchOptions $search): array
    {
        $groups = [
            [get_object_vars($defaults), get_object_vars($targets)],
            [get_object_vars(new TemplateScoringWeights), get_object_vars($targets->weights)],
            [get_object_vars(new TemplateSearchOptions), get_object_vars($search)],
        ];
        $changed = [];

        foreach ($groups as [$before, $after]) {
            foreach ($after as $name => $value) {
                if ($name === 'weights' || $value === $before[$name]) {
                    continue;
                }

                $changed[] = $name.'='.match (true) {
                    is_array($value) => implode('/', array_map(fn (float $share): string => (string) round(100 * $share), $value)),
                    is_float($value) => (string) round($value, 3),
                    default => (string) $value,
                };
            }
        }

        return $changed;
    }

    /**
     * @param  array<int, array<int, int|string>>  $grid
     * @param  list<string>  $customSettings
     */
    private function toCandidate(array $grid, TemplateScore $score, TemplateTargets $targets, int $seed, SlotPlan $slots, array $customSettings): GenerationCandidate
    {
        $size = $targets->size;
        $style = $targets->style;

        $strengths = [
            sprintf('%d words against a maximum of %d for this size', $score->wordCount, TemplateTargets::maxWordCount($size)),
            sprintf('Largest open area is %d×%d', $score->largestOpenSquare, $score->largestOpenSquare),
            sprintf('3-letter entries are %d%% of the fill', round(100 * $score->threeLetterShare)),
        ];
        $compromises = [];

        if ($score->cheaterCount === 0) {
            $strengths[] = 'No cheater squares';
        } else {
            $compromises[] = sprintf('%d cheater square(s)', $score->cheaterCount);
        }

        if ($targets->minTouchingBlocks > 0) {
            $strengths[] = sprintf('Every block touches at least %d other block(s)', $targets->minTouchingBlocks);
        }

        if ($score->chokepointCount === 0) {
            $strengths[] = 'No chokepoints between sections';
        } else {
            $compromises[] = sprintf('%d chokepoint square(s) where one cell links two sections', $score->chokepointCount);
        }

        if ($score->shortEntryCount > 0) {
            $compromises[] = sprintf('%d entries shorter than %d letters', $score->shortEntryCount, $targets->minEntryLength);
        }

        if ($score->longEntryCount > 0) {
            $compromises[] = sprintf('%d entries longer than %d letters', $score->longEntryCount, $targets->maxEntryLength);
        }

        if ($score->blockCount < $targets->minBlocks || $score->blockCount > $targets->maxBlocks) {
            $compromises[] = sprintf('Block count %d is outside the usual %d–%d', $score->blockCount, $targets->minBlocks, $targets->maxBlocks);
        }

        if ($score->wordCount < $targets->minWords || $score->wordCount > $targets->maxWords) {
            $compromises[] = sprintf('Word count %d is outside the usual %d–%d', $score->wordCount, $targets->minWords, $targets->maxWords);
        }

        return new GenerationCandidate(
            name: sprintf('%s %d #%d', $style->label(), $size, $seed),
            width: $size,
            height: $size,
            grid: $grid,
            philosophy: sprintf(
                'Procedurally generated %s grid (seed %d): %d blocks (%.1f%%), %d words, average entry %.2f letters.%s',
                strtolower($style->label()),
                $seed,
                $score->blockCount,
                100 * $score->blockDensity,
                $score->wordCount,
                $score->averageLength,
                $customSettings === [] ? '' : ' Custom settings: '.implode(', ', $customSettings).'.',
            ),
            strengths: $strengths,
            compromises: $compromises,
            bestFor: match (true) {
                $slots->placements !== [] => 'Fits '.implode('; ', $slots->placements).'.',
                $style === TemplateGeneratorStyle::Themeless => 'Themeless puzzles built on longer entries.',
                default => 'General-purpose puzzles at this size.',
            },
            avoidWhen: null,
            stats: $this->stats->forGrid($grid, $size, $size),
            validationErrors: $score->hardViolations,
        );
    }
}
