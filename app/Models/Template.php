<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TemplateFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $name
 * @property int $width
 * @property int $height
 * @property array<int, array<int, int|string>> $grid
 * @property array<string, array{bars?: list<string>}>|null $styles
 * @property int $min_word_length
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, TemplateTag> $templateTags
 * @property-read TemplateAnnotation|null $annotation
 *
 * @method static TemplateFactory factory($count = null, $state = [])
 *
 * @mixin Eloquent
 */
#[Fillable([
    'name', 'width', 'height', 'grid', 'styles', 'min_word_length', 'sort_order', 'is_active',
])]
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'grid' => 'array',
            'styles' => 'array',
            'min_word_length' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $template) => Cache::forget("grid_templates_{$template->width}x{$template->height}"));
        static::deleted(fn (self $template) => Cache::forget("grid_templates_{$template->width}x{$template->height}"));
    }

    /**
     * @return HasMany<TemplateTag, $this>
     */
    public function templateTags(): HasMany
    {
        return $this->hasMany(TemplateTag::class);
    }

    /**
     * @return HasOne<TemplateAnnotation, $this>
     */
    public function annotation(): HasOne
    {
        return $this->hasOne(TemplateAnnotation::class);
    }
}
