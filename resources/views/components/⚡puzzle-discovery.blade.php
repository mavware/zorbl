<?php

use App\Models\Crossword;
use App\Models\DailyPuzzle;
use App\Models\Tag;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';

    public string $gridSize = '';

    public string $puzzleType = '';

    public string $constructor = '';

    public string $dateRange = '';

    public string $difficulty = '';

    public string $tag = '';

    public string $minRating = '';

    public string $sortBy = 'trending';

    /**
     * Only sync filter properties to the URL when used as a standalone component (limit=0).
     * When embedded with a limit (e.g. on the dashboard), URL syncing is disabled to
     * avoid conflicts with the parent page component.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function queryString(): array
    {
        if ($this->limit > 0) {
            return [];
        }

        return [
            'search' => ['except' => ''],
            'gridSize' => ['except' => ''],
            'puzzleType' => ['except' => ''],
            'constructor' => ['except' => ''],
            'dateRange' => ['except' => ''],
            'difficulty' => ['except' => ''],
            'tag' => ['except' => ''],
            'minRating' => ['except' => ''],
            'sortBy' => ['except' => 'trending'],
        ];
    }

    public int $limit = 0;

    public bool $excludeAttempted = false;

    public bool $excludeOwn = false;

    public bool $showFilters = false;

    public function mount(
        int $limit = 0,
        bool $excludeAttempted = false,
        bool $excludeOwn = false,
    ): void {
        $this->limit = $limit;
        $this->excludeAttempted = $excludeAttempted;
        $this->excludeOwn = $excludeOwn;
    }

    #[Computed]
    public function dailyPuzzle(): ?Crossword
    {
        return DailyPuzzle::todayOrAuto();
    }

    /**
     * The daily puzzle is pinned above the list only in the default view:
     * any narrowing filter or a non-default sort would make a fixed first
     * slot misleading.
     */
    #[Computed]
    public function pinnedDailyPuzzle(): ?Crossword
    {
        if ($this->hasActiveFilters() || $this->sortBy !== 'trending') {
            return null;
        }

        return $this->dailyPuzzle;
    }

    #[Computed]
    public function puzzles()
    {
        $query = Crossword::where('is_published', true)
            ->safeFor(Auth::user())
            ->with('user:id,name', 'user.subscriptions', 'tags:id,name,slug')
            ->withCount('likes')
            ->withAvg('comments as avg_rating', 'rating');

        if ($pinnedId = $this->pinnedDailyPuzzle?->id) {
            $query->where('id', '!=', $pinnedId);
        }

        $hasExplicitFilters = $this->search !== '' || $this->constructor !== '';

        if ($this->excludeOwn && ! $hasExplicitFilters && Auth::check()) {
            $query->where('user_id', '!=', Auth::id());
        }

        if ($this->excludeAttempted && ! $hasExplicitFilters && Auth::check()) {
            $attemptedIds = Auth::user()
                ->puzzleAttempts()
                ->pluck('crossword_id');
            $query->whereNotIn('id', $attemptedIds);
        }

        if ($this->search !== '') {
            $term = $this->search;
            $query->where(function ($q) use ($term) {
                $q->whereLike('title', "%{$term}%")
                    ->orWhereLike('author', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->whereLike('name', "%{$term}%"));
            });
        }

        if ($this->constructor !== '') {
            $term = $this->constructor;
            $query->where(function ($q) use ($term) {
                $q->whereLike('author', "%{$term}%")
                    ->orWhereHas('user', fn ($q) => $q->whereLike('name', "%{$term}%"));
            });
        }

        if ($this->gridSize !== '') {
            match ($this->gridSize) {
                'small' => $query->where('width', '<=', 10)->where('height', '<=', 10),
                'medium' => $query->where('width', '>', 10)->where('width', '<=', 17)
                    ->where('height', '>', 10)->where('height', '<=', 17),
                'large' => $query->where(function ($q) {
                    $q->where('width', '>', 17)->orWhere('height', '>', 17);
                }),
                default => null,
            };
        }

        if ($this->puzzleType !== '') {
            $query->where('puzzle_type', $this->puzzleType);
        }

        if ($this->difficulty !== '') {
            $query->where('difficulty_label', $this->difficulty);
        }

        if ($this->tag !== '') {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $this->tag));
        }

        if ($this->minRating !== '') {
            $min = (int) $this->minRating;
            $query->whereRaw(
                '(SELECT AVG(rating) FROM puzzle_comments WHERE puzzle_comments.crossword_id = crosswords.id) >= ?',
                [$min]
            );
        }

        if (Auth::check()) {
            $blockedTagIds = Auth::user()->blockedTags()->pluck('tags.id');

            if ($blockedTagIds->isNotEmpty()) {
                $query->whereDoesntHave('tags', fn ($q) => $q->whereIn('tags.id', $blockedTagIds));
            }
        }

        if ($this->dateRange !== '') {
            match ($this->dateRange) {
                'today' => $query->whereDate('created_at', today()),
                'week' => $query->where('created_at', '>=', now()->subWeek()),
                'month' => $query->where('created_at', '>=', now()->subMonth()),
                'year' => $query->where('created_at', '>=', now()->subYear()),
                default => null,
            };
        }

        match ($this->sortBy) {
            'trending' => $this->orderByTrending($query),
            'oldest' => $query->oldest(),
            'most_liked' => $query->orderByDesc('likes_count'),
            'most_solved' => $query->orderByDesc('cached_completed_count'),
            'highest_rated' => $query->orderByDesc('avg_rating'),
            'most_played' => $query->orderByDesc('cached_attempts_count'),
            'largest' => $query->orderByRaw('width * height DESC'),
            'smallest' => $query->orderByRaw('width * height ASC'),
            default => $query->latest(),
        };

        if ($this->limit > 0) {
            return $query->limit($this->limit)->get();
        }

        return $query->paginate(18);
    }

    /**
     * Rank puzzles by solves started and likes received in the past week, so
     * the ones people are playing right now come first. Everything with no
     * recent activity ties at zero and falls back to newest-first, which is
     * what makes the list read as "trending, then new".
     *
     * Correlated subqueries (rather than withCount aliases) keep the ORDER BY
     * portable across SQLite, MySQL and Postgres.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Crossword>  $query
     */
    protected function orderByTrending($query): void
    {
        $since = now()->subWeek();

        $query->orderByRaw(
            '((SELECT COUNT(*) FROM puzzle_attempts WHERE puzzle_attempts.crossword_id = crosswords.id AND puzzle_attempts.created_at >= ?)'
            .' + (SELECT COUNT(*) FROM crossword_likes WHERE crossword_likes.crossword_id = crosswords.id AND crossword_likes.created_at >= ?)) DESC',
            [$since, $since]
        )->latest();
    }

    /** @return array<int, bool> */
    #[Computed]
    public function likedIds(): array
    {
        if (! Auth::check()) {
            return [];
        }

        return Auth::user()
            ->crosswordLikes()
            ->pluck('crossword_id')
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    /** @return array<int, bool> */
    #[Computed]
    public function solvedIds(): array
    {
        if (! Auth::check()) {
            return [];
        }

        return Auth::user()
            ->puzzleAttempts()
            ->where('is_completed', true)
            ->pluck('crossword_id')
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    public function updatedDifficulty(): void
    {
        $this->resetPage();
    }

    /**
     * Reset every narrowing filter. The sort is left as-is: it is not a
     * filter (see hasActiveFilters) and the user chose it independently.
     */
    public function clearFilters(): void
    {
        $this->reset('search', 'gridSize', 'puzzleType', 'constructor', 'dateRange', 'difficulty', 'tag', 'minRating');
        $this->resetPage();
        unset($this->puzzles);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedGridSize(): void
    {
        $this->resetPage();
    }

    public function updatedPuzzleType(): void
    {
        $this->resetPage();
    }

    public function updatedConstructor(): void
    {
        $this->resetPage();
    }

    public function updatedDateRange(): void
    {
        $this->resetPage();
    }

    public function updatedTag(): void
    {
        $this->resetPage();
    }

    public function updatedMinRating(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Tag> */
    #[Computed]
    public function allTags(): \Illuminate\Database\Eloquent\Collection
    {
        return Tag::orderBy('name')->get(['id', 'name', 'slug']);
    }

    /**
     * Whether any narrowing filter is set. Sorting is deliberately excluded:
     * it reorders results without hiding any, so it should not surface the
     * Clear All button or the "adjust your filters" empty state.
     */
    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->activeFilterCount() > 0;
    }

    /**
     * How many of the collapsible filters are set. Search is excluded because
     * it is always visible in the toolbar; this count is what the badge on
     * the Filters button shows so hidden filters are never a surprise.
     */
    public function activeFilterCount(): int
    {
        return count(array_filter([
            $this->difficulty,
            $this->gridSize,
            $this->puzzleType,
            $this->constructor,
            $this->dateRange,
            $this->tag,
            $this->minRating,
        ], fn (string $value): bool => $value !== ''));
    }

}
?>

<div class="@container space-y-4">

    {{-- Search + sort + filter toggles: stacked on phones, wrapping row on tablets, one line on desktop --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center lg:flex-nowrap">
        <label class="relative min-w-2/5 sm:flex-1 sm:basis-64">
            <span class="sr-only">{{ __('Search by title or constructor...') }}</span>
            <flux:icon name="magnifying-glass" class="text-ink-faint pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
            <input
                type="search"
                placeholder="{{ __('Search title or constructor...') }}"
                wire:model.live.debounce.300ms="search"
                class="field-classical w-full pr-3 pl-9"
            />
        </label>

        <flux:input.group class="group-classical sm:max-w-52">
            <flux:input.group.prefix>{{ __('Sort by') }}</flux:input.group.prefix>
            <flux:select wire:model.live="sortBy" aria-label="{{ __('Sort by') }}">
                <flux:select.option value="trending">{{ __('Trending') }}</flux:select.option>
                <flux:select.option value="newest">{{ __('Newest') }}</flux:select.option>
                <flux:select.option value="oldest">{{ __('Oldest') }}</flux:select.option>
                <flux:select.option value="most_liked">{{ __('Most Liked') }}</flux:select.option>
                <flux:select.option value="most_solved">{{ __('Most Solved') }}</flux:select.option>
                <flux:select.option value="highest_rated">{{ __('Highest Rated') }}</flux:select.option>
                <flux:select.option value="most_played">{{ __('Most Played') }}</flux:select.option>
                <flux:select.option value="largest">{{ __('Largest') }}</flux:select.option>
                <flux:select.option value="smallest">{{ __('Smallest') }}</flux:select.option>
            </flux:select>
        </flux:input.group>

        <div class="flex items-center gap-2 sm:ml-auto sm:shrink-0">
            <button
                type="button"
                class="btn-classical h-10 {{ $showFilters ? 'btn-amber-outline' : 'btn-classical-muted' }}"
                wire:click="$toggle('showFilters')"
            >
                <flux:icon name="adjustments-horizontal" class="size-4" />
                {{ __('Filters') }}
                @if(($activeFilterCount = $this->activeFilterCount()) > 0)
                    <span class="font-classical tnum inline-flex size-5 items-center justify-center rounded-full bg-amber-400 text-[11px] font-semibold text-zinc-900" data-test="filters-count-badge">{{ $activeFilterCount }}</span>
                @endif
            </button>
        </div>
    </div>

    {{-- Secondary Filters (collapsible) --}}
    @if($showFilters)
        <div class="border-border grid gap-4 rounded-sm border p-[18px] sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="meta-classical mb-1.5 block">{{ __('Difficulty') }}</span>
                <span class="relative block">
                    <select wire:model.live="difficulty" class="field-classical w-full appearance-none pr-9 pl-3.5">
                        <option value="">{{ __('Any Difficulty') }}</option>
                        <option value="Easy">{{ __('Easy') }}</option>
                        <option value="Medium">{{ __('Medium') }}</option>
                        <option value="Hard">{{ __('Hard') }}</option>
                        <option value="Expert">{{ __('Expert') }}</option>
                    </select>
                    <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                </span>
            </label>

            <label class="block">
                <span class="meta-classical mb-1.5 block">{{ __('Size') }}</span>
                <span class="relative block">
                    <select wire:model.live="gridSize" class="field-classical w-full appearance-none pr-9 pl-3.5">
                        <option value="">{{ __('Any Size') }}</option>
                        <option value="small">{{ __('Small') }}</option>
                        <option value="medium">{{ __('Medium') }}</option>
                        <option value="large">{{ __('Large') }}</option>
                    </select>
                    <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                </span>
            </label>

            <label class="block">
                <span class="meta-classical mb-1.5 block">{{ __('Type') }}</span>
                <span class="relative block">
                    <select wire:model.live="puzzleType" class="field-classical w-full appearance-none pr-9 pl-3.5">
                        <option value="">{{ __('Any Type') }}</option>
                        <option value="standard">{{ __('Standard') }}</option>
                        <option value="diamond">{{ __('Diamond') }}</option>
                        <option value="freestyle">{{ __('Freestyle') }}</option>
                    </select>
                    <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                </span>
            </label>

            <label class="block">
                <span class="meta-classical mb-1.5 block">{{ __('Constructor') }}</span>
                <input type="text" wire:model.live.debounce.300ms="constructor" placeholder="{{ __('Name...') }}" class="field-classical w-full px-3.5" />
            </label>

            <label class="block">
                <span class="meta-classical mb-1.5 block">{{ __('Published') }}</span>
                <span class="relative block">
                    <select wire:model.live="dateRange" class="field-classical w-full appearance-none pr-9 pl-3.5">
                        <option value="">{{ __('Any Time') }}</option>
                        <option value="today">{{ __('Today') }}</option>
                        <option value="week">{{ __('This Week') }}</option>
                        <option value="month">{{ __('This Month') }}</option>
                        <option value="year">{{ __('This Year') }}</option>
                    </select>
                    <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                </span>
            </label>

            <label class="block">
                <span class="meta-classical mb-1.5 block">{{ __('Tag') }}</span>
                <span class="relative block">
                    <select wire:model.live="tag" class="field-classical w-full appearance-none pr-9 pl-3.5">
                        <option value="">{{ __('All Tags') }}</option>
                        @foreach($this->allTags as $t)
                            <option value="{{ $t->slug }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                    <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                </span>
            </label>

            <label class="block">
                <span class="meta-classical mb-1.5 block">{{ __('Minimum Rating') }}</span>
                <span class="relative block">
                    <select wire:model.live="minRating" class="field-classical w-full appearance-none pr-9 pl-3.5">
                        <option value="">{{ __('Any Rating') }}</option>
                        <option value="4">{{ __('4+ Stars') }}</option>
                        <option value="3">{{ __('3+ Stars') }}</option>
                        <option value="2">{{ __('2+ Stars') }}</option>
                        <option value="1">{{ __('1+ Stars') }}</option>
                    </select>
                    <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                </span>
            </label>

            @if($this->hasActiveFilters())
                <div class="flex items-end">
                    <button type="button" class="btn-classical btn-classical-muted h-10 py-4.75 w-full" wire:click="clearFilters" data-test="clear-filters-button">
                        {{ __('Clear All') }}
                    </button>
                </div>
            @endif
        </div>
    @endif

    {{-- Results --}}
    @php
        $results = $this->puzzles;
        $items = ($limit ?? 0) > 0 ? $results : $results->items();
        $pinnedDaily = $this->pinnedDailyPuzzle;
        $onFirstPage = ($limit ?? 0) > 0 || $results->currentPage() === 1;
        $showPinned = $pinnedDaily !== null && $onFirstPage;
    @endphp

    @if(count($items) === 0 && ! $showPinned)
        <div class="border-border-strong flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-16 text-center">
            <flux:icon name="magnifying-glass" class="text-ink-faint mb-4 size-10" />
            <h3 class="font-classical text-ink text-[26px] leading-tight font-medium">{{ __('No puzzles found') }}</h3>
            <p class="text-ink-muted mt-2 text-sm">
                @if($this->hasActiveFilters())
                    {{ __('Try adjusting your filters or search terms.') }}
                @else
                    {{ __('No published puzzles available right now.') }}
                @endif
            </p>
        </div>
    @else
        <div class="grid gap-[22px] [grid-template-columns:repeat(auto-fill,minmax(268px,1fr))]">
            @if($showPinned)
                <livewire:puzzle-card
                    :crossword="$pinnedDaily"
                    :is-liked="isset($this->likedIds[$pinnedDaily->id])"
                    :is-solved="isset($this->solvedIds[$pinnedDaily->id])"
                    :is-daily="true"
                    :wire:key="'card-daily-'.$pinnedDaily->id"
                />
            @endif
            @foreach($items as $crossword)
                <livewire:puzzle-card
                    :crossword="$crossword"
                    :is-liked="isset($this->likedIds[$crossword->id])"
                    :is-solved="isset($this->solvedIds[$crossword->id])"
                    :wire:key="'card-'.$crossword->id"
                />
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($limit === 0 && $results->hasPages())
            <div class="mt-4">
                {{ $results->links() }}
            </div>
        @endif
    @endif
</div>
