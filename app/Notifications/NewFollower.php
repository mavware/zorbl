<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewFollower extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $follower,
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
            'type' => 'new_follower',
            'title' => __(':name started following you', ['name' => $this->follower->name]),
            'body' => null,
            'url' => route('constructors.show', $this->follower),
            'follower_id' => $this->follower->id,
        ];
    }
}
