{{-- Mobile title + settings row. Below `lg` the puzzle title and the settings
     cog form an ordinary row in the page flow, directly above the toolbar
     (Edit / Solve, tools), so they scroll with the page. From `lg` the title
     is edited in the settings sidebar and this row is not rendered.
     Included once by the editor page. --}}
<div class="mb-3 flex items-center gap-2 lg:hidden" data-test="editor-title-row">
    <div class="relative min-w-0 flex-1">
        <flux:input
            size="sm"
            placeholder="{{ __('Puzzle title') }}"
            wire:model.live.debounce.750ms="title"
        />
        <x-saved-check property="title" />
    </div>

    <flux:tooltip content="{{ __('Puzzle settings') }}">
        <flux:button variant="ghost" size="sm" icon="cog-6-tooth" wire:click="$set('showSettingsModal', true)" data-test="editor-settings-button"/>
    </flux:tooltip>
</div>
