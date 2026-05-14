<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ClueEntryFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $answer
 * @property string $clue
 * @property int|null $crossword_id
 * @property int $user_id
 * @property string|null $direction
 * @property int|null $clue_number
 * @property string $status
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Crossword|null $crossword
 * @property-read Collection<int, ClueReport> $reports
 * @property-read int|null $reports_count
 * @property-read User $user
 * @property-read User|null $reviewer
 *
 * @method static ClueEntryFactory factory($count = null, $state = [])
 * @method static Builder pending()
 * @method static Builder approved()
 *
 * @mixin Eloquent
 */
class ClueEntry extends Model
{
    /** @use HasFactory<ClueEntryFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    /** @var list<string> */
    protected $fillable = [
        'answer',
        'clue',
        'crossword_id',
        'user_id',
        'direction',
        'clue_number',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function crossword(): BelongsTo
    {
        return $this->belongsTo(Crossword::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<ClueReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ClueReport::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
