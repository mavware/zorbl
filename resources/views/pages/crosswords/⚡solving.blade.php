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
            ->with(['crossword' => fn ($q) => $q->with('user.subscriptions')]);

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
        if (! config('crosswordbuilder.features.contests')) {
            return collect();
        }

        return Contest::active()
            ->withCount(['entries', 'crosswords'])
            ->latest('starts_at')
            ->limit(3)
            ->get();
    }

    #[Computed]
    public function upcomingContests()
    {
        if (! config('crosswordbuilder.features.contests')) {
            return collect();
        }

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
            ->with(['user:id,name', 'user.subscriptions'])
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
            ->with(['user:id,name', 'user.subscriptions'])
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
            ->with(['user:id,name', 'user.subscriptions'])
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
            <flux:heading size="xl">{{ __('Solve') }}</flux:heading>
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
            @php($dailySolved = $this->dailyPuzzleSolved)
            <div @class([
                'rounded-sm border p-[18px] transition-colors',
                'border-border' => $dailySolved,
                'border-amber-400/60' => ! $dailySolved,
            ])>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div @class([
                            'flex size-12 shrink-0 items-center justify-center rounded-sm border',
                            'border-border-strong text-ink-faint' => $dailySolved,
                            'border-amber-400 text-amber-400' => ! $dailySolved,
                        ])>
                            <flux:icon :name="$dailySolved ? 'check-circle' : 'star'" class="size-6" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Puzzle of the Day') }}</h2>
                                <span class="chip-classical border-ink-faint text-ink-faint">{{ today()->format('M j') }}</span>
                                @if($dailySolved)
                                    <span class="chip-classical border-amber-400 text-amber-400 gap-1">
                                        <flux:icon name="check-circle" class="size-3" />
                                        {{ __('Solved') }}
                                    </span>
                                @endif
                            </div>
                            <div class="meta-classical mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <span class="font-classical text-ink text-[17px] font-semibold normal-case tracking-normal">{{ $dailyPuzzle->displayTitle() }}</span>
                                <span aria-hidden="true">&middot;</span>
                                <span class="flex items-center gap-1">
                                    {{ __('by :author', ['author' => $dailyPuzzle->user->name ?? __('Unknown')]) }} <x-supporter-badge :user="$dailyPuzzle->user" />
                                </span>
                                <span aria-hidden="true">&middot;</span>
                                <span class="tnum whitespace-nowrap">{{ $dailyPuzzle->width }}&times;{{ $dailyPuzzle->height }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end">
                        @if($dailySolved)
                            <a href="{{ route('crosswords.solver', $dailyPuzzle) }}" wire:navigate.hover class="btn-classical btn-classical-muted">
                                <flux:icon name="eye" class="size-4" />
                                {{ __('View Solution') }}
                            </a>
                        @else
                            <a href="{{ route('crosswords.solver', $dailyPuzzle) }}" wire:navigate.hover class="btn-classical btn-amber-outline">
                                <flux:icon name="play" class="size-4" />
                                {{ __('Solve Today\'s Puzzle') }}
                            </a>
                        @endif
                        <a href="{{ route('puzzles.daily-history') }}" wire:navigate class="meta-classical hover:text-amber-300 transition-colors">
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
            <div class="border-border-strong flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-16 text-center" data-test="solving-empty-state">
                <flux:icon name="puzzle-piece" class="text-ink-faint mb-4 size-10" />
                <h3 class="font-classical text-ink text-[26px] leading-tight font-medium">
                    @if($search !== '' || $filter !== '')
                        {{ __('No matching puzzles') }}
                    @else
                        {{ __('No puzzles in progress') }}
                    @endif
                </h3>
                <p class="text-ink-muted mt-2 text-sm">
                    @if($search !== '' || $filter !== '')
                        {{ __('Try adjusting your filters or search terms.') }}
                    @else
                        {{ __('Browse the community catalog to find your first puzzle to solve.') }}
                    @endif
                </p>
                @if($search === '' && $filter === '')
                    <a href="{{ route('puzzles.index') }}" wire:navigate.hover class="btn-classical btn-amber-outline mt-6">
                        <flux:icon name="puzzle-piece" class="size-4" />
                        {{ __('Browse puzzles') }}
                    </a>
                @endif
            </div>
        @else
            <div class="grid gap-[22px] [grid-template-columns:repeat(auto-fill,minmax(268px,1fr))]">
                @foreach($this->attempts as $attempt)
                    <article
                        wire:key="attempt-{{ $attempt->id }}"
                        class="border-border hover:border-border-strong flex flex-col gap-3.5 rounded-sm border p-[18px] transition-colors"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-classical text-ink truncate text-[21px] leading-tight font-semibold">
                                    <a href="{{ route('crosswords.solver', $attempt->crossword) }}" wire:navigate class="hover:text-amber-300 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        {{ $attempt->crossword->displayTitle() }}
                                    </a>
                                </h3>
                                <div class="meta-classical mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span class="flex items-center gap-1">
                                        {{ __('by :author', ['author' => $attempt->crossword->user->name ?? __('Unknown')]) }} <x-supporter-badge :user="$attempt->crossword->user" />
                                    </span>
                                    <span aria-hidden="true">&middot;</span>
                                    <span class="tnum whitespace-nowrap">{{ $attempt->crossword->width }}&times;{{ $attempt->crossword->height }}</span>
                                </div>
                            </div>
                            @if($attempt->is_completed)
                                <span class="chip-classical border-amber-400 text-amber-400">{{ __('Completed') }}</span>
                            @else
                                <span class="chip-classical border-ink-faint text-ink-faint">{{ __('In Progress') }}</span>
                            @endif
                        </div>

                        <a href="{{ route('crosswords.solver', $attempt->crossword) }}" wire:navigate class="flex justify-center py-1 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                            <x-grid-thumbnail
                                :grid="$attempt->crossword->grid"
                                :width="$attempt->crossword->width"
                                :height="$attempt->crossword->height"
                                frame-class="border-hairline bg-hairline rounded-sm border"
                                open-class="bg-panel"
                                block-class="bg-zinc-300"
                            />
                        </a>

                        @php($solveProgress = $attempt->is_completed ? 100 : $attempt->solveProgress())
                        <div>
                            <div class="meta-classical flex items-center justify-between gap-2">
                                <span>{{ $solveProgress === 100 ? __('Complete') : __('Progress') }}</span>
                                <span class="font-classical text-ink tnum text-[15px] font-medium normal-case tracking-normal">{{ $solveProgress }}%</span>
                            </div>
                            <div class="bg-border mt-1.5 h-0.5 w-full overflow-hidden">
                                <div class="h-full bg-amber-400 transition-all" style="width: {{ $solveProgress }}%"></div>
                            </div>
                        </div>

                        @php($avgSeconds = $attempt->is_completed ? ($attempt->crossword->cached_avg_solve_time ?? 0) : 0)
                        @php($solveTimeDiffPercent = ($attempt->is_completed && $attempt->solve_time_seconds && $avgSeconds > 0) ? (int) round((1 - $attempt->solve_time_seconds / $avgSeconds) * 100) : null)
                        <div class="meta-classical flex flex-wrap items-center gap-x-3 gap-y-1">
                            @if($attempt->is_completed && $attempt->formattedSolveTime())
                                <span class="flex items-center gap-1 whitespace-nowrap">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $attempt->formattedSolveTime() }}</span>
                                </span>
                            @endif
                            @if($solveTimeDiffPercent !== null && $solveTimeDiffPercent !== 0)
                                <span class="chip-classical {{ $solveTimeDiffPercent > 0 ? 'border-amber-400 text-amber-400' : 'border-ink-faint text-ink-faint' }}">
                                    {{ abs($solveTimeDiffPercent) }}% {{ $solveTimeDiffPercent > 0 ? __('faster') : __('slower') }}
                                </span>
                            @endif
                            <span class="whitespace-nowrap">{{ $attempt->updated_at->diffForHumans() }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-2 pt-1">
                            <a href="{{ route('crosswords.solver', $attempt->crossword) }}" wire:navigate class="btn-classical btn-amber-outline">
                                {{ $attempt->is_completed ? __('Review') : __('Continue') }}
                            </a>
                            <flux:dropdown position="bottom" align="end">
                                <button type="button" class="btn-classical btn-classical-muted w-9 px-0" aria-label="{{ __('More actions') }}">
                                    <flux:icon name="ellipsis-vertical" class="size-4" />
                                </button>
                                <flux:menu>
                                    <flux:menu.item icon="trash" variant="danger" wire:click="removeAttempt({{ $attempt->id }})" wire:confirm="{{ __('Remove this puzzle from your solving list?') }}">
                                        {{ __('Remove') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Active & Upcoming Contests --}}
    @if($this->activeContests->isNotEmpty() || $this->upcomingContests->isNotEmpty())
        <div class="border-border rounded-sm border p-[18px]">
            <div class="border-hairline mb-5 flex items-center justify-between gap-3 border-b pb-3.5">
                <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Contests') }}</h2>
                <a href="{{ route('contests.index') }}" wire:navigate class="btn-classical btn-classical-muted h-8 px-3 text-[14px]">
                    {{ __('View All') }}
                </a>
            </div>

            <div class="grid gap-[22px] [grid-template-columns:repeat(auto-fill,minmax(268px,1fr))]">
                @foreach($this->activeContests as $contest)
                    <a
                        href="{{ route('contests.show', $contest) }}"
                        wire:navigate
                        wire:key="contest-active-{{ $contest->id }}"
                        class="border-border hover:border-border-strong group flex flex-col gap-3 rounded-sm border p-[18px] transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                    >
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="chip-classical border-amber-400 text-amber-400">{{ __('Active') }}</span>
                            @if($contest->is_featured)
                                <span class="chip-classical border-amber-400 text-amber-400">{{ __('Featured') }}</span>
                            @endif
                        </div>
                        <h3 class="font-classical text-ink group-hover:text-amber-300 truncate text-[21px] leading-tight font-semibold transition-colors">
                            {{ $contest->title }}
                        </h3>
                        <div class="meta-classical flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <span class="whitespace-nowrap"><span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $contest->crosswords_count }}</span> {{ __('puzzles') }}</span>
                            <span aria-hidden="true">&middot;</span>
                            <span class="whitespace-nowrap"><span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $contest->entries_count }}</span> {{ __('participants') }}</span>
                        </div>
                        @if($contest->ends_at->isFuture())
                            <div class="meta-classical text-amber-400">
                                {{ __('Ends :time', ['time' => $contest->ends_at->diffForHumans()]) }}
                            </div>
                        @endif
                    </a>
                @endforeach

                @foreach($this->upcomingContests as $contest)
                    <a
                        href="{{ route('contests.show', $contest) }}"
                        wire:navigate
                        wire:key="contest-upcoming-{{ $contest->id }}"
                        class="border-border hover:border-border-strong group flex flex-col gap-3 rounded-sm border p-[18px] transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                    >
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="chip-classical border-ink-faint text-ink-faint">{{ __('Upcoming') }}</span>
                            @if($contest->is_featured)
                                <span class="chip-classical border-amber-400 text-amber-400">{{ __('Featured') }}</span>
                            @endif
                        </div>
                        <h3 class="font-classical text-ink group-hover:text-amber-300 truncate text-[21px] leading-tight font-semibold transition-colors">
                            {{ $contest->title }}
                        </h3>
                        <div class="meta-classical flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <span class="whitespace-nowrap"><span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $contest->crosswords_count }}</span> {{ __('puzzles') }}</span>
                            <span aria-hidden="true">&middot;</span>
                            <span class="whitespace-nowrap"><span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $contest->entries_count }}</span> {{ __('participants') }}</span>
                        </div>
                        <div class="meta-classical">
                            {{ __('Starts :time', ['time' => $contest->starts_at->diffForHumans()]) }}
                        </div>
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
                <div class="border-border-strong flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-10 text-center">
                    <flux:icon name="clock" class="text-ink-faint mb-3 size-8" />
                    <p class="text-ink-muted text-sm">{{ __('No new puzzles from people you follow yet.') }}</p>
                </div>
            @else
                <div class="grid gap-[22px] [grid-template-columns:repeat(auto-fill,minmax(268px,1fr))]">
                    @foreach($this->followingPuzzles as $crossword)
                        <a
                            href="{{ route('crosswords.solver', $crossword) }}"
                            wire:navigate
                            class="border-border hover:border-border-strong group flex flex-col gap-3.5 rounded-sm border p-[18px] transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                        >
                            <div class="min-w-0">
                                <h3 class="font-classical text-ink group-hover:text-amber-300 truncate text-[21px] leading-tight font-semibold transition-colors">
                                    {{ $crossword->displayTitle() }}
                                </h3>
                                <div class="meta-classical mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span class="flex items-center gap-1">
                                        {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }} <x-supporter-badge :user="$crossword->user" />
                                    </span>
                                    <span aria-hidden="true">&middot;</span>
                                    <span class="tnum whitespace-nowrap">{{ $crossword->width }}&times;{{ $crossword->height }}</span>
                                </div>
                            </div>

                            <div class="flex justify-center py-1">
                                <x-grid-thumbnail
                                    :grid="$crossword->grid"
                                    :width="$crossword->width"
                                    :height="$crossword->height"
                                    :cell-size="5"
                                    :max-width="80"
                                    frame-class="border-hairline bg-hairline rounded-sm border"
                                    open-class="bg-panel"
                                    block-class="bg-zinc-300"
                                />
                            </div>

                            <div class="meta-classical flex flex-wrap items-center gap-x-4 gap-y-1">
                                <span class="flex items-center gap-1 whitespace-nowrap">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
                                    <span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $crossword->likes_count }}</span>
                                </span>
                                <span class="whitespace-nowrap">{{ $crossword->created_at->diffForHumans() }}</span>
                            </div>

                            <div class="pt-1">
                                <span class="btn-classical btn-amber-outline">{{ __('Solve') }}</span>
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
        <div class="border-border rounded-sm border p-[18px]">
            <div class="border-hairline mb-2 flex items-center justify-between gap-3 border-b pb-3.5">
                <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Trending') }}</h2>
                <a href="{{ route('puzzles.index') }}" wire:navigate class="btn-classical btn-classical-muted h-8 px-3 text-[14px]">
                    {{ __('Browse All') }}
                </a>
            </div>

            @if($this->trendingPuzzles->isEmpty())
                <div class="border-border-strong mt-3 flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-10 text-center">
                    <flux:icon name="fire" class="text-ink-faint mb-3 size-8" />
                    <p class="text-ink-muted text-sm">{{ __('No trending puzzles this week') }}</p>
                </div>
            @else
                <div class="divide-hairline divide-y">
                    @foreach($this->trendingPuzzles as $crossword)
                        <a
                            href="{{ route('crosswords.solver', $crossword) }}"
                            wire:navigate
                            class="group flex items-center gap-3.5 py-3 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                        >
                            <x-grid-thumbnail
                                class="shrink-0"
                                :grid="$crossword->grid"
                                :width="$crossword->width"
                                :height="$crossword->height"
                                :cell-size="5"
                                :max-width="48"
                                frame-class="border-hairline bg-hairline rounded-sm border"
                                open-class="bg-panel"
                                block-class="bg-zinc-300"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="font-classical text-ink group-hover:text-amber-300 truncate text-[17px] leading-tight font-semibold transition-colors">{{ $crossword->displayTitle() }}</div>
                                <div class="meta-classical mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span class="flex items-center gap-1">
                                        {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }} <x-supporter-badge :user="$crossword->user" />
                                    </span>
                                    <span aria-hidden="true">&middot;</span>
                                    <span class="flex items-center gap-1 whitespace-nowrap">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="inline size-3" viewBox="0 0 24 24" fill="currentColor"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
                                        <span class="font-classical text-ink tnum text-[14px] font-medium tracking-normal">{{ $crossword->likes_count }}</span>
                                    </span>
                                </div>
                            </div>
                            <flux:icon name="chevron-right" class="text-ink-faint group-hover:text-amber-300 size-4 shrink-0 transition-colors" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Newest --}}
        <div class="border-border rounded-sm border p-[18px]">
            <div class="border-hairline mb-2 flex items-center justify-between gap-3 border-b pb-3.5">
                <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Newest') }}</h2>
                <a href="{{ route('puzzles.index') }}" wire:navigate class="btn-classical btn-classical-muted h-8 px-3 text-[14px]">
                    {{ __('Browse All') }}
                </a>
            </div>

            @if($this->newestPuzzles->isEmpty())
                <div class="border-border-strong mt-3 flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-10 text-center">
                    <flux:icon name="sparkles" class="text-ink-faint mb-3 size-8" />
                    <p class="text-ink-muted text-sm">{{ __('No published puzzles yet') }}</p>
                </div>
            @else
                <div class="divide-hairline divide-y">
                    @foreach($this->newestPuzzles as $crossword)
                        <a
                            href="{{ route('crosswords.solver', $crossword) }}"
                            wire:navigate
                            class="group flex items-center gap-3.5 py-3 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                        >
                            <x-grid-thumbnail
                                class="shrink-0"
                                :grid="$crossword->grid"
                                :width="$crossword->width"
                                :height="$crossword->height"
                                :cell-size="5"
                                :max-width="48"
                                frame-class="border-hairline bg-hairline rounded-sm border"
                                open-class="bg-panel"
                                block-class="bg-zinc-300"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="font-classical text-ink group-hover:text-amber-300 truncate text-[17px] leading-tight font-semibold transition-colors">{{ $crossword->displayTitle() }}</div>
                                <div class="meta-classical mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span class="flex items-center gap-1">
                                        {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }} <x-supporter-badge :user="$crossword->user" />
                                    </span>
                                    <span aria-hidden="true">&middot;</span>
                                    <span class="whitespace-nowrap">{{ $crossword->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                            <flux:icon name="chevron-right" class="text-ink-faint group-hover:text-amber-300 size-4 shrink-0 transition-colors" />
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
            'rounded-sm border p-[18px] transition-colors',
            'border-amber-400/60' => $this->streakIsActive,
            'border-border' => ! $this->streakIsActive,
        ]) data-test="dashboard-streak-card">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div @class([
                        'flex size-12 shrink-0 items-center justify-center rounded-sm border',
                        'border-amber-400 text-amber-400' => $this->streakIsActive,
                        'border-border-strong text-ink-faint' => ! $this->streakIsActive,
                    ])>
                        <flux:icon name="fire" class="size-6" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Solving Streak') }}</h2>
                            @if($this->streakIsActive)
                                <span class="chip-classical border-amber-400 text-amber-400">{{ __('Active') }}</span>
                            @else
                                <span class="chip-classical border-ink-faint text-ink-faint">{{ __('Inactive') }}</span>
                            @endif
                        </div>
                        <p class="text-ink-muted mt-1 text-sm">
                            @if($this->streakIsActive)
                                {{ trans_choice(':count day in a row!|:count days in a row!', $this->currentStreak) }}
                            @else
                                {{ __('Solve a puzzle today to start a new streak.') }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div class="text-center">
                        <div @class([
                            'font-classical tnum text-[32px] leading-none font-medium',
                            'text-amber-400' => $this->streakIsActive,
                            'text-ink' => ! $this->streakIsActive,
                        ])>{{ $this->currentStreak }}</div>
                        <div class="meta-classical mt-2">{{ __('Current') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="font-classical text-ink tnum text-[32px] leading-none font-medium">{{ $this->longestStreak }}</div>
                        <div class="meta-classical mt-2">{{ __('Best') }}</div>
                    </div>
                    @if(! $this->streakIsActive)
                        <a href="{{ route('puzzles.index') }}" wire:navigate.hover class="btn-classical btn-amber-outline">
                            <flux:icon name="play" class="size-4" />
                            {{ __('Solve Now') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="border-border grid rounded-sm border sm:grid-cols-2">
        {{-- Puzzles Solved --}}
        <div class="border-hairline flex items-center gap-4 border-b p-[18px] sm:border-e sm:border-b-0">
            <div class="border-border-strong text-ink-faint flex size-10 shrink-0 items-center justify-center rounded-sm border">
                <flux:icon name="check-circle" class="size-5" />
            </div>
            <div>
                <div class="meta-classical">{{ __('Solved') }}</div>
                <div class="font-classical text-ink tnum text-[30px] leading-none font-medium">{{ $this->solvedCount }}</div>
            </div>
        </div>

        {{-- Likes Given --}}
        <div class="flex items-center gap-4 p-[18px]">
            <div class="border-border-strong text-ink-faint flex size-10 shrink-0 items-center justify-center rounded-sm border">
                <flux:icon name="heart" class="size-5" />
            </div>
            <div>
                <div class="meta-classical">{{ __('Liked') }}</div>
                <div class="font-classical text-ink tnum text-[30px] leading-none font-medium">{{ $this->likedCount }}</div>
            </div>
        </div>
    </div>

    {{-- Community Stats --}}
    <div class="border-border rounded-sm border p-[18px]">
        <div class="border-hairline mb-5 border-b pb-3.5">
            <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Community') }}</h2>
        </div>
        <div class="divide-hairline grid divide-y sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            <div class="py-3 text-center sm:py-1">
                <div class="font-classical text-ink tnum text-[32px] leading-none font-medium">{{ $this->totalPublishedPuzzles }}</div>
                <div class="meta-classical mt-2">{{ __('Published Puzzles') }}</div>
            </div>
            <div class="py-3 text-center sm:py-1">
                <div class="font-classical text-ink tnum text-[32px] leading-none font-medium">{{ $this->totalSolves }}</div>
                <div class="meta-classical mt-2">{{ __('Total Solves') }}</div>
            </div>
            <div class="py-3 text-center sm:py-1">
                <div class="font-classical text-ink tnum text-[32px] leading-none font-medium">{{ $this->totalLikes }}</div>
                <div class="meta-classical mt-2">{{ __('Total Likes') }}</div>
            </div>
        </div>
    </div>
</div>
