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
    public bool $authorIsSupporter = false;

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
        $this->authorIsSupporter = $crossword->user?->isSupporter() ?? false;
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
        if (! Auth::check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

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

        if (Auth::check()) {
            $this->authorize('solve', $crossword);

            $this->redirect(route('crosswords.solver', $crossword), navigate: true);

            return;
        }

        abort_unless($crossword->is_published, 404);
        abort_unless($crossword->isVisibleToSafeSearch(null), 404);

        $solved = json_decode(request()->cookie('crosswordbuilder_guest_solved', '[]'), true) ?: [];

        if (count($solved) >= config('crosswordbuilder.guest_solve_limit') && ! in_array($crossword->id, $solved)) {
            $this->dispatch('show-signup-prompt');

            return;
        }

        $this->redirect(route('puzzles.solve', $crossword), navigate: true);
    }
};
?>

<div
    wire:click="startSolving"
    @class([
        'group relative flex cursor-pointer flex-col gap-3.5 rounded-sm border p-4.5 transition-colors',
        'border-amber-400/60 hover:border-amber-400' => $isDaily,
        'border-border hover:border-border-strong' => ! $isDaily,
    ])
>
    @if($isDaily)
        <div class="meta-classical text-amber-400 flex items-center gap-1.5">
            <flux:icon name="star" class="size-3.5" />
            {{ __('Puzzle of the Day') }}
        </div>
    @endif

    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="font-classical text-ink group-hover:text-amber-300 truncate text-[21px] leading-tight font-semibold transition-colors">{{ $title }}</h3>
            <div class="meta-classical mt-1 flex items-center gap-1">
                {{ __('by :author', ['author' => $authorName]) }} <x-supporter-badge :supporter="$authorIsSupporter" />
            </div>
        </div>
        @if($isSolved)
            <span class="chip-classical border-amber-400 text-amber-400 gap-1" title="{{ __('Solved') }}">
                <flux:icon name="check-circle" class="size-3" />
                {{ __('Solved') }}
            </span>
        @endif
    </div>

    <div class="flex justify-center py-1">
        <x-grid-thumbnail
            :grid="$grid"
            :width="$width"
            :height="$height"
            frame-class="border-hairline bg-hairline rounded-sm border"
            open-class="bg-panel"
            block-class="bg-zinc-300"
        />
    </div>

    <div class="flex flex-wrap items-center gap-1.5">
        <span class="chip-classical border-ink-faint text-ink-faint">{{ __($typeLabel) }}</span>
        <span class="chip-classical border-ink-faint text-ink-faint tnum">{{ $width }}&times;{{ $height }}</span>
        @if($difficultyLabel)
            <span class="chip-classical border-ink-faint text-ink-faint">{{ __($difficultyLabel) }}</span>
        @endif
        @foreach($tagNames as $tagName)
            <span class="chip-classical border-ink-faint text-ink-faint">{{ $tagName }}</span>
        @endforeach
    </div>

    <div class="meta-classical flex flex-wrap items-center gap-x-3 gap-y-1">
        <span class="flex items-center gap-1 whitespace-nowrap">
            <flux:icon name="check-circle" class="size-3.5" />
            <span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal normal-case">{{ trans_choice(':count solve|:count solves', $completedCount) }}</span>
        </span>
        @if($avgSolveTime)
            <span class="flex items-center gap-1 whitespace-nowrap">
                <flux:icon name="clock" class="size-3.5" />
                @php
                    $avgHours = intdiv($avgSolveTime, 3600);
                    $avgMinutes = intdiv($avgSolveTime % 3600, 60);
                    $avgSecs = $avgSolveTime % 60;
                    $formattedAvg = $avgHours > 0
                        ? sprintf('%d:%02d:%02d', $avgHours, $avgMinutes, $avgSecs)
                        : sprintf('%d:%02d', $avgMinutes, $avgSecs);
                @endphp
                <span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal normal-case">{{ __('avg :time', ['time' => $formattedAvg]) }}</span>
            </span>
        @endif
        @if($avgRating)
            <span class="flex items-center gap-0.5" title="{{ __(':rating out of 5', ['rating' => number_format($avgRating, 1)]) }}">
                @for($i = 1; $i <= 5; $i++)
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3 {{ $i <= round($avgRating) ? 'text-amber-400' : 'text-border-strong' }}" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
                @endfor
            </span>
        @endif
        @if($attemptsCount > 0)
            <span class="flex items-center gap-1 whitespace-nowrap">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal normal-case">{{ $attemptsCount }} {{ trans_choice('play|plays', $attemptsCount) }}</span>
            </span>
            @php
                $completionRate = (int) round(($completedCount / $attemptsCount) * 100);
            @endphp
            <span class="flex items-center gap-1 whitespace-nowrap" title="{{ __(':rate% of solvers completed this puzzle', ['rate' => $completionRate]) }}">
                <flux:icon name="chart-bar" class="size-3.5" />
                <span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal normal-case">{{ $completionRate }}%</span>
            </span>
        @endif
    </div>

    <div class="flex items-center justify-between gap-2 pt-1">
        <button type="button" class="btn-classical btn-amber-outline">
            @auth
                {{ __('Start Solving') }}
            @else
                {{ __('Try This Puzzle') }}
            @endauth
        </button>
        <button
            type="button"
            wire:click.stop="toggleLike"
            class="btn-classical h-9 px-3 {{ $isLiked ? 'btn-amber-outline' : 'btn-classical-muted' }}"
            aria-pressed="{{ $isLiked ? 'true' : 'false' }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="{{ $isLiked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
            </svg>
            <span class="tnum">{{ $likesCount }}</span>
        </button>
    </div>
</div>
