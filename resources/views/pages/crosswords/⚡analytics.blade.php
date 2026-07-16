<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
use Carbon\CarbonImmutable;
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
                'likes',
                'comments as reviews_count' => fn ($q) => $q->whereNotNull('rating'),
            ])
            ->withAvg('comments as avg_rating', 'rating');

        $allowed = ['title', 'cached_attempts_count', 'cached_completed_count', 'cached_avg_solve_time', 'likes_count', 'avg_rating'];
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
        return (int) Auth::user()->crosswords()
            ->where('is_published', true)
            ->sum('cached_attempts_count');
    }

    #[Computed]
    public function totalCompletions(): int
    {
        return (int) Auth::user()->crosswords()
            ->where('is_published', true)
            ->sum('cached_completed_count');
    }

    #[Computed]
    public function overallAvgSolveTime(): ?int
    {
        $avg = Auth::user()->crosswords()
            ->where('is_published', true)
            ->whereNotNull('cached_avg_solve_time')
            ->avg('cached_avg_solve_time');

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
    public function overallAvgRating(): ?float
    {
        $avg = PuzzleComment::whereIn(
            'crossword_id',
            Auth::user()->crosswords()->where('is_published', true)->select('id')
        )
            ->whereNotNull('rating')
            ->avg('rating');

        return $avg ? round((float) $avg, 1) : null;
    }

    #[Computed]
    public function totalReviews(): int
    {
        return PuzzleComment::whereIn(
            'crossword_id',
            Auth::user()->crosswords()->where('is_published', true)->select('id')
        )
            ->whereNotNull('rating')
            ->count();
    }

    /**
     * @return list<array{month: string, avg_rating: float, count: int}>
     */
    #[Computed]
    public function ratingTrend(): array
    {
        $publishedIds = Auth::user()
            ->crosswords()
            ->where('is_published', true)
            ->select('id');

        $cutoff = CarbonImmutable::now()->subMonths(11)->startOfMonth();

        return PuzzleComment::whereIn('crossword_id', $publishedIds)
            ->whereNotNull('rating')
            ->where('created_at', '>=', $cutoff)
            ->orderBy('created_at')
            ->get(['rating', 'created_at'])
            ->groupBy(fn (PuzzleComment $c) => $c->created_at->format('Y-m'))
            ->map(fn ($group, $month) => [
                'month' => $month,
                'avg_rating' => round($group->avg('rating'), 2),
                'count' => $group->count(),
            ])
            ->sortKeys()
            ->values()
            ->all();
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

    /**
     * @return list<array{puzzle_id: int, puzzle_title: string, prompt: string, accepted_answers: list<string>, responses: list<array{answer: string, count: int, is_correct: bool}>}>
     */
    #[Computed]
    public function metaAnswerResponses(): array
    {
        $puzzles = Auth::user()
            ->crosswords()
            ->where('is_published', true)
            ->whereNotNull('meta_answer_prompt')
            ->whereNotNull('meta_answers')
            ->get();

        $results = [];

        foreach ($puzzles as $puzzle) {
            if (! $puzzle->hasMetaAnswer()) {
                continue;
            }

            $responses = PuzzleAttempt::where('crossword_id', $puzzle->id)
                ->whereNotNull('meta_answer')
                ->where('meta_answer', '!=', '')
                ->select('meta_answer', DB::raw('count(*) as count'))
                ->groupBy('meta_answer')
                ->orderByDesc('count')
                ->limit(50)
                ->get();

            if ($responses->isEmpty()) {
                continue;
            }

            $results[] = [
                'puzzle_id' => $puzzle->id,
                'puzzle_title' => $puzzle->displayTitle(),
                'prompt' => $puzzle->meta_answer_prompt,
                'accepted_answers' => $puzzle->meta_answers,
                'responses' => $responses->map(fn ($r) => [
                    'answer' => $r->meta_answer,
                    'count' => $r->count,
                    'is_correct' => $puzzle->isMetaAnswerCorrect($r->meta_answer),
                ])->all(),
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
            {{ __('Build') }}
        </flux:button>
    </div>

    @unless (Auth::user()->isPro())
        <div class="relative">
            {{-- Blurred preview --}}
            <div class="pointer-events-none select-none blur-sm">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    @for ($i = 0; $i < 5; $i++)
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
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
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

        <div class="border-line rounded-xl border p-5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900/30">
                    <flux:icon name="star" class="size-5 text-yellow-500 dark:text-yellow-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-600">{{ __('Avg Rating') }}</flux:text>
                    <div class="text-2xl font-bold text-fg">{{ $this->overallAvgRating ?? '—' }}</div>
                    @if($this->totalReviews > 0)
                        <flux:text size="sm" class="text-zinc-500">{{ trans_choice(':count review|:count reviews', $this->totalReviews) }}</flux:text>
                    @endif
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
                    <flux:table.column sortable :sorted="$sortField === 'cached_attempts_count'" :direction="$sortDirection" wire:click="sortBy('cached_attempts_count')" align="center">{{ __('Attempts') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'cached_completed_count'" :direction="$sortDirection" wire:click="sortBy('cached_completed_count')" align="center">{{ __('Completed') }}</flux:table.column>
                    <flux:table.column align="center">{{ __('Completion Rate') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'cached_avg_solve_time'" :direction="$sortDirection" wire:click="sortBy('cached_avg_solve_time')" align="center">{{ __('Avg Time') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'likes_count'" :direction="$sortDirection" wire:click="sortBy('likes_count')" align="center">{{ __('Likes') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'avg_rating'" :direction="$sortDirection" wire:click="sortBy('avg_rating')" align="center">{{ __('Rating') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->publishedPuzzles as $puzzle)
                        <flux:table.row :key="$puzzle->id">
                            <flux:table.cell variant="strong">
                                <a href="{{ route('crosswords.editor', $puzzle) }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $puzzle->displayTitle() }}
                                </a>
                                <div class="text-xs text-zinc-500">{{ $puzzle->width }}&times;{{ $puzzle->height }}</div>
                            </flux:table.cell>
                            <flux:table.cell align="center">{{ $puzzle->cached_attempts_count }}</flux:table.cell>
                            <flux:table.cell align="center">{{ $puzzle->cached_completed_count }}</flux:table.cell>
                            <flux:table.cell align="center">
                                @php($rate = $puzzle->cached_attempts_count > 0 ? round(($puzzle->cached_completed_count / $puzzle->cached_attempts_count) * 100) : 0)
                                <span class="{{ $rate >= 75 ? 'text-emerald-600 dark:text-emerald-400' : ($rate >= 40 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-600') }}">
                                    {{ $rate }}%
                                </span>
                            </flux:table.cell>
                            <flux:table.cell align="center" class="font-mono">
                                {{ $this->formatTime($puzzle->cached_avg_solve_time) }}
                            </flux:table.cell>
                            <flux:table.cell align="center">
                                <span class="inline-flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-red-400" viewBox="0 0 24 24" fill="currentColor"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/></svg>
                                    {{ $puzzle->likes_count }}
                                </span>
                            </flux:table.cell>
                            <flux:table.cell align="center">
                                @if($puzzle->avg_rating)
                                    <span class="inline-flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-yellow-400" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
                                        {{ round($puzzle->avg_rating, 1) }}
                                    </span>
                                    @if($puzzle->reviews_count > 0)
                                        <div class="text-xs text-zinc-500">({{ $puzzle->reviews_count }})</div>
                                    @endif
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    {{-- Rating Trend Chart --}}
    @if(count($this->ratingTrend) >= 2)
        <div class="border-line rounded-xl border p-5">
            <flux:heading size="lg" class="mb-1">{{ __('Rating Trend') }}</flux:heading>
            <flux:text size="sm" class="mb-4 text-zinc-500">{{ __('Average rating received per month over the last 12 months.') }}</flux:text>

            <div
                x-data="{
                    points: @js($this->ratingTrend),
                    width: 600,
                    height: 200,
                    padX: 48,
                    padY: 24,
                    get chartWidth() { return this.width - this.padX * 2 },
                    get chartHeight() { return this.height - this.padY * 2 },
                    get minRating() { return Math.max(0, Math.floor(Math.min(...this.points.map(p => p.avg_rating)) * 2) / 2 - 0.5) },
                    get maxRating() { return Math.min(5, Math.ceil(Math.max(...this.points.map(p => p.avg_rating)) * 2) / 2 + 0.5) },
                    get ratingRange() { return this.maxRating - this.minRating || 1 },
                    x(i) { return this.padX + (i / (this.points.length - 1)) * this.chartWidth },
                    y(val) { return this.padY + this.chartHeight - ((val - this.minRating) / this.ratingRange) * this.chartHeight },
                    get linePath() {
                        return this.points.map((p, i) => `${i === 0 ? 'M' : 'L'}${this.x(i).toFixed(1)},${this.y(p.avg_rating).toFixed(1)}`).join(' ')
                    },
                    get areaPath() {
                        const bottom = this.padY + this.chartHeight;
                        return this.linePath + ` L${this.x(this.points.length - 1).toFixed(1)},${bottom} L${this.x(0).toFixed(1)},${bottom} Z`
                    },
                    get gridLines() {
                        const lines = [];
                        for (let v = Math.ceil(this.minRating); v <= Math.floor(this.maxRating); v++) {
                            lines.push({ y: this.y(v), label: v });
                        }
                        return lines;
                    },
                    formatMonth(m) {
                        const [y, mo] = m.split('-');
                        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                        return months[parseInt(mo) - 1];
                    },
                    tooltip: null,
                    showTooltip(i) {
                        const p = this.points[i];
                        this.tooltip = { x: this.x(i), y: this.y(p.avg_rating), rating: p.avg_rating, count: p.count, month: this.formatMonth(p.month) };
                    },
                    hideTooltip() { this.tooltip = null },
                }"
                class="w-full overflow-x-auto"
            >
                <svg :viewBox="`0 0 ${width} ${height}`" class="h-52 w-full min-w-[400px]" preserveAspectRatio="xMidYMid meet">
                    {{-- Grid lines --}}
                    <template x-for="line in gridLines" :key="line.label">
                        <g>
                            <line :x1="padX" :y1="line.y" :x2="width - padX" :y2="line.y" class="stroke-zinc-200 dark:stroke-zinc-700" stroke-dasharray="4 4" />
                            <text :x="padX - 8" :y="line.y + 4" text-anchor="end" class="fill-zinc-400 text-[11px]" x-text="line.label"></text>
                        </g>
                    </template>

                    {{-- Area fill --}}
                    <path :d="areaPath" class="fill-yellow-100/60 dark:fill-yellow-900/20" />

                    {{-- Line --}}
                    <path :d="linePath" fill="none" class="stroke-yellow-500" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

                    {{-- Data points --}}
                    <template x-for="(p, i) in points" :key="p.month">
                        <g>
                            <circle :cx="x(i)" :cy="y(p.avg_rating)" r="4" class="fill-yellow-500 stroke-white dark:stroke-zinc-900" stroke-width="2" @mouseenter="showTooltip(i)" @mouseleave="hideTooltip()" style="cursor: pointer" />
                        </g>
                    </template>

                    {{-- X-axis labels --}}
                    <template x-for="(p, i) in points" :key="'label-' + p.month">
                        <text :x="x(i)" :y="height - 4" text-anchor="middle" class="fill-zinc-400 text-[10px]" x-text="formatMonth(p.month)"></text>
                    </template>

                    {{-- Tooltip --}}
                    <g x-show="tooltip" x-cloak>
                        <rect :x="(tooltip?.x ?? 0) - 36" :y="(tooltip?.y ?? 0) - 42" width="72" height="34" rx="6" class="fill-zinc-800 dark:fill-zinc-200" opacity="0.95" />
                        <text :x="tooltip?.x ?? 0" :y="(tooltip?.y ?? 0) - 26" text-anchor="middle" class="fill-white dark:fill-zinc-900 text-[11px] font-semibold">
                            <tspan x-text="tooltip ? `★ ${tooltip.rating}` : ''"></tspan>
                        </text>
                        <text :x="tooltip?.x ?? 0" :y="(tooltip?.y ?? 0) - 14" text-anchor="middle" class="fill-zinc-300 dark:fill-zinc-500 text-[10px]">
                            <tspan x-text="tooltip ? `${tooltip.count} ${tooltip.count === 1 ? 'review' : 'reviews'}` : ''"></tspan>
                        </text>
                    </g>
                </svg>
            </div>
        </div>
    @elseif($this->totalReviews > 0 && count($this->ratingTrend) < 2)
        {{-- Not enough data points for a chart --}}
    @endif

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

    {{-- Meta Answer Responses --}}
    @if(count($this->metaAnswerResponses) > 0)
        <div class="border-line rounded-xl border p-5">
            <flux:heading size="lg" class="mb-1">{{ __('Meta Answer Responses') }}</flux:heading>
            <flux:text size="sm" class="mb-4 text-zinc-500">{{ __('See what solvers guessed for your themed puzzles.') }}</flux:text>

            <div class="space-y-6">
                @foreach($this->metaAnswerResponses as $puzzleData)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700/50">
                        <div class="mb-1 flex items-center justify-between">
                            <flux:heading size="sm">{{ $puzzleData['puzzle_title'] }}</flux:heading>
                        </div>
                        <flux:text size="sm" class="mb-3 text-zinc-500 italic">&ldquo;{{ $puzzleData['prompt'] }}&rdquo;</flux:text>

                        <div class="space-y-1.5">
                            @php($totalResponses = collect($puzzleData['responses'])->sum('count'))
                            @foreach($puzzleData['responses'] as $response)
                                @php($percentage = $totalResponses > 0 ? round(($response['count'] / $totalResponses) * 100) : 0)
                                <div class="relative overflow-hidden rounded-md border {{ $response['is_correct'] ? 'border-emerald-200 dark:border-emerald-800/50' : 'border-zinc-200 dark:border-zinc-700/50' }}">
                                    <div class="absolute inset-y-0 left-0 {{ $response['is_correct'] ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-zinc-50 dark:bg-zinc-800/30' }}" style="width: {{ $percentage }}%"></div>
                                    <div class="relative flex items-center justify-between px-3 py-1.5">
                                        <span class="flex items-center gap-2 text-sm">
                                            @if($response['is_correct'])
                                                <flux:icon name="check-circle" class="size-4 text-emerald-500" />
                                            @endif
                                            <span class="{{ $response['is_correct'] ? 'font-medium text-emerald-700 dark:text-emerald-400' : 'text-zinc-700 dark:text-zinc-300' }}">{{ $response['answer'] }}</span>
                                        </span>
                                        <span class="text-xs font-medium text-zinc-500">{{ $response['count'] }} ({{ $percentage }}%)</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-2 flex items-center justify-between text-xs text-zinc-500">
                            <span>{{ __('Total responses:') }} {{ $totalResponses }}</span>
                            <span>{{ __('Accepted:') }} {{ implode(', ', $puzzleData['accepted_answers']) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    @endunless
</div>
