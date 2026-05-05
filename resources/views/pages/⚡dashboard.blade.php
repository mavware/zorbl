<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\Follow;
use App\Models\PuzzleAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function publishedCount(): int
    {
        return Auth::user()->crosswords()->where('is_published', true)->count();
    }

    #[Computed]
    public function draftCount(): int
    {
        return Auth::user()->crosswords()->where('is_published', false)->count();
    }

    #[Computed]
    public function solvedCount(): int
    {
        return Auth::user()->puzzleAttempts()->where('is_completed', true)->count();
    }

    #[Computed]
    public function inProgressAttempts()
    {
        return Auth::user()
            ->puzzleAttempts()
            ->where('is_completed', false)
            ->with('crossword.user:id,name')
            ->latest('updated_at')
            ->limit(3)
            ->get();
    }

    #[Computed]
    public function recentDrafts()
    {
        return Auth::user()
            ->crosswords()
            ->where('is_published', false)
            ->latest('updated_at')
            ->limit(3)
            ->get();
    }

    #[Computed]
    public function trendingPuzzles()
    {
        $recentlyLikedIds = CrosswordLike::where('created_at', '>=', now()->subWeek())
            ->select('crossword_id')
            ->selectRaw('count(*) as recent_likes')
            ->groupBy('crossword_id')
            ->orderByDesc('recent_likes')
            ->limit(10)
            ->pluck('recent_likes', 'crossword_id');

        if ($recentlyLikedIds->isEmpty()) {
            return collect();
        }

        return Crossword::where('is_published', true)
            ->where('user_id', '!=', Auth::id())
            ->whereIn('id', $recentlyLikedIds->keys())
            ->with('user:id,name')
            ->withCount('likes')
            ->get()
            ->sortByDesc(fn ($c) => $recentlyLikedIds[$c->id] ?? 0)
            ->take(3)
            ->values();
    }

    #[Computed]
    public function newestPuzzles()
    {
        return Crossword::where('is_published', true)
            ->where('user_id', '!=', Auth::id())
            ->with('user:id,name')
            ->withCount('likes')
            ->latest()
            ->limit(3)
            ->get();
    }

    #[Computed]
    public function followingPuzzles()
    {
        $followingIds = Auth::user()->following()->pluck('users.id');

        if ($followingIds->isEmpty()) {
            return collect();
        }

        return Crossword::where('is_published', true)
            ->whereIn('user_id', $followingIds)
            ->with('user:id,name')
            ->withCount('likes')
            ->latest()
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function followingCount(): int
    {
        return Auth::user()->following()->count();
    }

    #[Computed]
    public function totalPublishedPuzzles(): int
    {
        return Cache::remember('stats:published_puzzles', 300, fn () => Crossword::where('is_published', true)->count());
    }

    #[Computed]
    public function totalSolves(): int
    {
        return Cache::remember('stats:total_solves', 300, fn () => PuzzleAttempt::where('is_completed', true)->count());
    }

    #[Computed]
    public function totalLikes(): int
    {
        return Cache::remember('stats:total_likes', 300, fn () => CrosswordLike::count());
    }

    #[Computed]
    public function likedCount(): int
    {
        return Auth::user()->crosswordLikes()->count();
    }
}
?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- In Progress --}}
        <div class="border-line rounded-xl border p-5">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Continue Solving') }}</flux:heading>
                <flux:button variant="ghost" size="sm" :href="route('crosswords.solving')" wire:navigate>
                    {{ __('View All') }}
                </flux:button>
            </div>

            @if($this->inProgressAttempts->isEmpty())
                <div class="border-line-strong flex flex-col items-center justify-center rounded-lg border border-dashed py-8">
                    <flux:icon name="play" class="mb-2 size-8 text-zinc-500" />
                    <flux:text size="sm" class="text-zinc-500">{{ __('No puzzles in progress') }}</flux:text>
                    <flux:button variant="ghost" size="sm" class="mt-2" :href="route('crosswords.solving')" wire:navigate>
                        {{ __('Browse Puzzles') }}
                    </flux:button>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($this->inProgressAttempts as $attempt)
                        <a
                            href="{{ route('crosswords.solver', $attempt->crossword) }}"
                            wire:navigate
                            class="flex items-center gap-3 rounded-lg p-2 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800"
                        >
                            <x-grid-thumbnail class="shrink-0" :grid="$attempt->crossword->grid" :width="$attempt->crossword->width" :height="$attempt->crossword->height" :cell-size="5" :max-width="48" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-fg">{{ $attempt->crossword->title ?: __('Untitled Puzzle') }}</div>
                                <flux:text size="sm" class="text-zinc-500">
                                    {{ __('by :author', ['author' => $attempt->crossword->user->name ?? __('Unknown')]) }}
                                    &middot;
                                    {{ $attempt->updated_at->diffForHumans() }}
                                </flux:text>
                            </div>
                            <flux:icon name="chevron-right" class="size-4 shrink-0 text-zinc-500" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Continue Constructing --}}
        <div class="border-line rounded-xl border p-5">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Continue Constructing') }}</flux:heading>
                <flux:button variant="ghost" size="sm" :href="route('crosswords.index')" wire:navigate>
                    {{ __('View All') }}
                </flux:button>
            </div>

            @if($this->recentDrafts->isEmpty())
                <div class="border-line-strong flex flex-col items-center justify-center rounded-lg border border-dashed py-8">
                    <flux:icon name="pencil-square" class="mb-2 size-8 text-zinc-500" />
                    <flux:text size="sm" class="text-zinc-500">{{ __('No drafts in progress') }}</flux:text>
                    <flux:button variant="ghost" size="sm" class="mt-2" :href="route('crosswords.index')" wire:navigate>
                        {{ __('Create a Puzzle') }}
                    </flux:button>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($this->recentDrafts as $crossword)
                        <a
                            href="{{ route('crosswords.editor', $crossword) }}"
                            wire:navigate
                            class="flex items-center gap-3 rounded-lg p-2 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800"
                        >
                            <x-grid-thumbnail class="shrink-0" :grid="$crossword->grid" :width="$crossword->width" :height="$crossword->height" :cell-size="5" :max-width="48" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-fg">{{ $crossword->title ?: __('Untitled Puzzle') }}</div>
                                <flux:text size="sm" class="text-zinc-500">
                                    {{ $crossword->width }}&times;{{ $crossword->height }}
                                    &middot;
                                    @php($completeness = $crossword->completeness())
                                    {{ $completeness['percentage'] }}% {{ __('complete') }}
                                </flux:text>
                            </div>
                            <flux:icon name="chevron-right" class="size-4 shrink-0 text-zinc-500" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- From People You Follow --}}
    @if($this->followingCount > 0)
        <div class="border-line rounded-xl border p-5">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ __('From People You Follow') }}</flux:heading>
                    <flux:badge size="sm" color="blue">{{ $this->followingCount }}</flux:badge>
                </div>
                <flux:button variant="ghost" size="sm" :href="route('crosswords.solving')" wire:navigate>
                    {{ __('Browse All') }}
                </flux:button>
            </div>

            @if($this->followingPuzzles->isEmpty())
                <div class="border-line-strong flex flex-col items-center justify-center rounded-lg border border-dashed py-8">
                    <flux:icon name="clock" class="mb-2 size-8 text-zinc-500" />
                    <flux:text size="sm" class="text-zinc-500">{{ __('No new puzzles from people you follow yet.') }}</flux:text>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($this->followingPuzzles as $crossword)
                        <a
                            href="{{ route('crosswords.solver', $crossword) }}"
                            wire:navigate
                            class="border-line group rounded-xl border p-4 transition-colors hover:border-zinc-400 dark:hover:border-zinc-600"
                        >
                            <div class="mb-3 flex justify-center">
                                <x-grid-thumbnail :grid="$crossword->grid" :width="$crossword->width" :height="$crossword->height" :cell-size="5" :max-width="80" />
                            </div>
                            <flux:heading size="sm" class="truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                {{ $crossword->title ?: __('Untitled Puzzle') }}
                            </flux:heading>
                            <flux:text size="sm" class="mt-1 text-zinc-500">
                                {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }}
                                &middot;
                                {{ $crossword->width }}&times;{{ $crossword->height }}
                            </flux:text>
                            <div class="mt-1.5 flex items-center gap-2 text-xs text-zinc-500">
                                <span class="flex items-center gap-0.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-red-400" viewBox="0 0 24 24" fill="currentColor"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
                                    {{ $crossword->likes_count }}
                                </span>
                                <span>{{ $crossword->created_at->diffForHumans() }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Trending & Newest --}}
    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Trending --}}
        <div class="border-line rounded-xl border p-5">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Trending') }}</flux:heading>
                <flux:button variant="ghost" size="sm" :href="route('crosswords.solving')" wire:navigate>
                    {{ __('Browse All') }}
                </flux:button>
            </div>

            @if($this->trendingPuzzles->isEmpty())
                <div class="border-line-strong flex flex-col items-center justify-center rounded-lg border border-dashed py-8">
                    <flux:icon name="fire" class="mb-2 size-8 text-zinc-500" />
                    <flux:text size="sm" class="text-zinc-500">{{ __('No trending puzzles this week') }}</flux:text>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($this->trendingPuzzles as $crossword)
                        <a
                            href="{{ route('crosswords.solver', $crossword) }}"
                            wire:navigate
                            class="flex items-center gap-3 rounded-lg p-2 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800"
                        >
                            <x-grid-thumbnail class="shrink-0" :grid="$crossword->grid" :width="$crossword->width" :height="$crossword->height" :cell-size="5" :max-width="48" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-fg">{{ $crossword->title ?: __('Untitled Puzzle') }}</div>
                                <flux:text size="sm" class="text-zinc-500">
                                    {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }}
                                    &middot;
                                    <span class="text-red-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="inline size-3" viewBox="0 0 24 24" fill="currentColor"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
                                        {{ $crossword->likes_count }}
                                    </span>
                                </flux:text>
                            </div>
                            <flux:icon name="chevron-right" class="size-4 shrink-0 text-zinc-500" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Newest --}}
        <div class="border-line rounded-xl border p-5">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Newest') }}</flux:heading>
                <flux:button variant="ghost" size="sm" :href="route('crosswords.solving')" wire:navigate>
                    {{ __('Browse All') }}
                </flux:button>
            </div>

            @if($this->newestPuzzles->isEmpty())
                <div class="border-line-strong flex flex-col items-center justify-center rounded-lg border border-dashed py-8">
                    <flux:icon name="sparkles" class="mb-2 size-8 text-zinc-500" />
                    <flux:text size="sm" class="text-zinc-500">{{ __('No published puzzles yet') }}</flux:text>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($this->newestPuzzles as $crossword)
                        <a
                            href="{{ route('crosswords.solver', $crossword) }}"
                            wire:navigate
                            class="flex items-center gap-3 rounded-lg p-2 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800"
                        >
                            <x-grid-thumbnail class="shrink-0" :grid="$crossword->grid" :width="$crossword->width" :height="$crossword->height" :cell-size="5" :max-width="48" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-fg">{{ $crossword->title ?: __('Untitled Puzzle') }}</div>
                                <flux:text size="sm" class="text-zinc-500">
                                    {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }}
                                    &middot;
                                    {{ $crossword->created_at->diffForHumans() }}
                                </flux:text>
                            </div>
                            <flux:icon name="chevron-right" class="size-4 shrink-0 text-zinc-500" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Discover Puzzles --}}
    <div class="border-line rounded-xl border p-5">
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="lg">{{ __('Discover Puzzles') }}</flux:heading>
            <flux:button variant="ghost" size="sm" :href="route('crosswords.solving')" wire:navigate>
                {{ __('Browse All') }}
            </flux:button>
        </div>

        <livewire:puzzle-discovery :limit="3" :exclude-attempted="true" />
    </div>

    {{-- Stats Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Published Puzzles --}}
        <div class="border-line rounded-xl border p-5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                    <flux:icon name="puzzle-piece" class="size-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-600">{{ __('Published') }}</flux:text>
                    <div class="text-2xl font-bold text-fg">{{ $this->publishedCount }}</div>
                </div>
            </div>
        </div>

        {{-- Draft Puzzles --}}
        <div class="border-line rounded-xl border p-5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-page">
                    <flux:icon name="pencil" class="size-5 text-zinc-700 dark:text-zinc-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-600">{{ __('Drafts') }}</flux:text>
                    <div class="text-2xl font-bold text-fg">{{ $this->draftCount }}</div>
                </div>
            </div>
        </div>

        {{-- Puzzles Solved --}}
        <div class="border-line rounded-xl border p-5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                    <flux:icon name="check-circle" class="size-5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-600">{{ __('Solved') }}</flux:text>
                    <div class="text-2xl font-bold text-fg">{{ $this->solvedCount }}</div>
                </div>
            </div>
        </div>

        {{-- Likes Given --}}
        <div class="border-line rounded-xl border p-5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="heart" class="size-5 text-red-500 dark:text-red-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-600">{{ __('Liked') }}</flux:text>
                    <div class="text-2xl font-bold text-fg">{{ $this->likedCount }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Community Stats --}}
    <div class="border-line rounded-xl border p-5">
        <flux:heading size="lg" class="mb-4">{{ __('Community') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="text-center">
                <div class="text-3xl font-bold text-fg">{{ $this->totalPublishedPuzzles }}</div>
                <flux:text size="sm" class="text-zinc-600">{{ __('Published Puzzles') }}</flux:text>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-fg">{{ $this->totalSolves }}</div>
                <flux:text size="sm" class="text-zinc-600">{{ __('Total Solves') }}</flux:text>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-fg">{{ $this->totalLikes }}</div>
                <flux:text size="sm" class="text-zinc-600">{{ __('Total Likes') }}</flux:text>
            </div>
        </div>
    </div>
</div>
