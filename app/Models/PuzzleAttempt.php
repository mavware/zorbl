<?php

namespace App\Models;

use Database\Factories\PuzzleAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PuzzleAttempt extends Model
{
    /** @use HasFactory<PuzzleAttemptFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'crossword_id',
        'progress',
        'is_completed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'progress' => 'array',
            'is_completed' => 'boolean',
        ];
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
