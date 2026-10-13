<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AiUsageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property CarbonImmutable|null $created_at
 *
 * @method static AiUsageFactory factory($count = null, $state = [])
 */
class AiUsage extends Model
{
    /** @use HasFactory<AiUsageFactory> */
    use HasFactory, MassPrunable;

    public $timestamps = false;

    protected $fillable = ['user_id', 'type'];

    /**
     * Quotas are counted per calendar month, so anything before the previous
     * month can never affect a limit check again.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->startOfMonth()->subMonth());
    }

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
