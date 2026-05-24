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
            ->with('user:id,name', 'tags:id,name,slug')
            ->withCount('likes')
            ->withAvg('comments as avg_rating', 'rating');

        if ($pinnedId = $this->pinnedDailyPuzzle?->id) {
            $query->where('id', '!=', $pinnedId);
        }

        $hasExplicitFilters = $this->search !== '' || $this->constructor !== '';

        if ($this->excludeOwn && ! $hasExplicitFilters) {
            $query->where('user_id', '!=', Auth::id());
        }

        if ($this->excludeAttempted && ! $hasExplicitFilters) {
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

<div class="space-y-4">

    <div class="mb-4 flex items-center justify-between gap-3">
        <flux:heading size="lg">{{ __('Discover Puzzles') }}</flux:heading>
        <flux:select wire:model.live="sortBy" size="sm" class="w-40">
            <flux:select.option value="newest">{{ __('Sort: Newest') }}</flux:select.option>
            <flux:select.option value="oldest">{{ __('Sort: Oldest') }}</flux:select.option>
            <flux:select.option value="most_liked">{{ __('Sort: Most Liked') }}</flux:select.option>
            <flux:select.option value="most_solved">{{ __('Sort: Most Solved') }}</flux:select.option>
            <flux:select.option value="highest_rated">{{ __('Sort: Highest Rated') }}</flux:select.option>
            <flux:select.option value="most_played">{{ __('Sort: Most Played') }}</flux:select.option>
            <flux:select.option value="largest">{{ __('Sort: Largest') }}</flux:select.option>
            <flux:select.option value="smallest">{{ __('Sort: Smallest') }}</flux:select.option>
        </flux:select>
    </div>

    {{-- Search + Primary Filters (single row on desktop) --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
        <div class="min-w-0 flex-1 sm:basis-64">
            <flux:input
                icon="magnifying-glass"
                size="sm"
                placeholder="{{ __('Search by title or constructor...') }}"
                wire:model.live.debounce.300ms="search"
            />
        </div>

        <flux:select wire:model.live="difficulty" size="sm" class="sm:w-36">
            <flux:select.option value="">{{ __('Any Difficulty') }}</flux:select.option>
            <flux:select.option value="Easy">{{ __('Easy') }}</flux:select.option>
            <flux:select.option value="Medium">{{ __('Medium') }}</flux:select.option>
            <flux:select.option value="Hard">{{ __('Hard') }}</flux:select.option>
            <flux:select.option value="Expert">{{ __('Expert') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="gridSize" size="sm" class="sm:w-32">
            <flux:select.option value="">{{ __('Any Size') }}</flux:select.option>
            <flux:select.option value="small">{{ __('Small') }}</flux:select.option>
            <flux:select.option value="medium">{{ __('Medium') }}</flux:select.option>
            <flux:select.option value="large">{{ __('Large') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="puzzleType" size="sm" class="sm:w-32">
            <flux:select.option value="">{{ __('Any Type') }}</flux:select.option>
            <flux:select.option value="standard">{{ __('Standard') }}</flux:select.option>
            <flux:select.option value="diamond">{{ __('Diamond') }}</flux:select.option>
            <flux:select.option value="freestyle">{{ __('Freestyle') }}</flux:select.option>
        </flux:select>

        <div class="flex items-center gap-2 sm:ml-auto">
            <flux:button
                size="sm"
                :variant="$showFilters ? 'primary' : 'ghost'"
                icon="adjustments-horizontal"
                wire:click="$toggle('showFilters')"
            >
                {{ __('More') }}
            </flux:button>
            @if($this->hasActiveFilters())
                <flux:button size="sm" variant="ghost" wire:click="clearFilters">
                    {{ __('Clear All') }}
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Secondary Filters (collapsible) --}}
    @if($showFilters)
        <div class="border-line grid gap-3 rounded-xl border p-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:field>
                <flux:label>{{ __('Constructor') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="constructor" size="sm" placeholder="{{ __('Name...') }}" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Published') }}</flux:label>
                <flux:select wire:model.live="dateRange" size="sm">
                    <flux:select.option value="">{{ __('Any Time') }}</flux:select.option>
                    <flux:select.option value="today">{{ __('Today') }}</flux:select.option>
                    <flux:select.option value="week">{{ __('This Week') }}</flux:select.option>
                    <flux:select.option value="month">{{ __('This Month') }}</flux:select.option>
                    <flux:select.option value="year">{{ __('This Year') }}</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Tag') }}</flux:label>
                <flux:select wire:model.live="tag" size="sm">
                    <flux:select.option value="">{{ __('All Tags') }}</flux:select.option>
                    @foreach($this->allTags as $t)
                        <flux:select.option value="{{ $t->slug }}">{{ $t->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Minimum Rating') }}</flux:label>
                <flux:select wire:model.live="minRating" size="sm">
                    <flux:select.option value="">{{ __('Any Rating') }}</flux:select.option>
                    <flux:select.option value="4">{{ __('4+ Stars') }}</flux:select.option>
                    <flux:select.option value="3">{{ __('3+ Stars') }}</flux:select.option>
                    <flux:select.option value="2">{{ __('2+ Stars') }}</flux:select.option>
                    <flux:select.option value="1">{{ __('1+ Stars') }}</flux:select.option>
                </flux:select>
            </flux:field>
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
        <div class="border-line-strong flex flex-col items-center justify-center rounded-xl border border-dashed py-12">
            <flux:icon name="magnifying-glass" class="mb-4 size-12 text-zinc-500" />
            <flux:heading size="lg" class="mb-2">{{ __('No puzzles found') }}</flux:heading>
            <flux:text class="text-zinc-500">
                @if($this->hasActiveFilters())
                    {{ __('Try adjusting your filters or search terms.') }}
                @else
                    {{ __('No published puzzles available right now.') }}
                @endif
            </flux:text>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
