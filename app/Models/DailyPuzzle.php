<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DailyPuzzleFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $date
 * @property int $crossword_id
 * @property int|null $selected_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Crossword $crossword
 * @property-read User|null $selector
 *
 * @method static DailyPuzzleFactory factory($count = null, $state = [])
 * @method static Builder<static> forDate(Carbon|string $date)
 *
 * @mixin Eloquent
 */
class DailyPuzzle extends Model
{
    /** @use HasFactory<DailyPuzzleFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'date',
        'crossword_id',
        'selected_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Crossword, $this>
     */
    public function crossword(): BelongsTo
    {
        return $this->belongsTo(Crossword::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function selector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForDate(Builder $query, Carbon|string $date): Builder
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : $date;

        return $query->where('date', $dateString);
    }

    public static function today(): ?self
    {
        return Cache::remember('daily_puzzle:'.today()->toDateString(), 3600, function () {
            return static::with('crossword.user:id,name')
                ->forDate(today())
                ->first();
        });
    }

    public static function todayOrAuto(): ?Crossword
    {
        $daily = static::today();

        if ($daily) {
            return $daily->crossword;
        }

        return static::autoSelect(today());
    }

    public static function autoSelect(Carbon|string $date): ?Crossword
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : $date;

        return Cache::remember('daily_puzzle_auto:'.$dateString, 3600, function () use ($dateString) {
            $candidates = Crossword::where('is_published', true)
                ->whereNotNull('title')
                ->where('title', '!=', '')
                ->whereHas('attempts', fn (Builder $q) => $q->where('is_completed', true))
                ->select('id')
                ->get();

            if ($candidates->isEmpty()) {
                return null;
            }

            $seed = crc32($dateString);
            $index = abs($seed) % $candidates->count();

            return Crossword::with('user:id,name')
                ->withCount('likes')
                ->find($candidates->values()->get($index)->id);
        });
    }
}
