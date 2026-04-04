<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ContestFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $rules
 * @property string $meta_answer
 * @property string|null $meta_hint
 * @property string $status
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $max_meta_attempts
 * @property bool $is_featured
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Crossword> $crosswords
 * @property-read int|null $crosswords_count
 * @property-read Collection<int, ContestEntry> $entries
 * @property-read int|null $entries_count
 *
 * @method static ContestFactory factory($count = null, $state = [])
 * @method static Builder<static> active()
 * @method static Builder<static> upcoming()
 * @method static Builder<static> ended()
 * @method static Builder<static> public()
 *
 * @mixin Eloquent
 */
#[Fillable([
    'user_id', 'title', 'slug', 'description', 'rules',
    'meta_answer', 'meta_hint', 'status',
    'starts_at', 'ends_at', 'max_meta_attempts', 'is_featured',
])]
class Contest extends Model
{
    /** @use HasFactory<ContestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_featured' => 'boolean',
            'max_meta_attempts' => 'integer',
        ];
    }

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
        return $this->belongsToMany(Crossword::class)
            ->withPivot(['sort_order', 'extraction_hint'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return HasMany<ContestEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ContestEntry::class);
    }

    /**
     * Check if the contest is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->starts_at->isPast()
            && $this->ends_at->isFuture();
    }

    /**
     * Check if the contest is upcoming (not yet started).
     */
    public function isUpcoming(): bool
    {
        return $this->status === 'upcoming'
            || ($this->status === 'active' && $this->starts_at->isFuture());
    }

    /**
     * Check if the contest has ended.
     */
    public function hasEnded(): bool
    {
        return $this->status === 'ended'
            || ($this->status === 'active' && $this->ends_at->isPast());
    }

    /**
     * Normalize and compare a submitted answer against the meta answer.
     */
    public function checkMetaAnswer(string $answer): bool
    {
        $normalize = fn (string $value): string => strtoupper(preg_replace('/[^A-Z]/i', '', $value));

        return $normalize($answer) === $normalize($this->meta_answer);
    }

    /**
     * Scope to active contests.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    /**
     * Scope to upcoming contests.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', 'upcoming')
                ->orWhere(function (Builder $q2) {
                    $q2->where('status', 'active')
                        ->where('starts_at', '>', now());
                });
        });
    }

    /**
     * Scope to ended contests.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEnded(Builder $query): Builder
    {
        return $query->where('status', 'ended');
    }

    /**
     * Scope to publicly visible contests (excludes draft and archived).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['draft', 'archived']);
    }
}
