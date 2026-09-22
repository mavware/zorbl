{{-- "Puzzle of the Day" banner shown above the solving list. Amber-edged with
     a solve call to action until the viewer has completed it, then a quiet
     border with a Solved chip and a link to the solution. Pass the daily
     crossword and whether the current user has already solved it. --}}
@props(['crossword', 'solved' => false])

<div {{ $attributes->class([
    'rounded-sm border p-[18px] transition-colors',
    'border-border' => $solved,
    'border-amber-400/60' => ! $solved,
]) }} data-daily-puzzle-banner>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div @class([
                'flex size-12 shrink-0 items-center justify-center rounded-sm border',
                'border-border-strong text-ink-faint' => $solved,
                'border-amber-400 text-amber-400' => ! $solved,
            ])>
                <flux:icon :name="$solved ? 'check-circle' : 'star'" class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Puzzle of the Day') }}</h2>
                    <span class="chip-classical border-ink-faint text-ink-faint">{{ today()->format('M j') }}</span>
                    @if($solved)
                        <span class="chip-classical border-amber-400 text-amber-400 gap-1">
                            <flux:icon name="check-circle" class="size-3" />
                            {{ __('Solved') }}
                        </span>
                    @endif
                </div>
                <div class="meta-classical mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                    <span class="font-classical text-ink text-[17px] font-semibold normal-case tracking-normal">{{ $crossword->displayTitle() }}</span>
                    <span aria-hidden="true">&middot;</span>
                    <span class="flex items-center gap-1">
                        {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }} <x-supporter-badge :user="$crossword->user" />
                    </span>
                    <span aria-hidden="true">&middot;</span>
                    <span class="tnum whitespace-nowrap">{{ $crossword->width }}&times;{{ $crossword->height }}</span>
                </div>
            </div>
        </div>
        <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end">
            @if($solved)
                <a href="{{ route('crosswords.solver', $crossword) }}" wire:navigate.hover class="btn-classical btn-classical-muted">
                    <flux:icon name="eye" class="size-4" />
                    {{ __('View Solution') }}
                </a>
            @else
                <a href="{{ route('crosswords.solver', $crossword) }}" wire:navigate.hover class="btn-classical btn-amber-outline">
                    <flux:icon name="play" class="size-4" />
                    {{ __('Solve Today\'s Puzzle') }}
                </a>
            @endif
            <a href="{{ route('puzzles.daily-history') }}" wire:navigate class="meta-classical hover:text-amber-300 transition-colors">
                {{ __('View past puzzles') }} &rarr;
            </a>
        </div>
    </div>
</div>
