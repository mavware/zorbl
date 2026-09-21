<?php

use App\Models\Contest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Contests')] class extends Component {
    #[Computed]
    public function activeContests()
    {
        return Contest::active()
            ->withCount(['entries', 'crosswords'])
            ->latest('starts_at')
            ->get();
    }

    #[Computed]
    public function upcomingContests()
    {
        return Contest::upcoming()
            ->withCount(['entries', 'crosswords'])
            ->orderBy('starts_at')
            ->get();
    }

    #[Computed]
    public function pastContests()
    {
        return Contest::ended()
            ->withCount(['entries', 'crosswords'])
            ->latest('ends_at')
            ->take(12)
            ->get();
    }
}
?>

<div class="space-y-10">
    {{-- Active Contests --}}
    <div class="space-y-4">
        <x-page-header :kicker="__('Compete')" :title="__('Active Contests')" />

        @if($this->activeContests->isEmpty())
            <div class="border-border-strong flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-16 text-center">
                <flux:icon name="trophy" class="text-ink-faint mb-4 size-10" />
                <h3 class="font-classical text-ink text-[26px] leading-tight font-medium">{{ __('No active contests') }}</h3>
                <p class="text-ink-muted mt-2 text-sm">{{ __('Check back soon for new contests.') }}</p>
            </div>
        @else
            <div class="grid gap-[22px] [grid-template-columns:repeat(auto-fill,minmax(268px,1fr))]">
                @foreach($this->activeContests as $contest)
                    <a
                        href="{{ route('contests.show', $contest) }}"
                        wire:navigate
                        wire:key="active-{{ $contest->id }}"
                        class="border-border hover:border-border-strong group flex flex-col gap-3 rounded-sm border p-[18px] transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                    >
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="chip-classical border-amber-400 text-amber-400">{{ __('Active') }}</span>
                            @if($contest->is_featured)
                                <span class="chip-classical border-amber-400 text-amber-400">{{ __('Featured') }}</span>
                            @endif
                        </div>
                        <h3 class="font-classical text-ink group-hover:text-amber-300 truncate text-[21px] leading-tight font-semibold transition-colors">{{ $contest->title }}</h3>
                        <div class="meta-classical tnum">
                            {{ $contest->starts_at->format('M j') }} &ndash; {{ $contest->ends_at->format('M j, Y') }}
                        </div>
                        <div class="meta-classical flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <span class="whitespace-nowrap"><span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $contest->crosswords_count }}</span> {{ __('puzzles') }}</span>
                            <span aria-hidden="true">&middot;</span>
                            <span class="whitespace-nowrap"><span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $contest->entries_count }}</span> {{ __('participants') }}</span>
                        </div>
                        @if($contest->ends_at->isFuture())
                            <div class="meta-classical text-amber-400">{{ __('Ends :time', ['time' => $contest->ends_at->diffForHumans()]) }}</div>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Upcoming Contests --}}
    @if($this->upcomingContests->isNotEmpty())
        <div class="space-y-5">
            <h2 class="font-classical text-ink border-hairline border-b pb-3.5 text-[26px] leading-tight font-medium">{{ __('Upcoming Contests') }}</h2>
            <div class="grid gap-[22px] [grid-template-columns:repeat(auto-fill,minmax(268px,1fr))]">
                @foreach($this->upcomingContests as $contest)
                    <a
                        href="{{ route('contests.show', $contest) }}"
                        wire:navigate
                        wire:key="upcoming-{{ $contest->id }}"
                        class="border-border hover:border-border-strong group flex flex-col gap-3 rounded-sm border p-[18px] transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                    >
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="chip-classical border-ink-faint text-ink-faint">{{ __('Upcoming') }}</span>
                            @if($contest->is_featured)
                                <span class="chip-classical border-amber-400 text-amber-400">{{ __('Featured') }}</span>
                            @endif
                        </div>
                        <h3 class="font-classical text-ink group-hover:text-amber-300 truncate text-[21px] leading-tight font-semibold transition-colors">{{ $contest->title }}</h3>
                        <div class="meta-classical tnum">
                            {{ $contest->starts_at->format('M j') }} &ndash; {{ $contest->ends_at->format('M j, Y') }}
                        </div>
                        <div class="meta-classical flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <span class="whitespace-nowrap"><span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $contest->crosswords_count }}</span> {{ __('puzzles') }}</span>
                            <span aria-hidden="true">&middot;</span>
                            <span class="whitespace-nowrap"><span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $contest->entries_count }}</span> {{ __('participants') }}</span>
                        </div>
                        <div class="meta-classical">{{ __('Starts :time', ['time' => $contest->starts_at->diffForHumans()]) }}</div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Past Contests --}}
    @if($this->pastContests->isNotEmpty())
        <div class="space-y-5">
            <h2 class="font-classical text-ink border-hairline border-b pb-3.5 text-[26px] leading-tight font-medium">{{ __('Past Contests') }}</h2>
            <div class="grid gap-[22px] [grid-template-columns:repeat(auto-fill,minmax(268px,1fr))]">
                @foreach($this->pastContests as $contest)
                    <a
                        href="{{ route('contests.show', $contest) }}"
                        wire:navigate
                        wire:key="past-{{ $contest->id }}"
                        class="border-border hover:border-border-strong group flex flex-col gap-3 rounded-sm border p-[18px] transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                    >
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="chip-classical border-ink-faint text-ink-faint">{{ __('Ended') }}</span>
                        </div>
                        <h3 class="font-classical text-ink group-hover:text-amber-300 truncate text-[21px] leading-tight font-semibold transition-colors">{{ $contest->title }}</h3>
                        <div class="meta-classical tnum">
                            {{ $contest->starts_at->format('M j') }} &ndash; {{ $contest->ends_at->format('M j, Y') }}
                        </div>
                        <div class="meta-classical flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <span class="whitespace-nowrap"><span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $contest->crosswords_count }}</span> {{ __('puzzles') }}</span>
                            <span aria-hidden="true">&middot;</span>
                            <span class="whitespace-nowrap"><span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $contest->entries_count }}</span> {{ __('participants') }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
