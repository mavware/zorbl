<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PuzzleAttemptFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $crossword_id
 * @property array<array-key, mixed> $progress
 * @property bool $is_completed
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property int|null $solve_time_seconds
 * @property array<array-key, mixed>|null $pencil_cells
 * @property-read Crossword $crossword
 * @property-read User $user
 *
 * @method static PuzzleAttemptFactory factory($count = null, $state = [])
 *
 * @mixin Eloquent
 */
class PuzzleAttempt extends Model
{
    /** @use HasFactory<PuzzleAttemptFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'crossword_id',
        'progress',
        'pencil_cells',
        'is_completed',
        'started_at',
        'completed_at',
        'solve_time_seconds',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'progress' => 'array',
            'pencil_cells' => 'array',
            'is_completed' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'solve_time_seconds' => 'integer',
        ];
    }

    /**
     * Format solve time as a human-readable string (e.g. "5:32" or "1:02:15").
     */
    public function formattedSolveTime(): ?string
    {
        if ($this->solve_time_seconds === null) {
            return null;
        }

        $seconds = $this->solve_time_seconds;
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function crossword(): BelongsTo
    {
        return $this->belongsTo(Crossword::class);
    }

    /**
     * Calculate what percentage of fillable cells the solver has entered.
     *
     * @return int 0–100
     */
    public function solveProgress(): int
    {
        $grid = $this->crossword->grid ?? [];
        $progress = $this->progress ?? [];
        $totalCells = 0;
        $filledCells = 0;

        foreach ($grid as $rowIdx => $row) {
            foreach ($row as $colIdx => $cell) {
                // Skip blocks and void cells
                if ($cell === '#' || $cell === null) {
                    continue;
                }

                $totalCells++;

                if (filled($progress[$rowIdx][$colIdx] ?? '')) {
                    $filledCells++;
                }
            }
        }

        if ($totalCells === 0) {
            return 0;
        }

        return (int) round(($filledCells / $totalCells) * 100);
    }
}
