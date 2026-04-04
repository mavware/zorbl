{{-- Clue quality indicator icon --}}
<template x-if="clueQualityIcon(clue, '{{ $dir }}')">
    <flux:tooltip>
        <x-slot:content>
            <span x-text="clueQualityTooltip(clue, '{{ $dir }}')"></span>
        </x-slot:content>
        <span class="inline-flex cursor-help">
            {{-- Warning icon (amber triangle) --}}
            <template x-if="clueQualityIcon(clue, '{{ $dir }}') === 'warning'">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-amber-500 dark:text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                </svg>
            </template>
            {{-- Error icon (red circle) --}}
            <template x-if="clueQualityIcon(clue, '{{ $dir }}') === 'error'">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-red-500 dark:text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                </svg>
            </template>
        </span>
    </flux:tooltip>
</template>
