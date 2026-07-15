<?php

namespace App\Models;

use App\Enums\CrosswordLayout;
use App\Enums\PuzzleType;
use App\Observers\CrosswordObserver;
use Carbon\CarbonImmutable;
use Database\Factories\CrosswordFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use CrosswordBuilder\CrosswordIO\Crossword as CrosswordDTO;
use CrosswordBuilder\CrosswordIO\GridNumberer;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $title
 * @property string|null $author
 * @property string|null $copyright
 * @property string|null $notes
 * @property string|null $pdf_narrative
 * @property string|null $pdf_image
 * @property string|null $meta_answer_prompt
 * @property array<array-key, string>|null $meta_answers
 * @property bool $meta_answer_reveal
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
 * @property bool $allow_embed
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property array<array-key, mixed>|null $user_progress
 * @property numeric|null $difficulty_score
 * @property string|null $difficulty_label
 * @property int $cached_attempts_count
 * @property int $cached_completed_count
 * @property int|null $cached_avg_solve_time
 * @property-read Collection<int, PuzzleAttempt> $attempts
 * @property-read int|null $attempts_count
 * @property-read Collection<int, ClueEntry> $clueEntries
 * @property-read int|null $clue_entries_count
 * @property-read Collection<int, CrosswordLike> $likes
 * @property-read int|null $likes_count
 * @property-read Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read string $display_title
 * @property-read User $user
 *
 * @mixin Eloquent
 */
#[Fillable([
    'title', 'author', 'copyright', 'notes', 'pdf_narrative', 'pdf_image',
    'meta_answer_prompt', 'meta_answers', 'meta_answer_reveal',
    'secret_theme', 'layout',
    'width', 'height', 'kind', 'puzzle_type', 'freestyle_locked',
    'grid', 'solution', 'prefilled', 'user_progress', 'clues_across', 'clues_down',
    'styles', 'metadata', 'is_published', 'allow_embed', 'contains_profanity',
    'difficulty_score', 'difficulty_label',
    'cached_attempts_count', 'cached_completed_count', 'cached_avg_solve_time',
])]
#[ObservedBy([CrosswordObserver::class])]
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
            'meta_answers' => 'array',
            'meta_answer_reveal' => 'boolean',
            'is_published' => 'boolean',
            'allow_embed' => 'boolean',
            'contains_profanity' => 'boolean',
            'freestyle_locked' => 'boolean',
            'layout' => CrosswordLayout::class,
            'puzzle_type' => PuzzleType::class,
        ];
    }

    /**
     * Hide profanity-flagged puzzles from users with Safe Search enabled.
     * Guests are treated as safe-search-on by default. Pass the puzzle's
     * own constructor as $user (or a logged-in admin) to bypass the filter.
     *
     * @param  Builder<Crossword>  $query
     */
    public function scopeSafeFor(Builder $query, ?User $user): Builder
    {
        $safe = $user === null ? true : (bool) $user->safe_search_enabled;
        if (! $safe) {
            return $query;
        }

        return $query->where(function ($q) use ($user): void {
            $q->where('contains_profanity', false);
            if ($user !== null) {
                $q->orWhere('user_id', $user->getKey());
            }
        });
    }

    /**
     * Convenience: should the given user be allowed to view this puzzle through
     * a safe-search lens? Owners and admins always pass.
     */
    public function isVisibleToSafeSearch(?User $user): bool
    {
        if (! $this->contains_profanity) {
            return true;
        }
        if ($user === null) {
            return false;
        }
        if ((int) $this->user_id === (int) $user->getKey()) {
            return true;
        }

        return ! (bool) $user->safe_search_enabled;
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
            'title' => $this->displayTitle(),
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
     * Display-ready title. Returns the user-supplied title when present,
     * otherwise a generated fallback like "15×15 Standard Crossword".
     */
    public function displayTitle(): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        $type = $this->puzzleTypeLabel();

        return "{$this->width}×{$this->height} {$type} Crossword";
    }

    /**
     * Calculate puzzle completeness as a breakdown of individual checks.
     *
     * @return array{percentage: int, checks: array{author: bool, fill: bool, clues_across: bool, clues_down: bool}}
     */
    public function completeness(): array
    {
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
     * Average solve time in seconds across all completed attempts.
     */
    public function averageSolveTimeSeconds(): ?int
    {
        return $this->cached_avg_solve_time;
    }

    /**
     * Recalculate and persist the cached solve-stats columns from puzzle_attempts.
     */
    public function refreshSolveStats(): void
    {
        $completedQuery = $this->attempts()
            ->where('is_completed', true);

        $attemptsCount = $this->attempts()->count();
        $completedCount = (clone $completedQuery)->count();
        $avgSolveTime = (clone $completedQuery)
            ->whereNotNull('solve_time_seconds')
            ->avg('solve_time_seconds');

        $this->updateQuietly([
            'cached_attempts_count' => $attemptsCount,
            'cached_completed_count' => $completedCount,
            'cached_avg_solve_time' => $avgSolveTime !== null ? (int) round($avgSolveTime) : null,
        ]);
    }

    public function hasMetaAnswer(): bool
    {
        return filled($this->meta_answer_prompt) && ! empty($this->meta_answers);
    }

    public function isMetaAnswerCorrect(string $submission): bool
    {
        $normalized = mb_strtolower(trim($submission));

        foreach ($this->meta_answers ?? [] as $answer) {
            if (mb_strtolower(trim($answer)) === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obfuscate the solution using XOR cipher + base64 encoding.
     * Prevents casual view-source cheating while keeping implementation simple.
     */
    public function obfuscateSolution(): string
    {
        $json = json_encode($this->solution);
        $key = 'crosswordbuilder_'.$this->id;
        $keyLength = strlen($key);
        $result = '';

        for ($i = 0, $len = strlen($json); $i < $len; $i++) {
            $result .= chr(ord($json[$i]) ^ ord($key[$i % $keyLength]));
        }

        return base64_encode($result);
    }
}
