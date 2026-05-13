<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CookieConsent extends Model
{
    public const CHOICE_ACCEPT_ALL = 'accept_all';

    public const CHOICE_REJECT_NON_ESSENTIAL = 'reject_non_essential';

    protected $fillable = [
        'user_id',
        'identifier_hash',
        'choice',
        'version',
        'region_country',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
