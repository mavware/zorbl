<?php

use App\Models\PuzzleAttempt;
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
    <x-page-header :kicker="__('Your progress')" :title="__('Solve Statistics')">
        <x-header-button variant="secondary" icon="arrow-left" :href="route('crosswords.solving')" wire:navigate>
            {{ __('Back to Solving') }}
        </x-header-button>

        <x-slot:footer>
            {{-- Headline solve stats — flush under the header rule, like the builder stats on the Build page --}}
            <livewire:solver-stats key="solver-stats" />
        </x-slot:footer>
    </x-page-header>

    {{-- Achievements --}}
    <div class="border-border rounded-sm border p-4.5">
        <h2 class="font-classical text-ink mb-3 text-[19px] leading-tight font-semibold">{{ __('Achievements') }}</h2>
        @php($achievements = Auth::user()->achievements()->orderBy('earned_at', 'desc')->get())
        @if($achievements->isEmpty())
            <p class="text-ink-muted text-sm">{{ __('Complete puzzles to earn achievements!') }}</p>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach($achievements as $achievement)
                    <flux:tooltip :content="$achievement->description">
                        <span class="chip-classical border-amber-400 text-amber-400 h-7 gap-1.5 px-2.5">
                            <flux:icon :name="$achievement->icon" class="size-3.5" />
                            {{ $achievement->label }}
                        </span>
                    </flux:tooltip>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Average by Size --}}
    @if(count($this->averageBySize) > 0)
        <div class="border-border rounded-sm border p-4.5">
            <div class="border-hairline mb-5 border-b pb-3.5">
                <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Times by Grid Size') }}</h2>
            </div>
            <div class="grid gap-5.5 sm:grid-cols-3">
                @foreach($this->averageBySize as $size)
                    <div class="border-border rounded-sm border p-4">
                        <div class="font-classical text-ink text-[19px] leading-tight font-semibold">{{ $size['label'] }}</div>
                        <dl class="divide-hairline mt-3 divide-y">
                            <div class="flex items-center justify-between py-1.5">
                                <dt class="meta-classical">{{ __('Solved') }}</dt>
                                <dd class="font-classical text-ink tnum text-[15px] font-medium">{{ $size['count'] }}</dd>
                            </div>
                            <div class="flex items-center justify-between py-1.5">
                                <dt class="meta-classical">{{ __('Average') }}</dt>
                                <dd class="font-classical text-ink tnum text-[15px] font-medium">{{ $this->formatTime($size['average']) }}</dd>
                            </div>
                            <div class="flex items-center justify-between py-1.5">
                                <dt class="meta-classical">{{ __('Fastest') }}</dt>
                                <dd class="font-classical tnum text-[15px] font-medium text-amber-400">{{ $this->formatTime($size['fastest']) }}</dd>
                            </div>
                        </dl>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Average by Difficulty --}}
    @if(count($this->averageByDifficulty) > 0)
        <div class="border-border rounded-sm border p-4.5">
            <div class="border-hairline mb-5 border-b pb-3.5">
                <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Times by Difficulty') }}</h2>
            </div>
            <div class="grid gap-5.5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($this->averageByDifficulty as $difficulty)
                    <div class="border-border rounded-sm border p-4">
                        <div class="font-classical text-ink text-[19px] leading-tight font-semibold">{{ $difficulty['label'] }}</div>
                        <dl class="divide-hairline mt-3 divide-y">
                            <div class="flex items-center justify-between py-1.5">
                                <dt class="meta-classical">{{ __('Solved') }}</dt>
                                <dd class="font-classical text-ink tnum text-[15px] font-medium">{{ $difficulty['count'] }}</dd>
                            </div>
                            <div class="flex items-center justify-between py-1.5">
                                <dt class="meta-classical">{{ __('Average') }}</dt>
                                <dd class="font-classical text-ink tnum text-[15px] font-medium">{{ $this->formatTime($difficulty['average']) }}</dd>
                            </div>
                            <div class="flex items-center justify-between py-1.5">
                                <dt class="meta-classical">{{ __('Fastest') }}</dt>
                                <dd class="font-classical tnum text-[15px] font-medium text-amber-400">{{ $this->formatTime($difficulty['fastest']) }}</dd>
                            </div>
                        </dl>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Solve History --}}
    <div class="border-border rounded-sm border p-4.5">
        <div class="border-hairline mb-2 border-b pb-3.5">
            <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Solve History') }}</h2>
        </div>

        @if($this->paginatedAttempts->isEmpty())
            <div class="border-border-strong mt-3 flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-10 text-center">
                <flux:icon name="clock" class="text-ink-faint mb-3 size-8" />
                <p class="text-ink-muted text-sm">{{ __('Complete puzzles to see your solve history here.') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-hairline border-b">
                            <th scope="col" class="meta-classical px-3 py-3 text-left font-normal">{{ __('Puzzle') }}</th>
                            <th scope="col" class="meta-classical px-3 py-3 text-left font-normal">{{ __('Size') }}</th>
                            <th scope="col" class="px-3 py-3 text-right font-normal">
                                <button type="button" wire:click="sortBy('solve_time_seconds')" class="meta-classical hover:text-ink inline-flex items-center gap-1 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                    {{ __('Solve Time') }}
                                    @if($sortField === 'solve_time_seconds')
                                        <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3 text-amber-400" />
                                    @endif
                                </button>
                            </th>
                            <th scope="col" class="meta-classical px-3 py-3 text-right font-normal">{{ __('vs. Avg') }}</th>
                            <th scope="col" class="px-3 py-3 text-right font-normal">
                                <button type="button" wire:click="sortBy('completed_at')" class="meta-classical hover:text-ink inline-flex items-center gap-1 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                    {{ __('Completed') }}
                                    @if($sortField === 'completed_at')
                                        <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3 text-amber-400" />
                                    @endif
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-hairline divide-y">
                        @foreach($this->paginatedAttempts as $attempt)
                            @php($communityAvg = $this->communityAverages[$attempt->crossword_id] ?? null)
                            @php($diff = $communityAvg && $communityAvg['solver_count'] > 1 ? $attempt->solve_time_seconds - $communityAvg['avg_time'] : null)
                            <tr wire:key="attempt-{{ $attempt->id }}" class="align-middle">
                                <td class="px-3 py-3">
                                    <a href="{{ route('crosswords.solver', $attempt->crossword_id) }}" wire:navigate class="font-classical text-ink hover:text-amber-300 text-[17px] leading-tight font-semibold transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        {{ $attempt->crossword->displayTitle() }}
                                    </a>
                                </td>
                                <td class="meta-classical tnum px-3 py-3 whitespace-nowrap">{{ $attempt->crossword->width }}&times;{{ $attempt->crossword->height }}</td>
                                <td class="font-classical text-ink tnum px-3 py-3 text-right text-[16px] font-medium whitespace-nowrap">{{ $this->formatTime($attempt->solve_time_seconds) }}</td>
                                <td class="tnum px-3 py-3 text-right whitespace-nowrap">
                                    @if($diff !== null)
                                        @if($diff < 0)
                                            <span class="text-amber-400">{{ $this->formatTime(abs($diff)) }} {{ __('faster') }}</span>
                                        @elseif($diff > 0)
                                            <span class="text-ink-muted">{{ $this->formatTime($diff) }} {{ __('slower') }}</span>
                                        @else
                                            <span class="text-ink-muted">{{ __('same') }}</span>
                                        @endif
                                    @else
                                        <span class="text-ink-faint">&mdash;</span>
                                    @endif
                                </td>
                                <td class="text-ink-muted px-3 py-3 text-right whitespace-nowrap">{{ $attempt->completed_at?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->paginatedAttempts->links() }}
            </div>
        @endif
    </div>
</div>
