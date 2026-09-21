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

    public string $sortBy = 'newest';

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
            'sortBy' => ['except' => 'newest'],
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

    #[Computed]
    public function pinnedDailyPuzzle(): ?Crossword
    {
        if ($this->hasActiveFilters()) {
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

    public function clearFilters(): void
    {
        $this->reset('search', 'gridSize', 'puzzleType', 'constructor', 'dateRange', 'difficulty', 'tag', 'minRating', 'sortBy');
        $this->sortBy = 'newest';
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

    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->gridSize !== ''
            || $this->puzzleType !== ''
            || $this->constructor !== ''
            || $this->dateRange !== ''
            || $this->difficulty !== ''
            || $this->tag !== ''
            || $this->minRating !== ''
            || $this->sortBy !== 'newest';
    }

}
?>

<div class="@container space-y-4">

    <div class="border-hairline mb-2 flex items-center justify-between gap-3 border-b pb-4">
        <h2 class="font-classical text-ink text-[26px] leading-tight font-medium">{{ __('Discover Puzzles') }}</h2>
        <label class="relative w-48 font-classical text-[15px] font-medium">
            <select wire:model.live="sortBy" class="field-classical font-classical w-full text-[15px] font-medium appearance-none pr-9 pl-3.5">
            <option value="newest">{{ __('Sort: Newest') }}</option>
            <option value="oldest">{{ __('Sort: Oldest') }}</option>
            <option value="most_liked">{{ __('Sort: Most Liked') }}</option>
            <option value="most_solved">{{ __('Sort: Most Solved') }}</option>
            <option value="highest_rated">{{ __('Sort: Highest Rated') }}</option>
            <option value="most_played">{{ __('Sort: Most Played') }}</option>
            <option value="largest">{{ __('Sort: Largest') }}</option>
            <option value="smallest">{{ __('Sort: Smallest') }}</option>
            </select>
            <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
        </label>
    </div>

    {{-- Search + Primary Filters (single row on desktop) --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
        <label class="relative min-w-0 flex-1 sm:basis-64">
            <span class="sr-only">{{ __('Search by title or constructor...') }}</span>
            <flux:icon name="magnifying-glass" class="text-ink-faint pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
            <input
                type="search"
                placeholder="{{ __('Search by title or constructor...') }}"
                wire:model.live.debounce.300ms="search"
                class="field-classical w-full pr-3 pl-9"
            />
        </label>

        <label class="relative sm:w-40">
            <select wire:model.live="difficulty" class="field-classical w-full appearance-none pr-9 pl-3.5">
            <option value="">{{ __('Any Difficulty') }}</option>
            <option value="Easy">{{ __('Easy') }}</option>
            <option value="Medium">{{ __('Medium') }}</option>
            <option value="Hard">{{ __('Hard') }}</option>
            <option value="Expert">{{ __('Expert') }}</option>
            </select>
            <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
        </label>

        <label class="relative sm:w-32">
            <select wire:model.live="gridSize" class="field-classical w-full appearance-none pr-9 pl-3.5">
            <option value="">{{ __('Any Size') }}</option>
            <option value="small">{{ __('Small') }}</option>
            <option value="medium">{{ __('Medium') }}</option>
            <option value="large">{{ __('Large') }}</option>
            </select>
            <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
        </label>

        <label class="relative sm:w-36">
            <select wire:model.live="puzzleType" class="field-classical w-full appearance-none pr-9 pl-3.5">
            <option value="">{{ __('Any Type') }}</option>
            <option value="standard">{{ __('Standard') }}</option>
            <option value="diamond">{{ __('Diamond') }}</option>
            <option value="freestyle">{{ __('Freestyle') }}</option>
            </select>
            <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
        </label>

        <div class="flex items-center gap-2 sm:ml-auto">
            <button
                type="button"
                class="btn-classical h-10 {{ $showFilters ? 'btn-amber-outline' : 'btn-classical-muted' }}"
                wire:click="$toggle('showFilters')"
            >
                <flux:icon name="adjustments-horizontal" class="size-4" />
                {{ __('More') }}
            </button>
            @if($this->hasActiveFilters())
                <button type="button" class="btn-classical btn-classical-muted h-10" wire:click="clearFilters">
                    {{ __('Clear All') }}
                </button>
            @endif
        </div>
    </div>

    {{-- Secondary Filters (collapsible) --}}
    @if($showFilters)
        <div class="border-border grid gap-4 rounded-sm border p-[18px] sm:grid-cols-2 lg:grid-cols-4">
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
