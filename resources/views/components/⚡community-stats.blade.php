<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Site-wide community totals, shown as a stats band under the Constructors
 * page header. The counts are cached briefly since they are the same for
 * every visitor and this is a public page.
 */
new class extends Component {
    #[Computed]
    public function constructorCount(): int
    {
        return Cache::remember('stats:constructors', 300, fn () => User::where('is_anonymous', false)
            ->whereHas('crosswords', fn ($q) => $q->where('is_published', true))
            ->count());
    }

    #[Computed]
    public function totalPublishedPuzzles(): int
    {
        return Cache::remember('stats:published_puzzles', 300, fn () => Crossword::where('is_published', true)->count());
    }

    #[Computed]
    public function totalSolves(): int
    {
        return Cache::remember('stats:total_solves', 300, fn () => PuzzleAttempt::where('is_completed', true)->count());
    }

    #[Computed]
    public function totalLikes(): int
    {
        return Cache::remember('stats:total_likes', 300, fn () => CrosswordLike::count());
    }
}
?>

<div>
    {{-- Overview band, matching the builder stats strip on the Build page.
         Phones get two tiles per row; wider screens take the whole row. --}}
    <div class="grid grid-cols-2 sm:grid-cols-4">
        @foreach ([
            ['label' => __('Constructors'), 'value' => $this->constructorCount],
            ['label' => __('Published Puzzles'), 'value' => $this->totalPublishedPuzzles],
            ['label' => __('Total Solves'), 'value' => $this->totalSolves],
            ['label' => __('Total Likes'), 'value' => $this->totalLikes],
        ] as $stat)
            <div class="border-hairline border-t-0 border px-4 py-3 sm:px-6 sm:py-4.5 lg:px-8" data-test="community-stat">
                <div class="font-classical tnum text-[22px] leading-none font-medium text-amber-700 sm:text-[30px] dark:text-amber-400">{{ $stat['value'] }}</div>
                <div class="meta-classical mt-1 sm:mt-1.5">{{ $stat['label'] }}</div>
            </div>
        @endforeach
    </div>
</div>
