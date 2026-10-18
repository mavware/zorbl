<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $word
 * @property int $length
 * @property float $score
 *
 * @mixin Eloquent
 */
#[Fillable(['word', 'length', 'score'])]
class Word extends Model
{
    use HasFactory;

    /**
     * Scores follow Spread the Wordlist's scale: 55 is lively fill, 40 to 45
     * is acceptable, 30 and below is poor. The floors below are what a word's
     * own vetted source guarantees it, whatever the list says.
     */
    public const float SCORE_LIVELY = 55.0;

    /** Floor for answers from professionally published crosswords. */
    public const float SCORE_PUBLISHED = 50.0;

    /** Floor for answers from puzzles published here; also the grid filler's quality threshold. */
    public const float SCORE_ACCEPTABLE = 40.0;

    /** Words with no vetted source: the filler reaches for them only when nothing better fits. */
    public const float SCORE_UNVETTED = 20.0;

    /**
     * @return HasMany<ClueEntry, $this>
     */
    public function clueEntries(): HasMany
    {
        return $this->hasMany(ClueEntry::class, 'answer', 'word');
    }
}
