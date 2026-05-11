<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Crossword;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewPuzzlePublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Crossword $crossword,
        public User $constructor,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        if (! $notifiable->wantsNotification(NotificationType::NewPuzzlePublished->value)) {
            return [];
        }

        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'puzzle.published',
            'title' => __(':name published a new puzzle: ":puzzle"', [
                'name' => $this->constructor->name,
                'puzzle' => $this->crossword->title ?: __('Untitled Puzzle'),
            ]),
            'body' => null,
            'url' => route('crosswords.solver', $this->crossword),
            'crossword_id' => $this->crossword->id,
            'constructor_id' => $this->constructor->id,
        ];
    }
}
