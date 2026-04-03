<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RoadmapItemFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string $type
 * @property string $status
 * @property int $sort_order
 * @property CarbonImmutable|null $target_date
 * @property CarbonImmutable|null $completed_date
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static RoadmapItemFactory factory($count = null, $state = [])
 *
 * @mixin Eloquent
 */
#[Fillable([
    'title', 'description', 'type', 'status',
    'sort_order', 'target_date', 'completed_date',
])]
class RoadmapItem extends Model
{
    /** @use HasFactory<RoadmapItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'target_date' => 'date',
            'completed_date' => 'date',
        ];
    }
}
