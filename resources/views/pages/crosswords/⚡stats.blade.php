<?php

use App\Models\PuzzleAttempt;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Solve Statistics')] class extends Component {
    #[Computed]
    public function completedAttempts()
    {
        return Auth::user()
            ->puzzleAttempts()
            ->where('is_completed', true)
            ->whereNotNull('solve_time_seconds')
            ->with('crossword:id,title,width,height,author')
            ->orderBy('completed_at', 'desc')
            ->get();
    }

    #[Computed]
    public function totalSolved(): int
    {
        return Auth::user()->puzzleAttempts()->where('is_completed', true)->count();
    }

    #[Computed]
    public function averageTime(): ?int
    {
        $avg = $this->completedAttempts->avg('solve_time_seconds');

        return $avg ? (int) round($avg) : null;
    }

    #[Computed]
    public function fastestSolve(): ?PuzzleAttempt
    {
        return $this->completedAttempts->sortBy('solve_time_seconds')->first();
    }

    #[Computed]
    public function averageBySize(): array
    {
        return $this->completedAttempts
            ->groupBy(fn (PuzzleAttempt $a) => $this->sizeCategory($a->crossword))
            ->map(fn ($group, $label) => [
                'label' => $label,
                'count' => $group->count(),
                'average' => (int) round($group->avg('solve_time_seconds')),
                'fastest' => (int) $group->min('solve_time_seconds'),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    public function formatTime(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }

    private function sizeCategory($crossword): string
    {
        $cells = $crossword->width * $crossword->height;

        if ($cells <= 100) {
            return 'Small (≤10×10)';
        }

        if ($cells <= 289) {
            return 'Medium (11–17)';
        }

        return 'Large (18+)';
    }
}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Solve Statistics') }}</flux:heading>
        <flux:button variant="ghost" size="sm" :href="route('crosswords.solving')" wire:navigate icon="arrow-left">
            {{ __('Back to Solving') }}
        </flux:button>
    </div>

    {{-- Streak & Achievements --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/30">
                    <flux:icon name="fire" class="size-5 text-orange-600 dark:text-orange-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Current Streak') }}</flux:text>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                        {{ Auth::user()->current_streak }} {{ __('days') }}
                    </div>
                    <flux:text size="sm" class="text-zinc-400">
                        {{ __('Best: :days days', ['days' => Auth::user()->longest_streak]) }}
                    </flux:text>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="sm" class="mb-3">{{ __('Achievements') }}</flux:heading>
            @php($achievements = Auth::user()->achievements()->orderBy('earned_at', 'desc')->get())
            @if($achievements->isEmpty())
                <flux:text size="sm" class="text-zinc-400">{{ __('Complete puzzles to earn achievements!') }}</flux:text>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach($achievements as $achievement)
                        <flux:tooltip :content="$achievement->description">
                            <div class="flex items-center gap-1.5 rounded-lg bg-amber-50 px-2.5 py-1.5 dark:bg-amber-900/20">
                                <flux:icon :name="$achievement->icon" class="size-4 text-amber-600 dark:text-amber-400" />
                                <span class="text-xs font-medium text-amber-800 dark:text-amber-200">{{ $achievement->label }}</span>
                            </div>
                        </flux:tooltip>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                    <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Puzzles Solved') }}</flux:text>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->totalSolved }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Average Time') }}</flux:text>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->formatTime($this->averageTime) }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-amber-600 dark:text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Fastest Solve') }}</flux:text>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->fastestSolve ? $this->formatTime($this->fastestSolve->solve_time_seconds) : '—' }}</div>
                    @if($this->fastestSolve)
                        <flux:text size="sm" class="truncate text-zinc-400">{{ $this->fastestSolve->crossword->title }}</flux:text>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Average by Size --}}
    @if(count($this->averageBySize) > 0)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Times by Grid Size') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach($this->averageBySize as $size)
                    <div class="rounded-lg border border-zinc-100 p-4 dark:border-zinc-700/50">
                        <flux:heading size="sm">{{ $size['label'] }}</flux:heading>
                        <div class="mt-2 space-y-1">
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-500">{{ __('Solved') }}</flux:text>
                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $size['count'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-500">{{ __('Average') }}</flux:text>
                                <span class="text-sm font-mono font-medium text-zinc-900 dark:text-zinc-100">{{ $this->formatTime($size['average']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-500">{{ __('Fastest') }}</flux:text>
                                <span class="text-sm font-mono font-medium text-emerald-600 dark:text-emerald-400">{{ $this->formatTime($size['fastest']) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Solve History --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-4">{{ __('Solve History') }}</flux:heading>

        @if($this->completedAttempts->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-zinc-300 py-8 dark:border-zinc-600">
                <flux:icon name="clock" class="mb-2 size-8 text-zinc-400" />
                <flux:text size="sm" class="text-zinc-400">{{ __('Complete puzzles to see your solve history here.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="pb-2 text-left font-medium text-zinc-500">{{ __('Puzzle') }}</th>
                            <th class="pb-2 text-left font-medium text-zinc-500">{{ __('Size') }}</th>
                            <th class="pb-2 text-right font-medium text-zinc-500">{{ __('Solve Time') }}</th>
                            <th class="pb-2 text-right font-medium text-zinc-500">{{ __('Completed') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->completedAttempts as $attempt)
                            <tr>
                                <td class="py-2.5">
                                    <a href="{{ route('crosswords.solver', $attempt->crossword_id) }}" wire:navigate class="font-medium text-zinc-900 hover:text-blue-600 dark:text-zinc-100 dark:hover:text-blue-400">
                                        {{ $attempt->crossword->title ?: __('Untitled Puzzle') }}
                                    </a>
                                </td>
                                <td class="py-2.5 text-zinc-500">
                                    {{ $attempt->crossword->width }}&times;{{ $attempt->crossword->height }}
                                </td>
                                <td class="py-2.5 text-right font-mono text-zinc-900 dark:text-zinc-100">
                                    {{ $this->formatTime($attempt->solve_time_seconds) }}
                                </td>
                                <td class="py-2.5 text-right text-zinc-400">
                                    {{ $attempt->completed_at?->diffForHumans() ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
