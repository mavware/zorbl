<?php

namespace App\Notifications;

use App\Models\PuzzleComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewPuzzleComment extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PuzzleComment $comment,
        public User $commenter,
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
            'type' => 'puzzle.comment',
            'title' => __(':name commented on your puzzle', ['name' => $this->commenter->name]),
            'body' => $this->comment->body,
            'url' => route('crosswords.solver', $this->comment->crossword_id),
            'comment_id' => $this->comment->id,
            'crossword_id' => $this->comment->crossword_id,
            'commenter_id' => $this->commenter->id,
        ];
    }
}
