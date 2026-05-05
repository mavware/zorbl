<?php

namespace App\Notifications;

use App\Models\Crossword;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PuzzleCompleted extends Notification implements ShouldQueue
{
    use Queueable;

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
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        $title = __(':name completed your puzzle ":puzzle"', [
            'name' => $this->solver->name,
            'puzzle' => $this->crossword->title ?: __('Untitled Puzzle'),
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

        $seconds = $this->solveTimeSeconds;
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return __('Solved in :time', ['time' => sprintf('%d:%02d:%02d', $hours, $minutes, $secs)]);
        }

        return __('Solved in :time', ['time' => sprintf('%d:%02d', $minutes, $secs)]);
    }
}
