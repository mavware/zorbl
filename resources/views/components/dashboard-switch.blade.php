@props(['active'])

@php
    $tabs = [
        'build' => ['label' => __('Build'), 'href' => route('crosswords.index'), 'icon' => 'wrench-screwdriver'],
        'solve' => ['label' => __('Solve'), 'href' => route('crosswords.solving'), 'icon' => 'play'],
    ];
@endphp

<div
    x-data="{
        init() {
            let from = sessionStorage.getItem('dashboard-switch-from')
            sessionStorage.removeItem('dashboard-switch-from')

            if (from && from !== @js($active)) {
                let pill = this.$refs.pill

                // Jump the pill back to the side we navigated from, then let
                // it slide into its resting (class-based) position. Tailwind's
                // translate-x-full uses the CSS `translate` property, so the
                // inline override must too.
                pill.style.transition = 'none'
                pill.style.translate = from === 'solve' ? '100% 0' : '0 0'

                requestAnimationFrame(() => requestAnimationFrame(() => {
                    pill.style.transition = ''
                    pill.style.translate = ''
                }))
            }
        },
    }"
    {{ $attributes->class('relative inline-grid grid-cols-2 rounded-full border border-zinc-200 bg-zinc-100 p-1 dark:border-zinc-800 dark:bg-zinc-900/60') }}
    data-test="dashboard-switch"
>
    {{-- Sliding highlight pill --}}
    <div
        x-ref="pill"
        @class([
            'absolute inset-y-1 left-1 w-[calc(50%-0.25rem)] rounded-full bg-amber-500 shadow-lg shadow-amber-500/20 transition-transform duration-300 ease-out motion-reduce:transition-none',
            'translate-x-full' => $active === 'solve',
        ])
        aria-hidden="true"
    ></div>

    @foreach ($tabs as $key => $tab)
        @if ($active === $key)
            <span class="relative flex items-center justify-center gap-2 rounded-full px-6 py-1.5 text-base font-semibold text-zinc-950" data-test="dashboard-switch-{{ $key }}">
                <flux:icon name="{{ $tab['icon'] }}" /> {{ $tab['label'] }}
            </span>
        @else
            <a
                href="{{ $tab['href'] }}"
                wire:navigate
                x-on:click="sessionStorage.setItem('dashboard-switch-from', @js($active))"
                class="relative flex items-center justify-center gap-2 rounded-full px-6 py-1.5 text-base font-semibold text-zinc-500 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                data-test="dashboard-switch-{{ $key }}"
            >
                <flux:icon name="{{ $tab['icon'] }}" /> {{ $tab['label'] }}
            </a>
        @endif
    @endforeach
</div>
