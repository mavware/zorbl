{{-- Download menu: the puzzle as .ipuz, .puz, .jpz or a print-ready PDF.
     It sits directly before the Publish button wherever that renders: in
     the toolbar on desktop, and beside the Across / Down tabs on mobile
     (editor-mobile-clues), where it shrinks to its icon to leave the tabs
     room.

     $testId   data-test for the button, distinct per surface.
     $iconOnly whether to show only the icon (the label stays as its accessible name). --}}
<flux:dropdown position="bottom" align="end">
    <flux:tooltip content="{{ __('Download this puzzle as a file') }}">
        @if ($iconOnly)
            <flux:button variant="ghost" size="sm" class="btn-muted-outline" icon="arrow-down-tray" data-test="{{ $testId }}" aria-label="{{ __('Download') }}" />
        @else
            <flux:button variant="ghost" size="sm" class="btn-muted-outline" icon="arrow-down-tray" data-test="{{ $testId }}">{{ __('Download') }}</flux:button>
        @endif
    </flux:tooltip>
    <flux:menu>
        <flux:menu.item wire:click="attemptExport('ipuz')">{{ __('.ipuz') }}</flux:menu.item>
        <flux:menu.item wire:click="attemptExport('puz')">{{ __('.puz (Across Lite)') }}</flux:menu.item>
        <flux:menu.item wire:click="attemptExport('jpz')">{{ __('.jpz (Crossword Compiler)') }}</flux:menu.item>
        <flux:menu.item wire:click="attemptExport('pdf')">{{ __('.pdf (Print-Ready)') }}</flux:menu.item>
    </flux:menu>
</flux:dropdown>
