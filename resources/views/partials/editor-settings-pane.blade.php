{{-- Desktop settings sidebar. Sits at the far right of the editor row beside
     whichever puzzle layout is active, mirroring the suggestions pane: a
     thin strip with a cog, and a fixed-width panel holding the shared
     settings form (editor-settings-form) with Export in its header and Save
     in its footer. Rendered from `lg` up only; below that the toolbar cog
     opens the modal (editor-settings-modal) instead.

     Unlike the suggestions pane the open panel does not push the layout:
     the strip keeps its 40px slot and the panel overlays the right edge of
     the editor from it. With the app navigation taking a quarter of a
     laptop screen, two clue columns, both strips and a 320px form leave the
     grid no room at all when they all push, so the grid stays put and the
     panel covers the suggestions strip and whatever clue column sits beside
     it until it is closed again.

     Open state is local client-side state, like the suggestions pane's
     collapse. Saving keeps the panel open; the toolbar's save status is the
     confirmation. --}}
<aside
    x-data="{ settingsPaneOpen: false }"
    aria-label="{{ __('Puzzle settings') }}"
    data-test="settings-pane"
    class="border-line bg-surface relative hidden w-10 shrink-0 flex-col rounded-lg border lg:flex lg:max-h-[calc(100dvh-8rem)]"
>
    {{-- Collapsed strip --}}
    <div x-show="!settingsPaneOpen" class="flex h-full flex-col items-center gap-2 py-2">
        <flux:tooltip content="{{ __('Show settings') }}" position="left">
            <button
                type="button"
                x-on:click="settingsPaneOpen = true"
                class="text-fg-muted hover:text-fg rounded p-1 transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-700/50"
                aria-label="{{ __('Show settings') }}"
                aria-expanded="false"
                data-test="settings-pane-open"
            >
                <flux:icon.cog-6-tooth variant="mini" class="size-4" />
            </button>
        </flux:tooltip>
        <span class="text-fg-subtle text-[11px] font-medium tracking-wide [writing-mode:vertical-rl]">{{ __('Settings') }}</span>
    </div>

    {{-- Open panel, anchored to the strip's right edge and laid over the editor --}}
    <div
        x-show="settingsPaneOpen"
        x-cloak
        x-transition.opacity.duration.150ms
        class="border-line bg-surface absolute inset-y-0 right-0 z-20 flex w-80 flex-col overflow-hidden rounded-lg border shadow-xl"
        data-test="settings-pane-panel"
    >
        <div class="border-line flex shrink-0 items-center justify-between gap-2 border-b px-3 py-2">
            <h2 class="text-fg truncate text-sm font-medium">{{ __('Puzzle Settings') }}</h2>

            <div class="flex shrink-0 items-center gap-1">
                {{-- Export --}}
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" size="sm" icon="arrow-down-tray">
                        {{ __('Export') }}
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item wire:click="attemptExport('ipuz')">{{ __('.ipuz') }}</flux:menu.item>
                        <flux:menu.item wire:click="attemptExport('puz')">{{ __('.puz (Across Lite)') }}</flux:menu.item>
                        <flux:menu.item wire:click="attemptExport('jpz')">{{ __('.jpz (Crossword Compiler)') }}</flux:menu.item>
                        <flux:menu.item wire:click="attemptExport('pdf')">{{ __('.pdf (Print-Ready)') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                <flux:tooltip content="{{ __('Hide settings') }}">
                    <button
                        type="button"
                        x-on:click="settingsPaneOpen = false"
                        class="text-fg-subtle hover:text-fg -mr-1 rounded p-1 transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-700/50"
                        aria-label="{{ __('Hide settings') }}"
                        data-test="settings-pane-close"
                    >
                        <flux:icon.chevron-double-right variant="mini" class="size-4" />
                    </button>
                </flux:tooltip>
            </div>
        </div>

        <div class="flex min-h-0 flex-1 flex-col px-3">
            @include('partials.editor-settings-form', ['settingsSurface' => 'pane'])
        </div>

        <div class="border-line flex shrink-0 justify-end border-t px-3 py-2">
            <flux:button variant="primary" size="sm" wire:click="saveMetadata">{{ __('Save') }}</flux:button>
        </div>
    </div>
</aside>
