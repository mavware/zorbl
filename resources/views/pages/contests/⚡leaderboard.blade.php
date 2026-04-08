<?php

use App\Models\Contest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Leaderboard')] class extends Component {
    #[Locked]
    public Contest $contest;

    #[Url]
    public string $sortField = 'rank';

    #[Url]
    public string $sortDirection = 'asc';

    public function mount(Contest $contest): void
    {
        $this->contest = $contest;
    }

    #[Computed]
    public function entries()
    {
        $query = $this->contest->entries()->with('user');

        $allowed = ['rank', 'puzzles_completed', 'total_solve_time'];
        $field = in_array($this->sortField, $allowed) ? $this->sortField : 'rank';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($field, $direction)->get();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function totalPuzzles(): int
    {
        return $this->contest->crosswords()->count();
    }
}
?>

<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="2xl">{{ __('Leaderboard') }}</flux:heading>
            <flux:text size="sm" class="mt-1">{{ $contest->title }}</flux:text>
        </div>
        <flux:button variant="ghost" size="sm" :href="route('contests.show', $contest)" wire:navigate icon="arrow-left">
            {{ __('Back to Contest') }}
        </flux:button>
    </div>

    @if($this->entries->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-12 dark:border-zinc-600">
            <flux:icon name="chart-bar" class="mb-4 size-12 text-zinc-400" />
            <flux:heading size="lg" class="mb-2">{{ __('No entries yet') }}</flux:heading>
            <flux:text>{{ __('Be the first to join this contest!') }}</flux:text>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'rank'" :direction="$sortDirection" wire:click="sortBy('rank')">{{ __('Rank') }}</flux:table.column>
                <flux:table.column>{{ __('Solver') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Meta') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'puzzles_completed'" :direction="$sortDirection" wire:click="sortBy('puzzles_completed')" align="center">{{ __('Puzzles') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'total_solve_time'" :direction="$sortDirection" wire:click="sortBy('total_solve_time')" align="end">{{ __('Total Time') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Submitted') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->entries as $entry)
                    <flux:table.row :key="$entry->id" @class(['bg-amber-50/50 dark:bg-amber-900/10' => auth()->check() && $entry->user_id === auth()->id()])>
                        <flux:table.cell>
                            @if($entry->rank <= 3)
                                <span @class([
                                    'inline-flex size-7 items-center justify-center rounded-full text-sm font-bold text-white',
                                    'bg-amber-500' => $entry->rank === 1,
                                    'bg-zinc-400' => $entry->rank === 2,
                                    'bg-amber-700' => $entry->rank === 3,
                                ])>{{ $entry->rank }}</span>
                            @else
                                <span class="px-2 text-sm text-zinc-500">{{ $entry->rank }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell variant="strong">{{ $entry->user->name }}</flux:table.cell>
                        <flux:table.cell align="center">
                            @if($entry->meta_solved)
                                <flux:icon name="check-circle" class="inline size-5 text-green-500" />
                            @else
                                <flux:icon name="x-circle" class="inline size-5 text-zinc-300 dark:text-zinc-600" />
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="center">{{ $entry->puzzles_completed }} / {{ $this->totalPuzzles }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono">{{ $entry->formattedSolveTime() ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $entry->meta_submitted_at?->format('M j, g:ia') ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
