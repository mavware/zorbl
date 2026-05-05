<?php

namespace App\Models;

use App\Enums\CrosswordLayout;
use App\Enums\PuzzleType;
use Carbon\CarbonImmutable;
use Database\Factories\CrosswordFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Zorbl\CrosswordIO\Crossword as CrosswordDTO;
use Zorbl\CrosswordIO\GridNumberer;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $title
 * @property string|null $author
 * @property string|null $copyright
 * @property string|null $notes
 * @property string|null $secret_theme
 * @property CrosswordLayout|null $layout
 * @property PuzzleType $puzzle_type
 * @property bool $freestyle_locked
 * @property int $width
 * @property int $height
 * @property string $kind
 * @property array<array-key, mixed> $grid
 * @property array<array-key, mixed> $solution
 * @property array<array-key, mixed> $clues_across
 * @property array<array-key, mixed> $clues_down
 * @property array<array-key, mixed>|null $styles
 * @property array<array-key, mixed>|null $metadata
 * @property bool $is_published
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property array<array-key, mixed>|null $user_progress
 * @property numeric|null $difficulty_score
 * @property string|null $difficulty_label
 * @property-read Collection<int, PuzzleAttempt> $attempts
 * @property-read int|null $attempts_count
 * @property-read Collection<int, ClueEntry> $clueEntries
 * @property-read int|null $clue_entries_count
 * @property-read Collection<int, CrosswordLike> $likes
 * @property-read int|null $likes_count
 * @property-read Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read User $user
 *
 * @mixin Eloquent
 */
#[Fillable([
    'title', 'author', 'copyright', 'notes', 'secret_theme', 'layout',
    'width', 'height', 'kind', 'puzzle_type', 'freestyle_locked',
    'grid', 'solution', 'prefilled', 'user_progress', 'clues_across', 'clues_down',
    'styles', 'metadata', 'is_published',
    'difficulty_score', 'difficulty_label',
])]
class Crossword extends Model
{
    /** @use HasFactory<CrosswordFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'grid' => 'array',
            'solution' => 'array',
            'prefilled' => 'array',
            'user_progress' => 'array',
            'clues_across' => 'array',
            'clues_down' => 'array',
            'styles' => 'array',
            'metadata' => 'array',
            'is_published' => 'boolean',
            'freestyle_locked' => 'boolean',
            'layout' => CrosswordLayout::class,
            'puzzle_type' => PuzzleType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PuzzleAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(PuzzleAttempt::class);
    }

    /**
     * @return HasMany<ClueEntry, $this>
     */
    public function clueEntries(): HasMany
    {
        return $this->hasMany(ClueEntry::class);
    }

    /**
     * @return HasMany<CrosswordLike, $this>
     */
    public function likes(): HasMany
    {
        return $this->hasMany(CrosswordLike::class);
    }

    /**
     * @return HasMany<PuzzleComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(PuzzleComment::class);
    }

    /**
     * @return BelongsToMany<Contest, $this>
     */
    public function contests(): BelongsToMany
    {
        return $this->belongsToMany(Contest::class)
            ->withPivot(['sort_order', 'extraction_hint'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /**
     * Convert this Eloquent model to a package-level DTO for import/export operations.
     */
    public function toCrosswordIO(): CrosswordDTO
    {
        return CrosswordDTO::fromArray([
            'width' => $this->width,
            'height' => $this->height,
            'grid' => $this->grid,
            'solution' => $this->solution,
            'clues_across' => $this->clues_across ?? [],
            'clues_down' => $this->clues_down ?? [],
            'title' => $this->title,
            'author' => $this->author,
            'copyright' => $this->copyright,
            'notes' => $this->notes,
            'kind' => $this->kind ?? 'https://ipuz.org/crossword#1',
            'styles' => $this->styles,
            'metadata' => $this->metadata,
            'prefilled' => $this->prefilled,
        ]);
    }

    /**
     * Calculate puzzle completeness as a breakdown of individual checks.
     *
     * @return array{percentage: int, checks: array{title: bool, author: bool, fill: bool, clues_across: bool, clues_down: bool}}
     */
    public function completeness(): array
    {
        $hasTitle = filled($this->title) && $this->title !== 'Untitled Puzzle';
        $hasAuthor = filled($this->author);

        // Check all non-block, non-void cells have letters
        $totalCells = 0;
        $filledCells = 0;

        foreach ($this->solution ?? [] as $row) {
            foreach ($row as $cell) {
                if ($cell === '#' || $cell === null) {
                    continue;
                }

                $totalCells++;

                if (filled($cell)) {
                    $filledCells++;
                }
            }
        }

        // Freestyle puzzles are allowed to publish with empty cells; the publish
        // flow converts those into voids. Require at least one filled cell.
        $hasFill = $this->puzzle_type === PuzzleType::Freestyle
            ? $filledCells > 0
            : $totalCells > 0 && $filledCells === $totalCells;

        // Check all clue slots have text
        $acrossClues = $this->clues_across ?? [];
        $hasAllAcross = count($acrossClues) > 0 && collect($acrossClues)->every(fn ($c) => filled($c['clue'] ?? ''));

        $downClues = $this->clues_down ?? [];
        $hasAllDown = count($downClues) > 0 && collect($downClues)->every(fn ($c) => filled($c['clue'] ?? ''));

        $checks = [
            'title' => $hasTitle,
            'author' => $hasAuthor,
            'fill' => $hasFill,
            'clues_across' => $hasAllAcross,
            'clues_down' => $hasAllDown,
        ];

        $passed = count(array_filter($checks));
        $percentage = (int) round(($passed / count($checks)) * 100);

        return [
            'percentage' => $percentage,
            'checks' => $checks,
        ];
    }

    /**
     * Generate an empty grid of the given dimensions.
     *
     * @return array<int, array<int, int>>
     */
    public static function emptyGrid(int $width, int $height): array
    {
        return array_fill(0, $height, array_fill(0, $width, 0));
    }

    /**
     * Generate an empty solution grid of the given dimensions.
     *
     * @return array<int, array<int, string>>
     */
    public static function emptySolution(int $width, int $height): array
    {
        return array_fill(0, $height, array_fill(0, $width, ''));
    }

    /**
     * Freestyle workflow: turn every empty playable cell into a void cell.
     * Re-numbers the grid and reconciles clues, keying old clues by start
     * coordinates so renumbering doesn't reassign them to different slots.
     */
    public function convertEmptyCellsToVoid(): void
    {
        $oldClues = $this->indexCluesByLocation();
        $grid = $this->grid ?? [];
        $solution = $this->solution ?? [];

        foreach ($grid as $r => $row) {
            foreach ($row as $c => $cell) {
                if ($cell === null || $cell === '#') {
                    continue;
                }
                $value = $solution[$r][$c] ?? '';
                if ($value === '' || $value === null) {
                    $grid[$r][$c] = null;
                    $solution[$r][$c] = null;
                }
            }
        }

        $this->applyRenumberedGrid($grid, $solution, $oldClues);
    }

    /**
     * Freestyle workflow inverse: turn every void cell back into an empty
     * playable cell, re-number, and append empty clue entries for new slots.
     */
    public function restoreVoidCellsToEmpty(): void
    {
        $oldClues = $this->indexCluesByLocation();
        $grid = $this->grid ?? [];
        $solution = $this->solution ?? [];

        foreach ($grid as $r => $row) {
            foreach ($row as $c => $cell) {
                if ($cell === null) {
                    $grid[$r][$c] = 0;
                    $solution[$r][$c] = '';
                }
            }
        }

        $this->applyRenumberedGrid($grid, $solution, $oldClues);
    }

    /**
     * @return array{across: array<string, string>, down: array<string, string>}
     */
    private function indexCluesByLocation(): array
    {
        $numberer = app(GridNumberer::class);
        $result = $numberer->number($this->grid ?? [], $this->width, $this->height, $this->styles ?? []);

        $across = collect($this->clues_across ?? [])->keyBy('number');
        $down = collect($this->clues_down ?? [])->keyBy('number');

        $byLocation = ['across' => [], 'down' => []];
        foreach ($result['across'] as $slot) {
            $clue = $across->get($slot['number']);
            $byLocation['across'][$slot['row'].','.$slot['col']] = $clue['clue'] ?? '';
        }
        foreach ($result['down'] as $slot) {
            $clue = $down->get($slot['number']);
            $byLocation['down'][$slot['row'].','.$slot['col']] = $clue['clue'] ?? '';
        }

        return $byLocation;
    }

    /**
     * @param  array<int, array<int, mixed>>  $grid
     * @param  array<int, array<int, mixed>>  $solution
     * @param  array{across: array<string, string>, down: array<string, string>}  $oldClues
     */
    private function applyRenumberedGrid(array $grid, array $solution, array $oldClues): void
    {
        $numberer = app(GridNumberer::class);
        $result = $numberer->number($grid, $this->width, $this->height, $this->styles ?? []);

        $newAcross = array_map(fn ($slot) => [
            'number' => $slot['number'],
            'clue' => $oldClues['across'][$slot['row'].','.$slot['col']] ?? '',
        ], $result['across']);

        $newDown = array_map(fn ($slot) => [
            'number' => $slot['number'],
            'clue' => $oldClues['down'][$slot['row'].','.$slot['col']] ?? '',
        ], $result['down']);

        $this->update([
            'grid' => $result['grid'],
            'solution' => $solution,
            'clues_across' => $newAcross,
            'clues_down' => $newDown,
        ]);
    }

    /**
     * Human-readable puzzle type label, including sub-types like Shaped and Barred
     * that are inferred from the grid structure rather than stored explicitly.
     */
    public function puzzleTypeLabel(): string
    {
        if ($this->puzzle_type !== PuzzleType::Standard) {
            return $this->puzzle_type->label();
        }

        if ($this->grid && collect($this->grid)->flatten()->contains(fn ($v) => $v === null)) {
            return 'Shaped';
        }

        if ($this->styles && collect($this->styles)->contains(fn ($s) => ! empty($s['bars'] ?? []))) {
            return 'Barred';
        }

        return 'Standard';
    }

    /**
     * Obfuscate the solution using XOR cipher + base64 encoding.
     * Prevents casual view-source cheating while keeping implementation simple.
     */
    public function obfuscateSolution(): string
    {
        $json = json_encode($this->solution);
        $key = 'zorbl_'.$this->id;
        $keyLength = strlen($key);
        $result = '';

        for ($i = 0, $len = strlen($json); $i < $len; $i++) {
            $result .= chr(ord($json[$i]) ^ ord($key[$i % $keyLength]));
        }

        return base64_encode($result);
    }
}
