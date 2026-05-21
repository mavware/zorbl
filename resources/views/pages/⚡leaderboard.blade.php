<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Leaderboard')] class extends Component {
    #[Url]
    public string $tab = 'solvers';

    #[Url]
    public string $period = 'all';

    public function updatedPeriod(): void
    {
        unset($this->topSolvers, $this->speedDemons, $this->topConstructors);
    }

    private function periodCutoff(): ?string
    {
        return match ($this->period) {
            'week' => now()->subWeek()->toDateTimeString(),
            'month' => now()->subMonth()->toDateTimeString(),
            default => null,
        };
    }

    #[Computed]
    public function topSolvers()
    {
        $period = $this->period;

        return Cache::remember("leaderboard:top_solvers:{$period}", 300, function () {
            $query = User::select('users.id', 'users.name')
                ->join('puzzle_attempts', 'users.id', '=', 'puzzle_attempts.user_id')
                ->where('puzzle_attempts.is_completed', true)
                ->groupBy('users.id', 'users.name')
                ->selectRaw('count(*) as completed_count')
                ->orderByDesc('completed_count')
                ->limit(50);

            if ($cutoff = $this->periodCutoff()) {
                $query->where('puzzle_attempts.completed_at', '>=', $cutoff);
            }

            return $query->get();
        });
    }

    #[Computed]
    public function speedDemons()
    {
        $period = $this->period;

        return Cache::remember("leaderboard:speed_demons:{$period}", 300, function () {
            $minSolves = $this->period === 'all' ? 5 : 3;

            $query = User::select('users.id', 'users.name')
                ->join('puzzle_attempts', 'users.id', '=', 'puzzle_attempts.user_id')
                ->where('puzzle_attempts.is_completed', true)
                ->whereNotNull('puzzle_attempts.solve_time_seconds')
                ->groupBy('users.id', 'users.name')
                ->havingRaw("count(*) >= {$minSolves}")
                ->selectRaw('round(avg(puzzle_attempts.solve_time_seconds)) as avg_time')
                ->selectRaw('count(*) as solved_count')
                ->orderBy('avg_time')
                ->limit(50);

            if ($cutoff = $this->periodCutoff()) {
                $query->where('puzzle_attempts.completed_at', '>=', $cutoff);
            }

            return $query->get();
        });
    }

    #[Computed]
    public function topConstructors()
    {
        $period = $this->period;

        return Cache::remember("leaderboard:top_constructors:{$period}", 300, function () {
            $query = User::select('users.id', 'users.name')
                ->join('crosswords', 'users.id', '=', 'crosswords.user_id')
                ->where('crosswords.is_published', true)
                ->leftJoin('puzzle_attempts', function ($join) {
                    $join->on('crosswords.id', '=', 'puzzle_attempts.crossword_id')
                        ->where('puzzle_attempts.is_completed', true);

                    if ($cutoff = $this->periodCutoff()) {
                        $join->where('puzzle_attempts.completed_at', '>=', $cutoff);
                    }
                })
                ->groupBy('users.id', 'users.name')
                ->selectRaw('count(distinct crosswords.id) as published_count')
                ->selectRaw('count(puzzle_attempts.id) as total_solves')
                ->orderByDesc('total_solves')
                ->limit(50);

            return $query->get();
        });
    }

    #[Computed]
    public function streakLeaders()
    {
        return Cache::remember('leaderboard:streak_leaders', 300, function () {
            return User::select('id', 'name', 'current_streak', 'longest_streak')
                ->where('longest_streak', '>', 0)
                ->orderByDesc('longest_streak')
                ->orderByDesc('current_streak')
                ->limit(50)
                ->get();
        });
    }

    public function periodLabel(): string
    {
        return match ($this->period) {
            'week' => __('This Week'),
            'month' => __('This Month'),
            default => __('All Time'),
        };
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
    <div>
        <flux:heading size="xl">{{ __('Leaderboard') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-600">{{ __('See how the community ranks across different categories.') }}</flux:text>
    </div>

    {{-- Tab Navigation --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:radio.group wire:model.live="tab" variant="segmented" size="sm">
            <flux:radio value="solvers" label="{{ __('Top Solvers') }}" />
            <flux:radio value="speed" label="{{ __('Speed Demons') }}" />
            <flux:radio value="constructors" label="{{ __('Top Constructors') }}" />
            <flux:radio value="streaks" label="{{ __('Best Streaks') }}" />
        </flux:radio.group>

        @if($tab !== 'streaks')
            <flux:radio.group wire:model.live="period" variant="segmented" size="sm">
                <flux:radio value="all" label="{{ __('All Time') }}" />
                <flux:radio value="week" label="{{ __('This Week') }}" />
                <flux:radio value="month" label="{{ __('This Month') }}" />
            </flux:radio.group>
        @endif
    </div>

    {{-- Top Solvers --}}
    @if($tab === 'solvers')
        <div class="border-line rounded-xl border p-5">
            <flux:heading size="lg" class="mb-1">{{ __('Top Solvers') }}</flux:heading>
            <flux:text size="sm" class="mb-4 text-zinc-500">{{ __('Ranked by total puzzles completed.') }} {{ $period !== 'all' ? '(' . $this->periodLabel() . ')' : '' }}</flux:text>

            @if($this->topSolvers->isEmpty())
                <x-leaderboard-empty />
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Rank') }}</flux:table.column>
                        <flux:table.column>{{ __('Solver') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Puzzles Solved') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($this->topSolvers as $index => $solver)
                            <flux:table.row :key="$solver->id" @class(['bg-amber-50/50 dark:bg-amber-900/10' => $solver->id === Auth::id()])>
                                <flux:table.cell>
                                    <x-leaderboard-rank :rank="$index + 1" />
                                </flux:table.cell>
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('constructors.show', $solver) }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400">
                                        {{ $solver->name }}
                                    </a>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <span class="font-mono font-semibold">{{ number_format($solver->completed_count) }}</span>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    @endif

    {{-- Speed Demons --}}
    @if($tab === 'speed')
        <div class="border-line rounded-xl border p-5">
            <flux:heading size="lg" class="mb-1">{{ __('Speed Demons') }}</flux:heading>
            <flux:text size="sm" class="mb-4 text-zinc-500">{{ __('Ranked by average solve time (minimum :count solves).', ['count' => $period === 'all' ? 5 : 3]) }} {{ $period !== 'all' ? '(' . $this->periodLabel() . ')' : '' }}</flux:text>

            @if($this->speedDemons->isEmpty())
                <x-leaderboard-empty />
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Rank') }}</flux:table.column>
                        <flux:table.column>{{ __('Solver') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Avg Time') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Solves') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($this->speedDemons as $index => $solver)
                            <flux:table.row :key="$solver->id" @class(['bg-amber-50/50 dark:bg-amber-900/10' => $solver->id === Auth::id()])>
                                <flux:table.cell>
                                    <x-leaderboard-rank :rank="$index + 1" />
                                </flux:table.cell>
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('constructors.show', $solver) }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400">
                                        {{ $solver->name }}
                                    </a>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <span class="font-mono font-semibold">{{ $this->formatTime((int) $solver->avg_time) }}</span>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <span class="text-zinc-500">{{ number_format($solver->solved_count) }}</span>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    @endif

    {{-- Top Constructors --}}
    @if($tab === 'constructors')
        <div class="border-line rounded-xl border p-5">
            <flux:heading size="lg" class="mb-1">{{ __('Top Constructors') }}</flux:heading>
            <flux:text size="sm" class="mb-4 text-zinc-500">{{ __('Ranked by total solves across their published puzzles.') }} {{ $period !== 'all' ? '(' . $this->periodLabel() . ')' : '' }}</flux:text>

            @if($this->topConstructors->isEmpty())
                <x-leaderboard-empty />
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Rank') }}</flux:table.column>
                        <flux:table.column>{{ __('Constructor') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Published') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Total Solves') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($this->topConstructors as $index => $constructor)
                            <flux:table.row :key="$constructor->id" @class(['bg-amber-50/50 dark:bg-amber-900/10' => $constructor->id === Auth::id()])>
                                <flux:table.cell>
                                    <x-leaderboard-rank :rank="$index + 1" />
                                </flux:table.cell>
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('constructors.show', $constructor) }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400">
                                        {{ $constructor->name }}
                                    </a>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <span class="text-zinc-500">{{ number_format($constructor->published_count) }}</span>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <span class="font-mono font-semibold">{{ number_format($constructor->total_solves) }}</span>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    @endif

    {{-- Streak Leaders --}}
    @if($tab === 'streaks')
        <div class="border-line rounded-xl border p-5">
            <flux:heading size="lg" class="mb-1">{{ __('Best Streaks') }}</flux:heading>
            <flux:text size="sm" class="mb-4 text-zinc-500">{{ __('Ranked by longest daily solving streak.') }}</flux:text>

            @if($this->streakLeaders->isEmpty())
                <x-leaderboard-empty />
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Rank') }}</flux:table.column>
                        <flux:table.column>{{ __('Solver') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Best Streak') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Current Streak') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($this->streakLeaders as $index => $user)
                            <flux:table.row :key="$user->id" @class(['bg-amber-50/50 dark:bg-amber-900/10' => $user->id === Auth::id()])>
                                <flux:table.cell>
                                    <x-leaderboard-rank :rank="$index + 1" />
                                </flux:table.cell>
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('constructors.show', $user) }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400">
                                        {{ $user->name }}
                                    </a>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <span class="font-mono font-semibold">{{ $user->longest_streak }} {{ __('days') }}</span>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @if($user->current_streak > 0)
                                        <span class="font-mono text-orange-600 dark:text-orange-400">{{ $user->current_streak }} {{ __('days') }}</span>
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
    @endif
</div>
