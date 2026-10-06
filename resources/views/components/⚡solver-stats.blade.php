<?php

use App\Models\PuzzleAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The headline solve figures, shown as a stats band under the Solve
 * Statistics page header in the same format as the builder stats on the
 * Build page.
 */
new class extends Component {
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
            ->orderBy('solve_time_seconds')
            ->first();
    }

    #[Computed]
    public function currentStreak(): int
    {
        return Auth::user()->current_streak ?? 0;
    }

    #[Computed]
    public function longestStreak(): int
    {
        return Auth::user()->longest_streak ?? 0;
    }

    /**
     * How many of the user's timed solves beat the community average on
     * puzzles that more than one person has completed.
     *
     * @return array{total: int, faster: int}
     */
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
}
?>

<div>
    {{-- Overview band, matching the builder stats strip on the Build page.
         Phones get a single compact row of Solved and Streak; the time and
         comparison tiles join from the sm breakpoint. The community tile only
         appears once the user has solved a puzzle someone else has timed. --}}
    @php
        $comparison = $this->communityComparison;

        $stats = [
            ['label' => __('Puzzles Solved'), 'value' => $this->totalSolved, 'mobile' => true],
            ['label' => __('Current Streak'), 'value' => trans_choice(':count day|:count days', $this->currentStreak), 'mobile' => true],
            ['label' => __('Best Streak'), 'value' => trans_choice(':count day|:count days', $this->longestStreak), 'mobile' => false],
            ['label' => __('Average Time'), 'value' => $this->formatTime($this->averageTime), 'mobile' => false],
            ['label' => __('Fastest Solve'), 'value' => $this->fastestSolve ? $this->formatTime($this->fastestSolve->solve_time_seconds) : '—', 'mobile' => false],
        ];

        if ($comparison['total'] > 0) {
            $stats[] = ['label' => __('Faster Than Avg'), 'value' => round(($comparison['faster'] / $comparison['total']) * 100).'%', 'mobile' => false];
        }
    @endphp
    <div class="grid grid-cols-2 {{ count($stats) === 6 ? 'lg:grid-cols-6' : 'lg:grid-cols-5' }}">
        @foreach ($stats as $stat)
            <div class="border-hairline border-t-0 border px-4 py-3 sm:px-6 sm:py-4.5 lg:px-8 {{ $stat['mobile'] ? '' : 'hidden sm:block' }}" data-test="solver-stat">
                <div class="font-classical tnum text-[22px] leading-none font-medium text-amber-700 sm:text-[30px] dark:text-amber-400">{{ $stat['value'] }}</div>
                <div class="meta-classical mt-1 sm:mt-1.5">{{ $stat['label'] }}</div>
            </div>
        @endforeach
    </div>
</div>
