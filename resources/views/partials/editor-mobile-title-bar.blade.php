{{-- Mobile title + settings bar. Below `lg` the puzzle title and the settings
     cog sit on a strip fixed to the very bottom of the screen, under the
     suggestions sheet's peek strip (editor-suggestions-sheet), so the two
     stack like one bottom bar with suggestions on top. From `lg` the title
     is edited in the settings sidebar and this bar is not rendered.

     The spacer keeps the page's own bottom edge clear of the bar; the sheet
     reserves its own room above it. Included once by the editor page. --}}
<div class="h-12 shrink-0 lg:hidden" aria-hidden="true"></div>

<div
    class="border-line bg-surface fixed inset-x-0 bottom-0 z-30 flex h-12 items-center gap-2 border-t px-4 lg:hidden"
    data-test="editor-title-bar"
>
    <flux:input
        size="sm"
        placeholder="{{ __('Puzzle title') }}"
        wire:model.blur="title"
        wire:change="saveMetadata"
        class="min-w-0 flex-1"
    />

    <flux:tooltip content="{{ __('Puzzle settings') }}">
        <flux:button variant="ghost" size="sm" icon="cog-6-tooth" wire:click="$set('showSettingsModal', true)" data-test="editor-settings-button"/>
    </flux:tooltip>
</div>
