<?php

namespace App\Models;

use App\Enums\WordplayType;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $word
 * @property WordplayType $type
 * @property array $notes
 * @property string $status
 *
 * @mixin Eloquent
 */
#[Fillable(['word', 'type', 'notes', 'status'])]
class WordplayEntry extends Model
{
    protected function casts(): array
    {
        // LUKEWARMONGER
        return [
            'type' => WordplayType::class,
            'notes' => 'array',
        ];
    }
}
