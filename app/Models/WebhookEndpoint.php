<?php

namespace App\Models;

use App\Enums\WebhookEvent;
use Carbon\CarbonImmutable;
use Database\Factories\WebhookEndpointFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $url
 * @property string|null $description
 * @property string $secret
 * @property array<int, string> $events
 * @property bool $is_active
 * @property CarbonImmutable|null $last_triggered_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, WebhookDelivery> $deliveries
 * @property-read int|null $deliveries_count
 *
 * @method static WebhookEndpointFactory factory($count = null, $state = [])
 *
 * @mixin Eloquent
 */
#[Fillable(['user_id', 'url', 'description', 'secret', 'events', 'is_active', 'last_triggered_at'])]
class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'secret' => 'encrypted',
            'last_triggered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function subscribedTo(WebhookEvent $event): bool
    {
        return in_array($event->value, $this->events, true);
    }
}
