<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Crossword;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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

        $channels = ['database'];

        if ($notifiable->wantsEmailNotification(NotificationType::NewPuzzlePublished->value)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $puzzleTitle = $this->crossword->displayTitle();

        return (new MailMessage)
            ->subject(__(':name published a new puzzle', ['name' => $this->constructor->name]))
            ->greeting(__('New puzzle from :name', ['name' => $this->constructor->name]))
            ->line(__(':name just published ":puzzle" — try solving it now!', [
                'name' => $this->constructor->name,
                'puzzle' => $puzzleTitle,
            ]))
            ->action(__('Solve now'), route('crosswords.solver', $this->crossword))
            ->line(__('You received this because you follow :name. You can update your notification preferences in settings.', [
                'name' => $this->constructor->name,
            ]));
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
                'puzzle' => $this->crossword->displayTitle(),
            ]),
            'body' => null,
            'url' => route('crosswords.solver', $this->crossword),
            'crossword_id' => $this->crossword->id,
            'constructor_id' => $this->constructor->id,
        ];
    }
}
