@props([
    /** @var array<int, \App\Support\NavigationItem> */
    'tabs',
])

{{-- Sub-page tabs, placed in a page header's footer slot so the strip sits
     flush under the header rule. The current tab carries an amber underline
     that overlaps the strip's bottom hairline. --}}
<nav {{ $attributes->class(['border-hairline border-b px-6 lg:px-8']) }} aria-label="{{ __('Section pages') }}" data-test="page-tabs">
    <ul class="-mb-px flex gap-x-6 overflow-x-auto">
        @foreach ($tabs as $tab)
            <li class="shrink-0">
                <a
                    href="{{ $tab->href }}"
                    {{ $tab->attributes() }}
                    @if ($tab->current) aria-current="page" data-current @endif
                    @class([
                        'label-classical font-classical flex items-center gap-2 border-b-2 py-3.5 text-[13px] font-semibold transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 [--label-tracking:0.12em]',
                        'border-amber-400 text-ink' => $tab->current,
                        'text-ink-muted hover:text-ink border-transparent' => ! $tab->current,
                    ])
                    data-test="page-tab"
                >
                    <flux:icon :name="$tab->icon" class="size-4" />
                    {{ $tab->label }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
