<?php

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
            ->with('crossword.user');

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
}
?>

<div class="space-y-8">
    {{-- My Attempts --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Solving') }}</flux:heading>
            <flux:button variant="ghost" size="sm" :href="route('crosswords.stats')" wire:navigate icon="chart-bar">
                {{ __('Stats') }}
            </flux:button>
        </div>

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
            <div class="border-line-strong flex flex-col items-center justify-center rounded-xl border border-dashed py-12">
                <flux:icon name="puzzle-piece" class="mb-4 size-12 text-zinc-500" />
                <flux:heading size="lg" class="mb-2">
                    @if($search !== '' || $filter !== '')
                        {{ __('No matching puzzles') }}
                    @else
                        {{ __('No puzzles in progress') }}
                    @endif
                </flux:heading>
                <flux:text>
                    @if($search !== '' || $filter !== '')
                        {{ __('Try adjusting your filters or search terms.') }}
                    @else
                        {{ __('Browse published puzzles below and start solving.') }}
                    @endif
                </flux:text>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($this->attempts as $attempt)
                    <div
                        wire:key="attempt-{{ $attempt->id }}"
                        class="border-line group relative rounded-xl border p-4 transition-colors hover:border-zinc-400 dark:hover:border-zinc-500"
                    >
                        <a href="{{ route('crosswords.solver', $attempt->crossword) }}" wire:navigate class="block">
                            <div class="mb-3 flex justify-center">
                                <x-grid-thumbnail :grid="$attempt->crossword->grid" :width="$attempt->crossword->width" :height="$attempt->crossword->height" />
                            </div>

                            <flux:heading size="sm" class="truncate">{{ $attempt->crossword->title ?: __('Untitled Puzzle') }}</flux:heading>
                            <flux:text size="sm" class="mt-1">
                                {{ __('by :author', ['author' => $attempt->crossword->user->name ?? __('Unknown')]) }}
                                &middot;
                                {{ $attempt->crossword->width }}&times;{{ $attempt->crossword->height }}
                            </flux:text>
                            @php($solveProgress = $attempt->is_completed ? 100 : $attempt->solveProgress())
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <div
                                        class="h-full rounded-full transition-all {{ $solveProgress === 100 ? 'bg-emerald-500' : ($solveProgress >= 50 ? 'bg-sky-500' : 'bg-zinc-400') }}"
                                        style="width: {{ $solveProgress }}%"
                                    ></div>
                                </div>
                                <span class="text-xs tabular-nums text-zinc-500">{{ $solveProgress }}%</span>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                @if($attempt->is_completed)
                                    <flux:badge size="sm" variant="solid" color="green">{{ __('Completed') }}</flux:badge>
                                    @if($attempt->formattedSolveTime())
                                        <flux:text size="sm" class="flex items-center gap-1 text-zinc-500">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            {{ $attempt->formattedSolveTime() }}
                                        </flux:text>
                                    @endif
                                @else
                                    <flux:badge size="sm" variant="solid" color="sky">{{ __('In Progress') }}</flux:badge>
                                @endif
                                <flux:text size="sm" class="text-zinc-500">{{ $attempt->updated_at->diffForHumans() }}</flux:text>
                            </div>
                        </a>

                        <div class="absolute top-2 right-2 opacity-0 transition-opacity group-hover:opacity-100">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    <flux:menu.item icon="trash" variant="danger" wire:click="removeAttempt({{ $attempt->id }})" wire:confirm="{{ __('Remove this puzzle from your solving list?') }}">
                                        {{ __('Remove') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Browse Published Puzzles --}}
    <div class="space-y-4">
        <flux:heading size="lg">{{ __('Browse Puzzles') }}</flux:heading>

        <livewire:puzzle-discovery :exclude-attempted="true" />
    </div>
</div>
