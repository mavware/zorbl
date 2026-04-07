<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
    public function puzzles()
    {
        $query = Crossword::where('is_published', true)
            ->with('user:id,name')
            ->withCount('likes');

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
            match ($this->puzzleType) {
                'standard' => $query->where(function ($q) {
                    $q->whereNull('styles')
                        ->orWhere('styles', '[]')
                        ->orWhere('styles', '{}');
                })->whereRaw($this->noNullCellsCondition()),
                'barred' => $query->where('styles', 'like', '%bars%'),
                'shaped' => $query->where('grid', 'like', '%null%'),
                default => null,
            };
        }

        if ($this->difficulty !== '') {
            $query->where('difficulty_label', $this->difficulty);
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
        return Auth::user()
            ->crosswordLikes()
            ->pluck('crossword_id')
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    public function toggleLike(int $crosswordId): void
    {
        $like = CrosswordLike::where('user_id', Auth::id())
            ->where('crossword_id', $crosswordId)
            ->first();

        if ($like) {
            $like->delete();
        } else {
            CrosswordLike::create([
                'user_id' => Auth::id(),
                'crossword_id' => $crosswordId,
            ]);
        }

        unset($this->likedIds, $this->puzzles);
    }

    public function startSolving(int $crosswordId): void
    {
        $crossword = Crossword::findOrFail($crosswordId);
        $this->authorize('solve', $crossword);

        $this->redirect(route('crosswords.solver', $crossword), navigate: true);
    }

    public function updatedDifficulty(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'gridSize', 'puzzleType', 'constructor', 'dateRange', 'difficulty', 'sortBy');
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

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    public function puzzleTypeLabel(Crossword $crossword): string
    {
        if ($crossword->grid && collect($crossword->grid)->flatten()->contains(null)) {
            return __('Shaped');
        }

        if ($crossword->styles && collect($crossword->styles)->contains(fn ($s) => ! empty($s['bars'] ?? []))) {
            return __('Barred');
        }

        return __('Standard');
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->gridSize !== ''
            || $this->puzzleType !== ''
            || $this->constructor !== ''
            || $this->dateRange !== ''
            || $this->difficulty !== ''
            || $this->sortBy !== 'newest';
    }

    private function noNullCellsCondition(): string
    {
        return "grid NOT LIKE '%null%'";
    }
}
?>

<div class="space-y-4">
    {{-- Search Bar & Filter Toggle --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                icon="magnifying-glass"
                placeholder="{{ __('Search by title or constructor...') }}"
                wire:model.live.debounce.300ms="search"
            />
        </div>
        <div class="flex items-center gap-2">
            <flux:button
                size="sm"
                :variant="$showFilters ? 'primary' : 'ghost'"
                icon="adjustments-horizontal"
                wire:click="$toggle('showFilters')"
            >
                {{ __('Filters') }}
                @if($this->hasActiveFilters())
                    <flux:badge size="sm" color="amber" class="ml-1">!</flux:badge>
                @endif
            </flux:button>
            @if($this->hasActiveFilters())
                <flux:button size="sm" variant="ghost" wire:click="clearFilters">
                    {{ __('Clear') }}
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Filters Panel --}}
    @if($showFilters)
        <div class="grid gap-3 rounded-xl border border-zinc-200 p-4 sm:grid-cols-2 lg:grid-cols-5 dark:border-zinc-700">
            <flux:field>
                <flux:label>{{ __('Grid Size') }}</flux:label>
                <flux:select wire:model.live="gridSize" size="sm">
                    <flux:select.option value="">{{ __('Any Size') }}</flux:select.option>
                    <flux:select.option value="small">{{ __('Small (≤10×10)') }}</flux:select.option>
                    <flux:select.option value="medium">{{ __('Medium (11–17)') }}</flux:select.option>
                    <flux:select.option value="large">{{ __('Large (18+)') }}</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Puzzle Type') }}</flux:label>
                <flux:select wire:model.live="puzzleType" size="sm">
                    <flux:select.option value="">{{ __('All Types') }}</flux:select.option>
                    <flux:select.option value="standard">{{ __('Standard') }}</flux:select.option>
                    <flux:select.option value="barred">{{ __('Barred / Cryptic') }}</flux:select.option>
                    <flux:select.option value="shaped">{{ __('Shaped / Diamond') }}</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Constructor') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="constructor" size="sm" placeholder="{{ __('Name...') }}" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Difficulty') }}</flux:label>
                <flux:select wire:model.live="difficulty" size="sm">
                    <flux:select.option value="">{{ __('Any Difficulty') }}</flux:select.option>
                    <flux:select.option value="Easy">{{ __('Easy') }}</flux:select.option>
                    <flux:select.option value="Medium">{{ __('Medium') }}</flux:select.option>
                    <flux:select.option value="Hard">{{ __('Hard') }}</flux:select.option>
                    <flux:select.option value="Expert">{{ __('Expert') }}</flux:select.option>
                </flux:select>
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
        </div>

        {{-- Sort --}}
        <div class="flex items-center gap-2">
            <flux:text size="sm" class="text-zinc-500">{{ __('Sort by:') }}</flux:text>
            <flux:select wire:model.live="sortBy" size="sm" class="w-40">
                <flux:select.option value="newest">{{ __('Newest') }}</flux:select.option>
                <flux:select.option value="oldest">{{ __('Oldest') }}</flux:select.option>
                <flux:select.option value="most_liked">{{ __('Most Liked') }}</flux:select.option>
                <flux:select.option value="largest">{{ __('Largest') }}</flux:select.option>
                <flux:select.option value="smallest">{{ __('Smallest') }}</flux:select.option>
            </flux:select>
        </div>
    @endif

    {{-- Results --}}
    @php
        $results = $this->puzzles;
        $items = ($limit ?? 0) > 0 ? $results : $results->items();
    @endphp

    @if(count($items) === 0)
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-12 dark:border-zinc-600">
            <flux:icon name="magnifying-glass" class="mb-4 size-12 text-zinc-400" />
            <flux:heading size="lg" class="mb-2">{{ __('No puzzles found') }}</flux:heading>
            <flux:text class="text-zinc-400">
                @if($this->hasActiveFilters())
                    {{ __('Try adjusting your filters or search terms.') }}
                @else
                    {{ __('No published puzzles available right now.') }}
                @endif
            </flux:text>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($items as $crossword)
                <div
                    wire:key="discover-{{ $crossword->id }}"
                    class="group rounded-xl border border-zinc-200 p-4 transition-colors hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500"
                >
                    {{-- Mini grid thumbnail --}}
                    <div class="mb-3 flex justify-center">
                        <div
                            class="inline-grid gap-px rounded border border-zinc-200 bg-zinc-200 p-px dark:border-zinc-600 dark:bg-zinc-600"
                            style="grid-template-columns: repeat({{ $crossword->width }}, minmax(0, 1fr)); width: {{ min($crossword->width * 8, 120) }}px;"
                        >
                            @for($row = 0; $row < $crossword->height; $row++)
                                @for($col = 0; $col < $crossword->width; $col++)
                                    <div class="{{ $crossword->grid[$row][$col] === null ? 'invisible' : (($crossword->grid[$row][$col] ?? 0) === '#' ? 'bg-zinc-800 dark:bg-zinc-300' : 'bg-white dark:bg-zinc-800') }}" style="aspect-ratio: 1;"></div>
                                @endfor
                            @endfor
                        </div>
                    </div>

                    <flux:heading size="sm" class="truncate">{{ $crossword->title ?: __('Untitled Puzzle') }}</flux:heading>
                    <flux:text size="sm" class="mt-1">
                        {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }}
                        &middot;
                        {{ $crossword->width }}&times;{{ $crossword->height }}
                    </flux:text>

                    <div class="mt-1.5 flex items-center gap-2">
                        <flux:badge size="sm" variant="outline">{{ $this->puzzleTypeLabel($crossword) }}</flux:badge>
                        @if($crossword->difficulty_label)
                            <flux:badge
                                size="sm"
                                :color="match($crossword->difficulty_label) { 'Easy' => 'green', 'Medium' => 'amber', 'Hard' => 'orange', 'Expert' => 'red', default => 'zinc' }"
                            >{{ __($crossword->difficulty_label) }}</flux:badge>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center justify-between">
                        <flux:button size="sm" variant="primary" wire:click="startSolving({{ $crossword->id }})">
                            {{ __('Start Solving') }}
                        </flux:button>
                        <button
                            wire:click.stop="toggleLike({{ $crossword->id }})"
                            class="flex items-center gap-1 rounded-lg px-2 py-1 text-xs transition-colors {{ isset($this->likedIds[$crossword->id]) ? 'text-red-500' : 'text-zinc-400 hover:text-red-400' }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="{{ isset($this->likedIds[$crossword->id]) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                            </svg>
                            <span>{{ $crossword->likes_count }}</span>
                        </button>
                    </div>
                </div>
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
