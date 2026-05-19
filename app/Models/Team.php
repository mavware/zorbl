<?php

namespace App\Models;

use App\Enums\TeamRole;
use Carbon\CarbonImmutable;
use Database\Factories\TeamFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $owner_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $owner
 * @property-read Collection<int, User> $members
 * @property-read int|null $members_count
 * @property-read Collection<int, Crossword> $crosswords
 * @property-read int|null $crosswords_count
 *
 * @mixin Eloquent
 */
#[Fillable(['name', 'description', 'owner_id'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Crossword, $this>
     */
    public function crosswords(): HasMany
    {
        return $this->hasMany(Crossword::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function memberRole(User $user): ?TeamRole
    {
        $pivot = $this->members()->where('user_id', $user->id)->first()?->pivot;

        return $pivot ? TeamRole::from($pivot->role) : null;
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }
}
