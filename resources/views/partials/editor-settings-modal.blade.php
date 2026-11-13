{{-- Mobile settings modal. Below `lg` the toolbar cog opens this full-height
     flyout on the right, so the grid stays visible while metadata is edited.
     It holds the same shared form as the desktop sidebar
     (editor-settings-pane), with Save/Cancel pinned to the bottom. Hidden from `lg` up, where the sidebar takes over. --}}
<flux:modal
    wire:model="showSettingsModal"
    variant="flyout"
    position="right"
    class="w-full max-w-xl md:min-w-[28rem]! flex flex-col pb-0! lg:hidden"
    data-test="settings-flyout"
>
    <div class="mb-4 shrink-0 pe-8">
        <flux:heading size="lg">{{ __('Puzzle Settings') }}</flux:heading>
    </div>

    @include('partials.editor-settings-form', ['settingsSurface' => 'modal'])

    <div class="sticky bottom-0 -mx-8 flex shrink-0 justify-end gap-2 border-t border-zinc-200 bg-white px-8 py-4 dark:border-zinc-700 dark:bg-zinc-800">
        <flux:button wire:click="$set('showSettingsModal', false)">{{ __('Cancel') }}</flux:button>
        <flux:button variant="primary" wire:click="saveMetadata">{{ __('Save') }}</flux:button>
    </div>
</flux:modal>
