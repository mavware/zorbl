<?php

namespace App\Models;

use Database\Factories\PuzzleAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PuzzleAttempt extends Model
{
    /** @use HasFactory<PuzzleAttemptFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'crossword_id',
        'progress',
        'is_completed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'progress' => 'array',
            'is_completed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function crossword(): BelongsTo
    {
        return $this->belongsTo(Crossword::class);
    }
}
