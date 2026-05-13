<?php

namespace App\Models;

use Database\Factories\ContentReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentReport extends Model
{
    /** @use HasFactory<ContentReportFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWING = 'reviewing';

    public const STATUS_ACTIONED = 'actioned';

    public const STATUS_DISMISSED = 'dismissed';

    /**
     * The reasons users can pick when filing a report. Keys stay stable so we
     * can filter on them; labels are translated in the UI.
     */
    public const REASONS = [
        'spam' => 'Spam or self-promotion',
        'harassment' => 'Harassment or hateful content',
        'copyright' => 'Copyright infringement',
        'inappropriate' => 'Inappropriate or sexually explicit',
        'misinformation' => 'False or misleading information',
        'other' => 'Other',
    ];

    /**
     * The polymorphic targets we accept. Used by the report-button component
     * to translate a class string into a stable short key.
     */
    public const REPORTABLE_TYPES = [
        Crossword::class => 'puzzle',
        PuzzleComment::class => 'comment',
        User::class => 'profile',
    ];

    protected $fillable = [
        'reporter_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'details',
        'status',
        'reviewed_by',
        'reviewed_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_REVIEWING]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_REVIEWING], true);
    }
}
