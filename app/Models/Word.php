<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $word
 * @property int $length
 * @property float $score
 *
 * @mixin Eloquent
 */
#[Fillable(['word', 'length', 'score'])]
class Word extends Model
{
    //
}
