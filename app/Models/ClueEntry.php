<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Collection;
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
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Crossword|null $crossword
 * @property-read Collection<int, ClueReport> $reports
 * @property-read int|null $reports_count
 * @property-read User $user
 *
 * @mixin Eloquent
 */
class ClueEntry extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'answer',
        'clue',
        'crossword_id',
        'user_id',
        'direction',
        'clue_number',
    ];

    public function crossword(): BelongsTo
    {
        return $this->belongsTo(Crossword::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ClueReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ClueReport::class);
    }
}
