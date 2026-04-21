<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TemplateFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $name
 * @property int $width
 * @property int $height
 * @property array<int, array<int, int|string>> $grid
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static TemplateFactory factory($count = null, $state = [])
 *
 * @mixin Eloquent
 */
#[Fillable([
    'name', 'width', 'height', 'grid', 'sort_order', 'is_active',
])]
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'grid' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $template) => Cache::forget("grid_templates_{$template->width}x{$template->height}"));
        static::deleted(fn (self $template) => Cache::forget("grid_templates_{$template->width}x{$template->height}"));
    }
}
