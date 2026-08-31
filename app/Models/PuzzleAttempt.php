<?php

namespace App\Models;

use App\Observers\PuzzleAttemptObserver;
use App\Support\Concerns\FormatsTime;
use Carbon\CarbonImmutable;
use Database\Factories\PuzzleAttemptFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
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
 * @property array<array-key, mixed>|null $revealed_cells
 * @property string|null $meta_answer
 * @property-read Crossword $crossword
 * @property-read User $user
 *
 * @method static PuzzleAttemptFactory factory($count = null, $state = [])
 *
 * @mixin Eloquent
 */
#[ObservedBy(PuzzleAttemptObserver::class)]
class PuzzleAttempt extends Model
{
    /** @use HasFactory<PuzzleAttemptFactory> */
    use FormatsTime, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'crossword_id',
        'progress',
        'pencil_cells',
        'revealed_cells',
        'meta_answer',
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
            'revealed_cells' => 'array',
            'is_completed' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'solve_time_seconds' => 'integer',
        ];
    }

    public function formattedSolveTime(): ?string
    {
        if ($this->solve_time_seconds === null) {
            return null;
        }

        return $this->formatTime($this->solve_time_seconds);
    }

    /**
     * What percentage of solvers this attempt was faster than.
     *
     * @return int|null 0–100, or null if no other solvers
     */
    public function fasterThanPercent(): ?int
    {
        if ($this->solve_time_seconds === null || ! $this->is_completed) {
            return null;
        }

        $totalSolvers = self::where('crossword_id', $this->crossword_id)
            ->where('is_completed', true)
            ->whereNotNull('solve_time_seconds')
            ->where('id', '!=', $this->id)
            ->count();

        if ($totalSolvers === 0) {
            return null;
        }

        $slowerCount = self::where('crossword_id', $this->crossword_id)
            ->where('is_completed', true)
            ->whereNotNull('solve_time_seconds')
            ->where('id', '!=', $this->id)
            ->where('solve_time_seconds', '>', $this->solve_time_seconds)
            ->count();

        return (int) round(($slowerCount / $totalSolvers) * 100);
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
