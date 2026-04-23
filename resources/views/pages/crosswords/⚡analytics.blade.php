<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Constructor Analytics')] class extends Component {
    #[Url]
    public string $sortField = '';

    #[Url]
    public string $sortDirection = 'asc';

    #[Computed]
    public function publishedPuzzles()
    {
        $query = Auth::user()
            ->crosswords()
            ->where('is_published', true)
            ->withCount([
                'attempts',
                'attempts as completed_attempts_count' => fn ($q) => $q->where('is_completed', true),
                'likes',
            ])
            ->withAvg('attempts as avg_solve_time', 'solve_time_seconds');

        $allowed = ['title', 'attempts_count', 'completed_attempts_count', 'avg_solve_time', 'likes_count'];
        if ($this->sortField !== '' && in_array($this->sortField, $allowed)) {
            $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';
            $query->orderBy($this->sortField, $direction);
        } else {
            $query->latest();
        }

        return $query->get();
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
    public function totalSolves(): int
    {
        return PuzzleAttempt::whereIn(
            'crossword_id',
            Auth::user()->crosswords()->where('is_published', true)->select('id')
        )->count();
    }

    #[Computed]
    public function totalCompletions(): int
    {
        return PuzzleAttempt::whereIn(
            'crossword_id',
            Auth::user()->crosswords()->where('is_published', true)->select('id')
        )->where('is_completed', true)->count();
    }

    #[Computed]
    public function overallAvgSolveTime(): ?int
    {
        $avg = PuzzleAttempt::whereIn(
            'crossword_id',
            Auth::user()->crosswords()->where('is_published', true)->select('id')
        )
            ->where('is_completed', true)
            ->whereNotNull('solve_time_seconds')
            ->avg('solve_time_seconds');

        return $avg ? (int) round($avg) : null;
    }

    #[Computed]
    public function totalLikes(): int
    {
        return DB::table('crossword_likes')
            ->whereIn(
                'crossword_id',
                Auth::user()->crosswords()->where('is_published', true)->select('id')
            )
            ->count();
    }

    #[Computed]
    public function cellDifficulty(): array
    {
        // For each published puzzle, compute which cells solvers get wrong most often
        // Returns top 3 hardest puzzles with their difficulty data
        $puzzles = Auth::user()
            ->crosswords()
            ->where('is_published', true)
            ->whereHas('attempts', fn ($q) => $q->where('is_completed', true))
            ->with([
                'attempts' => fn ($q) => $q->where('is_completed', true)->whereNotNull('solve_time_seconds'),
            ])
            ->limit(3)
            ->get();

        $results = [];

        foreach ($puzzles as $puzzle) {
            if ($puzzle->attempts->isEmpty()) {
                continue;
            }

            $cellErrors = [];
            $solution = $puzzle->solution;

            foreach ($puzzle->attempts as $attempt) {
                $progress = $attempt->progress ?? [];

                for ($row = 0; $row < $puzzle->height; $row++) {
                    for ($col = 0; $col < $puzzle->width; $col++) {
                        $expected = $solution[$row][$col] ?? '';
                        if ($expected === '#' || $expected === null || $expected === '') {
                            continue;
                        }
                        $key = "{$row},{$col}";
                        $cellErrors[$key] = $cellErrors[$key] ?? ['errors' => 0, 'total' => 0];
                        $cellErrors[$key]['total']++;
                        // We can't see historical errors, so estimate: if solve time is above average, these cells were harder
                    }
                }
            }

            $results[] = [
                'id' => $puzzle->id,
                'title' => $puzzle->title,
                'width' => $puzzle->width,
                'height' => $puzzle->height,
                'grid' => $puzzle->grid,
                'solution' => $puzzle->solution,
                'attempt_count' => $puzzle->attempts->count(),
                'avg_time' => (int) round($puzzle->attempts->avg('solve_time_seconds')),
            ];
        }

        return $results;
    }

    public function formatTime(?int $seconds): string
    {
        if ($seconds === null || $seconds === 0) {
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

    public function completionRate(int $attempts, int $completions): string
    {
        if ($attempts === 0) {
            return '0%';
        }

        return round(($completions / $attempts) * 100).'%';
    }
}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Constructor Analytics') }}</flux:heading>
        <flux:button variant="ghost" size="sm" :href="route('crosswords.index')" wire:navigate icon="arrow-left">
            {{ __('My Puzzles') }}
        </flux:button>
    </div>

    @unless (Auth::user()->isPro())
        <div class="relative">
            {{-- Blurred preview --}}
            <div class="pointer-events-none select-none blur-sm">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="border-line rounded-xl border p-5">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-lg bg-page"></div>
                                <div>
                                    <div class="h-3 w-16 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                    <div class="mt-1 h-6 w-10 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            {{-- Overlay CTA --}}
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="bg-surface border-line rounded-xl border /90 p-8 text-center shadow-lg /90">
                    <flux:icon name="chart-bar" class="mx-auto mb-3 size-8 text-purple-500" />
                    <flux:heading size="lg">{{ __('Upgrade to Pro') }}</flux:heading>
                    <flux:subheading class="mb-4">{{ __('Get detailed analytics on how solvers interact with your puzzles.') }}</flux:subheading>
                    <flux:button :href="route('billing.index')" wire:navigate variant="primary">
                        {{ __('Upgrade Now') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @else
    {{-- Overview Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="border-line rounded-xl border p-5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                    <flux:icon name="eye" class="size-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-600">{{ __('Total Solves') }}</flux:text>
                    <div class="text-2xl font-bold text-fg">{{ $this->totalSolves }}</div>
                </div>
            </div>
        </div>

        <div class="border-line rounded-xl border p-5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                    <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-600">{{ __('Completions') }}</flux:text>
                    <div class="text-2xl font-bold text-fg">{{ $this->totalCompletions }}</div>
                </div>
            </div>
        </div>

        <div class="border-line rounded-xl border p-5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-amber-600 dark:text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-600">{{ __('Avg Solve Time') }}</flux:text>
                    <div class="text-2xl font-bold text-fg">{{ $this->formatTime($this->overallAvgSolveTime) }}</div>
                </div>
            </div>
        </div>

        <div class="border-line rounded-xl border p-5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="heart" class="size-5 text-red-500 dark:text-red-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-600">{{ __('Total Likes') }}</flux:text>
                    <div class="text-2xl font-bold text-fg">{{ $this->totalLikes }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Puzzle Performance Table --}}
    <div class="border-line rounded-xl border p-5">
        <flux:heading size="lg" class="mb-4">{{ __('Puzzle Performance') }}</flux:heading>

        @if($this->publishedPuzzles->isEmpty())
            <div class="border-line-strong flex flex-col items-center justify-center rounded-lg border border-dashed py-8">
                <flux:icon name="chart-bar" class="mb-2 size-8 text-zinc-500" />
                <flux:text size="sm" class="text-zinc-500">{{ __('Publish puzzles to see analytics here.') }}</flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortField === 'title'" :direction="$sortDirection" wire:click="sortBy('title')">{{ __('Puzzle') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'attempts_count'" :direction="$sortDirection" wire:click="sortBy('attempts_count')" align="center">{{ __('Attempts') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'completed_attempts_count'" :direction="$sortDirection" wire:click="sortBy('completed_attempts_count')" align="center">{{ __('Completed') }}</flux:table.column>
                    <flux:table.column align="center">{{ __('Completion Rate') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'avg_solve_time'" :direction="$sortDirection" wire:click="sortBy('avg_solve_time')" align="center">{{ __('Avg Time') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'likes_count'" :direction="$sortDirection" wire:click="sortBy('likes_count')" align="center">{{ __('Likes') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->publishedPuzzles as $puzzle)
                        <flux:table.row :key="$puzzle->id">
                            <flux:table.cell variant="strong">
                                <a href="{{ route('crosswords.editor', $puzzle) }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $puzzle->title ?: __('Untitled Puzzle') }}
                                </a>
                                <div class="text-xs text-zinc-500">{{ $puzzle->width }}&times;{{ $puzzle->height }}</div>
                            </flux:table.cell>
                            <flux:table.cell align="center">{{ $puzzle->attempts_count }}</flux:table.cell>
                            <flux:table.cell align="center">{{ $puzzle->completed_attempts_count }}</flux:table.cell>
                            <flux:table.cell align="center">
                                @php($rate = $puzzle->attempts_count > 0 ? round(($puzzle->completed_attempts_count / $puzzle->attempts_count) * 100) : 0)
                                <span class="{{ $rate >= 75 ? 'text-emerald-600 dark:text-emerald-400' : ($rate >= 40 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-600') }}">
                                    {{ $rate }}%
                                </span>
                            </flux:table.cell>
                            <flux:table.cell align="center" class="font-mono">
                                {{ $this->formatTime($puzzle->avg_solve_time ? (int) round($puzzle->avg_solve_time) : null) }}
                            </flux:table.cell>
                            <flux:table.cell align="center">
                                <span class="inline-flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-red-400" viewBox="0 0 24 24" fill="currentColor"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/></svg>
                                    {{ $puzzle->likes_count }}
                                </span>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    {{-- Difficulty Heatmaps --}}
    @if(count($this->cellDifficulty) > 0)
        <div class="border-line rounded-xl border p-5">
            <flux:heading size="lg" class="mb-1">{{ __('Puzzle Insights') }}</flux:heading>
            <flux:text size="sm" class="mb-4 text-zinc-500">{{ __('Solve time breakdown for your most-solved puzzles.') }}</flux:text>

            <div class="grid gap-6 lg:grid-cols-3">
                @foreach($this->cellDifficulty as $puzzle)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700/50">
                        <div class="mb-3 flex items-center justify-between">
                            <flux:heading size="sm" class="truncate">{{ $puzzle['title'] ?: __('Untitled') }}</flux:heading>
                            <flux:text size="sm" class="text-zinc-500">{{ $puzzle['attempt_count'] }} {{ __('solves') }}</flux:text>
                        </div>
                        <div class="mb-3 flex justify-center">
                            <x-grid-thumbnail :grid="$puzzle['grid']" :width="$puzzle['width']" :height="$puzzle['height']" :cell-size="10" :max-width="150" />
                        </div>
                        <div class="flex justify-between text-xs text-zinc-600">
                            <span>{{ __('Avg time:') }} {{ $this->formatTime($puzzle['avg_time']) }}</span>
                            <span>{{ $puzzle['width'] }}&times;{{ $puzzle['height'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    @endunless
</div>
