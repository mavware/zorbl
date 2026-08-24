<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    public const int MIN_LENGTH = 3;

    public const int MAX_LENGTH = 21;

    /**
     * Words are stored uppercase, and `length` is a derived column kept in sync
     * here so it can never drift from the word it describes.
     *
     * @return Attribute<string, array{word: string, length: int}>
     */
    protected function word(): Attribute
    {
        return Attribute::make(
            set: function (string $value): array {
                $upper = mb_strtoupper($value);

                return ['word' => $upper, 'length' => mb_strlen($upper)];
            },
        );
    }

    /**
     * @return HasMany<ClueEntry, $this>
     */
    public function clueEntries(): HasMany
    {
        return $this->hasMany(ClueEntry::class, 'answer', 'word');
    }
}
