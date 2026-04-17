<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\StripeWebhookLogFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $stripe_event_id
 * @property string $type
 * @property bool $livemode
 * @property int|null $user_id
 * @property string|null $stripe_customer_id
 * @property array<string, mixed> $payload
 * @property CarbonImmutable|null $processed_at
 * @property string|null $error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 *
 * @method static StripeWebhookLogFactory factory($count = null, $state = [])
 *
 * @mixin Eloquent
 */
#[Fillable(['stripe_event_id', 'type', 'livemode', 'user_id', 'stripe_customer_id', 'payload', 'processed_at', 'error'])]
class StripeWebhookLog extends Model
{
    /** @use HasFactory<StripeWebhookLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
