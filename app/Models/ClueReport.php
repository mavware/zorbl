<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $clue_entry_id
 * @property int $user_id
 * @property string $reason
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ClueEntry $clueEntry
 * @property-read User $user
 *
 * @mixin Eloquent
 */
class ClueReport extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'clue_entry_id',
        'user_id',
        'reason',
        'notes',
    ];

    public function clueEntry(): BelongsTo
    {
        return $this->belongsTo(ClueEntry::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
