<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component {
    /**
     * The rating trend chart is hidden for now; flip this to bring it back.
     * The ratingTrend data behind it is still computed and tested.
     */
    public const bool SHOWS_RATING_TREND = false;

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

<div>
    {{-- Puzzle Performance Table --}}
    <div class="py-6">
        <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Puzzle Analytics') }}</h2>
        <p class="text-ink-muted mt-1 mb-5 text-sm">{{ __('How solvers are getting on with each of your published puzzles.') }}</p>

        @if($this->publishedPuzzles->isEmpty())
            <div class="border-border-strong flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-10 text-center">
                <flux:icon name="chart-bar" class="text-ink-faint mb-3 size-8" />
                <p class="text-ink-muted text-sm">{{ __('Publish puzzles to see analytics here.') }}</p>
            </div>
        @else
            @php
                $columns = [
                    ['label' => __('Puzzle'), 'field' => 'title', 'align' => 'text-left'],
                    ['label' => __('Attempts'), 'field' => 'cached_attempts_count', 'align' => 'text-right'],
                    ['label' => __('Completed'), 'field' => 'cached_completed_count', 'align' => 'text-right'],
                    ['label' => __('Completion Rate'), 'field' => null, 'align' => 'text-right'],
                    ['label' => __('Avg Time'), 'field' => 'cached_avg_solve_time', 'align' => 'text-right'],
                    ['label' => __('Likes'), 'field' => 'likes_count', 'align' => 'text-right'],
                    ['label' => __('Rating'), 'field' => 'avg_rating', 'align' => 'text-right'],
                ];
            @endphp
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-hairline border-b">
                            @foreach($columns as $column)
                                <th scope="col" class="px-3 py-3 font-normal {{ $column['align'] }}">
                                    @if($column['field'])
                                        <button type="button" wire:click="sortBy('{{ $column['field'] }}')" class="meta-classical hover:text-ink inline-flex items-center gap-1 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                            {{ $column['label'] }}
                                            @if($sortField === $column['field'])
                                                <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3 text-amber-400" />
                                            @endif
                                        </button>
                                    @else
                                        <span class="meta-classical">{{ $column['label'] }}</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-hairline divide-y">
                        @foreach($this->publishedPuzzles as $puzzle)
                            <tr wire:key="analytics-{{ $puzzle->id }}">
                                <td class="px-3 py-3.5">
                                    <a href="{{ route('crosswords.editor', $puzzle) }}" wire:navigate class="font-classical text-ink hover:text-amber-400 text-[17px] leading-tight font-medium transition-colors">
                                        {{ $puzzle->displayTitle() }}
                                    </a>
                                    <div class="meta-classical mt-1">{{ $puzzle->width }}&times;{{ $puzzle->height }}</div>
                                </td>
                                <td class="font-classical text-ink tnum px-3 py-3.5 text-right text-[15px] font-medium">{{ $puzzle->cached_attempts_count }}</td>
                                <td class="font-classical text-ink tnum px-3 py-3.5 text-right text-[15px] font-medium">{{ $puzzle->cached_completed_count }}</td>
                                <td class="font-classical text-ink tnum px-3 py-3.5 text-right text-[15px] font-medium">
                                    @php
                                        $rate = $puzzle->cached_attempts_count > 0 ? round(($puzzle->cached_completed_count / $puzzle->cached_attempts_count) * 100) : 0;
                                    @endphp
                                    {{ $rate }}%
                                </td>
                                <td class="text-ink tnum px-3 py-3.5 text-right font-mono text-[13px]">{{ $this->formatTime($puzzle->cached_avg_solve_time) }}</td>
                                <td class="px-3 py-3.5 text-right">
                                    <span class="font-classical text-ink tnum inline-flex items-center gap-1.5 text-[15px] font-medium">
                                        <flux:icon name="heart" variant="outline" class="text-ink-faint size-3.5" />
                                        {{ $puzzle->likes_count }}
                                    </span>
                                </td>
                                <td class="px-3 py-3.5 text-right">
                                    @if($puzzle->avg_rating)
                                        <span class="font-classical text-ink tnum inline-flex items-center gap-1.5 text-[15px] font-medium">
                                            <flux:icon name="star" variant="outline" class="size-3.5 text-amber-400" />
                                            {{ round($puzzle->avg_rating, 1) }}
                                        </span>
                                        @if($puzzle->reviews_count > 0)
                                            <div class="meta-classical mt-1">({{ $puzzle->reviews_count }})</div>
                                        @endif
                                    @else
                                        <span class="text-ink-faint">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Rating Trend Chart (hidden for now, see SHOWS_RATING_TREND) --}}
    @if(static::SHOWS_RATING_TREND && count($this->ratingTrend) >= 2)
        <div class="border-hairline -mx-6 border-t px-6 pt-6 lg:-mx-8 lg:px-8">
            <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Rating Trend') }}</h2>
            <p class="text-ink-muted mt-1 mb-5 text-sm">{{ __('Average rating received per month over the last 12 months.') }}</p>

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
                x-init="
                    const measure = () => { width = Math.max(400, Math.round($el.clientWidth)) };
                    measure();
                    new ResizeObserver(measure).observe($el);
                "
                class="w-full overflow-x-auto"
            >
                @php
                    $trendRatings = array_column($this->ratingTrend, 'avg_rating');
                    $trendMin = max(0, floor(min($trendRatings) * 2) / 2 - 0.5);
                    $trendMax = min(5, ceil(max($trendRatings) * 2) / 2 + 0.5);
                @endphp
                <svg :viewBox="`0 0 ${width} ${height}`" class="w-full min-w-[400px]" preserveAspectRatio="xMidYMid meet">
                    {{-- Grid lines --}}
                    @for($value = (int) ceil($trendMin); $value <= (int) floor($trendMax); $value++)
                        <line :x1="padX" :y1="y({{ $value }})" :x2="width - padX" :y2="y({{ $value }})" class="stroke-hairline" stroke-dasharray="3 5" />
                        <text :x="padX - 8" :y="y({{ $value }}) + 4" text-anchor="end" class="fill-ink-faint text-[11px]">{{ $value }}</text>
                    @endfor

                    {{-- Area fill --}}
                    <path :d="areaPath" class="fill-amber-400/10" />

                    {{-- Line --}}
                    <path :d="linePath" fill="none" class="stroke-amber-400" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />

                    {{-- Data points --}}
                    @foreach($this->ratingTrend as $point)
                        <circle :cx="x({{ $loop->index }})" :cy="y({{ $point['avg_rating'] }})" r="4" class="fill-ground stroke-amber-400" stroke-width="1.5" @mouseenter="showTooltip({{ $loop->index }})" @mouseleave="hideTooltip()" style="cursor: pointer" />
                    @endforeach

                    {{-- X-axis labels --}}
                    @foreach($this->ratingTrend as $point)
                        <text :x="x({{ $loop->index }})" :y="height - 4" text-anchor="middle" class="fill-ink-faint label-classical text-[10px]" x-text="formatMonth('{{ $point['month'] }}')"></text>
                    @endforeach

                    {{-- Tooltip --}}
                    <g x-show="tooltip" x-cloak>
                        <rect :x="(tooltip?.x ?? 0) - 40" :y="(tooltip?.y ?? 0) - 44" width="80" height="34" rx="4" class="fill-panel stroke-border-strong" stroke-width="1" />
                        <text :x="tooltip?.x ?? 0" :y="(tooltip?.y ?? 0) - 28" text-anchor="middle" class="fill-ink text-[11px] font-semibold">
                            <tspan x-text="tooltip ? `★ ${tooltip.rating}` : ''"></tspan>
                        </text>
                        <text :x="tooltip?.x ?? 0" :y="(tooltip?.y ?? 0) - 16" text-anchor="middle" class="fill-ink-muted text-[10px]">
                            <tspan x-text="tooltip ? `${tooltip.count} ${tooltip.count === 1 ? 'review' : 'reviews'}` : ''"></tspan>
                        </text>
                    </g>
                </svg>
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
</div>
