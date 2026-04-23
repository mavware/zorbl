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
        <flux:heading size="xl">{{ __('Active Contests') }}</flux:heading>

        @if($this->activeContests->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-400 py-12 dark:border-zinc-600">
                <flux:icon name="trophy" class="mb-4 size-12 text-zinc-500" />
                <flux:heading size="lg" class="mb-2">{{ __('No active contests') }}</flux:heading>
                <flux:text>{{ __('Check back soon for new contests.') }}</flux:text>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($this->activeContests as $contest)
                    <a
                        href="{{ route('contests.show', $contest) }}"
                        wire:navigate
                        wire:key="active-{{ $contest->id }}"
                        class="group rounded-xl border border-zinc-300 p-5 transition-colors hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500"
                    >
                        <div class="mb-3 flex items-center gap-2">
                            <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @if($contest->is_featured)
                                <flux:badge color="amber" size="sm">{{ __('Featured') }}</flux:badge>
                            @endif
                        </div>
                        <flux:heading size="lg" class="truncate">{{ $contest->title }}</flux:heading>
                        <flux:text size="sm" class="mt-1">
                            {{ $contest->starts_at->format('M j') }} &ndash; {{ $contest->ends_at->format('M j, Y') }}
                        </flux:text>
                        <div class="mt-3 flex items-center gap-4">
                            <flux:text size="sm">
                                <span class="font-medium">{{ $contest->crosswords_count }}</span> {{ __('puzzles') }}
                            </flux:text>
                            <flux:text size="sm">
                                <span class="font-medium">{{ $contest->entries_count }}</span> {{ __('participants') }}
                            </flux:text>
                        </div>
                        @if($contest->ends_at->isFuture())
                            <flux:text size="xs" class="mt-2 text-amber-600 dark:text-amber-400">
                                {{ __('Ends :time', ['time' => $contest->ends_at->diffForHumans()]) }}
                            </flux:text>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Upcoming Contests --}}
    @if($this->upcomingContests->isNotEmpty())
        <div class="space-y-4">
            <flux:heading size="xl">{{ __('Upcoming Contests') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($this->upcomingContests as $contest)
                    <a
                        href="{{ route('contests.show', $contest) }}"
                        wire:navigate
                        wire:key="upcoming-{{ $contest->id }}"
                        class="group rounded-xl border border-zinc-300 p-5 transition-colors hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500"
                    >
                        <div class="mb-3 flex items-center gap-2">
                            <flux:badge color="blue" size="sm">{{ __('Upcoming') }}</flux:badge>
                            @if($contest->is_featured)
                                <flux:badge color="amber" size="sm">{{ __('Featured') }}</flux:badge>
                            @endif
                        </div>
                        <flux:heading size="lg" class="truncate">{{ $contest->title }}</flux:heading>
                        <flux:text size="sm" class="mt-1">
                            {{ $contest->starts_at->format('M j') }} &ndash; {{ $contest->ends_at->format('M j, Y') }}
                        </flux:text>
                        <div class="mt-3 flex items-center gap-4">
                            <flux:text size="sm">
                                <span class="font-medium">{{ $contest->crosswords_count }}</span> {{ __('puzzles') }}
                            </flux:text>
                            <flux:text size="sm">
                                <span class="font-medium">{{ $contest->entries_count }}</span> {{ __('participants') }}
                            </flux:text>
                        </div>
                        <flux:text size="xs" class="mt-2 text-blue-600 dark:text-blue-400">
                            {{ __('Starts :time', ['time' => $contest->starts_at->diffForHumans()]) }}
                        </flux:text>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Past Contests --}}
    @if($this->pastContests->isNotEmpty())
        <div class="space-y-4">
            <flux:heading size="xl">{{ __('Past Contests') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($this->pastContests as $contest)
                    <a
                        href="{{ route('contests.show', $contest) }}"
                        wire:navigate
                        wire:key="past-{{ $contest->id }}"
                        class="group rounded-xl border border-zinc-300 p-5 transition-colors hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500"
                    >
                        <div class="mb-3">
                            <flux:badge color="zinc" size="sm">{{ __('Ended') }}</flux:badge>
                        </div>
                        <flux:heading size="lg" class="truncate">{{ $contest->title }}</flux:heading>
                        <flux:text size="sm" class="mt-1">
                            {{ $contest->starts_at->format('M j') }} &ndash; {{ $contest->ends_at->format('M j, Y') }}
                        </flux:text>
                        <div class="mt-3 flex items-center gap-4">
                            <flux:text size="sm">
                                <span class="font-medium">{{ $contest->crosswords_count }}</span> {{ __('puzzles') }}
                            </flux:text>
                            <flux:text size="sm">
                                <span class="font-medium">{{ $contest->entries_count }}</span> {{ __('participants') }}
                            </flux:text>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
