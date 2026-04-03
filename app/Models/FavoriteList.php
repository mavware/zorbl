<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FavoriteListFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Crossword> $crosswords
 * @property-read int|null $crosswords_count
 * @property-read User $user
 *
 * @mixin Eloquent
 */
#[Fillable(['user_id', 'name'])]
class FavoriteList extends Model
{
    /** @use HasFactory<FavoriteListFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Crossword, $this>
     */
    public function crosswords(): BelongsToMany
    {
        return $this->belongsToMany(Crossword::class)->withTimestamps();
    }
}
