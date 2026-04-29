<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $template_id
 * @property string $philosophy
 * @property list<string>|null $strengths
 * @property list<string>|null $compromises
 * @property string|null $best_for
 * @property string|null $avoid_when
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Template $template
 *
 * @mixin Eloquent
 */
#[Fillable([
    'template_id', 'philosophy', 'strengths', 'compromises', 'best_for', 'avoid_when',
])]
class TemplateAnnotation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'strengths' => 'array',
            'compromises' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
