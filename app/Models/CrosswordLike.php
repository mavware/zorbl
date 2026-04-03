<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CrosswordLikeFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $crossword_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Crossword $crossword
 * @property-read User $user
 *
 * @method static CrosswordLikeFactory factory($count = null, $state = [])
 *
 * @mixin Eloquent
 */
#[Fillable(['user_id', 'crossword_id'])]
class CrosswordLike extends Model
{
    /** @use HasFactory<CrosswordLikeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Crossword, $this>
     */
    public function crossword(): BelongsTo
    {
        return $this->belongsTo(Crossword::class);
    }
}
