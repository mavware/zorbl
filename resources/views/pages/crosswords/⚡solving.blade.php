<?php

use App\Models\Contest;
use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\DailyPuzzle;
use App\Models\PuzzleAttempt;
use Illuminate\Support\Facades\Auth;
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
}
?>

<div class="space-y-8">
    {{-- My Attempts --}}
    <div class="space-y-4">
        <x-page-header :kicker="__('Puzzles you\'re solving')" :title="__('Solve')">
            <x-header-button icon="magnifying-glass" :href="route('crosswords.discover')" wire:navigate data-test="discover-puzzles-button">
                {{ __('Discover Puzzles') }}
            </x-header-button>
            @unless (auth()->user()->isAnonymous())
                <x-header-button variant="secondary" icon="chart-bar" :href="route('crosswords.stats')" wire:navigate data-test="solving-stats-button">
                    {{ __('Stats') }}
                </x-header-button>
            @endunless
        </x-page-header>

        {{-- Puzzle of the Day --}}
{{--        @if($dailyPuzzle = $this->dailyPuzzle)--}}
{{--            <x-daily-puzzle-banner :crossword="$dailyPuzzle" :solved="$this->dailyPuzzleSolved" />--}}
{{--        @endif--}}

        {{-- Filters --}}
        {{-- Same controls as the Build page's search row: search and sort
             together on the left, the status filter on the right. --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" data-test="attempt-filters">
            <div class="flex items-center gap-2">
                <label class="relative max-sm:flex-auto sm:w-48">
                    <span class="sr-only">{{ __('Search puzzles...') }}</span>
                    <flux:icon name="magnifying-glass" class="text-ink-faint pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                    <input
                        type="search"
                        placeholder="{{ __('Search puzzles...') }}"
                        wire:model.live.debounce.300ms="search"
                        class="field-classical w-full pr-3 pl-9"
                    />
                </label>
                <label class="relative max-sm:flex-auto sm:w-36">
                    <span class="sr-only">{{ __('Sort') }}</span>
                    <select wire:model.live="sortBy" class="field-classical font-classical w-full appearance-none pr-9 pl-3.5 text-[15px] font-medium">
                        <option value="recent">{{ __('Recent') }}</option>
                        <option value="oldest">{{ __('Oldest') }}</option>
                        <option value="fastest">{{ __('Fastest') }}</option>
                    </select>
                    <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                </label>
            </div>

            <div class="border-border-strong divide-hairline flex h-10 divide-x overflow-hidden rounded-sm border max-sm:flex-auto" role="radiogroup" aria-label="{{ __('Status') }}">
                @foreach (['' => __('All').' ('.$this->attemptCounts['all'].')', 'in_progress' => __('In Progress').' ('.$this->attemptCounts['in_progress'].')', 'completed' => __('Completed').' ('.$this->attemptCounts['completed'].')'] as $value => $label)
                    <label class="cursor-pointer max-sm:flex-auto">
                        <input type="radio" name="filter" value="{{ $value }}" wire:model.live="filter" class="peer sr-only" />
                        <span class="font-classical text-ink-muted hover:text-ink peer-checked:bg-zinc-800 peer-checked:text-white dark:peer-checked:bg-zinc-700 peer-focus-visible:outline-2 peer-focus-visible:-outline-offset-2 peer-focus-visible:outline-amber-400 tnum flex h-full items-center justify-center px-3.5 text-[15px] font-medium whitespace-nowrap transition-colors max-sm:px-2">
                            {{ $label }}
                        </span>
                    </label>
                @endforeach
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
            <div data-test="attempt-results">
                {{-- Styled like the Build page results: below `sm` the grid holds
                     three compact cards per row rather than one full card. --}}
                <div class="grid gap-2 max-sm:[grid-template-columns:repeat(3,minmax(0,1fr))] sm:gap-[22px] sm:[grid-template-columns:repeat(auto-fill,minmax(268px,1fr))]" data-test="attempt-results-grid">
                @foreach($this->attempts as $attempt)
                    @php($solveProgress = $attempt->is_completed ? 100 : $attempt->solveProgress())
                    @php($avgSeconds = $attempt->is_completed ? ($attempt->crossword->cached_avg_solve_time ?? 0) : 0)
                    @php($solveTimeDiffPercent = ($attempt->is_completed && $attempt->solve_time_seconds && $avgSeconds > 0) ? (int) round((1 - $attempt->solve_time_seconds / $avgSeconds) * 100) : null)
                    <article
                        wire:key="attempt-{{ $attempt->id }}"
                        class="border-border hover:border-border-strong flex min-w-0 flex-col gap-2 rounded-sm border p-2 transition-colors sm:gap-3.5 sm:p-4.5"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-classical text-ink flex min-w-0 items-center gap-1.5 text-[15px] leading-[1.15] font-semibold sm:text-[21px]">
                                    @if($attempt->is_completed)
                                        {{-- Phones drop the badge for space; a dot keeps the status visible. --}}
                                        <span class="size-1.5 shrink-0 rounded-full bg-amber-400 sm:hidden" title="{{ __('Completed') }}" data-test="completed-dot"></span>
                                    @endif
                                    <a href="{{ route('crosswords.solver', $attempt->crossword) }}" wire:navigate class="truncate hover:text-amber-300 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        {{ $attempt->crossword->displayTitle() }}
                                    </a>
                                </h3>
                                {{-- Same meta line as x-puzzle-details, with the constructor in place of the edit date. --}}
                                <div class="text-ink-faint tnum mt-1.5 flex min-w-0 items-center gap-x-2 overflow-hidden text-[12px] whitespace-nowrap label-classical [--label-tracking:0.06em]">
                                    <flux:tooltip :content="$attempt->crossword->puzzle_type->label()">
                                        <flux:icon :name="$attempt->crossword->puzzle_type->icon()" class="size-3 shrink-0"/>
                                    </flux:tooltip>
                                    <span class="shrink-0">{{ $attempt->crossword->width }}&times;{{ $attempt->crossword->height }}</span>
                                    <span aria-hidden="true" class="shrink-0">&middot;</span>
                                    <span class="flex min-w-0 items-center gap-1">
                                        <span class="truncate">{{ __('by :author', ['author' => $attempt->crossword->user->name ?? __('Unknown')]) }}</span>
                                        <x-supporter-badge :user="$attempt->crossword->user" />
                                    </span>
                                </div>
                            </div>
                            @if($attempt->is_completed)
                                <span class="badge-classical border-amber-400 text-amber-400 max-sm:hidden!">{{ __('Completed') }}</span>
                            @else
                                <span class="badge-classical border-ink-faint text-ink-faint max-sm:hidden!">{{ __('In Progress') }}</span>
                            @endif
                        </div>

                        <a href="{{ route('crosswords.solver', $attempt->crossword) }}" wire:navigate class="block focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                            <x-grid-thumbnail
                                :grid="$attempt->crossword->grid"
                                :width="$attempt->crossword->width"
                                :height="$attempt->crossword->height"
                                :fluid="true"
                                frame-class="border-hairline bg-hairline rounded-sm border"
                                open-class="bg-panel"
                                block-class="bg-zinc-300"
                            />
                        </a>

                        {{-- Same bar as x-puzzle-completeness-bar, tracking solve progress. --}}
                        <div>
                            <div class="meta-classical tnum mb-[7px] flex items-center justify-between gap-2">
                                <span class="max-sm:hidden">{{ $solveProgress === 100 ? __('Complete') : __('Progress') }}</span>
                                <span class="max-sm:ml-auto">{{ $solveProgress }}%</span>
                            </div>
                            <div class="bg-border h-[2px] w-full overflow-hidden">
                                <div class="h-full bg-amber-400" style="width: {{ $solveProgress }}%"></div>
                            </div>
                        </div>

                        <div class="meta-classical flex flex-wrap items-center gap-x-3 gap-y-1 max-sm:hidden">
                            @if($attempt->is_completed && $attempt->formattedSolveTime())
                                <span class="flex items-center gap-1 whitespace-nowrap">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $attempt->formattedSolveTime() }}</span>
                                </span>
                            @endif
                            @if($solveTimeDiffPercent !== null && $solveTimeDiffPercent !== 0)
                                <span class="badge-classical {{ $solveTimeDiffPercent > 0 ? 'border-amber-400 text-amber-400' : 'border-ink-faint text-ink-faint' }}">
                                    {{ abs($solveTimeDiffPercent) }}% {{ $solveTimeDiffPercent > 0 ? __('faster') : __('slower') }}
                                </span>
                            @endif
                            <span class="whitespace-nowrap">{{ $attempt->updated_at->diffForHumans() }}</span>
                        </div>

                        <div class="flex gap-[10px] pt-1">
                            <a href="{{ route('crosswords.solver', $attempt->crossword) }}" wire:navigate class="btn-classical btn-classical-compact btn-amber-outline flex-1 max-sm:hidden!">
                                {{ $attempt->is_completed ? __('Review') : __('Continue') }}
                            </a>
                            <flux:dropdown position="bottom" align="end" class="max-sm:flex-1">
                                <button type="button" class="btn-classical btn-classical-compact btn-classical-muted max-sm:w-full max-sm:py-1.5!" aria-label="{{ __('More actions') }}">
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

                <div class="mt-5">
                    <span class="meta-classical tnum" data-test="attempt-results-count">{{ trans_choice('Showing :count puzzle|Showing :count puzzles', $this->attempts->count(), ['count' => $this->attempts->count()]) }}</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Active & Upcoming Contests --}}
    @if($this->activeContests->isNotEmpty() || $this->upcomingContests->isNotEmpty())
        <div class="border-border rounded-sm border p-4.5">
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
                        class="border-border hover:border-border-strong group flex flex-col gap-3 rounded-sm border p-4.5 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
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
                        class="border-border hover:border-border-strong group flex flex-col gap-3 rounded-sm border p-4.5 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
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
                            class="border-border hover:border-border-strong group flex flex-col gap-3.5 rounded-sm border p-4.5 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
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

{{--    --}}{{-- Trending & Newest --}}
{{--    <div class="grid gap-6 lg:grid-cols-2">--}}
{{--        --}}{{-- Trending --}}
{{--        <div class="border-border rounded-sm border p-4.5">--}}
{{--            <div class="border-hairline mb-2 flex items-center justify-between gap-3 border-b pb-3.5">--}}
{{--                <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Trending') }}</h2>--}}
{{--                <a href="{{ route('puzzles.index') }}" wire:navigate class="btn-classical btn-classical-muted h-8 px-3 text-[14px]">--}}
{{--                    {{ __('Browse All') }}--}}
{{--                </a>--}}
{{--            </div>--}}

{{--            @if($this->trendingPuzzles->isEmpty())--}}
{{--                <div class="border-border-strong mt-3 flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-10 text-center">--}}
{{--                    <flux:icon name="fire" class="text-ink-faint mb-3 size-8" />--}}
{{--                    <p class="text-ink-muted text-sm">{{ __('No trending puzzles this week') }}</p>--}}
{{--                </div>--}}
{{--            @else--}}
{{--                <div class="divide-hairline divide-y">--}}
{{--                    @foreach($this->trendingPuzzles as $crossword)--}}
{{--                        <a--}}
{{--                            href="{{ route('crosswords.solver', $crossword) }}"--}}
{{--                            wire:navigate--}}
{{--                            class="group flex items-center gap-3.5 py-3 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"--}}
{{--                        >--}}
{{--                            <x-grid-thumbnail--}}
{{--                                class="shrink-0"--}}
{{--                                :grid="$crossword->grid"--}}
{{--                                :width="$crossword->width"--}}
{{--                                :height="$crossword->height"--}}
{{--                                :cell-size="5"--}}
{{--                                :max-width="48"--}}
{{--                                frame-class="border-hairline bg-hairline rounded-sm border"--}}
{{--                                open-class="bg-panel"--}}
{{--                                block-class="bg-zinc-300"--}}
{{--                            />--}}
{{--                            <div class="min-w-0 flex-1">--}}
{{--                                <div class="font-classical text-ink group-hover:text-amber-300 truncate text-[17px] leading-tight font-semibold transition-colors">{{ $crossword->displayTitle() }}</div>--}}
{{--                                <div class="meta-classical mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">--}}
{{--                                    <span class="flex items-center gap-1">--}}
{{--                                        {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }} <x-supporter-badge :user="$crossword->user" />--}}
{{--                                    </span>--}}
{{--                                    <span aria-hidden="true">&middot;</span>--}}
{{--                                    <span class="flex items-center gap-1 whitespace-nowrap">--}}
{{--                                        <svg xmlns="http://www.w3.org/2000/svg" class="inline size-3" viewBox="0 0 24 24" fill="currentColor"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>--}}
{{--                                        <span class="font-classical text-ink tnum text-[14px] font-medium tracking-normal">{{ $crossword->likes_count }}</span>--}}
{{--                                    </span>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                            <flux:icon name="chevron-right" class="text-ink-faint group-hover:text-amber-300 size-4 shrink-0 transition-colors" />--}}
{{--                        </a>--}}
{{--                    @endforeach--}}
{{--                </div>--}}
{{--            @endif--}}
{{--        </div>--}}

{{--        --}}{{-- Newest --}}
{{--        <div class="border-border rounded-sm border p-4.5">--}}
{{--            <div class="border-hairline mb-2 flex items-center justify-between gap-3 border-b pb-3.5">--}}
{{--                <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Newest') }}</h2>--}}
{{--                <a href="{{ route('puzzles.index') }}" wire:navigate class="btn-classical btn-classical-muted h-8 px-3 text-[14px]">--}}
{{--                    {{ __('Browse All') }}--}}
{{--                </a>--}}
{{--            </div>--}}

{{--            @if($this->newestPuzzles->isEmpty())--}}
{{--                <div class="border-border-strong mt-3 flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-10 text-center">--}}
{{--                    <flux:icon name="sparkles" class="text-ink-faint mb-3 size-8" />--}}
{{--                    <p class="text-ink-muted text-sm">{{ __('No published puzzles yet') }}</p>--}}
{{--                </div>--}}
{{--            @else--}}
{{--                <div class="divide-hairline divide-y">--}}
{{--                    @foreach($this->newestPuzzles as $crossword)--}}
{{--                        <a--}}
{{--                            href="{{ route('crosswords.solver', $crossword) }}"--}}
{{--                            wire:navigate--}}
{{--                            class="group flex items-center gap-3.5 py-3 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"--}}
{{--                        >--}}
{{--                            <x-grid-thumbnail--}}
{{--                                class="shrink-0"--}}
{{--                                :grid="$crossword->grid"--}}
{{--                                :width="$crossword->width"--}}
{{--                                :height="$crossword->height"--}}
{{--                                :cell-size="5"--}}
{{--                                :max-width="48"--}}
{{--                                frame-class="border-hairline bg-hairline rounded-sm border"--}}
{{--                                open-class="bg-panel"--}}
{{--                                block-class="bg-zinc-300"--}}
{{--                            />--}}
{{--                            <div class="min-w-0 flex-1">--}}
{{--                                <div class="font-classical text-ink group-hover:text-amber-300 truncate text-[17px] leading-tight font-semibold transition-colors">{{ $crossword->displayTitle() }}</div>--}}
{{--                                <div class="meta-classical mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">--}}
{{--                                    <span class="flex items-center gap-1">--}}
{{--                                        {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }} <x-supporter-badge :user="$crossword->user" />--}}
{{--                                    </span>--}}
{{--                                    <span aria-hidden="true">&middot;</span>--}}
{{--                                    <span class="whitespace-nowrap">{{ $crossword->created_at->diffForHumans() }}</span>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                            <flux:icon name="chevron-right" class="text-ink-faint group-hover:text-amber-300 size-4 shrink-0 transition-colors" />--}}
{{--                        </a>--}}
{{--                    @endforeach--}}
{{--                </div>--}}
{{--            @endif--}}
{{--        </div>--}}
{{--    </div>--}}

</div>
