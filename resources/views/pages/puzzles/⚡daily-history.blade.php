<?php

use App\Models\Crossword;
use App\Models\DailyPuzzle;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('Daily Puzzle History')]
#[Layout('layouts.public')]
class extends Component {
    use WithPagination;

    #[Computed]
    public function dailyPuzzles()
    {
        $query = DailyPuzzle::where('date', '<=', today())
            ->with(['crossword.user:id,name', 'crossword.user.subscriptions'])
            ->orderByDesc('date');

        return $query->paginate(21);
    }

    /** @return array<int, bool> */
    #[Computed]
    public function solvedCrosswordIds(): array
    {
        if (! Auth::check()) {
            return [];
        }

        $crosswordIds = collect($this->dailyPuzzles->items())
            ->pluck('crossword_id')
            ->unique()
            ->values();

        if ($crosswordIds->isEmpty()) {
            return [];
        }

        return Auth::user()
            ->puzzleAttempts()
            ->whereIn('crossword_id', $crosswordIds)
            ->where('is_completed', true)
            ->pluck('crossword_id')
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    public function startSolving(int $crosswordId): void
    {
        $crossword = Crossword::findOrFail($crosswordId);
        abort_unless($crossword->is_published, 404);
        abort_unless($crossword->isVisibleToSafeSearch(Auth::user()), 404);

        if (Auth::check()) {
            $this->redirect(route('crosswords.solver', $crossword), navigate: true);

            return;
        }

        $this->redirect(route('puzzles.solve', $crossword), navigate: true);
    }
}
?>

<div class="space-y-6">
    <x-seo-meta
        title="Puzzle of the Day"
        :canonical="route('puzzles.daily-history')"
        :description="__('A new featured crossword every day. Solve today\'s puzzle or catch up on ones you missed.')"
    />

    @push('head_meta')
        @php
            $dailyJsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => __('Puzzle of the Day'),
                'url' => route('puzzles.daily-history'),
                'description' => __('A new featured crossword every day. Solve today\'s puzzle or catch up on ones you missed.'),
                'isPartOf' => ['@id' => url('/').'#website'],
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'itemListElement' => collect($this->dailyPuzzles->items())
                        ->filter(fn ($daily) => $daily->crossword !== null)
                        ->values()
                        ->map(fn ($daily, $i) => [
                            '@type' => 'ListItem',
                            'position' => $i + 1,
                            'name' => __('Daily Puzzle — :date', ['date' => $daily->date->format('F j, Y')]),
                            'url' => route('puzzles.solve', $daily->crossword),
                        ])->all(),
                ],
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($dailyJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <x-page-header :kicker="__('Puzzle of the day')" :title="__('Daily Puzzle History')" :subtitle="__('Catch up on puzzles you may have missed.')">
        <x-header-button variant="secondary" icon="arrow-left" :href="route('puzzles.index')" wire:navigate>
            {{ __('Browse Puzzles') }}
        </x-header-button>
    </x-page-header>

    @php $results = $this->dailyPuzzles; @endphp

    @if($results->isEmpty())
        <div class="border-border-strong flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-16 text-center">
            <flux:icon name="calendar" class="text-ink-faint mb-4 size-10" />
            <h3 class="font-classical text-ink text-[26px] leading-tight font-medium">{{ __('No daily puzzles yet') }}</h3>
            <p class="text-ink-muted mt-2 text-sm">{{ __('Check back soon for daily puzzles.') }}</p>
        </div>
    @else
        <div class="grid gap-[22px] [grid-template-columns:repeat(auto-fill,minmax(268px,1fr))]">
            @foreach($results as $daily)
                @php
                    $crossword = $daily->crossword;
                    $isSolved = isset($this->solvedCrosswordIds[$crossword->id]);
                    $isToday = $daily->date->isToday();
                @endphp
                <article
                    wire:key="daily-{{ $daily->id }}"
                    @class([
                        'flex flex-col gap-3.5 rounded-sm border p-[18px] transition-colors',
                        'border-amber-400/60 hover:border-amber-400' => $isToday,
                        'border-border hover:border-border-strong' => ! $isToday,
                    ])
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="chip-classical tnum {{ $isToday ? 'border-amber-400 text-amber-400' : 'border-ink-faint text-ink-faint' }}">
                                {{ $daily->date->format('M j, Y') }}
                            </span>
                            @if($isToday)
                                <span class="chip-classical border-amber-400 text-amber-400">{{ __('Today') }}</span>
                            @endif
                        </div>
                        @if($isSolved)
                            <span class="chip-classical border-amber-400 text-amber-400 gap-1">
                                <flux:icon name="check-circle" class="size-3" />
                                {{ __('Solved') }}
                            </span>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <h3 class="font-classical text-ink truncate text-[21px] leading-tight font-semibold">{{ $crossword->displayTitle() }}</h3>
                        <div class="meta-classical mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <span class="flex items-center gap-1">
                                {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }} <x-supporter-badge :user="$crossword->user" />
                            </span>
                            <span aria-hidden="true">&middot;</span>
                            <span class="tnum whitespace-nowrap">{{ $crossword->width }}&times;{{ $crossword->height }}</span>
                        </div>
                    </div>

                    <div class="flex justify-center py-1">
                        <x-grid-thumbnail
                            :grid="$crossword->grid"
                            :width="$crossword->width"
                            :height="$crossword->height"
                            frame-class="border-hairline bg-hairline rounded-sm border"
                            open-class="bg-panel"
                            block-class="bg-zinc-300"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        @if($crossword->difficulty_label)
                            <span class="chip-classical border-ink-faint text-ink-faint">{{ __($crossword->difficulty_label) }}</span>
                        @endif
                        <span class="chip-classical border-ink-faint text-ink-faint tnum">{{ $crossword->width }}&times;{{ $crossword->height }}</span>
                    </div>

                    <div class="pt-1">
                        @if($isSolved)
                            <button type="button" class="btn-classical btn-classical-muted" wire:click="startSolving({{ $crossword->id }})">
                                <flux:icon name="eye" class="size-4" />
                                {{ __('View Solution') }}
                            </button>
                        @else
                            <button type="button" class="btn-classical btn-amber-outline" wire:click="startSolving({{ $crossword->id }})">
                                <flux:icon name="play" class="size-4" />
                                {{ __('Solve') }}
                            </button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if($results->hasPages())
            <div class="mt-4">
                {{ $results->links() }}
            </div>
        @endif
    @endif
</div>
