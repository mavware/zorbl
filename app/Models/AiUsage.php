<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property CarbonImmutable|null $created_at
 */
class AiUsage extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'type'];

    protected static function booted(): void
    {
        static::creating(function (AiUsage $usage) {
            $usage->created_at ??= now();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
