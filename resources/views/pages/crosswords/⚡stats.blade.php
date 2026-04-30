<?php

use App\Models\PuzzleAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Solve Statistics')] class extends Component {
    #[Url]
    public string $sortField = 'completed_at';

    #[Url]
    public string $sortDirection = 'desc';

    #[Computed]
    public function completedAttempts()
    {
        $query = Auth::user()
            ->puzzleAttempts()
            ->where('is_completed', true)
            ->whereNotNull('solve_time_seconds')
            ->with('crossword:id,title,width,height,author');

        $allowed = ['solve_time_seconds', 'completed_at'];
        $field = in_array($this->sortField, $allowed) ? $this->sortField : 'completed_at';
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

    #[Computed]
    public function communityAverages(): array
    {
        $crosswordIds = $this->completedAttempts->pluck('crossword_id')->unique();

        if ($crosswordIds->isEmpty()) {
            return [];
        }

        return DB::table('puzzle_attempts')
            ->select('crossword_id', DB::raw('AVG(solve_time_seconds) as avg_time'), DB::raw('COUNT(*) as solver_count'))
            ->where('is_completed', true)
            ->whereNotNull('solve_time_seconds')
            ->whereIn('crossword_id', $crosswordIds)
            ->groupBy('crossword_id')
            ->get()
            ->keyBy('crossword_id')
            ->map(fn ($row) => [
                'avg_time' => (int) round($row->avg_time),
                'solver_count' => $row->solver_count,
            ])
            ->all();
    }

    #[Computed]
    public function fasterThanAverageCount(): int
    {
        $averages = $this->communityAverages;

        return $this->completedAttempts->filter(function (PuzzleAttempt $attempt) use ($averages) {
            $avg = $averages[$attempt->crossword_id] ?? null;

            return $avg && $avg['solver_count'] > 1 && $attempt->solve_time_seconds < $avg['avg_time'];
        })->count();
    }

    #[Computed]
    public function puzzlesWithCommunityData(): int
    {
        $averages = $this->communityAverages;

        return $this->completedAttempts->filter(function (PuzzleAttempt $attempt) use ($averages) {
            $avg = $averages[$attempt->crossword_id] ?? null;

            return $avg && $avg['solver_count'] > 1;
        })->count();
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
        <div class="border-line rounded-xl border p-5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/30">
                    <flux:icon name="fire" class="size-5 text-orange-600 dark:text-orange-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-600">{{ __('Current Streak') }}</flux:text>
                    <div class="text-2xl font-bold text-fg">
                        {{ Auth::user()->current_streak }} {{ __('days') }}
                    </div>
                    <flux:text size="sm" class="text-zinc-500">
                        {{ __('Best: :days days', ['days' => Auth::user()->longest_streak]) }}
                    </flux:text>
                </div>
            </div>
        </div>

        <div class="border-line rounded-xl border p-5">
            <flux:heading size="sm" class="mb-3">{{ __('Achievements') }}</flux:heading>
            @php($achievements = Auth::user()->achievements()->orderBy('earned_at', 'desc')->get())
            @if($achievements->isEmpty())
                <flux:text size="sm" class="text-zinc-500">{{ __('Complete puzzles to earn achievements!') }}</flux:text>
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
    <div class="grid gap-4 sm:grid-cols-4">
        <div class="border-line rounded-xl border p-5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                    <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-600">{{ __('Puzzles Solved') }}</flux:text>
                    <div class="text-2xl font-bold text-fg">{{ $this->totalSolved }}</div>
                </div>
            </div>
        </div>

        <div class="border-line rounded-xl border p-5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-600">{{ __('Average Time') }}</flux:text>
                    <div class="text-2xl font-bold text-fg">{{ $this->formatTime($this->averageTime) }}</div>
                </div>
            </div>
        </div>

        <div class="border-line rounded-xl border p-5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-amber-600 dark:text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-600">{{ __('Fastest Solve') }}</flux:text>
                    <div class="text-2xl font-bold text-fg">{{ $this->fastestSolve ? $this->formatTime($this->fastestSolve->solve_time_seconds) : '—' }}</div>
                    @if($this->fastestSolve)
                        <flux:text size="sm" class="truncate text-zinc-500">{{ $this->fastestSolve->crossword->title }}</flux:text>
                    @endif
                </div>
            </div>
        </div>

        @if($this->puzzlesWithCommunityData > 0)
            <div class="border-line rounded-xl border p-5">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
                        <flux:icon name="arrow-trending-up" class="size-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <flux:text size="sm" class="text-zinc-600">{{ __('Faster Than Avg') }}</flux:text>
                        <div class="text-2xl font-bold text-fg">
                            {{ round(($this->fasterThanAverageCount / $this->puzzlesWithCommunityData) * 100) }}%
                        </div>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __(':count of :total puzzles', ['count' => $this->fasterThanAverageCount, 'total' => $this->puzzlesWithCommunityData]) }}
                        </flux:text>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Average by Size --}}
    @if(count($this->averageBySize) > 0)
        <div class="border-line rounded-xl border p-5">
            <flux:heading size="lg" class="mb-4">{{ __('Times by Grid Size') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach($this->averageBySize as $size)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700/50">
                        <flux:heading size="sm">{{ $size['label'] }}</flux:heading>
                        <div class="mt-2 space-y-1">
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-600">{{ __('Solved') }}</flux:text>
                                <span class="text-sm font-medium text-fg">{{ $size['count'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-600">{{ __('Average') }}</flux:text>
                                <span class="text-sm font-mono font-medium text-fg">{{ $this->formatTime($size['average']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-600">{{ __('Fastest') }}</flux:text>
                                <span class="text-sm font-mono font-medium text-emerald-600 dark:text-emerald-400">{{ $this->formatTime($size['fastest']) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Solve History --}}
    <div class="border-line rounded-xl border p-5">
        <flux:heading size="lg" class="mb-4">{{ __('Solve History') }}</flux:heading>

        @if($this->completedAttempts->isEmpty())
            <div class="border-line-strong flex flex-col items-center justify-center rounded-lg border border-dashed py-8">
                <flux:icon name="clock" class="mb-2 size-8 text-zinc-500" />
                <flux:text size="sm" class="text-zinc-500">{{ __('Complete puzzles to see your solve history here.') }}</flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Puzzle') }}</flux:table.column>
                    <flux:table.column>{{ __('Size') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'solve_time_seconds'" :direction="$sortDirection" wire:click="sortBy('solve_time_seconds')" align="end">{{ __('Solve Time') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('vs. Avg') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'completed_at'" :direction="$sortDirection" wire:click="sortBy('completed_at')" align="end">{{ __('Completed') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->completedAttempts as $attempt)
                        @php($communityAvg = $this->communityAverages[$attempt->crossword_id] ?? null)
                        @php($diff = $communityAvg && $communityAvg['solver_count'] > 1 ? $attempt->solve_time_seconds - $communityAvg['avg_time'] : null)
                        <flux:table.row :key="$attempt->id">
                            <flux:table.cell variant="strong">
                                <a href="{{ route('crosswords.solver', $attempt->crossword_id) }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $attempt->crossword->title ?: __('Untitled Puzzle') }}
                                </a>
                            </flux:table.cell>
                            <flux:table.cell>{{ $attempt->crossword->width }}&times;{{ $attempt->crossword->height }}</flux:table.cell>
                            <flux:table.cell align="end" class="font-mono">{{ $this->formatTime($attempt->solve_time_seconds) }}</flux:table.cell>
                            <flux:table.cell align="end" class="font-mono text-sm">
                                @if($diff !== null)
                                    @if($diff < 0)
                                        <span class="text-emerald-600 dark:text-emerald-400">{{ $this->formatTime(abs($diff)) }} {{ __('faster') }}</span>
                                    @elseif($diff > 0)
                                        <span class="text-zinc-500">{{ $this->formatTime($diff) }} {{ __('slower') }}</span>
                                    @else
                                        <span class="text-zinc-500">{{ __('same') }}</span>
                                    @endif
                                @else
                                    <span class="text-zinc-400 dark:text-zinc-600">—</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $attempt->completed_at?->diffForHumans() ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</div>
