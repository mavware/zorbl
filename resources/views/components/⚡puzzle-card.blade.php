<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $crosswordId;

    #[Locked]
    public string $title;

    #[Locked]
    public string $authorName;

    #[Locked]
    public int $width;

    #[Locked]
    public int $height;

    /** @var array<int, array<int, mixed>> */
    #[Locked]
    public array $grid = [];

    #[Locked]
    public string $typeLabel;

    #[Locked]
    public ?string $difficultyLabel = null;

    /** @var list<string> */
    #[Locked]
    public array $tagNames = [];

    #[Locked]
    public int $completedCount = 0;

    #[Locked]
    public ?int $avgSolveTime = null;

    #[Locked]
    public ?float $avgRating = null;

    #[Locked]
    public int $attemptsCount = 0;

    public int $likesCount = 0;

    public bool $isLiked = false;

    #[Locked]
    public bool $isDaily = false;

    #[Locked]
    public bool $isSolved = false;

    public function mount(Crossword $crossword, bool $isLiked = false, bool $isDaily = false, bool $isSolved = false): void
    {
        $this->crosswordId = $crossword->id;
        $this->title = $crossword->displayTitle();
        $this->authorName = $crossword->user->name ?? __('Unknown');
        $this->width = $crossword->width;
        $this->height = $crossword->height;
        $this->grid = $crossword->grid ?? [];
        $this->typeLabel = $crossword->puzzleTypeLabel();
        $this->difficultyLabel = $crossword->difficulty_label;
        $this->tagNames = $crossword->tags->pluck('name')->all();
        $this->completedCount = (int) $crossword->cached_completed_count;
        $this->avgSolveTime = $crossword->cached_avg_solve_time !== null
            ? (int) $crossword->cached_avg_solve_time
            : null;
        $this->avgRating = $crossword->avg_rating !== null
            ? (float) $crossword->avg_rating
            : null;
        $this->attemptsCount = (int) $crossword->cached_attempts_count;
        $this->likesCount = (int) ($crossword->likes_count ?? 0);
        $this->isLiked = $isLiked;
        $this->isDaily = $isDaily;
        $this->isSolved = $isSolved;
    }

    public function toggleLike(): void
    {
        $like = CrosswordLike::where('user_id', Auth::id())
            ->where('crossword_id', $this->crosswordId)
            ->first();

        if ($like) {
            $like->delete();
            $this->isLiked = false;
            $this->likesCount = max(0, $this->likesCount - 1);
        } else {
            CrosswordLike::create([
                'user_id' => Auth::id(),
                'crossword_id' => $this->crosswordId,
            ]);
            $this->isLiked = true;
            $this->likesCount++;
        }
    }

    public function startSolving(): void
    {
        $crossword = Crossword::findOrFail($this->crosswordId);
        $this->authorize('solve', $crossword);

        $this->redirect(route('crosswords.solver', $crossword), navigate: true);
    }
};
?>

<div
    wire:click="startSolving"
    @class([
        'group relative rounded-xl border overflow-hidden transition-colors cursor-pointer',
        'border-amber-200 bg-amber-50 hover:border-amber-300 dark:border-amber-800/50 dark:bg-amber-950/30 dark:hover:border-amber-700/60' => $isDaily,
        'border-line hover:border-zinc-400 dark:hover:border-zinc-500 p-4' => ! $isDaily,
    ])
>
    @if($isDaily)
        <div class="bg-amber-200 dark:bg-amber-800/50 px-4 py-1.5 text-center">
            <flux:heading size="sm" class="text-amber-800 dark:text-amber-200">{{ __('Puzzle of the Day') }}</flux:heading>
        </div>
    @endif

    <div @class(['p-4 relative' => $isDaily])>
    @if($isSolved)
        <span class="absolute right-2 top-2 inline-flex" title="{{ __('Solved') }}">
            <flux:icon name="check-circle" class="size-5 text-emerald-500" />
        </span>
    @endif

    <div class="mb-3 flex justify-center">
        <x-grid-thumbnail :grid="$grid" :width="$width" :height="$height" />
    </div>

    <flux:heading size="sm" class="truncate">{{ $title }}</flux:heading>
    <flux:text size="sm" class="mt-1">
        {{ __('by :author', ['author' => $authorName]) }}
    </flux:text>

    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
        <flux:badge size="sm" variant="outline">{{ __($typeLabel) }}</flux:badge>
        <flux:badge size="sm" color="indigo">{{ $width }}&times;{{ $height }}</flux:badge>
        @if($difficultyLabel)
            <flux:badge
                size="sm"
                :color="match($difficultyLabel) { 'Easy' => 'green', 'Medium' => 'amber', 'Hard' => 'orange', 'Expert' => 'red', default => 'zinc' }"
            >{{ __($difficultyLabel) }}</flux:badge>
        @endif
        @foreach($tagNames as $tagName)
            <flux:badge size="sm" color="blue">{{ $tagName }}</flux:badge>
        @endforeach
    </div>

    <div class="mt-2 flex items-center gap-3 text-xs text-zinc-500">
        <span class="flex items-center gap-0.5">
            <flux:icon name="check-circle" class="size-3.5" />
            {{ trans_choice(':count solve|:count solves', $completedCount) }}
        </span>
        @if($avgSolveTime)
            <span class="flex items-center gap-0.5">
                <flux:icon name="clock" class="size-3.5" />
                @php
                    $avgHours = intdiv($avgSolveTime, 3600);
                    $avgMinutes = intdiv($avgSolveTime % 3600, 60);
                    $avgSecs = $avgSolveTime % 60;
                    $formattedAvg = $avgHours > 0
                        ? sprintf('%d:%02d:%02d', $avgHours, $avgMinutes, $avgSecs)
                        : sprintf('%d:%02d', $avgMinutes, $avgSecs);
                @endphp
                {{ __('avg :time', ['time' => $formattedAvg]) }}
            </span>
        @endif
        @if($avgRating)
            <span class="flex items-center gap-0.5" title="{{ __(':rating out of 5', ['rating' => number_format($avgRating, 1)]) }}">
                @for($i = 1; $i <= 5; $i++)
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3 {{ $i <= round($avgRating) ? 'text-amber-400' : 'text-zinc-300 dark:text-zinc-600' }}" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
                @endfor
            </span>
        @endif
        @if($attemptsCount > 0)
            <span class="flex items-center gap-0.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                {{ $attemptsCount }} {{ trans_choice('play|plays', $attemptsCount) }}
            </span>
            @php
                $completionRate = (int) round(($completedCount / $attemptsCount) * 100);
            @endphp
            <span class="flex items-center gap-0.5" title="{{ __(':rate% of solvers completed this puzzle', ['rate' => $completionRate]) }}">
                <flux:icon name="chart-bar" class="size-3.5" />
                {{ $completionRate }}%
            </span>
        @endif
    </div>

    <div class="mt-3 flex items-center justify-between">
        <flux:button size="sm" variant="primary">
            {{ __('Start Solving') }}
        </flux:button>
        <button
            type="button"
            wire:click.stop="toggleLike"
            class="flex items-center gap-1 rounded-lg px-2 py-1 text-xs transition-colors {{ $isLiked ? 'text-red-500' : 'text-zinc-500 hover:text-red-400' }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="{{ $isLiked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
            </svg>
            <span>{{ $likesCount }}</span>
        </button>
    </div>
    </div>
</div>
