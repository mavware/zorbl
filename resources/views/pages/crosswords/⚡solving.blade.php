<?php

use App\Models\PuzzleAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Solving')] class extends Component {
    #[Computed]
    public function attempts()
    {
        return Auth::user()
            ->puzzleAttempts()
            ->with('crossword.user')
            ->latest('updated_at')
            ->get();
    }

    public function removeAttempt(int $attemptId): void
    {
        $attempt = PuzzleAttempt::findOrFail($attemptId);

        Gate::authorize('delete', $attempt);

        $attempt->delete();
        unset($this->attempts);
    }
}
?>

<div class="space-y-8">
    {{-- In Progress --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Solving') }}</flux:heading>
            <flux:button variant="ghost" size="sm" :href="route('crosswords.stats')" wire:navigate icon="chart-bar">
                {{ __('Stats') }}
            </flux:button>
        </div>

        @if($this->attempts->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-12 dark:border-zinc-600">
                <flux:icon name="puzzle-piece" class="mb-4 size-12 text-zinc-400" />
                <flux:heading size="lg" class="mb-2">{{ __('No puzzles in progress') }}</flux:heading>
                <flux:text>{{ __('Browse published puzzles below and start solving.') }}</flux:text>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($this->attempts as $attempt)
                    <div
                        wire:key="attempt-{{ $attempt->id }}"
                        class="group relative rounded-xl border border-zinc-200 p-4 transition-colors hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500"
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
                                <span class="text-xs tabular-nums text-zinc-400">{{ $solveProgress }}%</span>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                @if($attempt->is_completed)
                                    <flux:badge size="sm" variant="solid" color="green">{{ __('Completed') }}</flux:badge>
                                    @if($attempt->formattedSolveTime())
                                        <flux:text size="sm" class="flex items-center gap-1 text-zinc-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            {{ $attempt->formattedSolveTime() }}
                                        </flux:text>
                                    @endif
                                @else
                                    <flux:badge size="sm" variant="solid" color="sky">{{ __('In Progress') }}</flux:badge>
                                @endif
                                <flux:text size="sm" class="text-zinc-400">{{ $attempt->updated_at->diffForHumans() }}</flux:text>
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
