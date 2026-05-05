<?php

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Constructors')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortBy = 'most_puzzles';

    #[Computed]
    public function constructors()
    {
        $query = User::query()
            ->with(['subscriptions', 'roles'])
            ->whereHas('crosswords', fn ($q) => $q->where('is_published', true))
            ->withCount([
                'crosswords as published_puzzles_count' => fn ($q) => $q->where('is_published', true),
                'followers',
            ])
            ->addSelect([
                'total_likes' => \App\Models\CrosswordLike::query()
                    ->selectRaw('count(*)')
                    ->whereIn(
                        'crossword_id',
                        \App\Models\Crossword::query()
                            ->select('id')
                            ->whereColumn('user_id', 'users.id')
                            ->where('is_published', true)
                    ),
            ]);

        if ($this->search !== '') {
            $term = $this->search;
            $query->whereLike('name', "%{$term}%");
        }

        match ($this->sortBy) {
            'most_popular' => $query->orderByDesc('total_likes'),
            'most_followers' => $query->orderByDesc('followers_count'),
            'newest' => $query->latest(),
            default => $query->orderByDesc('published_puzzles_count'),
        };

        return $query->paginate(12);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }
}
?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Constructors') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-600">{{ __('Discover crossword creators and follow your favorites.') }}</flux:text>
    </div>

    {{-- Search & Sort --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                icon="magnifying-glass"
                placeholder="{{ __('Search by name...') }}"
                wire:model.live.debounce.300ms="search"
            />
        </div>
        <flux:select wire:model.live="sortBy" size="sm" class="w-44">
            <flux:select.option value="most_puzzles">{{ __('Most Puzzles') }}</flux:select.option>
            <flux:select.option value="most_popular">{{ __('Most Popular') }}</flux:select.option>
            <flux:select.option value="most_followers">{{ __('Most Followers') }}</flux:select.option>
            <flux:select.option value="newest">{{ __('Newest') }}</flux:select.option>
        </flux:select>
    </div>

    {{-- Results --}}
    @php $results = $this->constructors; @endphp

    @if($results->isEmpty())
        <div class="border-line-strong flex flex-col items-center justify-center rounded-xl border border-dashed py-12">
            <flux:icon name="users" class="mb-4 size-12 text-zinc-500" />
            <flux:heading size="lg" class="mb-2">{{ __('No constructors found') }}</flux:heading>
            <flux:text class="text-zinc-500">
                @if($search !== '')
                    {{ __('Try a different search term.') }}
                @else
                    {{ __('No constructors have published puzzles yet.') }}
                @endif
            </flux:text>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($results as $constructor)
                <a
                    href="{{ route('constructors.show', $constructor) }}"
                    wire:navigate
                    wire:key="constructor-{{ $constructor->id }}"
                    class="border-line group rounded-xl border p-5 transition-colors hover:border-zinc-400 dark:hover:border-zinc-600"
                >
                    <div class="flex items-center gap-4">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-lg font-bold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                            {{ $constructor->initials() }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <flux:heading size="sm" class="truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                    {{ $constructor->name }}
                                </flux:heading>
                                @if($constructor->isPro())
                                    <flux:badge color="purple" size="sm">{{ __('Pro') }}</flux:badge>
                                @endif
                            </div>
                            @if($constructor->bio)
                                <flux:text size="sm" class="mt-0.5 line-clamp-1 text-zinc-500">
                                    {{ $constructor->bio }}
                                </flux:text>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 flex items-center gap-4 text-xs text-zinc-500">
                        <span class="flex items-center gap-1">
                            <flux:icon name="puzzle-piece" class="size-3.5" />
                            {{ trans_choice(':count puzzle|:count puzzles', $constructor->published_puzzles_count) }}
                        </span>
                        <span class="flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-red-400" viewBox="0 0 24 24" fill="currentColor"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
                            {{ $constructor->total_likes }}
                        </span>
                        <span class="flex items-center gap-1">
                            <flux:icon name="users" class="size-3.5" />
                            {{ trans_choice(':count follower|:count followers', $constructor->followers_count) }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        @if($results->hasPages())
            <div class="mt-4">
                {{ $results->links() }}
            </div>
        @endif
    @endif
</div>
