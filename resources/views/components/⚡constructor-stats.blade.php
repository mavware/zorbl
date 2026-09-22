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
    <div class="grid sm:grid-cols-2 lg:grid-cols-5">
        @foreach ([
            ['label' => __('Published'), 'value' => $this->publishedCount],
            ['label' => __('Drafts'), 'value' => $this->draftCount],
            ['label' => __('Total Solves'), 'value' => $this->totalSolves],
            ['label' => __('Completions'), 'value' => $this->totalCompletions],
            ['label' => __('Total Likes'), 'value' => $this->totalLikes],
        ] as $stat)
            <div class="border-hairline border-t-0 border px-6 py-4.5 lg:px-8" data-test="constructor-stat">
                <div class="font-classical tnum text-[30px] leading-none font-medium text-amber-700 dark:text-amber-400">{{ $stat['value'] }}</div>
                <div class="meta-classical mt-1.5">{{ $stat['label'] }}</div>
            </div>
        @endforeach
    </div>
</div>
