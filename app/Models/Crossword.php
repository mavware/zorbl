<?php

namespace App\Models;

use Database\Factories\CrosswordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
