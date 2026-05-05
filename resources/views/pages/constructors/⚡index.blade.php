<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        $query = User::whereHas('crosswords', fn ($q) => $q->where('is_published', true))
            ->with('subscriptions')
            ->withCount([
                'crosswords as published_puzzles_count' => fn ($q) => $q->where('is_published', true),
            ])
            ->addSelect([
                'total_likes' => DB::table('crossword_likes')
                    ->join('crosswords', 'crosswords.id', '=', 'crossword_likes.crossword_id')
                    ->whereColumn('crosswords.user_id', 'users.id')
                    ->where('crosswords.is_published', true)
                    ->selectRaw('count(*)'),
                'total_solves' => DB::table('puzzle_attempts')
                    ->join('crosswords', 'crosswords.id', '=', 'puzzle_attempts.crossword_id')
                    ->whereColumn('crosswords.user_id', 'users.id')
                    ->where('crosswords.is_published', true)
                    ->where('puzzle_attempts.is_completed', true)
                    ->selectRaw('count(*)'),
            ])
            ->withCount('followers');

        if ($this->search !== '') {
            $query->whereLike('name', "%{$this->search}%");
        }

        match ($this->sortBy) {
            'most_liked' => $query->orderByDesc('total_likes'),
            'most_solved' => $query->orderByDesc('total_solves'),
            'most_followers' => $query->orderByDesc('followers_count'),
            'newest' => $query->latest(),
            default => $query->orderByDesc('published_puzzles_count'),
        };

        return $query->paginate(18);
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
    <flux:heading size="xl">{{ __('Constructors') }}</flux:heading>

    {{-- Search & Sort --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                icon="magnifying-glass"
                placeholder="{{ __('Search constructors...') }}"
                wire:model.live.debounce.300ms="search"
            />
        </div>
        <flux:select wire:model.live="sortBy" size="sm" class="w-44">
            <flux:select.option value="most_puzzles">{{ __('Most Puzzles') }}</flux:select.option>
            <flux:select.option value="most_liked">{{ __('Most Liked') }}</flux:select.option>
            <flux:select.option value="most_solved">{{ __('Most Solved') }}</flux:select.option>
            <flux:select.option value="most_followers">{{ __('Most Followers') }}</flux:select.option>
            <flux:select.option value="newest">{{ __('Newest') }}</flux:select.option>
        </flux:select>
    </div>

    {{-- Constructor Grid --}}
    @if($this->constructors->isEmpty())
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
            @foreach($this->constructors as $constructor)
                <a
                    href="{{ route('constructors.show', $constructor) }}"
                    wire:navigate
                    wire:key="constructor-{{ $constructor->id }}"
                    class="border-line group rounded-xl border p-5 transition-colors hover:border-zinc-400 dark:hover:border-zinc-600"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-lg font-bold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                            {{ $constructor->initials() }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <flux:heading size="sm" class="truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                {{ $constructor->name }}
                                @if($constructor->isPro())
                                    <flux:badge color="purple" size="sm" class="ml-1 align-middle">{{ __('Pro') }}</flux:badge>
                                @endif
                            </flux:heading>
                            @if($constructor->bio)
                                <flux:text size="sm" class="mt-0.5 line-clamp-1 text-zinc-500">
                                    {{ $constructor->bio }}
                                </flux:text>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                        <div>
                            <div class="text-lg font-semibold text-fg">{{ $constructor->published_puzzles_count }}</div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Puzzles') }}</flux:text>
                        </div>
                        <div>
                            <div class="text-lg font-semibold text-fg">{{ (int) $constructor->total_solves }}</div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Solves') }}</flux:text>
                        </div>
                        <div>
                            <div class="text-lg font-semibold text-fg">{{ $constructor->followers_count }}</div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Followers') }}</flux:text>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($this->constructors->hasPages())
            <div class="mt-4">
                {{ $this->constructors->links() }}
            </div>
        @endif
    @endif
</div>
