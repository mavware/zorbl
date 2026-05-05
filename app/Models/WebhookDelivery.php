<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\WebhookDeliveryFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $webhook_endpoint_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property int|null $response_code
 * @property string|null $response_body
 * @property bool $success
 * @property int $attempt_count
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read WebhookEndpoint $endpoint
 *
 * @method static WebhookDeliveryFactory factory($count = null, $state = [])
 *
 * @mixin Eloquent
 */
#[Fillable(['webhook_endpoint_id', 'event', 'payload', 'response_code', 'response_body', 'success', 'attempt_count', 'delivered_at'])]
class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'success' => 'boolean',
            'response_code' => 'integer',
            'attempt_count' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
