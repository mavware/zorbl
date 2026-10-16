{{-- Mobile settings modal. Below `lg` the toolbar cog opens this full-height
     flyout on the right, so the grid stays visible while metadata is edited.
     It holds the same shared form as the desktop sidebar
     (editor-settings-pane), with Export in the header and Save/Cancel pinned
     to the bottom. Hidden from `lg` up, where the sidebar takes over. --}}
<flux:modal
    wire:model="showSettingsModal"
    variant="flyout"
    position="right"
    class="w-full max-w-xl md:min-w-[28rem]! flex flex-col pb-0! lg:hidden"
    data-test="settings-flyout"
>
    <div class="mb-4 flex shrink-0 items-center justify-between gap-4 pe-8">
        <flux:heading size="lg">{{ __('Puzzle Settings') }}</flux:heading>

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
    </div>

    @include('partials.editor-settings-form', ['settingsSurface' => 'modal'])

    <div class="sticky bottom-0 -mx-8 flex shrink-0 justify-end gap-2 border-t border-zinc-200 bg-white px-8 py-4 dark:border-zinc-700 dark:bg-zinc-800">
        <flux:button wire:click="$set('showSettingsModal', false)">{{ __('Cancel') }}</flux:button>
        <flux:button variant="primary" wire:click="saveMetadata">{{ __('Save') }}</flux:button>
    </div>
</flux:modal>
