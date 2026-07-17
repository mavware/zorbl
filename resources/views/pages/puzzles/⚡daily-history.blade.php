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
            ->with('crossword.user:id,name')
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

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Daily Puzzle History') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-600">{{ __('Catch up on puzzles you may have missed.') }}</flux:text>
        </div>
        <flux:button variant="ghost" size="sm" :href="route('puzzles.index')" wire:navigate icon="arrow-left">
            {{ __('Browse Puzzles') }}
        </flux:button>
    </div>

    @php $results = $this->dailyPuzzles; @endphp

    @if($results->isEmpty())
        <div class="border-line-strong flex flex-col items-center justify-center rounded-xl border border-dashed py-12">
            <flux:icon name="calendar" class="mb-4 size-12 text-zinc-500" />
            <flux:heading size="lg" class="mb-2">{{ __('No daily puzzles yet') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Check back soon for daily puzzles.') }}</flux:text>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($results as $daily)
                @php
                    $crossword = $daily->crossword;
                    $isSolved = isset($this->solvedCrosswordIds[$crossword->id]);
                    $isToday = $daily->date->isToday();
                @endphp
                <div
                    wire:key="daily-{{ $daily->id }}"
                    class="border-line group rounded-xl border p-4 transition-colors hover:border-zinc-400 dark:hover:border-zinc-500 {{ $isToday ? 'ring-2 ring-amber-400/50' : '' }}"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" color="{{ $isToday ? 'amber' : 'zinc' }}">
                                {{ $daily->date->format('M j, Y') }}
                            </flux:badge>
                            @if($isToday)
                                <flux:badge size="sm" color="amber">{{ __('Today') }}</flux:badge>
                            @endif
                        </div>
                        @if($isSolved)
                            <flux:badge size="sm" color="green" icon="check-circle">{{ __('Solved') }}</flux:badge>
                        @endif
                    </div>

                    <div class="mb-3 flex justify-center">
                        <x-grid-thumbnail :grid="$crossword->grid" :width="$crossword->width" :height="$crossword->height" />
                    </div>

                    <flux:heading size="sm" class="truncate">{{ $crossword->displayTitle() }}</flux:heading>
                    <flux:text size="sm" class="mt-1">
                        {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }}
                        &middot;
                        {{ $crossword->width }}&times;{{ $crossword->height }}
                    </flux:text>

                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        @if($crossword->difficulty_label)
                            <flux:badge
                                size="sm"
                                :color="match($crossword->difficulty_label) { 'Easy' => 'green', 'Medium' => 'amber', 'Hard' => 'orange', 'Expert' => 'red', default => 'zinc' }"
                            >{{ __($crossword->difficulty_label) }}</flux:badge>
                        @endif
                        <flux:badge size="sm" variant="outline">{{ $crossword->width }}&times;{{ $crossword->height }}</flux:badge>
                    </div>

                    <div class="mt-3">
                        @if($isSolved)
                            <flux:button size="sm" variant="filled" wire:click="startSolving({{ $crossword->id }})" icon="eye">
                                {{ __('View Solution') }}
                            </flux:button>
                        @else
                            <flux:button size="sm" variant="primary" wire:click="startSolving({{ $crossword->id }})" icon="play">
                                {{ __('Solve') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if($results->hasPages())
            <div class="mt-4">
                {{ $results->links() }}
            </div>
        @endif
    @endif
</div>
