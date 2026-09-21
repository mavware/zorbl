@props([
    'kicker' => null,
    'title',
    'subtitle' => null,
    'level' => 1,
    'size' => 'lg',
    'bleed' => true,
    'rule' => true,
    'leading' => null,
    'badges' => null,
    'meta' => null,
])

@php
    $tag = 'h'.max(1, min(6, (int) $level));

    $titleClasses = match ($size) {
        'md' => 'font-classical text-ink m-0 text-[28px] leading-none font-normal sm:text-[30px]',
        default => 'font-classical text-ink m-0 text-[34px] leading-none font-normal sm:text-[46px]',
    };

    $wrapperClasses = ($size === 'md' ? 'pb-5' : 'pt-[34px] pb-6').($bleed ? ' -mx-6 px-6 lg:-mx-8 lg:px-8' : ' px-6 lg:px-8');

    $hasSlot = fn ($slot): bool => $slot instanceof \Illuminate\View\ComponentSlot ? $slot->isNotEmpty() : filled($slot);
@endphp

<div {{ $attributes->class(['flex flex-wrap items-end justify-between gap-5', 'border-hairline border-b' => $rule, $wrapperClasses]) }} data-page-header>
    <div class="flex min-w-0 items-center gap-5">
        @if($hasSlot($leading))
            <div class="shrink-0">{{ $leading }}</div>
        @endif

        <div class="min-w-0">
            @if($hasSlot($badges))
                <div class="mb-3 flex flex-wrap items-center gap-1.5">{{ $badges }}</div>
            @endif

            @if($hasSlot($kicker))
                <div class="font-classical mb-2 text-[12px] font-semibold tracking-[0.14em] text-amber-400 uppercase">{{ $kicker }}</div>
            @endif

            <{{ $tag }} class="{{ $titleClasses }}">{{ $title }}</{{ $tag }}>

            @if($hasSlot($subtitle))
                <p class="text-ink-muted mt-2.5 text-sm leading-[1.65]">{{ $subtitle }}</p>
            @endif

            @if($hasSlot($meta))
                <div class="meta-classical mt-3 flex flex-wrap items-center gap-x-4 gap-y-1">{{ $meta }}</div>
            @endif
        </div>
    </div>

    @if($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-3">
            {{ $slot }}
        </div>
    @endif
</div>
