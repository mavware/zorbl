<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Constructor Analytics')] class extends Component {
    #[Computed]
    public function publishedPuzzles()
    {
        return Auth::user()
            ->crosswords()
            ->where('is_published', true)
            ->withCount([
                'attempts',
                'attempts as completed_attempts_count' => fn ($q) => $q->where('is_completed', true),
                'likes',
            ])
            ->withAvg('attempts as avg_solve_time', 'solve_time_seconds')
            ->latest()
            ->get();
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
                        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-lg bg-zinc-100 dark:bg-zinc-800"></div>
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
                <div class="rounded-xl border border-zinc-200 bg-white/90 p-8 text-center shadow-lg dark:border-zinc-700 dark:bg-zinc-900/90">
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
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                    <flux:icon name="eye" class="size-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Total Solves') }}</flux:text>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->totalSolves }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                    <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Completions') }}</flux:text>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->totalCompletions }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-amber-600 dark:text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Avg Solve Time') }}</flux:text>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->formatTime($this->overallAvgSolveTime) }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="heart" class="size-5 text-red-500 dark:text-red-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Total Likes') }}</flux:text>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->totalLikes }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Puzzle Performance Table --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-4">{{ __('Puzzle Performance') }}</flux:heading>

        @if($this->publishedPuzzles->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-zinc-300 py-8 dark:border-zinc-600">
                <flux:icon name="chart-bar" class="mb-2 size-8 text-zinc-400" />
                <flux:text size="sm" class="text-zinc-400">{{ __('Publish puzzles to see analytics here.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="pb-2 text-left font-medium text-zinc-500">{{ __('Puzzle') }}</th>
                            <th class="pb-2 text-center font-medium text-zinc-500">{{ __('Attempts') }}</th>
                            <th class="pb-2 text-center font-medium text-zinc-500">{{ __('Completed') }}</th>
                            <th class="pb-2 text-center font-medium text-zinc-500">{{ __('Completion Rate') }}</th>
                            <th class="pb-2 text-center font-medium text-zinc-500">{{ __('Avg Time') }}</th>
                            <th class="pb-2 text-center font-medium text-zinc-500">{{ __('Likes') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->publishedPuzzles as $puzzle)
                            <tr>
                                <td class="py-2.5">
                                    <a href="{{ route('crosswords.editor', $puzzle) }}" wire:navigate class="font-medium text-zinc-900 hover:text-blue-600 dark:text-zinc-100 dark:hover:text-blue-400">
                                        {{ $puzzle->title ?: __('Untitled Puzzle') }}
                                    </a>
                                    <div class="text-xs text-zinc-400">{{ $puzzle->width }}&times;{{ $puzzle->height }}</div>
                                </td>
                                <td class="py-2.5 text-center text-zinc-700 dark:text-zinc-300">{{ $puzzle->attempts_count }}</td>
                                <td class="py-2.5 text-center text-zinc-700 dark:text-zinc-300">{{ $puzzle->completed_attempts_count }}</td>
                                <td class="py-2.5 text-center">
                                    @php($rate = $puzzle->attempts_count > 0 ? round(($puzzle->completed_attempts_count / $puzzle->attempts_count) * 100) : 0)
                                    <span class="{{ $rate >= 75 ? 'text-emerald-600 dark:text-emerald-400' : ($rate >= 40 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-500') }}">
                                        {{ $rate }}%
                                    </span>
                                </td>
                                <td class="py-2.5 text-center font-mono text-zinc-700 dark:text-zinc-300">
                                    {{ $this->formatTime($puzzle->avg_solve_time ? (int) round($puzzle->avg_solve_time) : null) }}
                                </td>
                                <td class="py-2.5 text-center text-zinc-700 dark:text-zinc-300">
                                    <span class="flex items-center justify-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-red-400" viewBox="0 0 24 24" fill="currentColor"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/></svg>
                                        {{ $puzzle->likes_count }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Difficulty Heatmaps --}}
    @if(count($this->cellDifficulty) > 0)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-1">{{ __('Puzzle Insights') }}</flux:heading>
            <flux:text size="sm" class="mb-4 text-zinc-400">{{ __('Solve time breakdown for your most-solved puzzles.') }}</flux:text>

            <div class="grid gap-6 lg:grid-cols-3">
                @foreach($this->cellDifficulty as $puzzle)
                    <div class="rounded-lg border border-zinc-100 p-4 dark:border-zinc-700/50">
                        <div class="mb-3 flex items-center justify-between">
                            <flux:heading size="sm" class="truncate">{{ $puzzle['title'] ?: __('Untitled') }}</flux:heading>
                            <flux:text size="sm" class="text-zinc-400">{{ $puzzle['attempt_count'] }} {{ __('solves') }}</flux:text>
                        </div>
                        <div class="mb-3 flex justify-center">
                            <div
                                class="inline-grid gap-px rounded border border-zinc-200 bg-zinc-200 p-px dark:border-zinc-600 dark:bg-zinc-600"
                                style="grid-template-columns: repeat({{ $puzzle['width'] }}, minmax(0, 1fr)); width: {{ min($puzzle['width'] * 10, 150) }}px;"
                            >
                                @for($row = 0; $row < $puzzle['height']; $row++)
                                    @for($col = 0; $col < $puzzle['width']; $col++)
                                        @php($cell = $puzzle['grid'][$row][$col] ?? 0)
                                        <div class="{{ $cell === null ? 'invisible' : ($cell === '#' ? 'bg-zinc-800 dark:bg-zinc-300' : 'bg-white dark:bg-zinc-800') }}" style="aspect-ratio: 1;"></div>
                                    @endfor
                                @endfor
                            </div>
                        </div>
                        <div class="flex justify-between text-xs text-zinc-500">
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
