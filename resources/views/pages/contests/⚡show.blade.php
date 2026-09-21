<?php

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\PuzzleAttempt;
use App\Services\AchievementService;
use App\Services\ContestService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Contest')] class extends Component {
    #[Locked]
    public Contest $contest;

    public string $metaAnswer = '';
    public ?string $metaFeedback = null;
    public bool $metaSuccess = false;

    public function mount(Contest $contest): void
    {
        $this->contest = $contest->loadCount(['entries', 'crosswords']);
    }

    #[Computed]
    public function entry(): ?ContestEntry
    {
        if (! Auth::check()) {
            return null;
        }

        return ContestEntry::where('contest_id', $this->contest->id)
            ->where('user_id', Auth::id())
            ->first();
    }

    #[Computed]
    public function crosswords()
    {
        return $this->contest->crosswords()->orderByPivot('sort_order')->get();
    }

    #[Computed]
    public function puzzleStatuses(): array
    {
        if (! Auth::check()) {
            return [];
        }

        $crosswordIds = $this->crosswords->pluck('id');

        return PuzzleAttempt::where('user_id', Auth::id())
            ->whereIn('crossword_id', $crosswordIds)
            ->where('is_completed', true)
            ->pluck('crossword_id')
            ->flip()
            ->all();
    }

    public function register(): void
    {
        $this->authorize('register', $this->contest);

        app(ContestService::class)->register(Auth::user(), $this->contest);
        unset($this->entry);
    }

    public function submitMetaAnswer(): void
    {
        $this->authorize('submitMeta', $this->contest);

        $this->validate([
            'metaAnswer' => ['required', 'string', 'max:255'],
        ]);

        $entry = $this->entry;
        $isCorrect = app(ContestService::class)->submitMetaAnswer($entry, $this->metaAnswer);

        if ($isCorrect) {
            $this->metaFeedback = __('Correct! You solved the meta!');
            $this->metaSuccess = true;
            app(AchievementService::class)->checkContestAchievements(Auth::user(), $entry->fresh());
        } else {
            $this->metaFeedback = __('Incorrect. Try again!');
            $this->metaSuccess = false;
        }

        $this->metaAnswer = '';
        unset($this->entry);
    }
}
?>

<div class="mx-auto max-w-4xl space-y-8">
    {{-- Contest Header --}}
    <x-page-header :title="$contest->title">
        <x-slot:badges>
            @if($contest->isActive())
                <span class="chip-classical border-amber-400 text-amber-400">{{ __('Active') }}</span>
            @elseif($contest->isUpcoming())
                <span class="chip-classical border-ink-faint text-ink-faint">{{ __('Upcoming') }}</span>
            @elseif($contest->hasEnded())
                <span class="chip-classical border-ink-faint text-ink-faint">{{ __('Ended') }}</span>
            @endif
            @if($contest->is_featured)
                <span class="chip-classical border-amber-400 text-amber-400">{{ __('Featured') }}</span>
            @endif
        </x-slot:badges>

        <x-slot:meta>
            <span class="tnum whitespace-nowrap">{{ $contest->starts_at->format('M j, Y g:ia') }} &ndash; {{ $contest->ends_at->format('M j, Y g:ia') }}</span>
            <span class="whitespace-nowrap"><span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $contest->crosswords_count }}</span> {{ __('puzzles') }}</span>
            <span class="whitespace-nowrap"><span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $contest->entries_count }}</span> {{ __('participants') }}</span>
            @if($contest->isActive() && $contest->ends_at->isFuture())
                <span class="whitespace-nowrap text-amber-400">{{ __('Ends :time', ['time' => $contest->ends_at->diffForHumans()]) }}</span>
            @endif
        </x-slot:meta>
    </x-page-header>

    {{-- Description & Rules --}}
    @if($contest->description)
        <div class="prose dark:prose-invert max-w-none">
            {!! nl2br(e($contest->description)) !!}
        </div>
    @endif

    @if($contest->rules)
        <div class="border-line rounded-xl border p-5">
            <flux:heading size="lg" class="mb-2">{{ __('Rules') }}</flux:heading>
            <div class="prose dark:prose-invert max-w-none text-sm">
                {!! nl2br(e($contest->rules)) !!}
            </div>
        </div>
    @endif

    {{-- Join / Registration --}}
    @auth
        @if(! $this->entry && ($contest->isActive() || $contest->isUpcoming()))
            <div class="flex justify-center">
                <flux:button variant="primary" wire:click="register" icon="plus">
                    {{ __('Join Contest') }}
                </flux:button>
            </div>
        @elseif($this->entry)
            <flux:badge color="green" size="sm">{{ __('Registered') }}</flux:badge>
        @endif
    @else
        <div class="flex justify-center">
            <flux:button variant="primary" :href="route('login')" wire:navigate>
                {{ __('Sign in to join') }}
            </flux:button>
        </div>
    @endauth

    {{-- Puzzle List --}}
    <div class="space-y-5">
        <h2 class="font-classical text-ink border-hairline border-b pb-3.5 text-[26px] leading-tight font-medium">{{ __('Puzzles') }}</h2>

        <div class="border-border divide-hairline divide-y rounded-sm border">
            @foreach($this->crosswords as $index => $crossword)
                <div
                    wire:key="puzzle-{{ $crossword->id }}"
                    class="flex flex-wrap items-center justify-between gap-3 px-[18px] py-3.5"
                >
                    <div class="flex min-w-0 items-center gap-3.5">
                        <span class="border-border-strong font-classical text-ink tnum flex size-8 shrink-0 items-center justify-center rounded-full border text-[15px] font-medium">
                            {{ $index + 1 }}
                        </span>
                        <div class="min-w-0">
                            <div class="font-classical text-ink truncate text-[18px] leading-tight font-semibold">{{ $crossword->displayTitle() }}</div>
                            <div class="meta-classical mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <span class="tnum whitespace-nowrap">{{ $crossword->width }}&times;{{ $crossword->height }}</span>
                                @if($crossword->pivot->extraction_hint)
                                    <span aria-hidden="true">&middot;</span>
                                    <span>{{ $crossword->pivot->extraction_hint }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        @if(isset($this->puzzleStatuses[$crossword->id]))
                            <span class="chip-classical border-amber-400 text-amber-400 gap-1">
                                <flux:icon name="check" class="size-3" />
                                {{ __('Solved') }}
                            </span>
                        @endif

                        @if($this->entry && $contest->isActive())
                            <a href="{{ route('crosswords.solver', $crossword) }}" wire:navigate class="btn-classical btn-amber-outline h-8 px-3 text-[14px]">
                                {{ __('Solve') }}
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Meta Answer Submission --}}
    @if($this->entry && $contest->isActive())
        <div class="border-line rounded-xl border p-5">
            <flux:heading size="lg" class="mb-2">{{ __('Meta Answer') }}</flux:heading>

            @if($contest->meta_hint)
                <flux:text size="sm" class="mb-4">{{ $contest->meta_hint }}</flux:text>
            @endif

            @if($this->entry->meta_solved)
                <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                    <flux:icon name="check-circle" class="size-5" />
                    <flux:text class="font-medium">{{ __('You solved the meta!') }}</flux:text>
                </div>
            @else
                <form wire:submit="submitMetaAnswer" class="flex gap-3">
                    <flux:input
                        wire:model="metaAnswer"
                        placeholder="{{ __('Enter your answer...') }}"
                        class="flex-1"
                    />
                    <flux:button type="submit" variant="primary">
                        {{ __('Submit') }}
                    </flux:button>
                </form>

                @if($contest->max_meta_attempts > 0)
                    <flux:text size="xs" class="mt-2">
                        {{ __(':used / :max attempts used', ['used' => $this->entry->meta_attempts_count, 'max' => $contest->max_meta_attempts]) }}
                    </flux:text>
                @endif

                @if($metaFeedback)
                    <div class="mt-3 rounded-lg p-3 {{ $metaSuccess ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' }}">
                        {{ $metaFeedback }}
                    </div>
                @endif
            @endif
        </div>
    @endif

    {{-- Leaderboard Link --}}
    <div class="flex justify-center">
        <flux:button variant="ghost" :href="route('contests.leaderboard', $contest)" wire:navigate icon="chart-bar">
            {{ __('View Leaderboard') }}
        </flux:button>
    </div>
</div>
