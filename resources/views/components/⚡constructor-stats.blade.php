<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function publishedCount(): int
    {
        return Auth::user()->crosswords()->where('is_published', true)->count();
    }

    #[Computed]
    public function draftCount(): int
    {
        return Auth::user()->crosswords()->where('is_published', false)->count();
    }

    #[Computed]
    public function totalSolves(): int
    {
        return (int) Auth::user()->crosswords()
            ->where('is_published', true)
            ->sum('cached_attempts_count');
    }

    #[Computed]
    public function totalCompletions(): int
    {
        return (int) Auth::user()->crosswords()
            ->where('is_published', true)
            ->sum('cached_completed_count');
    }

    #[Computed]
    public function totalLikes(): int
    {
        return DB::table('crossword_likes')
            ->whereIn(
                'crossword_id',
                Auth::user()->crosswords()->where('is_published', true)->select('id')
            )
            ->count();
    }
}
?>

<div>
    {{-- Overview Cards. Phones get a single compact row of just Published and
         Drafts; the solve/completion/like tiles join from the sm breakpoint. --}}
    <div class="grid grid-cols-2 lg:grid-cols-5">
        @foreach ([
            ['label' => __('Published'), 'value' => $this->publishedCount, 'mobile' => true],
            ['label' => __('Drafts'), 'value' => $this->draftCount, 'mobile' => true],
            ['label' => __('Total Solves'), 'value' => $this->totalSolves, 'mobile' => false],
            ['label' => __('Completions'), 'value' => $this->totalCompletions, 'mobile' => false],
            ['label' => __('Total Likes'), 'value' => $this->totalLikes, 'mobile' => false],
        ] as $stat)
            <div class="border-hairline border-t-0 border px-4 py-3 sm:px-6 sm:py-4.5 lg:px-8 {{ $stat['mobile'] ? '' : 'hidden sm:block' }}" data-test="constructor-stat">
                <div class="font-classical tnum text-[22px] leading-none font-medium text-amber-700 sm:text-[30px] dark:text-amber-400">{{ $stat['value'] }}</div>
                <div class="meta-classical mt-1 sm:mt-1.5">{{ $stat['label'] }}</div>
            </div>
        @endforeach
    </div>
</div>
