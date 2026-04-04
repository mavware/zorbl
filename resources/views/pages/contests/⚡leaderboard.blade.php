<?php

use App\Models\Contest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Leaderboard')] class extends Component {
    #[Locked]
    public Contest $contest;

    public function mount(Contest $contest): void
    {
        $this->contest = $contest;
    }

    #[Computed]
    public function entries()
    {
        return $this->contest->entries()
            ->with('user')
            ->orderBy('rank')
            ->get();
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
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full">
                <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Rank') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Solver') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Meta') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Puzzles') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Total Time') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Submitted') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($this->entries as $entry)
                        <tr
                            wire:key="entry-{{ $entry->id }}"
                            @class([
                                'bg-amber-50/50 dark:bg-amber-900/10' => auth()->check() && $entry->user_id === auth()->id(),
                            ])
                        >
                            <td class="px-4 py-3">
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
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-sm font-medium">{{ $entry->user->name }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($entry->meta_solved)
                                    <flux:icon name="check-circle" class="inline size-5 text-green-500" />
                                @else
                                    <flux:icon name="x-circle" class="inline size-5 text-zinc-300 dark:text-zinc-600" />
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-sm">{{ $entry->puzzles_completed }} / {{ $this->totalPuzzles }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="text-sm font-mono">{{ $entry->formattedSolveTime() ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="text-sm text-zinc-500">
                                    {{ $entry->meta_submitted_at?->format('M j, g:ia') ?? '—' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
