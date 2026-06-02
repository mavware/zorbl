<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\DailyPuzzle;
use App\Models\PuzzleAttempt;
use App\Models\Tag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('Browse Puzzles')]
#[Layout('layouts.public')]
class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $gridSize = '';

    #[Url]
    public string $puzzleType = '';

    #[Url]
    public string $constructor = '';

    #[Url]
    public string $dateRange = '';

    #[Url]
    public string $difficulty = '';

    #[Url]
    public string $tag = '';

    #[Url]
    public string $minRating = '';

    #[Url]
    public string $sortBy = 'newest';

    public bool $showFilters = false;

    #[Computed]
    public function dailyPuzzle(): ?Crossword
    {
        return DailyPuzzle::todayOrAuto();
    }

    #[Computed]
    public function dailyPuzzleSolved(): bool
    {
        if (! Auth::check()) {
            return false;
        }

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
                'standard' => $query->where('puzzle_type', 'standard')->where(function ($q) {
                    $q->whereNull('styles')
                        ->orWhere('styles', '[]')
                        ->orWhere('styles', '{}');
                })->whereRaw("grid NOT LIKE '%null%'"),
                'diamond' => $query->where('puzzle_type', 'diamond'),
                'freestyle' => $query->where('puzzle_type', 'freestyle'),
                'barred' => $query->where('styles', 'like', '%bars%'),
                'shaped' => $query->where('grid', 'like', '%null%'),
                default => null,
            };
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
            'largest' => $query->orderByRaw('width * height DESC'),
            'smallest' => $query->orderByRaw('width * height ASC'),
            default => $query->latest(),
        };

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

    public function toggleLike(int $crosswordId): void
    {
        if (! Auth::check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

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
        abort_unless($crossword->is_published, 404);
        abort_unless($crossword->isVisibleToSafeSearch(Auth::user()), 404);

        if (Auth::check()) {
            $this->redirect(route('crosswords.solver', $crossword), navigate: true);

            return;
        }

        // Check guest solve cookie
        $solved = json_decode(request()->cookie('zorbl_guest_solved', '[]'), true) ?: [];

        if (count($solved) > 0 && ! in_array($crossword->id, $solved)) {
            $this->dispatch('show-signup-prompt');

            return;
        }

        $this->redirect(route('puzzles.solve', $crossword), navigate: true);
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

    public function updatedDifficulty(): void
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

    /** @return \Illuminate\Support\Collection<int, object{id: int, name: string, slug: string, published_count: int}> */
    #[Computed]
    public function popularTags(): \Illuminate\Support\Collection
    {
        return Cache::remember('browse:popular_tags', 300, fn () => Tag::whereHas('crosswords', fn ($q) => $q->where('is_published', true))
            ->withCount(['crosswords as published_count' => fn ($q) => $q->where('is_published', true)])
            ->orderByDesc('published_count')
            ->limit(12)
            ->get(['id', 'name', 'slug']));
    }

    public function selectTag(string $slug): void
    {
        $this->tag = $this->tag === $slug ? '' : $slug;
        $this->resetPage();
        unset($this->puzzles);
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

<div
    class="space-y-6"
    x-data="{ showSignup: false }"
    x-on:show-signup-prompt.window="showSignup = true"
>
    <div>
        <flux:heading size="xl">{{ __('Browse Puzzles') }}</flux:heading>
    </div>

    {{-- Puzzle of the Day --}}
{{--    @if($dailyPuzzle = $this->dailyPuzzle)--}}
{{--        <x-daily-puzzle-banner :puzzle="$dailyPuzzle" :solved="$this->dailyPuzzleSolved" />--}}
{{--    @endif--}}

    {{-- Search Bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                icon="magnifying-glass"
                placeholder="{{ __('Search by title or constructor...') }}"
                wire:model.live.debounce.300ms="search"
            />
        </div>
        <div class="flex items-center gap-2">
            <flux:select wire:model.live="sortBy" size="sm" class="w-40">
                <flux:select.option value="newest">{{ __('Newest') }}</flux:select.option>
                <flux:select.option value="oldest">{{ __('Oldest') }}</flux:select.option>
                <flux:select.option value="most_liked">{{ __('Most Liked') }}</flux:select.option>
                <flux:select.option value="largest">{{ __('Largest') }}</flux:select.option>
                <flux:select.option value="smallest">{{ __('Smallest') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Popular Tags --}}
    @if($this->popularTags->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2" data-test="popular-tags">
            <flux:text size="sm" class="text-zinc-500">{{ __('Popular:') }}</flux:text>
            @foreach($this->popularTags as $popularTag)
                <button
                    type="button"
                    wire:click="selectTag('{{ $popularTag->slug }}')"
                    wire:key="popular-tag-{{ $popularTag->slug }}"
                    @class([
                        'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium transition',
                        'bg-amber-500 text-zinc-950' => $tag === $popularTag->slug,
                        'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' => $tag !== $popularTag->slug,
                    ])
                >
                    {{ $popularTag->name }}
                    <span @class([
                        'text-[10px] tabular-nums',
                        'text-zinc-800/70' => $tag === $popularTag->slug,
                        'text-zinc-500 dark:text-zinc-500' => $tag !== $popularTag->slug,
                    ])>{{ $popularTag->published_count }}</span>
                </button>
            @endforeach
        </div>
    @endif

    {{-- Primary Filters (always visible) --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:gap-4">
        <flux:field>
            <flux:label size="sm">{{ __('Difficulty') }}</flux:label>
            <flux:radio.group wire:model.live="difficulty" variant="segmented" size="sm">
                <flux:radio value="" label="{{ __('All') }}" />
                <flux:radio value="Easy" label="{{ __('Easy') }}" />
                <flux:radio value="Medium" label="{{ __('Medium') }}" />
                <flux:radio value="Hard" label="{{ __('Hard') }}" />
                <flux:radio value="Expert" label="{{ __('Expert') }}" />
            </flux:radio.group>
        </flux:field>

        <flux:field>
            <flux:label size="sm">{{ __('Size') }}</flux:label>
            <flux:radio.group wire:model.live="gridSize" variant="segmented" size="sm">
                <flux:radio value="" label="{{ __('All') }}" />
                <flux:radio value="small" label="{{ __('Small') }}" />
                <flux:radio value="medium" label="{{ __('Medium') }}" />
                <flux:radio value="large" label="{{ __('Large') }}" />
            </flux:radio.group>
        </flux:field>

        <flux:field>
            <flux:label size="sm">{{ __('Type') }}</flux:label>
            <flux:radio.group wire:model.live="puzzleType" variant="segmented" size="sm">
                <flux:radio value="" label="{{ __('All') }}" />
                <flux:radio value="standard" label="{{ __('Standard') }}" />
                <flux:radio value="diamond" label="{{ __('Diamond') }}" />
                <flux:radio value="freestyle" label="{{ __('Freestyle') }}" />
                <flux:radio value="barred" label="{{ __('Barred') }}" />
                <flux:radio value="shaped" label="{{ __('Shaped') }}" />
            </flux:radio.group>
        </flux:field>

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
        $pinnedDaily = $this->pinnedDailyPuzzle;
        $showPinned = $pinnedDaily !== null && $results->currentPage() === 1;
        $dailyPuzzleId = $this->dailyPuzzle?->id;
        $dailyRingClass = 'border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-950/30';
    @endphp

    @if($results->isEmpty() && ! $showPinned)
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
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @if($showPinned)
                <x-puzzle-card-old
                    :crossword="$pinnedDaily"
                    :show-like="true"
                    :is-liked="isset($this->likedIds[$pinnedDaily->id])"
                    :is-solved="isset($this->solvedIds[$pinnedDaily->id])"
                    :class="$dailyRingClass"
                    :is-daily="true"
                />
            @endif
            @foreach($results as $crossword)
                @php $isDaily = $crossword->id === $dailyPuzzleId; @endphp
                <x-puzzle-card-old
                    :crossword="$crossword"
                    :show-like="true"
                    :is-liked="isset($this->likedIds[$crossword->id])"
                    :is-solved="isset($this->solvedIds[$crossword->id])"
                    :class="$isDaily ? $dailyRingClass : ''"
                    :is-daily="$isDaily"
                />
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($results->hasPages())
            <div class="mt-4">
                {{ $results->links() }}
            </div>
        @endif
    @endif

    {{-- Signup Prompt Modal --}}
    <template x-teleport="body">
        <div
            x-show="showSignup"
            x-cloak
            x-on:keydown.escape.window="showSignup = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            x-on:click.self="showSignup = false"
        >
            <div class="bg-elevated mx-4 w-full max-w-md rounded-2xl p-8 text-center shadow-xl" x-on:click.stop>
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-8 text-amber-600 dark:text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/>
                        <path d="m9 15 2 2 4-4"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-fg">{{ __('Ready for more puzzles?') }}</h3>
                <p class="mt-2 text-sm text-fg-muted">
                    {{ __('Create a free account to solve unlimited puzzles, save your progress across devices, and track your stats.') }}
                </p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <a href="{{ route('register') }}" class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-zinc-950 hover:bg-amber-400 transition">
                        {{ __('Create Free Account') }}
                    </a>
                    <a href="{{ route('login') }}" class="border-line-strong rounded-xl border px-6 py-2.5 text-sm font-semibold text-zinc-800 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-700 transition">
                        {{ __('Log In') }}
                    </a>
                </div>
                <button x-on:click="showSignup = false" class="mt-4 text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                    {{ __('Maybe later') }}
                </button>
            </div>
        </div>
    </template>
</div>
