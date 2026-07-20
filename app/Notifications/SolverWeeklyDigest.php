<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SolverWeeklyDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{puzzles_solved: int, puzzles_completed: int, total_solve_time_seconds: int, current_streak: int, longest_streak: int, best_puzzle: array{title: string, solve_time_seconds: int}|null, new_puzzles_available: int}  $stats
     */
    public function __construct(
        public array $stats,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        if (! $notifiable->wantsNotification(NotificationType::SolverWeeklyDigest->value)) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('Your weekly solving recap'))
            ->greeting(__('Hi :name, here\'s your week of solving!', ['name' => $notifiable->name]));

        $message->line(__('Here\'s a look at your solving activity this past week:'));

        $lines = [];

        if ($this->stats['puzzles_completed'] > 0) {
            $lines[] = __(':count puzzle(s) completed', ['count' => $this->stats['puzzles_completed']]);
        }

        if ($this->stats['puzzles_solved'] > $this->stats['puzzles_completed']) {
            $inProgress = $this->stats['puzzles_solved'] - $this->stats['puzzles_completed'];
            $lines[] = __(':count puzzle(s) started (in progress)', ['count' => $inProgress]);
        }

        if ($this->stats['total_solve_time_seconds'] > 0) {
            $lines[] = __(':time spent solving', ['time' => $this->formatDuration($this->stats['total_solve_time_seconds'])]);
        }

        if ($this->stats['current_streak'] > 0) {
            $lines[] = __(':count-day solving streak', ['count' => $this->stats['current_streak']]);
        }

        if ($this->stats['longest_streak'] > $this->stats['current_streak']) {
            $lines[] = __('Longest streak: :count days', ['count' => $this->stats['longest_streak']]);
        }

        if (count($lines) === 0) {
            $message->line(__('It was a quiet week — no solving activity. Time to jump back in!'));
        } else {
            foreach ($lines as $line) {
                $message->line('• '.$line);
            }
        }

        if ($this->stats['best_puzzle']) {
            $message->line('');
            $message->line(__('Fastest solve: ":title" in :time.', [
                'title' => $this->stats['best_puzzle']['title'],
                'time' => $this->formatDuration($this->stats['best_puzzle']['solve_time_seconds']),
            ]));
        }

        if ($this->stats['new_puzzles_available'] > 0) {
            $message->line('');
            $message->line(__(':count new puzzle(s) were published this week — go explore!', [
                'count' => $this->stats['new_puzzles_available'],
            ]));
        }

        $message->action(__('Browse puzzles'), route('crosswords.solving'));

        $message->line(__('You can manage digest preferences in your notification settings.'));

        return $message;
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return __(':count second(s)', ['count' => $seconds]);
        }

        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;

        if ($minutes < 60) {
            return $remaining > 0
                ? __(':min min :sec sec', ['min' => $minutes, 'sec' => $remaining])
                : __(':min min', ['min' => $minutes]);
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $remainingMinutes > 0
            ? __(':hr hr :min min', ['hr' => $hours, 'min' => $remainingMinutes])
            : __(':hr hr', ['hr' => $hours]);
    }
}
