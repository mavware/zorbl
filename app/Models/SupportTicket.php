<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SupportTicketFactory;
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
 * @property string $subject
 * @property string $description
 * @property string $category
 * @property string $status
 * @property string $priority
 * @property int|null $assigned_to
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read User|null $assignee
 * @property-read Collection<int, TicketResponse> $responses
 *
 * @method static SupportTicketFactory factory($count = null, $state = [])
 *
 * @mixin Eloquent
 */
#[Fillable([
    'user_id', 'subject', 'description', 'category',
    'status', 'priority', 'assigned_to', 'closed_at',
])]
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<TicketResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(TicketResponse::class);
    }
}
