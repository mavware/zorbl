<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ContestEntryFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $contest_id
 * @property int $user_id
 * @property CarbonImmutable $registered_at
 * @property int|null $total_solve_time_seconds
 * @property int $puzzles_completed
 * @property string|null $meta_answer
 * @property bool $meta_solved
 * @property CarbonImmutable|null $meta_submitted_at
 * @property int $meta_attempts_count
 * @property int|null $rank
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Contest $contest
 * @property-read User $user
 *
 * @method static ContestEntryFactory factory($count = null, $state = [])
 *
 * @mixin Eloquent
 */
#[Fillable([
    'contest_id', 'user_id', 'registered_at',
    'total_solve_time_seconds', 'puzzles_completed',
    'meta_answer', 'meta_solved', 'meta_submitted_at',
    'meta_attempts_count', 'rank',
])]
class ContestEntry extends Model
{
    /** @use HasFactory<ContestEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'meta_submitted_at' => 'datetime',
            'meta_solved' => 'boolean',
            'total_solve_time_seconds' => 'integer',
            'puzzles_completed' => 'integer',
            'meta_attempts_count' => 'integer',
            'rank' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Contest, $this>
     */
    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Format total solve time as a human-readable string.
     */
    public function formattedSolveTime(): ?string
    {
        if ($this->total_solve_time_seconds === null) {
            return null;
        }

        $seconds = $this->total_solve_time_seconds;
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }
}
