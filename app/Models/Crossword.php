<?php

namespace App\Models;

use Database\Factories\CrosswordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title', 'author', 'copyright', 'notes',
    'width', 'height', 'kind',
    'grid', 'solution', 'user_progress', 'clues_across', 'clues_down',
    'styles', 'metadata', 'is_published',
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
            'user_progress' => 'array',
            'clues_across' => 'array',
            'clues_down' => 'array',
            'styles' => 'array',
            'metadata' => 'array',
            'is_published' => 'boolean',
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
     * @return BelongsToMany<User, $this>
     */
    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'crossword_likes')->withTimestamps();
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

        $hasFill = $totalCells > 0 && $filledCells === $totalCells;

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
}
