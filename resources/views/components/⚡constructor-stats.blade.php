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
            ['icon' => 'puzzle-piece', 'label' => __('Published'), 'value' => $this->publishedCount],
            ['icon' => 'pencil', 'label' => __('Drafts'), 'value' => $this->draftCount],
            ['icon' => 'eye', 'label' => __('Total Solves'), 'value' => $this->totalSolves],
            ['icon' => 'check-circle', 'label' => __('Completions'), 'value' => $this->totalCompletions],
            ['icon' => 'heart', 'label' => __('Total Likes'), 'value' => $this->totalLikes],
        ] as $stat)
            <div class="border-hairline flex items-center gap-4 border-b px-6 py-[18px] last:border-b-0 sm:border-e sm:even:border-e-0 lg:border-b-0 lg:even:border-e lg:last:border-e-0 lg:px-8">
                <div class="border-border-strong text-ink-faint flex size-10 shrink-0 items-center justify-center rounded-sm border">
                    <flux:icon :name="$stat['icon']" variant="outline" class="size-5" />
                </div>
                <div>
                    <div class="meta-classical">{{ $stat['label'] }}</div>
                    <div class="font-classical text-ink tnum text-[30px] leading-none font-medium">{{ $stat['value'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>
