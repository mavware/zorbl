<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TicketResponseFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $support_ticket_id
 * @property int $user_id
 * @property string $body
 * @property bool $is_admin_response
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SupportTicket $supportTicket
 * @property-read User $user
 *
 * @method static TicketResponseFactory factory($count = null, $state = [])
 *
 * @mixin Eloquent
 */
#[Fillable([
    'support_ticket_id', 'user_id', 'body', 'is_admin_response',
])]
class TicketResponse extends Model
{
    /** @use HasFactory<TicketResponseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_admin_response' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
