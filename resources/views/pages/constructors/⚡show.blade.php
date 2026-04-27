<?php

use App\Models\Crossword;
use App\Models\Follow;
use App\Models\PuzzleAttempt;
use App\Models\User;
use App\Notifications\NewFollower;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Constructor Profile')] class extends Component {
    #[Locked]
    public int $constructorId;

    public string $constructorName = '';

    #[Url]
    public string $sortBy = 'newest';

    #[Url]
    public string $difficulty = '';

    public function mount(User $constructor): void
    {
        $this->constructorId = $constructor->id;
        $this->constructorName = $constructor->name;
    }

    #[Computed]
    public function constructor(): User
    {
        return User::findOrFail($this->constructorId);
    }

    #[Computed]
    public function publishedPuzzles()
    {
        $query = Crossword::where('user_id', $this->constructorId)
            ->where('is_published', true)
            ->withCount([
                'likes',
                'attempts',
                'attempts as completed_attempts_count' => fn ($q) => $q->where('is_completed', true),
            ]);

        if ($this->difficulty !== '') {
            $query->where('difficulty_label', $this->difficulty);
        }

        match ($this->sortBy) {
            'oldest' => $query->oldest(),
            'most_liked' => $query->orderByDesc('likes_count'),
            'most_played' => $query->orderByDesc('attempts_count'),
            default => $query->latest(),
        };

        return $query->get();
    }

    public function updatedSortBy(): void
    {
        unset($this->publishedPuzzles);
    }

    public function updatedDifficulty(): void
    {
        unset($this->publishedPuzzles);
    }

    #[Computed]
    public function followersCount(): int
    {
        return Follow::where('following_id', $this->constructorId)->count();
    }

    #[Computed]
    public function followingCount(): int
    {
        return Follow::where('follower_id', $this->constructorId)->count();
    }

    #[Computed]
    public function isFollowing(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        return Follow::where('follower_id', Auth::id())
            ->where('following_id', $this->constructorId)
            ->exists();
    }

    #[Computed]
    public function totalSolves(): int
    {
        return PuzzleAttempt::whereHas('crossword', fn ($q) => $q
            ->where('user_id', $this->constructorId)
            ->where('is_published', true)
        )->count();
    }

    public function toggleFollow(): void
    {
        $user = Auth::user();

        if (! $user || $user->id === $this->constructorId) {
            return;
        }

        $existing = Follow::where('follower_id', $user->id)
            ->where('following_id', $this->constructorId)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            Follow::create([
                'follower_id' => $user->id,
                'following_id' => $this->constructorId,
            ]);

            $followedUser = User::find($this->constructorId);

            if ($followedUser) {
                $followedUser->notify(new NewFollower($user));
            }
        }

        unset($this->isFollowing, $this->followersCount);
    }
}
?>

<div class="space-y-6">
    {{-- Profile Header --}}
    <div class="flex items-start justify-between">
        <div class="flex items-center gap-4">
            <div class="flex size-16 items-center justify-center rounded-full bg-zinc-200 text-xl font-bold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                {{ $this->constructor->initials() }}
            </div>
            <div>
                <flux:heading size="xl">
                    {{ $constructorName }}
                    @if ($this->constructor->isPro())
                        <flux:badge color="purple" size="sm" class="ml-1 align-middle">{{ __('Pro') }}</flux:badge>
                    @endif
                </flux:heading>
                <div class="mt-1 flex items-center gap-4 text-sm text-zinc-600">
                    <span>{{ trans_choice(':count puzzle|:count puzzles', $this->publishedPuzzles->count()) }}</span>
                    <span>{{ trans_choice(':count follower|:count followers', $this->followersCount) }}</span>
                    <span>{{ __(':count total solves', ['count' => $this->totalSolves]) }}</span>
                </div>
                <flux:text size="sm" class="mt-0.5 text-zinc-500">
                    {{ __('Joined :date', ['date' => $this->constructor->created_at->format('M Y')]) }}
                </flux:text>
            </div>
        </div>

        @auth
            @if(Auth::id() !== $constructorId)
                <flux:button
                    wire:click="toggleFollow"
                    :variant="$this->isFollowing ? 'ghost' : 'primary'"
                    size="sm"
                    :icon="$this->isFollowing ? 'user-minus' : 'user-plus'"
                >
                    {{ $this->isFollowing ? __('Unfollow') : __('Follow') }}
                </flux:button>
            @endif
        @endauth
    </div>

    {{-- Published Puzzles --}}
    <div>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="lg">{{ __('Published Puzzles') }}</flux:heading>

            <div class="flex flex-wrap items-center gap-3">
                <flux:radio.group wire:model.live="difficulty" variant="segmented" size="sm">
                    <flux:radio value="" label="{{ __('All') }}" />
                    <flux:radio value="Easy" label="{{ __('Easy') }}" />
                    <flux:radio value="Medium" label="{{ __('Medium') }}" />
                    <flux:radio value="Hard" label="{{ __('Hard') }}" />
                    <flux:radio value="Expert" label="{{ __('Expert') }}" />
                </flux:radio.group>

                <flux:select wire:model.live="sortBy" size="sm" class="w-36">
                    <flux:select.option value="newest">{{ __('Newest') }}</flux:select.option>
                    <flux:select.option value="oldest">{{ __('Oldest') }}</flux:select.option>
                    <flux:select.option value="most_liked">{{ __('Most Liked') }}</flux:select.option>
                    <flux:select.option value="most_played">{{ __('Most Played') }}</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if($this->publishedPuzzles->isEmpty())
            <div class="border-line-strong flex flex-col items-center justify-center rounded-lg border border-dashed py-8">
                <flux:icon name="puzzle-piece" class="mb-2 size-8 text-zinc-500" />
                <flux:text size="sm" class="text-zinc-500">
                    @if($this->difficulty !== '')
                        {{ __('No puzzles match this difficulty.') }}
                    @else
                        {{ __('No published puzzles yet.') }}
                    @endif
                </flux:text>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($this->publishedPuzzles as $puzzle)
                    <a
                        href="{{ route('crosswords.solver', $puzzle) }}"
                        wire:navigate
                        class="border-line group rounded-xl border p-4 transition-colors hover:border-zinc-400 dark:hover:border-zinc-600"
                    >
                        <div class="mb-3 flex justify-center">
                            <x-grid-thumbnail :grid="$puzzle->grid" :width="$puzzle->width" :height="$puzzle->height" />
                        </div>
                        <div class="mb-2 flex items-start justify-between">
                            <flux:heading size="sm" class="group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                {{ $puzzle->title ?: __('Untitled Puzzle') }}
                            </flux:heading>
                            @if($puzzle->difficulty_label)
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' => $puzzle->difficulty_label === 'Easy',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $puzzle->difficulty_label === 'Medium',
                                    'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' => $puzzle->difficulty_label === 'Hard',
                                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $puzzle->difficulty_label === 'Expert',
                                ])>{{ $puzzle->difficulty_label }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 text-xs text-zinc-500">
                            <span>{{ $puzzle->width }}&times;{{ $puzzle->height }}</span>
                            <span class="flex items-center gap-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
                                {{ $puzzle->likes_count }}
                            </span>
                            <span class="flex items-center gap-0.5">
                                <flux:icon name="play" class="size-3.5" />
                                {{ $puzzle->attempts_count }}
                            </span>
                            @if($puzzle->attempts_count > 0)
                                @php($completionRate = round(($puzzle->completed_attempts_count / $puzzle->attempts_count) * 100))
                                <span @class([
                                    'text-emerald-600 dark:text-emerald-400' => $completionRate >= 75,
                                    'text-amber-600 dark:text-amber-400' => $completionRate >= 40 && $completionRate < 75,
                                    'text-zinc-600' => $completionRate < 40,
                                ])>{{ $completionRate }}% {{ __('solved') }}</span>
                            @endif
                        </div>
                        <flux:text size="sm" class="mt-1 text-zinc-500">
                            {{ $puzzle->created_at->diffForHumans() }}
                        </flux:text>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
