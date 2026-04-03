<?php

namespace App\Models;

use Database\Factories\PuzzleCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PuzzleComment extends Model
{
    /** @use HasFactory<PuzzleCommentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'crossword_id',
        'body',
        'rating',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
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
     * @return BelongsTo<Crossword, $this>
     */
    public function crossword(): BelongsTo
    {
        return $this->belongsTo(Crossword::class);
    }
}
