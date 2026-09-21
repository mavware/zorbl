<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Subscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Leaderboard')] class extends Component {
    #[Url]
    public string $tab = 'solvers';

    #[Computed]
    public function topSolvers()
    {
        return Cache::remember('leaderboard:top_solvers', 300, function () {
            return DB::table('users')
                ->select('users.id', 'users.name')
                ->where('users.is_anonymous', false)
                ->join('puzzle_attempts', 'users.id', '=', 'puzzle_attempts.user_id')
                ->where('puzzle_attempts.is_completed', true)
                ->groupBy('users.id', 'users.name')
                ->selectRaw('count(*) as completed_count')
                ->orderByDesc('completed_count')
                ->limit(50)
                ->get();
        });
    }

    #[Computed]
    public function speedDemons()
    {
        return Cache::remember('leaderboard:speed_demons', 300, function () {
            return DB::table('users')
                ->select('users.id', 'users.name')
                ->where('users.is_anonymous', false)
                ->join('puzzle_attempts', 'users.id', '=', 'puzzle_attempts.user_id')
                ->where('puzzle_attempts.is_completed', true)
                ->whereNotNull('puzzle_attempts.solve_time_seconds')
                ->groupBy('users.id', 'users.name')
                ->havingRaw('count(*) >= 5')
                ->selectRaw('round(avg(puzzle_attempts.solve_time_seconds)) as avg_time')
                ->selectRaw('count(*) as solved_count')
                ->orderBy('avg_time')
                ->limit(50)
                ->get();
        });
    }

    #[Computed]
    public function topConstructors()
    {
        return Cache::remember('leaderboard:top_constructors', 300, function () {
            return DB::table('users')
                ->select('users.id', 'users.name')
                ->where('users.is_anonymous', false)
                ->join('crosswords', 'users.id', '=', 'crosswords.user_id')
                ->where('crosswords.is_published', true)
                ->leftJoin('puzzle_attempts', function ($join) {
                    $join->on('crosswords.id', '=', 'puzzle_attempts.crossword_id')
                        ->where('puzzle_attempts.is_completed', true);
                })
                ->groupBy('users.id', 'users.name')
                ->selectRaw('count(distinct crosswords.id) as published_count')
                ->selectRaw('count(puzzle_attempts.id) as total_solves')
                ->orderByDesc('total_solves')
                ->limit(50)
                ->get();
        });
    }

    #[Computed]
    public function streakLeaders()
    {
        return Cache::remember('leaderboard:streak_leaders', 300, function () {
            return DB::table('users')
                ->select('id', 'name', 'current_streak', 'longest_streak')
                ->where('is_anonymous', false)
                ->where('longest_streak', '>', 0)
                ->orderByDesc('longest_streak')
                ->orderByDesc('current_streak')
                ->limit(50)
                ->get();
        });
    }

    /**
     * Ids of users with an active subscription, for the supporter badge. The
     * ranking rows above are plain query-builder results (not User models),
     * so we resolve badges with one lookup instead of a query per row.
     *
     * @return list<int>
     */
    #[Computed]
    public function supporterIds(): array
    {
        return Subscription::query()
            ->where('type', 'default')
            ->active()
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array{rank: int, value: int}|null
     */
    #[Computed]
    public function yourSolverRank(): ?array
    {
        $userId = Auth::id();

        $userCount = (int) DB::table('puzzle_attempts')
            ->where('user_id', $userId)
            ->where('is_completed', true)
            ->count();

        if ($userCount === 0) {
            return null;
        }

        $rank = (int) DB::table(
            DB::raw('(SELECT user_id, count(*) as completed_count FROM puzzle_attempts WHERE is_completed = 1 GROUP BY user_id) as rankings')
        )
            ->join('users', 'users.id', '=', 'rankings.user_id')
            ->where('users.is_anonymous', false)
            ->where('rankings.completed_count', '>', $userCount)
            ->count() + 1;

        return ['rank' => $rank, 'value' => $userCount];
    }

    /**
     * @return array{rank: int, value: int, solved_count: int}|null
     */
    #[Computed]
    public function yourSpeedRank(): ?array
    {
        $userId = Auth::id();

        $userStats = DB::table('puzzle_attempts')
            ->where('user_id', $userId)
            ->where('is_completed', true)
            ->whereNotNull('solve_time_seconds')
            ->selectRaw('count(*) as solved_count, round(avg(solve_time_seconds)) as avg_time')
            ->first();

        if (! $userStats || $userStats->solved_count < 5) {
            return null;
        }

        $avgTime = (int) $userStats->avg_time;

        $rank = (int) DB::table(
            DB::raw('(SELECT user_id, round(avg(solve_time_seconds)) as avg_time, count(*) as solved_count FROM puzzle_attempts WHERE is_completed = 1 AND solve_time_seconds IS NOT NULL GROUP BY user_id HAVING count(*) >= 5) as rankings')
        )
            ->join('users', 'users.id', '=', 'rankings.user_id')
            ->where('users.is_anonymous', false)
            ->where('rankings.avg_time', '<', $avgTime)
            ->count() + 1;

        return ['rank' => $rank, 'value' => $avgTime, 'solved_count' => (int) $userStats->solved_count];
    }

    /**
     * @return array{rank: int, value: int, published_count: int}|null
     */
    #[Computed]
    public function yourConstructorRank(): ?array
    {
        $userId = Auth::id();

        $publishedCount = (int) DB::table('crosswords')
            ->where('user_id', $userId)
            ->where('is_published', true)
            ->count();

        if ($publishedCount === 0) {
            return null;
        }

        $totalSolves = (int) DB::table('crosswords')
            ->join('puzzle_attempts', function ($join) {
                $join->on('crosswords.id', '=', 'puzzle_attempts.crossword_id')
                    ->where('puzzle_attempts.is_completed', true);
            })
            ->where('crosswords.user_id', $userId)
            ->where('crosswords.is_published', true)
            ->count();

        $allConstructors = DB::table('crosswords')
            ->select('crosswords.user_id')
            ->where('crosswords.is_published', true)
            ->leftJoin('puzzle_attempts', function ($join) {
                $join->on('crosswords.id', '=', 'puzzle_attempts.crossword_id')
                    ->where('puzzle_attempts.is_completed', true);
            })
            ->join('users', 'users.id', '=', 'crosswords.user_id')
            ->where('users.is_anonymous', false)
            ->groupBy('crosswords.user_id')
            ->havingRaw('count(puzzle_attempts.id) > ?', [$totalSolves])
            ->get();

        $rank = $allConstructors->count() + 1;

        return ['rank' => $rank, 'value' => $totalSolves, 'published_count' => $publishedCount];
    }

    /**
     * @return array{rank: int, longest: int, current: int}|null
     */
    #[Computed]
    public function yourStreakRank(): ?array
    {
        $user = Auth::user();

        if ($user->longest_streak <= 0) {
            return null;
        }

        $rank = (int) User::where('is_anonymous', false)
            ->where(function ($q) use ($user) {
                $q->where('longest_streak', '>', $user->longest_streak)
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('longest_streak', $user->longest_streak)
                            ->where('current_streak', '>', $user->current_streak);
                    });
            })
            ->count() + 1;

        return ['rank' => $rank, 'longest' => $user->longest_streak, 'current' => $user->current_streak];
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

<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header :kicker="__('Community')" :title="__('Leaderboard')" :subtitle="__('See how the community ranks across different categories.')" />

    {{-- Tab Navigation --}}
    <div class="border-border-strong divide-hairline inline-flex h-10 max-w-full divide-x overflow-x-auto rounded-sm border" role="radiogroup" aria-label="{{ __('Leaderboard') }}">
        @foreach (['solvers' => __('Top Solvers'), 'speed' => __('Speed Demons'), 'constructors' => __('Top Constructors'), 'streaks' => __('Best Streaks')] as $value => $label)
            <label class="cursor-pointer">
                <input type="radio" name="tab" value="{{ $value }}" wire:model.live="tab" class="peer sr-only" />
                <span class="font-classical text-ink-muted hover:text-ink peer-checked:bg-amber-400/10 peer-checked:text-amber-400 peer-focus-visible:outline-2 peer-focus-visible:-outline-offset-2 peer-focus-visible:outline-amber-400 flex h-full items-center px-3.5 text-[15px] font-medium whitespace-nowrap transition-colors">
                    {{ $label }}
                </span>
            </label>
        @endforeach
    </div>

    {{-- Top Solvers --}}
    @if($tab === 'solvers')
        <div class="border-border rounded-sm border p-[18px]">
            <div class="border-hairline mb-2 border-b pb-3.5">
                <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Top Solvers') }}</h2>
                <p class="meta-classical mt-1.5">{{ __('Ranked by total puzzles completed.') }}</p>
            </div>

            @if($this->topSolvers->isEmpty())
                <div class="mt-3"><x-leaderboard-empty /></div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-hairline border-b">
                            <th scope="col" class="meta-classical px-3 py-3 text-left font-normal">{{ __('Rank') }}</th>
                            <th scope="col" class="meta-classical px-3 py-3 text-left font-normal">{{ __('Solver') }}</th>
                            <th scope="col" class="meta-classical px-3 py-3 text-right font-normal">{{ __('Puzzles Solved') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-hairline divide-y">
                            @foreach($this->topSolvers as $index => $solver)
                                <tr wire:key="solver-{{ $solver->id }}" @class(['align-middle', 'shadow-[inset_2px_0_0_0_var(--color-amber-400)]' => $solver->id === Auth::id()])>
                                    <td class="w-14 px-3 py-3"><x-leaderboard-rank :rank="$index + 1" /></td>
                                    <td class="px-3 py-3"><a href="{{ route('constructors.show', $solver->id) }}" wire:navigate class="font-classical text-ink hover:text-amber-300 text-[17px] leading-tight font-semibold transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        {{ $solver->name }} <x-supporter-badge :supporter="in_array($solver->id, $this->supporterIds, true)" />
                                    </a></td>
                                    <td class="px-3 py-3 text-right whitespace-nowrap"><span class="font-classical text-ink tnum text-[16px] font-medium">{{ number_format($solver->completed_count) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if($yourSolverRank = $this->yourSolverRank)
            @unless($this->topSolvers->contains('id', Auth::id()))
                <div class="border-amber-400/60 flex flex-wrap items-center justify-between gap-3 rounded-sm border p-[18px]" data-test="your-solver-rank">
                    <div class="flex items-center gap-3.5">
                        <div class="border-amber-400 font-classical text-amber-400 tnum flex size-10 shrink-0 items-center justify-center rounded-sm border text-[15px] font-semibold">#{{ $yourSolverRank['rank'] }}</div>
                        <div>
                            <div class="font-classical text-ink text-[18px] leading-tight font-semibold">{{ __('Your Rank') }}</div>
                            <div class="text-ink-muted mt-0.5 text-sm">
                                {{ trans_choice(':count puzzle solved|:count puzzles solved', $yourSolverRank['value']) }}
                            </div>
                        </div>
                    </div>
                        <a href="{{ route('crosswords.solving') }}" wire:navigate class="btn-classical btn-classical-muted">
                            {{ __('Solve more') }}
                            <flux:icon name="arrow-right" class="size-4" />
                        </a>
                </div>
            @endunless
        @endif
    @endif

    {{-- Speed Demons --}}
    @if($tab === 'speed')
        <div class="border-border rounded-sm border p-[18px]">
            <div class="border-hairline mb-2 border-b pb-3.5">
                <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Speed Demons') }}</h2>
                <p class="meta-classical mt-1.5">{{ __('Ranked by average solve time (minimum 5 solves).') }}</p>
            </div>

            @if($this->speedDemons->isEmpty())
                <div class="mt-3"><x-leaderboard-empty /></div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-hairline border-b">
                            <th scope="col" class="meta-classical px-3 py-3 text-left font-normal">{{ __('Rank') }}</th>
                            <th scope="col" class="meta-classical px-3 py-3 text-left font-normal">{{ __('Solver') }}</th>
                            <th scope="col" class="meta-classical px-3 py-3 text-right font-normal">{{ __('Avg Time') }}</th>
                            <th scope="col" class="meta-classical px-3 py-3 text-right font-normal">{{ __('Solves') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-hairline divide-y">
                            @foreach($this->speedDemons as $index => $solver)
                                <tr wire:key="solver-{{ $solver->id }}" @class(['align-middle', 'shadow-[inset_2px_0_0_0_var(--color-amber-400)]' => $solver->id === Auth::id()])>
                                    <td class="w-14 px-3 py-3"><x-leaderboard-rank :rank="$index + 1" /></td>
                                    <td class="px-3 py-3"><a href="{{ route('constructors.show', $solver->id) }}" wire:navigate class="font-classical text-ink hover:text-amber-300 text-[17px] leading-tight font-semibold transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        {{ $solver->name }} <x-supporter-badge :supporter="in_array($solver->id, $this->supporterIds, true)" />
                                    </a></td>
                                    <td class="px-3 py-3 text-right whitespace-nowrap"><span class="font-classical text-ink tnum text-[16px] font-medium">{{ $this->formatTime((int) $solver->avg_time) }}</span></td>
                                    <td class="px-3 py-3 text-right whitespace-nowrap"><span class="text-ink-muted tnum">{{ number_format($solver->solved_count) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if($yourSpeedRank = $this->yourSpeedRank)
            @unless($this->speedDemons->contains('id', Auth::id()))
                <div class="border-amber-400/60 flex flex-wrap items-center justify-between gap-3 rounded-sm border p-[18px]" data-test="your-speed-rank">
                    <div class="flex items-center gap-3.5">
                        <div class="border-amber-400 font-classical text-amber-400 tnum flex size-10 shrink-0 items-center justify-center rounded-sm border text-[15px] font-semibold">#{{ $yourSpeedRank['rank'] }}</div>
                        <div>
                            <div class="font-classical text-ink text-[18px] leading-tight font-semibold">{{ __('Your Rank') }}</div>
                            <div class="text-ink-muted mt-0.5 text-sm">
                                {{ __('Avg :time across :count solves', ['time' => $this->formatTime($yourSpeedRank['value']), 'count' => $yourSpeedRank['solved_count']]) }}
                            </div>
                        </div>
                    </div>
                </div>
            @endunless
        @endif
    @endif

    {{-- Top Constructors --}}
    @if($tab === 'constructors')
        <div class="border-border rounded-sm border p-[18px]">
            <div class="border-hairline mb-2 border-b pb-3.5">
                <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Top Constructors') }}</h2>
                <p class="meta-classical mt-1.5">{{ __('Ranked by total solves across their published puzzles.') }}</p>
            </div>

            @if($this->topConstructors->isEmpty())
                <div class="mt-3"><x-leaderboard-empty /></div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-hairline border-b">
                            <th scope="col" class="meta-classical px-3 py-3 text-left font-normal">{{ __('Rank') }}</th>
                            <th scope="col" class="meta-classical px-3 py-3 text-left font-normal">{{ __('Constructor') }}</th>
                            <th scope="col" class="meta-classical px-3 py-3 text-right font-normal">{{ __('Published') }}</th>
                            <th scope="col" class="meta-classical px-3 py-3 text-right font-normal">{{ __('Total Solves') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-hairline divide-y">
                            @foreach($this->topConstructors as $index => $constructor)
                                <tr wire:key="constructor-{{ $constructor->id }}" @class(['align-middle', 'shadow-[inset_2px_0_0_0_var(--color-amber-400)]' => $constructor->id === Auth::id()])>
                                    <td class="w-14 px-3 py-3"><x-leaderboard-rank :rank="$index + 1" /></td>
                                    <td class="px-3 py-3"><a href="{{ route('constructors.show', $constructor->id) }}" wire:navigate class="font-classical text-ink hover:text-amber-300 text-[17px] leading-tight font-semibold transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        {{ $constructor->name }} <x-supporter-badge :supporter="in_array($constructor->id, $this->supporterIds, true)" />
                                    </a></td>
                                    <td class="px-3 py-3 text-right whitespace-nowrap"><span class="text-ink-muted tnum">{{ number_format($constructor->published_count) }}</span></td>
                                    <td class="px-3 py-3 text-right whitespace-nowrap"><span class="font-classical text-ink tnum text-[16px] font-medium">{{ number_format($constructor->total_solves) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if($yourConstructorRank = $this->yourConstructorRank)
            @unless($this->topConstructors->contains('id', Auth::id()))
                <div class="border-amber-400/60 flex flex-wrap items-center justify-between gap-3 rounded-sm border p-[18px]" data-test="your-constructor-rank">
                    <div class="flex items-center gap-3.5">
                        <div class="border-amber-400 font-classical text-amber-400 tnum flex size-10 shrink-0 items-center justify-center rounded-sm border text-[15px] font-semibold">#{{ $yourConstructorRank['rank'] }}</div>
                        <div>
                            <div class="font-classical text-ink text-[18px] leading-tight font-semibold">{{ __('Your Rank') }}</div>
                            <div class="text-ink-muted mt-0.5 text-sm">
                                {{ trans_choice(':count published puzzle|:count published puzzles', $yourConstructorRank['published_count']) }}\n                                &middot;\n                                {{ trans_choice(':count total solve|:count total solves', $yourConstructorRank['value']) }}
                            </div>
                        </div>
                    </div>
                        <a href="{{ route('crosswords.index') }}" wire:navigate class="btn-classical btn-classical-muted">
                            {{ __('Build more') }}
                            <flux:icon name="arrow-right" class="size-4" />
                        </a>
                </div>
            @endunless
        @endif
    @endif

    {{-- Streak Leaders --}}
    @if($tab === 'streaks')
        <div class="border-border rounded-sm border p-[18px]">
            <div class="border-hairline mb-2 border-b pb-3.5">
                <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Best Streaks') }}</h2>
                <p class="meta-classical mt-1.5">{{ __('Ranked by longest daily solving streak.') }}</p>
            </div>

            @if($this->streakLeaders->isEmpty())
                <div class="mt-3"><x-leaderboard-empty /></div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-hairline border-b">
                            <th scope="col" class="meta-classical px-3 py-3 text-left font-normal">{{ __('Rank') }}</th>
                            <th scope="col" class="meta-classical px-3 py-3 text-left font-normal">{{ __('Solver') }}</th>
                            <th scope="col" class="meta-classical px-3 py-3 text-right font-normal">{{ __('Best Streak') }}</th>
                            <th scope="col" class="meta-classical px-3 py-3 text-right font-normal">{{ __('Current Streak') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-hairline divide-y">
                            @foreach($this->streakLeaders as $index => $user)
                                <tr wire:key="user-{{ $user->id }}" @class(['align-middle', 'shadow-[inset_2px_0_0_0_var(--color-amber-400)]' => $user->id === Auth::id()])>
                                    <td class="w-14 px-3 py-3"><x-leaderboard-rank :rank="$index + 1" /></td>
                                    <td class="px-3 py-3"><a href="{{ route('constructors.show', $user->id) }}" wire:navigate class="font-classical text-ink hover:text-amber-300 text-[17px] leading-tight font-semibold transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        {{ $user->name }} <x-supporter-badge :supporter="in_array($user->id, $this->supporterIds, true)" />
                                    </a></td>
                                    <td class="px-3 py-3 text-right whitespace-nowrap"><span class="font-classical text-ink tnum text-[16px] font-medium">{{ $user->longest_streak }} {{ __('days') }}</span></td>
                                    <td class="px-3 py-3 text-right whitespace-nowrap">
                                        @if($user->current_streak > 0)
                                            <span class="font-classical tnum text-[16px] font-medium text-amber-400">{{ $user->current_streak }} {{ __('days') }}</span>
                                        @else
                                            <span class="text-ink-faint">&mdash;</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if($yourStreakRank = $this->yourStreakRank)
            @unless($this->streakLeaders->contains('id', Auth::id()))
                <div class="border-amber-400/60 flex flex-wrap items-center justify-between gap-3 rounded-sm border p-[18px]" data-test="your-streak-rank">
                    <div class="flex items-center gap-3.5">
                        <div class="border-amber-400 font-classical text-amber-400 tnum flex size-10 shrink-0 items-center justify-center rounded-sm border text-[15px] font-semibold">#{{ $yourStreakRank['rank'] }}</div>
                        <div>
                            <div class="font-classical text-ink text-[18px] leading-tight font-semibold">{{ __('Your Rank') }}</div>
                            <div class="text-ink-muted mt-0.5 text-sm">
                                {{ __('Best: :best days', ['best' => $yourStreakRank['longest']]) }}\n                                @if($yourStreakRank['current'] > 0)\n                                    &middot;\n                                    {{ __('Current: :current days', ['current' => $yourStreakRank['current']]) }}\n                                @endif
                            </div>
                        </div>
                    </div>
                        <a href="{{ route('crosswords.solving') }}" wire:navigate class="btn-classical btn-classical-muted">
                            {{ __('Solve today') }}
                            <flux:icon name="arrow-right" class="size-4" />
                        </a>
                </div>
            @endunless
        @endif
    @endif
</div>
