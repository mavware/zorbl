<?php

namespace App\Notifications;

use App\Models\Crossword;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CrosswordLiked extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Crossword $crossword,
        public User $liker,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'crossword.liked',
            'title' => __(':name liked your puzzle ":puzzle"', [
                'name' => $this->liker->name,
                'puzzle' => $this->crossword->title ?: __('Untitled Puzzle'),
            ]),
            'body' => null,
            'url' => route('crosswords.solver', $this->crossword),
            'crossword_id' => $this->crossword->id,
            'liker_id' => $this->liker->id,
        ];
    }
}
