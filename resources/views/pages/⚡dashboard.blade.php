<?php

use App\Models\Contest;
use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\DailyPuzzle;
use App\Models\Follow;
use App\Models\PuzzleAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function dailyPuzzle(): ?Crossword
    {
        return DailyPuzzle::todayOrAuto();
    }

    #[Computed]
    public function dailyPuzzleSolved(): bool
    {
        $puzzle = $this->dailyPuzzle;

        if (! $puzzle) {
            return false;
        }

        return Auth::user()
            ->puzzleAttempts()
            ->where('crossword_id', $puzzle->id)
            ->where('is_completed', true)
            ->exists();
    }

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

    #[Computed]
    public function currentStreak(): int
    {
        return Auth::user()->current_streak ?? 0;
    }

    #[Computed]
    public function longestStreak(): int
    {
        return Auth::user()->longest_streak ?? 0;
    }

    #[Computed]
    public function streakIsActive(): bool
    {
        $lastSolve = Auth::user()->last_solve_date;

        if (! $lastSolve) {
            return false;
        }

        $lastSolveDate = \Carbon\Carbon::parse($lastSolve);

        return $lastSolveDate->isToday() || $lastSolveDate->isYesterday();
    }

    /**
     * Brand-new account with no activity yet — render a friendlier first-run
     * hero so they don't bounce off a wall of zero-state cards.
     */
    #[Computed]
    public function isNewUser(): bool
    {
        return $this->publishedCount === 0
            && $this->draftCount === 0
            && $this->solvedCount === 0
            && $this->inProgressAttempts->isEmpty();
    }

    #[Computed]
    public function activeContests()
    {
        return Contest::active()
            ->withCount(['entries', 'crosswords'])
            ->latest('starts_at')
            ->limit(3)
            ->get();
    }

    #[Computed]
    public function upcomingContests()
    {
        return Contest::upcoming()
            ->withCount(['entries', 'crosswords'])
            ->orderBy('starts_at')
            ->limit(3)
            ->get();
    }
}
?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

    {{-- First-run welcome — only visible to brand-new accounts with zero activity. --}}
    @if($this->isNewUser)
        <div class="relative overflow-hidden rounded-xl border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 p-6 dark:border-amber-800/50 dark:from-amber-950/30 dark:to-orange-950/20" data-test="dashboard-welcome-hero">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="max-w-xl">
                    <flux:heading size="lg" class="!text-amber-700 dark:!text-amber-300">
                        {{ __('Welcome to :app, :name!', ['app' => config('app.name'), 'name' => auth()->user()->name]) }}
                    </flux:heading>
                    <flux:text class="mt-2">
                        {{ __('You\'re all set up. Two good ways to get started:') }}
                    </flux:text>
                    <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                        <li class="flex items-start gap-2">
                            <flux:icon name="play" class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            <span>{{ __('Try a solve — pick any puzzle from the community to see how the editor and solver feel.') }}</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <flux:icon name="pencil-square" class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            <span>{{ __('Build your first puzzle — the editor handles symmetry, numbering, and exports for you.') }}</span>
                        </li>
                    </ul>
                </div>
                <div class="flex flex-shrink-0 flex-col gap-2 sm:items-end">
                    <flux:button variant="primary" icon="play" :href="route('crosswords.solving')" wire:navigate>
                        {{ __('Browse puzzles to solve') }}
                    </flux:button>
                    <flux:button variant="ghost" icon="plus" :href="route('crosswords.index')" wire:navigate>
                        {{ __('Build a puzzle') }}
                    </flux:button>
                    <a href="{{ route('help.index') }}" wire:navigate class="mt-1 text-xs text-zinc-600 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200">
                        {{ __('Read the Help Center →') }}
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Puzzle of the Day --}}
    @if($dailyPuzzle = $this->dailyPuzzle)
        @php
            $dailySolved = $this->dailyPuzzleSolved;
            $dailyIconName = $dailySolved ? 'check-circle' : 'star';
            $dailyBorderClass = $dailySolved
                ? 'border-emerald-200 bg-gradient-to-r from-emerald-50 to-green-50 dark:border-emerald-800/50 dark:from-emerald-950/30 dark:to-green-950/30'
                : 'border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 dark:border-amber-800/50 dark:from-amber-950/30 dark:to-orange-950/30';
            $dailyIconBgClass = $dailySolved ? 'bg-emerald-100 dark:bg-emerald-900/50' : 'bg-amber-100 dark:bg-amber-900/50';
            $dailyIconClass = $dailySolved ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400';
        @endphp
        <div class="relative overflow-hidden rounded-xl border {{ $dailyBorderClass }} p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl {{ $dailyIconBgClass }}">
                        <flux:icon :name="$dailyIconName" class="size-6 {{ $dailyIconClass }}" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ __('Puzzle of the Day') }}</flux:heading>
                            <flux:badge size="sm" color="amber">{{ today()->format('M j') }}</flux:badge>
                            @if($dailySolved)
                                <flux:badge size="sm" color="green" icon="check-circle">{{ __('Solved') }}</flux:badge>
                            @endif
                        </div>
                        <flux:text size="sm" class="mt-0.5 text-zinc-600 dark:text-zinc-400">
                            <span class="font-medium text-fg">{{ $dailyPuzzle->displayTitle() }}</span>
                            &middot;
                            {{ __('by :author', ['author' => $dailyPuzzle->user->name ?? __('Unknown')]) }}
                            &middot;
                            {{ $dailyPuzzle->width }}&times;{{ $dailyPuzzle->height }}
                        </flux:text>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-2">
                    @if($dailySolved)
                        <flux:button variant="filled" size="sm" :href="route('crosswords.solver', $dailyPuzzle)" wire:navigate icon="eye">
                            {{ __('View Solution') }}
                        </flux:button>
                    @else
                        <flux:button variant="primary" size="sm" :href="route('crosswords.solver', $dailyPuzzle)" wire:navigate icon="play">
                            {{ __('Solve Today\'s Puzzle') }}
                        </flux:button>
                    @endif
                    <a href="{{ route('puzzles.daily-history') }}" wire:navigate class="text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                        {{ __('View past puzzles') }} &rarr;
                    </a>
                </div>
            </div>
        </div>
    @endif

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
                                <div class="truncate text-sm font-medium text-fg">{{ $attempt->crossword->displayTitle() }}</div>
                                <flux:text size="sm" class="text-zinc-500">
                                    {{ __('by :author', ['author' => $attempt->crossword->user->name ?? __('Unknown')]) }}
                                    &middot;
                                    {{ $attempt->updated_at->diffForHumans() }}
                                </flux:text>
                                @php($solveProgress = $attempt->solveProgress())
                                <div class="mt-1.5 flex items-center gap-2">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                        <div
                                            class="h-full rounded-full {{ $solveProgress >= 50 ? 'bg-sky-500' : 'bg-zinc-400' }}"
                                            style="width: {{ $solveProgress }}%"
                                        ></div>
                                    </div>
                                    <span class="text-xs tabular-nums text-zinc-500">{{ $solveProgress }}%</span>
                                </div>
                            </div>
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
                                <div class="truncate text-sm font-medium text-fg">{{ $crossword->displayTitle() }}</div>
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

    {{-- Active & Upcoming Contests --}}
    @if($this->activeContests->isNotEmpty() || $this->upcomingContests->isNotEmpty())
        <div class="border-line rounded-xl border p-5">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ __('Contests') }}</flux:heading>
                </div>
                <flux:button variant="ghost" size="sm" :href="route('contests.index')" wire:navigate>
                    {{ __('View All') }}
                </flux:button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($this->activeContests as $contest)
                    <a
                        href="{{ route('contests.show', $contest) }}"
                        wire:navigate
                        wire:key="contest-active-{{ $contest->id }}"
                        class="border-line group rounded-xl border p-4 transition-colors hover:border-zinc-400 dark:hover:border-zinc-600"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @if($contest->is_featured)
                                <flux:badge color="amber" size="sm">{{ __('Featured') }}</flux:badge>
                            @endif
                        </div>
                        <flux:heading size="sm" class="truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                            {{ $contest->title }}
                        </flux:heading>
                        <flux:text size="sm" class="mt-1 text-zinc-500">
                            {{ $contest->crosswords_count }} {{ __('puzzles') }}
                            &middot;
                            {{ $contest->entries_count }} {{ __('participants') }}
                        </flux:text>
                        @if($contest->ends_at->isFuture())
                            <flux:text size="xs" class="mt-1.5 text-amber-600 dark:text-amber-400">
                                {{ __('Ends :time', ['time' => $contest->ends_at->diffForHumans()]) }}
                            </flux:text>
                        @endif
                    </a>
                @endforeach

                @foreach($this->upcomingContests as $contest)
                    <a
                        href="{{ route('contests.show', $contest) }}"
                        wire:navigate
                        wire:key="contest-upcoming-{{ $contest->id }}"
                        class="border-line group rounded-xl border p-4 transition-colors hover:border-zinc-400 dark:hover:border-zinc-600"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <flux:badge color="blue" size="sm">{{ __('Upcoming') }}</flux:badge>
                            @if($contest->is_featured)
                                <flux:badge color="amber" size="sm">{{ __('Featured') }}</flux:badge>
                            @endif
                        </div>
                        <flux:heading size="sm" class="truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                            {{ $contest->title }}
                        </flux:heading>
                        <flux:text size="sm" class="mt-1 text-zinc-500">
                            {{ $contest->crosswords_count }} {{ __('puzzles') }}
                            &middot;
                            {{ $contest->entries_count }} {{ __('participants') }}
                        </flux:text>
                        <flux:text size="xs" class="mt-1.5 text-blue-600 dark:text-blue-400">
                            {{ __('Starts :time', ['time' => $contest->starts_at->diffForHumans()]) }}
                        </flux:text>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

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
                                {{ $crossword->displayTitle() }}
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
                                <div class="truncate text-sm font-medium text-fg">{{ $crossword->displayTitle() }}</div>
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
                                <div class="truncate text-sm font-medium text-fg">{{ $crossword->displayTitle() }}</div>
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
        <livewire:puzzle-discovery :limit="3" :exclude-attempted="true" />
    </div>

    {{-- Solving Streak --}}
    @if($this->currentStreak > 0 || $this->longestStreak > 0)
        <div @class([
            'relative overflow-hidden rounded-xl border p-5',
            'border-orange-200 bg-gradient-to-r from-orange-50 to-amber-50 dark:border-orange-800/50 dark:from-orange-950/30 dark:to-amber-950/20' => $this->streakIsActive,
            'border-line' => ! $this->streakIsActive,
        ]) data-test="dashboard-streak-card">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div @class([
                        'flex size-12 shrink-0 items-center justify-center rounded-xl',
                        'bg-orange-100 dark:bg-orange-900/50' => $this->streakIsActive,
                        'bg-zinc-100 dark:bg-zinc-800' => ! $this->streakIsActive,
                    ])>
                        <flux:icon name="fire" @class([
                            'size-6',
                            'text-orange-600 dark:text-orange-400' => $this->streakIsActive,
                            'text-zinc-500' => ! $this->streakIsActive,
                        ]) />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ __('Solving Streak') }}</flux:heading>
                            @if($this->streakIsActive)
                                <flux:badge size="sm" color="orange">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </div>
                        <flux:text size="sm" class="mt-0.5 text-zinc-600 dark:text-zinc-400">
                            @if($this->streakIsActive)
                                {{ trans_choice(':count day in a row!|:count days in a row!', $this->currentStreak) }}
                            @else
                                {{ __('Solve a puzzle today to start a new streak.') }}
                            @endif
                        </flux:text>
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div class="text-center">
                        <div @class([
                            'text-3xl font-bold tabular-nums',
                            'text-orange-600 dark:text-orange-400' => $this->streakIsActive,
                            'text-fg' => ! $this->streakIsActive,
                        ])>{{ $this->currentStreak }}</div>
                        <flux:text size="sm" class="text-zinc-500">{{ __('Current') }}</flux:text>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold tabular-nums text-fg">{{ $this->longestStreak }}</div>
                        <flux:text size="sm" class="text-zinc-500">{{ __('Best') }}</flux:text>
                    </div>
                    @if(! $this->streakIsActive)
                        <flux:button variant="primary" size="sm" :href="route('puzzles.index')" wire:navigate icon="play">
                            {{ __('Solve Now') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    @endif

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
