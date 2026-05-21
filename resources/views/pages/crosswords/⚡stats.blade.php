<?php

use App\Models\PuzzleAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Solve Statistics')] class extends Component {
    use WithPagination;

    #[Url]
    public string $sortField = 'completed_at';

    #[Url]
    public string $sortDirection = 'desc';

    #[Computed]
    public function completedAttempts()
    {
        return Auth::user()
            ->puzzleAttempts()
            ->where('is_completed', true)
            ->whereNotNull('solve_time_seconds')
            ->with('crossword:id,title,width,height,author,difficulty_label')
            ->get();
    }

    #[Computed]
    public function paginatedAttempts()
    {
        $allowed = ['solve_time_seconds', 'completed_at'];
        $field = in_array($this->sortField, $allowed) ? $this->sortField : 'completed_at';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return Auth::user()
            ->puzzleAttempts()
            ->where('is_completed', true)
            ->whereNotNull('solve_time_seconds')
            ->with('crossword:id,title,width,height,author,difficulty_label')
            ->orderBy($field, $direction)
            ->paginate(15);
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    #[Computed]
    public function totalSolved(): int
    {
        return Auth::user()->puzzleAttempts()->where('is_completed', true)->count();
    }

    #[Computed]
    public function averageTime(): ?int
    {
        $avg = Auth::user()
            ->puzzleAttempts()
            ->where('is_completed', true)
            ->whereNotNull('solve_time_seconds')
            ->avg('solve_time_seconds');

        return $avg ? (int) round($avg) : null;
    }

    #[Computed]
    public function fastestSolve(): ?PuzzleAttempt
    {
        return Auth::user()
            ->puzzleAttempts()
            ->where('is_completed', true)
            ->whereNotNull('solve_time_seconds')
            ->with('crossword:id,title')
            ->orderBy('solve_time_seconds')
            ->first();
    }

    #[Computed]
    public function averageBySize(): array
    {
        return Auth::user()
            ->puzzleAttempts()
            ->where('is_completed', true)
            ->whereNotNull('solve_time_seconds')
            ->with('crossword:id,width,height')
            ->get(['id', 'crossword_id', 'solve_time_seconds'])
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
    public function averageByDifficulty(): array
    {
        return $this->completedAttempts
            ->filter(fn (PuzzleAttempt $a) => $a->crossword->difficulty_label !== null)
            ->groupBy(fn (PuzzleAttempt $a) => $a->crossword->difficulty_label)
            ->map(fn ($group, $label) => [
                'label' => $label,
                'count' => $group->count(),
                'average' => (int) round($group->avg('solve_time_seconds')),
                'fastest' => (int) $group->min('solve_time_seconds'),
            ])
            ->sortBy(fn ($item) => match ($item['label']) {
                'Easy' => 0,
                'Medium' => 1,
                'Hard' => 2,
                'Expert' => 3,
                default => 4,
            })
            ->values()
            ->all();
    }

    #[Computed]
    public function communityAverages(): array
    {
        $crosswordIds = $this->paginatedAttempts->pluck('crossword_id')->unique();

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
    public function communityComparison(): array
    {
        $subquery = DB::table('puzzle_attempts')
            ->select('crossword_id', DB::raw('AVG(solve_time_seconds) as avg_time'))
            ->where('is_completed', true)
            ->whereNotNull('solve_time_seconds')
            ->groupBy('crossword_id')
            ->havingRaw('COUNT(*) > 1');

        $result = DB::table('puzzle_attempts', 'pa')
            ->joinSub($subquery, 'community', 'pa.crossword_id', '=', 'community.crossword_id')
            ->where('pa.user_id', Auth::id())
            ->where('pa.is_completed', true)
            ->whereNotNull('pa.solve_time_seconds')
            ->selectRaw('COUNT(*) as total_with_community')
            ->selectRaw('SUM(CASE WHEN pa.solve_time_seconds < community.avg_time THEN 1 ELSE 0 END) as faster_count')
            ->first();

        return [
            'total' => (int) ($result->total_with_community ?? 0),
            'faster' => (int) ($result->faster_count ?? 0),
        ];
    }

    /**
     * @return array{days: array<string, int>, weeks: list<list<array{date: string, count: int, level: int, day: int}>>, months: list<array{label: string, col: int}>, totalInRange: int}
     */
    #[Computed]
    public function activityHeatmap(): array
    {
        $end = CarbonImmutable::today();
        $start = $end->subWeeks(52)->startOfWeek(CarbonImmutable::SUNDAY);

        $counts = Auth::user()
            ->puzzleAttempts()
            ->where('is_completed', true)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $start)
            ->select(DB::raw('DATE(completed_at) as solve_date'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('solve_date')
            ->pluck('cnt', 'solve_date')
            ->all();

        $max = max(1, max($counts ?: [0]));
        $weeks = [];
        $currentWeek = [];
        $months = [];
        $lastMonth = null;
        $colIndex = 0;
        $totalInRange = 0;

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d');
            $count = $counts[$key] ?? 0;
            $totalInRange += $count;

            $level = match (true) {
                $count === 0 => 0,
                $count <= ceil($max * 0.25) => 1,
                $count <= ceil($max * 0.50) => 2,
                $count <= ceil($max * 0.75) => 3,
                default => 4,
            };

            $currentWeek[] = [
                'date' => $key,
                'count' => $count,
                'level' => $level,
                'day' => $cursor->dayOfWeek,
            ];

            $monthLabel = $cursor->format('M');
            if ($monthLabel !== $lastMonth) {
                $months[] = ['label' => $monthLabel, 'col' => $colIndex];
                $lastMonth = $monthLabel;
            }

            if ($cursor->dayOfWeek === CarbonImmutable::SATURDAY) {
                $weeks[] = $currentWeek;
                $currentWeek = [];
                $colIndex++;
            }

            $cursor = $cursor->addDay();
        }

        if (! empty($currentWeek)) {
            $weeks[] = $currentWeek;
        }

        return [
            'days' => $counts,
            'weeks' => $weeks,
            'months' => $months,
            'totalInRange' => $totalInRange,
        ];
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

    {{-- Solve Activity Heatmap --}}
    @php($heatmap = $this->activityHeatmap)
    <div class="border-line rounded-xl border p-5">
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="lg">{{ __('Solve Activity') }}</flux:heading>
            <flux:text size="sm" class="text-zinc-500">
                {{ trans_choice(':count puzzle solved in the last year|:count puzzles solved in the last year', $heatmap['totalInRange'], ['count' => $heatmap['totalInRange']]) }}
            </flux:text>
        </div>

        <div class="overflow-x-auto" data-test="activity-heatmap">
            <div class="inline-flex flex-col">
                {{-- Month labels --}}
                <div class="relative mb-1" style="padding-left: 30px; height: 16px;">
                    @php($prevCol = -4)
                    @foreach($heatmap['months'] as $month)
                        @if($month['col'] - $prevCol >= 3)
                            <span class="absolute text-[10px] text-zinc-400 dark:text-zinc-500" style="left: {{ 30 + $month['col'] * 15 }}px;">{{ $month['label'] }}</span>
                            @php($prevCol = $month['col'])
                        @endif
                    @endforeach
                </div>

                <div class="flex gap-[3px]">
                    {{-- Day labels --}}
                    <div class="flex flex-col gap-[3px] text-[10px] text-zinc-400 dark:text-zinc-500" style="width: 26px;">
                        <span class="h-[13px]"></span>
                        <span class="flex h-[13px] items-center">{{ __('Mon') }}</span>
                        <span class="h-[13px]"></span>
                        <span class="flex h-[13px] items-center">{{ __('Wed') }}</span>
                        <span class="h-[13px]"></span>
                        <span class="flex h-[13px] items-center">{{ __('Fri') }}</span>
                        <span class="h-[13px]"></span>
                    </div>

                    {{-- Grid --}}
                    @foreach($heatmap['weeks'] as $week)
                        <div class="flex flex-col gap-[3px]">
                            @foreach($week as $day)
                                @php($bgClass = match($day['level']) {
                                    0 => 'bg-zinc-100 dark:bg-zinc-800',
                                    1 => 'bg-emerald-200 dark:bg-emerald-900',
                                    2 => 'bg-emerald-400 dark:bg-emerald-700',
                                    3 => 'bg-emerald-500 dark:bg-emerald-500',
                                    4 => 'bg-emerald-700 dark:bg-emerald-400',
                                    default => 'bg-zinc-100 dark:bg-zinc-800',
                                })
                                <div
                                    class="size-[13px] rounded-sm {{ $bgClass }}"
                                    title="{{ \Carbon\Carbon::parse($day['date'])->format('M j, Y') }}: {{ trans_choice(':count solve|:count solves', $day['count'], ['count' => $day['count']]) }}"
                                ></div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                {{-- Legend --}}
                <div class="mt-2 flex items-center justify-end gap-1.5 text-[10px] text-zinc-400 dark:text-zinc-500">
                    <span>{{ __('Less') }}</span>
                    <div class="size-[11px] rounded-sm bg-zinc-100 dark:bg-zinc-800"></div>
                    <div class="size-[11px] rounded-sm bg-emerald-200 dark:bg-emerald-900"></div>
                    <div class="size-[11px] rounded-sm bg-emerald-400 dark:bg-emerald-700"></div>
                    <div class="size-[11px] rounded-sm bg-emerald-500 dark:bg-emerald-500"></div>
                    <div class="size-[11px] rounded-sm bg-emerald-700 dark:bg-emerald-400"></div>
                    <span>{{ __('More') }}</span>
                </div>
            </div>
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

        @if($this->communityComparison['total'] > 0)
            <div class="border-line rounded-xl border p-5">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
                        <flux:icon name="arrow-trending-up" class="size-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <flux:text size="sm" class="text-zinc-600">{{ __('Faster Than Avg') }}</flux:text>
                        <div class="text-2xl font-bold text-fg">
                            {{ round(($this->communityComparison['faster'] / $this->communityComparison['total']) * 100) }}%
                        </div>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __(':count of :total puzzles', ['count' => $this->communityComparison['faster'], 'total' => $this->communityComparison['total']]) }}
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

    {{-- Average by Difficulty --}}
    @if(count($this->averageByDifficulty) > 0)
        <div class="border-line rounded-xl border p-5">
            <flux:heading size="lg" class="mb-4">{{ __('Times by Difficulty') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-4">
                @foreach($this->averageByDifficulty as $difficulty)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700/50">
                        <flux:heading size="sm">
                            @php($colors = ['Easy' => 'text-emerald-600 dark:text-emerald-400', 'Medium' => 'text-blue-600 dark:text-blue-400', 'Hard' => 'text-amber-600 dark:text-amber-400', 'Expert' => 'text-red-600 dark:text-red-400'])
                            <span class="{{ $colors[$difficulty['label']] ?? 'text-fg' }}">{{ $difficulty['label'] }}</span>
                        </flux:heading>
                        <div class="mt-2 space-y-1">
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-600">{{ __('Solved') }}</flux:text>
                                <span class="text-sm font-medium text-fg">{{ $difficulty['count'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-600">{{ __('Average') }}</flux:text>
                                <span class="text-sm font-mono font-medium text-fg">{{ $this->formatTime($difficulty['average']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-600">{{ __('Fastest') }}</flux:text>
                                <span class="text-sm font-mono font-medium text-emerald-600 dark:text-emerald-400">{{ $this->formatTime($difficulty['fastest']) }}</span>
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

        @if($this->paginatedAttempts->isEmpty())
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
                    @foreach($this->paginatedAttempts as $attempt)
                        @php($communityAvg = $this->communityAverages[$attempt->crossword_id] ?? null)
                        @php($diff = $communityAvg && $communityAvg['solver_count'] > 1 ? $attempt->solve_time_seconds - $communityAvg['avg_time'] : null)
                        <flux:table.row :key="$attempt->id">
                            <flux:table.cell variant="strong">
                                <a href="{{ route('crosswords.solver', $attempt->crossword_id) }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $attempt->crossword->displayTitle() }}
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

            <div class="mt-4">
                {{ $this->paginatedAttempts->links() }}
            </div>
        @endif
    </div>
</div>
