{{--
    On-screen QWERTY for touch devices. Hidden on devices with a fine pointer
    (mouse / trackpad) where the physical keyboard is the better experience.
    Lives inside the crosswordSolver Alpine scope, so it can call typeCharacter,
    pressBackspace, and toggleDirection directly.
--}}
<div
    class="pointer-coarse:flex hidden fixed inset-x-0 bottom-0 z-40 flex-col gap-1.5 border-t border-zinc-200 bg-zinc-100 px-1.5 pt-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] shadow-2xl dark:border-zinc-800 dark:bg-zinc-900"
    role="group"
    aria-label="{{ __('Virtual keyboard') }}"
>
    @php
        $rows = [
            ['Q','W','E','R','T','Y','U','I','O','P'],
            ['A','S','D','F','G','H','J','K','L'],
            ['Z','X','C','V','B','N','M'],
        ];
    @endphp

    @foreach ($rows as $rowIdx => $row)
        <div class="flex w-full justify-center gap-1">
            @if ($rowIdx === 2)
                <button
                    type="button"
                    x-on:click="toggleDirection()"
                    x-on:touchstart.prevent="toggleDirection()"
                    :aria-label="direction === 'across' ? '{{ __('Switch to down clues') }}' : '{{ __('Switch to across clues') }}'"
                    class="flex h-11 min-w-[2.75rem] flex-1 items-center justify-center rounded-md bg-zinc-200 px-1 text-xs font-semibold text-zinc-700 active:bg-zinc-300 dark:bg-zinc-700 dark:text-zinc-200 dark:active:bg-zinc-600"
                >
                    <span x-text="direction === 'across' ? '↕' : '↔'"></span>
                </button>
            @endif

            @foreach ($row as $key)
                <button
                    type="button"
                    x-on:click="typeCharacter('{{ $key }}')"
                    x-on:touchstart.prevent="typeCharacter('{{ $key }}')"
                    class="flex h-11 min-w-0 flex-1 items-center justify-center rounded-md bg-white text-base font-semibold text-zinc-900 shadow-sm active:bg-zinc-300 dark:bg-zinc-700 dark:text-zinc-100 dark:active:bg-zinc-600"
                    aria-label="{{ $key }}"
                >{{ $key }}</button>
            @endforeach

            @if ($rowIdx === 2)
                <button
                    type="button"
                    x-on:click="pressBackspace()"
                    x-on:touchstart.prevent="pressBackspace()"
                    :aria-label="'{{ __('Backspace') }}'"
                    class="flex h-11 min-w-[2.75rem] flex-1 items-center justify-center rounded-md bg-zinc-200 px-1 text-base font-semibold text-zinc-700 active:bg-zinc-300 dark:bg-zinc-700 dark:text-zinc-200 dark:active:bg-zinc-600"
                >⌫</button>
            @endif
        </div>
    @endforeach
</div>
