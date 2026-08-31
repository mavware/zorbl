<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Crossword;
use App\Models\User;
use App\Support\Concerns\FormatsTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PuzzleCompleted extends Notification implements ShouldQueue
{
    use FormatsTime, Queueable;

    public function __construct(
        public Crossword $crossword,
        public User $solver,
        public ?int $solveTimeSeconds = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        if (! $notifiable->wantsNotification(NotificationType::PuzzleCompleted->value)) {
            return [];
        }

        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        $title = __(':name completed your puzzle ":puzzle"', [
            'name' => $this->solver->name,
            'puzzle' => $this->crossword->displayTitle(),
        ]);

        return [
            'type' => 'puzzle.completed',
            'title' => $title,
            'body' => $this->formattedSolveTime(),
            'url' => route('crosswords.solver', $this->crossword),
            'crossword_id' => $this->crossword->id,
            'solver_id' => $this->solver->id,
        ];
    }

    private function formattedSolveTime(): ?string
    {
        if ($this->solveTimeSeconds === null) {
            return null;
        }

        return __('Solved in :time', ['time' => $this->formatTime($this->solveTimeSeconds)]);
    }
}
