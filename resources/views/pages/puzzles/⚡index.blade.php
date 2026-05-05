<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\DailyPuzzle;
use App\Models\Tag;
use Illuminate\Support\Facades\Auth;
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
    public string $sortBy = 'newest';

    public bool $showFilters = false;

    #[Computed]
    public function dailyPuzzle(): ?Crossword
    {
        return DailyPuzzle::todayOrAuto();
    }

    #[Computed]
    public function puzzles()
    {
        $query = Crossword::where('is_published', true)
            ->with('user:id,name', 'tags:id,name,slug')
            ->withCount('likes');

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
        $this->reset('search', 'gridSize', 'puzzleType', 'constructor', 'dateRange', 'difficulty', 'tag', 'sortBy');
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
        <flux:text class="mt-1 text-zinc-600">{{ __('Discover and solve crosswords from the community.') }}</flux:text>
    </div>

    {{-- Puzzle of the Day --}}
    @if($dailyPuzzle = $this->dailyPuzzle)
        <div class="relative overflow-hidden rounded-xl border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 p-5 dark:border-amber-800/50 dark:from-amber-950/30 dark:to-orange-950/30">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="flex shrink-0 justify-center sm:justify-start">
                    <x-grid-thumbnail :grid="$dailyPuzzle->grid" :width="$dailyPuzzle->width" :height="$dailyPuzzle->height" :cell-size="5" :max-width="64" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <flux:icon name="star" class="size-5 text-amber-500" />
                        <flux:heading size="lg">{{ __('Puzzle of the Day') }}</flux:heading>
                        <flux:badge size="sm" color="amber">{{ today()->format('M j') }}</flux:badge>
                    </div>
                    <div class="mt-1">
                        <span class="font-medium text-fg">{{ $dailyPuzzle->title ?: __('Untitled Puzzle') }}</span>
                        <flux:text size="sm" class="mt-0.5 text-zinc-600 dark:text-zinc-400">
                            {{ __('by :author', ['author' => $dailyPuzzle->user->name ?? __('Unknown')]) }}
                            &middot;
                            {{ $dailyPuzzle->width }}&times;{{ $dailyPuzzle->height }}
                            @if($dailyPuzzle->difficulty_label)
                                &middot;
                                {{ __($dailyPuzzle->difficulty_label) }}
                            @endif
                        </flux:text>
                    </div>
                </div>
                <div class="shrink-0">
                    <flux:button variant="primary" size="sm" wire:click="startSolving({{ $dailyPuzzle->id }})" icon="play">
                        {{ __('Solve Now') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

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
        <div class="border-line grid gap-3 rounded-xl border p-4 sm:grid-cols-2 lg:grid-cols-3">
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
        </div>
    @endif

    {{-- Results --}}
    @php $results = $this->puzzles; @endphp

    @if($results->isEmpty())
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
            @foreach($results as $crossword)
                <div
                    wire:key="browse-{{ $crossword->id }}"
                    class="border-line group rounded-xl border p-4 transition-colors hover:border-zinc-400 dark:hover:border-zinc-500"
                >
                    <div class="mb-3 flex justify-center">
                        <x-grid-thumbnail :grid="$crossword->grid" :width="$crossword->width" :height="$crossword->height" />
                    </div>

                    <flux:heading size="sm" class="truncate">{{ $crossword->title ?: __('Untitled Puzzle') }}</flux:heading>
                    <flux:text size="sm" class="mt-1">
                        {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }}
                        &middot;
                        {{ $crossword->width }}&times;{{ $crossword->height }}
                    </flux:text>

                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        <flux:badge size="sm" variant="outline">{{ __($crossword->puzzleTypeLabel()) }}</flux:badge>
                        @if($crossword->difficulty_label)
                            <flux:badge
                                size="sm"
                                :color="match($crossword->difficulty_label) { 'Easy' => 'green', 'Medium' => 'amber', 'Hard' => 'orange', 'Expert' => 'red', default => 'zinc' }"
                            >{{ __($crossword->difficulty_label) }}</flux:badge>
                        @endif
                        @foreach($crossword->tags as $crosswordTag)
                            <flux:badge size="sm" color="blue">{{ $crosswordTag->name }}</flux:badge>
                        @endforeach
                    </div>

                    <div class="mt-3 flex items-center justify-between">
                        <flux:button size="sm" variant="primary" wire:click="startSolving({{ $crossword->id }})">
                            @auth
                                {{ __('Start Solving') }}
                            @else
                                {{ __('Try This Puzzle') }}
                            @endauth
                        </flux:button>
                        <button
                            wire:click.stop="toggleLike({{ $crossword->id }})"
                            class="flex items-center gap-1 rounded-lg px-2 py-1 text-xs transition-colors {{ isset($this->likedIds[$crossword->id]) ? 'text-red-500' : 'text-zinc-500 hover:text-red-400' }}"
                            @guest title="{{ __('Sign in to like') }}" @endguest
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
