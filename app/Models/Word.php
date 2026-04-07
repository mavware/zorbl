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
     * @return HasMany<ClueEntry, $this>
     */
    public function clueEntries(): HasMany
    {
        return $this->hasMany(ClueEntry::class, 'answer', 'word');
    }
}
