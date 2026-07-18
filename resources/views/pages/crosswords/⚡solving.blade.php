<?php

use App\Models\Contest;
use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\DailyPuzzle;
use App\Models\PuzzleAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Solving')] class extends Component {
    #[Url]
    public string $filter = '';

    #[Url]
    public string $sortBy = 'recent';

    #[Url]
    public string $search = '';

    #[Computed]
    public function attempts()
    {
        $query = Auth::user()
            ->puzzleAttempts()
            ->with(['crossword' => fn ($q) => $q->with('user')]);

        if ($this->filter === 'in_progress') {
            $query->where('is_completed', false);
        } elseif ($this->filter === 'completed') {
            $query->where('is_completed', true);
        }

        if ($this->search !== '') {
            $term = $this->search;
            $query->whereHas('crossword', fn ($q) => $q->whereLike('title', "%{$term}%"));
        }

        match ($this->sortBy) {
            'oldest' => $query->oldest('updated_at'),
            'fastest' => $query->where('is_completed', true)
                ->whereNotNull('solve_time_seconds')
                ->orderBy('solve_time_seconds', 'asc'),
            default => $query->latest('updated_at'),
        };

        return $query->get();
    }

    #[Computed]
    public function attemptCounts(): array
    {
        $base = Auth::user()->puzzleAttempts();

        if ($this->search !== '') {
            $term = $this->search;
            $base->whereHas('crossword', fn ($q) => $q->whereLike('title', "%{$term}%"));
        }

        return [
            'all' => (clone $base)->count(),
            'in_progress' => (clone $base)->where('is_completed', false)->count(),
            'completed' => (clone $base)->where('is_completed', true)->count(),
        ];
    }

    public function updatedFilter(): void
    {
        unset($this->attempts, $this->attemptCounts);
    }

    public function updatedSortBy(): void
    {
        unset($this->attempts);
    }

    public function updatedSearch(): void
    {
        unset($this->attempts, $this->attemptCounts);
    }

    public function removeAttempt(int $attemptId): void
    {
        $attempt = PuzzleAttempt::findOrFail($attemptId);

        Gate::authorize('delete', $attempt);

        $attempt->delete();
        unset($this->attempts, $this->attemptCounts);
    }

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

    #[Computed]
    public function solvedCount(): int
    {
        return Auth::user()->puzzleAttempts()->where('is_completed', true)->count();
    }

    #[Computed]
    public function likedCount(): int
    {
        return Auth::user()->crosswordLikes()->count();
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

    public function surpriseMe(): void
    {
        $query = Crossword::where('is_published', true)
            ->where('user_id', '!=', Auth::id())
            ->safeFor(Auth::user());

        $blockedTagIds = Auth::user()->blockedTags()->pluck('tags.id');

        if ($blockedTagIds->isNotEmpty()) {
            $query->whereDoesntHave('tags', fn ($q) => $q->whereIn('tags.id', $blockedTagIds));
        }

        $crossword = $query->inRandomOrder()->first();

        if (! $crossword) {
            return;
        }

        $this->redirect(route('crosswords.solver', $crossword), navigate: true);
    }
}
?>

<div class="space-y-8">
    {{-- My Attempts --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <x-dashboard-switch active="solve" />
            <div class="flex items-center gap-2">
                <flux:button
                    wire:click="surpriseMe"
                    variant="ghost"
                    size="sm"
                    icon="sparkles"
                    data-test="surprise-me-button"
                >
                    {{ __('Surprise Me') }}
                </flux:button>
                <flux:button variant="ghost" size="sm" :href="route('crosswords.stats')" wire:navigate icon="chart-bar">
                    {{ __('Stats') }}
                </flux:button>
            </div>
        </div>

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
                            <flux:button variant="filled" size="sm" :href="route('crosswords.solver', $dailyPuzzle)" wire:navigate.hover icon="eye">
                                {{ __('View Solution') }}
                            </flux:button>
                        @else
                            <flux:button variant="primary" size="sm" :href="route('crosswords.solver', $dailyPuzzle)" wire:navigate.hover icon="play">
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

        {{-- Filters --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:radio.group wire:model.live="filter" variant="segmented" size="sm">
                <flux:radio value="" label="{{ __('All') }} ({{ $this->attemptCounts['all'] }})" />
                <flux:radio value="in_progress" label="{{ __('In Progress') }} ({{ $this->attemptCounts['in_progress'] }})" />
                <flux:radio value="completed" label="{{ __('Completed') }} ({{ $this->attemptCounts['completed'] }})" />
            </flux:radio.group>

            <div class="flex items-center gap-2">
                <flux:input
                    icon="magnifying-glass"
                    placeholder="{{ __('Search puzzles...') }}"
                    wire:model.live.debounce.300ms="search"
                    size="sm"
                    class="w-48"
                />
                <flux:select wire:model.live="sortBy" size="sm" class="w-36">
                    <flux:select.option value="recent">{{ __('Recent') }}</flux:select.option>
                    <flux:select.option value="oldest">{{ __('Oldest') }}</flux:select.option>
                    <flux:select.option value="fastest">{{ __('Fastest') }}</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if($this->attempts->isEmpty())
            <div class="border-line-strong flex flex-col items-center justify-center rounded-xl border border-dashed py-12 px-6 text-center" data-test="solving-empty-state">
                <flux:icon name="puzzle-piece" class="mb-4 size-12 text-zinc-500" />
                <flux:heading size="lg" class="mb-2">
                    @if($search !== '' || $filter !== '')
                        {{ __('No matching puzzles') }}
                    @else
                        {{ __('No puzzles in progress') }}
                    @endif
                </flux:heading>
                <flux:text class="mb-4">
                    @if($search !== '' || $filter !== '')
                        {{ __('Try adjusting your filters or search terms.') }}
                    @else
                        {{ __('Browse the community catalog to find your first puzzle to solve.') }}
                    @endif
                </flux:text>
                @if($search === '' && $filter === '')
                    <flux:button variant="primary" icon="puzzle-piece" :href="route('puzzles.index')" wire:navigate.hover>
                        {{ __('Browse puzzles') }}
                    </flux:button>
                @endif
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($this->attempts as $attempt)
                    <div
                        wire:key="attempt-{{ $attempt->id }}"
                        class="border-line group relative rounded-xl border p-4 transition-colors hover:border-zinc-400 dark:hover:border-zinc-500"
                    >
                        <a href="{{ route('crosswords.solver', $attempt->crossword) }}" wire:navigate class="block">
                            <div class="mb-3 flex justify-center">
                                <x-grid-thumbnail :grid="$attempt->crossword->grid" :width="$attempt->crossword->width" :height="$attempt->crossword->height" />
                            </div>

                            <flux:heading size="sm" class="truncate">{{ $attempt->crossword->displayTitle() }}</flux:heading>
                            <flux:text size="sm" class="mt-1">
                                {{ __('by :author', ['author' => $attempt->crossword->user->name ?? __('Unknown')]) }}
                                &middot;
                                {{ $attempt->crossword->width }}&times;{{ $attempt->crossword->height }}
                            </flux:text>
                            @php($solveProgress = $attempt->is_completed ? 100 : $attempt->solveProgress())
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <div
                                        class="h-full rounded-full transition-all {{ $solveProgress === 100 ? 'bg-emerald-500' : ($solveProgress >= 50 ? 'bg-sky-500' : 'bg-zinc-400') }}"
                                        style="width: {{ $solveProgress }}%"
                                    ></div>
                                </div>
                                <span class="text-xs tabular-nums text-zinc-500">{{ $solveProgress }}%</span>
                            </div>
                            @php($avgSeconds = $attempt->is_completed ? ($attempt->crossword->cached_avg_solve_time ?? 0) : 0)
                            @php($solveTimeDiffPercent = ($attempt->is_completed && $attempt->solve_time_seconds && $avgSeconds > 0) ? (int) round((1 - $attempt->solve_time_seconds / $avgSeconds) * 100) : null)
                            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                @if($attempt->is_completed)
                                    <flux:badge size="sm" variant="solid" color="green">{{ __('Completed') }}</flux:badge>
                                    @if($attempt->formattedSolveTime())
                                        <flux:text size="sm" class="flex items-center gap-1 text-zinc-500">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            {{ $attempt->formattedSolveTime() }}
                                        </flux:text>
                                    @endif
                                @else
                                    <flux:badge size="sm" variant="solid" color="sky">{{ __('In Progress') }}</flux:badge>
                                @endif
                                @if($solveTimeDiffPercent !== null && $solveTimeDiffPercent !== 0)
                                    <flux:badge size="sm" variant="pill" color="{{ $solveTimeDiffPercent > 0 ? 'green' : 'amber' }}">
                                        {{ abs($solveTimeDiffPercent) }}% {{ $solveTimeDiffPercent > 0 ? __('faster') : __('slower') }}
                                    </flux:badge>
                                @endif
                                <flux:text size="sm" class="text-zinc-500">{{ $attempt->updated_at->diffForHumans() }}</flux:text>
                            </div>
                        </a>

                        <div class="absolute top-2 right-2 opacity-0 transition-opacity group-hover:opacity-100">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    <flux:menu.item icon="trash" variant="danger" wire:click="removeAttempt({{ $attempt->id }})" wire:confirm="{{ __('Remove this puzzle from your solving list?') }}">
                                        {{ __('Remove') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
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
                <flux:button variant="ghost" size="sm" :href="route('puzzles.index')" wire:navigate>
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
                <flux:button variant="ghost" size="sm" :href="route('puzzles.index')" wire:navigate>
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
                <flux:button variant="ghost" size="sm" :href="route('puzzles.index')" wire:navigate>
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

    {{-- Browse Published Puzzles --}}
    <div class="space-y-4">
        <livewire:puzzle-discovery :exclude-attempted="true" />
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
                        <flux:button variant="primary" size="sm" :href="route('puzzles.index')" wire:navigate.hover icon="play">
                            {{ __('Solve Now') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid gap-4 sm:grid-cols-2">
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
