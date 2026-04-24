<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Crossword> $crosswords
 * @property-read int|null $crosswords_count
 *
 * @mixin Eloquent
 */
#[Fillable(['name', 'slug'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /**
     * Common crossword tag names offered as suggestions in the editor.
     *
     * @var list<string>
     */
    public const STANDARD = [
        'Themed',
        'Themeless',
        'Cryptic',
        'Grid Art',
        'Mini',
        'Meta',
        'Logic',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tag $tag): void {
            if (blank($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    /**
     * Standard tag names matching the given search term (case-insensitive).
     *
     * @return list<string>
     */
    public static function standardSuggestions(string $search = ''): array
    {
        $search = trim($search);

        if ($search === '') {
            return self::STANDARD;
        }

        $needle = mb_strtolower($search);

        return array_values(array_filter(
            self::STANDARD,
            fn (string $name): bool => str_contains(mb_strtolower($name), $needle),
        ));
    }

    /**
     * @return BelongsToMany<Crossword, $this>
     */
    public function crosswords(): BelongsToMany
    {
        return $this->belongsToMany(Crossword::class)->withTimestamps();
    }
}
