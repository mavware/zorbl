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
    {{-- Overview Cards --}}
    <div class="border-hairline grid border-b sm:grid-cols-2 lg:grid-cols-5">
        @foreach ([
            ['label' => __('PUBLISHED'), 'value' => $this->publishedCount],
            ['label' => __('DRAFTS'), 'value' => $this->draftCount],
            ['label' => __('TOTAL SOLVES'), 'value' => $this->totalSolves],
            ['label' => __('COMPLETIONS'), 'value' => $this->totalCompletions],
            ['label' => __('TOTAL LIKES'), 'value' => $this->totalLikes],
        ] as $stat)
            <div class="border-hairline border-b px-6 py-[18px] last:border-b-0 sm:border-e sm:even:border-e-0 lg:border-b-0 lg:even:border-e lg:last:border-e-0 lg:px-8" data-test="constructor-stat">
                <div class="font-classical tnum text-[30px] leading-none font-medium text-amber-700 dark:text-amber-400">{{ $stat['value'] }}</div>
                <div class="meta-classical mt-1.5">{{ $stat['label'] }}</div>
            </div>
        @endforeach
    </div>
</div>
