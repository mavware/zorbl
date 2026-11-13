{{-- Download button: opens the download modal (pdf-export-modal), where the
     format is chosen: a print-ready PDF with its options, or .ipuz, .puz or
     .jpz. It sits directly before the Publish button wherever that renders:
     in the toolbar on desktop, and beside the Across / Down tabs on mobile
     (editor-mobile-clues), where it shrinks to its icon to leave the tabs
     room.

     $testId   data-test for the button, distinct per surface.
     $iconOnly whether to show only the icon (the label stays as its accessible name). --}}
<flux:tooltip content="{{ __('Download this puzzle as a file') }}">
    @if ($iconOnly)
        <flux:button variant="ghost" size="sm" class="btn-muted-outline" icon="arrow-down-tray" wire:click="openDownloadModal" data-test="{{ $testId }}" aria-label="{{ __('Download') }}" />
    @else
        <flux:button variant="ghost" size="sm" class="btn-muted-outline" icon="arrow-down-tray" wire:click="openDownloadModal" data-test="{{ $testId }}">{{ __('Download') }}</flux:button>
    @endif
</flux:tooltip>
