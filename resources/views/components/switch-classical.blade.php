@props(['checked' => false])

<button
    type="button"
    role="switch"
    aria-checked="{{ $checked ? 'true' : 'false' }}"
    {{ $attributes->class([
        'relative inline-flex h-6 w-10 shrink-0 cursor-pointer items-center rounded-full border transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400',
        'border-amber-400 bg-amber-400/15' => $checked,
        'border-border-strong bg-transparent hover:border-border-hover' => ! $checked,
    ]) }}
>
    <span @class([
        'absolute top-1/2 size-4 -translate-y-1/2 rounded-full border transition-transform',
        'left-[3px] translate-x-4 border-amber-400 bg-amber-400/40' => $checked,
        'left-[3px] translate-x-0 border-ink-faint' => ! $checked,
    ])></span>
</button>
