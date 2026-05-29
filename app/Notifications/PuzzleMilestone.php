<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Crossword;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PuzzleMilestone extends Notification implements ShouldQueue
{
    use Queueable;

    public const THRESHOLDS = [10, 25, 50, 100, 250, 500, 1000];

    public function __construct(
        public Crossword $crossword,
        public int $milestone,
    ) {}

    public static function reachedMilestone(int $completedCount): ?int
    {
        $reached = null;

        foreach (self::THRESHOLDS as $threshold) {
            if ($completedCount >= $threshold) {
                $reached = $threshold;
            }
        }

        if ($reached !== null && $completedCount === $reached) {
            return $reached;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        if (! $notifiable->wantsNotification(NotificationType::PuzzleMilestone->value)) {
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
            'type' => 'puzzle.milestone',
            'title' => __('Your puzzle ":puzzle" reached :count solves!', [
                'puzzle' => $this->crossword->displayTitle(),
                'count' => $this->milestone,
            ]),
            'body' => null,
            'url' => route('crosswords.solver', $this->crossword),
            'crossword_id' => $this->crossword->id,
            'milestone' => $this->milestone,
        ];
    }
}
