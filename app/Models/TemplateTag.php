<?php

namespace App\Models;

use App\Enums\TemplateStyle;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $template_id
 * @property TemplateStyle $tag
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Template $template
 *
 * @mixin Eloquent
 */
#[Fillable(['template_id', 'tag'])]
class TemplateTag extends Model
{
    protected $table = 'template_tag';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tag' => TemplateStyle::class,
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
