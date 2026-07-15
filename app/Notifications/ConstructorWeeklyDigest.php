<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConstructorWeeklyDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{new_solves: int, new_completions: int, new_likes: int, new_comments: int, new_followers: int, top_puzzle: array{title: string, solves: int}|null}  $stats
     */
    public function __construct(
        public array $stats,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        if (! $notifiable->wantsNotification(NotificationType::WeeklyDigest->value)) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('Your weekly puzzle digest'))
            ->greeting(__('Hi :name, here\'s your week in review!', ['name' => $notifiable->name]));

        $message->line(__('Here\'s what happened with your puzzles this past week:'));

        $lines = [];

        if ($this->stats['new_solves'] > 0) {
            $lines[] = __(':count new solve attempt(s)', ['count' => $this->stats['new_solves']]);
        }

        if ($this->stats['new_completions'] > 0) {
            $lines[] = __(':count puzzle completion(s)', ['count' => $this->stats['new_completions']]);
        }

        if ($this->stats['new_likes'] > 0) {
            $lines[] = __(':count new like(s)', ['count' => $this->stats['new_likes']]);
        }

        if ($this->stats['new_comments'] > 0) {
            $lines[] = __(':count new comment(s)', ['count' => $this->stats['new_comments']]);
        }

        if ($this->stats['new_followers'] > 0) {
            $lines[] = __(':count new follower(s)', ['count' => $this->stats['new_followers']]);
        }

        if (count($lines) === 0) {
            $message->line(__('It was a quiet week — no new activity on your puzzles.'));
        } else {
            foreach ($lines as $line) {
                $message->line('• '.$line);
            }
        }

        if ($this->stats['top_puzzle']) {
            $message->line('');
            $message->line(__('Your most active puzzle: ":title" with :count solve(s) this week.', [
                'title' => $this->stats['top_puzzle']['title'],
                'count' => $this->stats['top_puzzle']['solves'],
            ]));
        }

        $message->action(__('View your analytics'), route('crosswords.analytics'));

        $message->line(__('You can manage digest preferences in your notification settings.'));

        return $message;
    }
}
