{{-- PDF Export Settings Modal, shared by every page that uses the ExportsCrossword trait.
     Expects $hasSavedPdfImage: whether the puzzle being exported already has a header image saved,
     and $canIncludeAnswerKey: whether this page may offer the answer key (builder pages only). --}}
<flux:modal wire:model="showPdfExportModal" class="w-full md:max-w-5xl">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('PDF Export Settings') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Configure the page orientation and layout, add an image, and optional narrative text for your PDF export.') }}</flux:text>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-6">
                <flux:radio.group wire:model="pdfOrientation" label="{{ __('Orientation') }}">
                    <flux:radio value="portrait" label="{{ __('Portrait') }}" description="{{ __('Standard vertical layout (8.5 × 11 in)') }}" />
                    <flux:radio value="landscape" label="{{ __('Landscape') }}" description="{{ __('Horizontal layout (11 × 8.5 in) — better for wide puzzles') }}" />
                </flux:radio.group>

                {{-- Selection is tracked by Alpine straight off $wire, so a click highlights
                     at once and the preview's own watcher picks the change up. --}}
                <flux:field>
                    <flux:label>{{ __('Layout') }}</flux:label>
                    <div class="mt-2 grid grid-cols-5 gap-2" data-testid="pdf-layout-picker">
                        @foreach (\App\Enums\PdfLayout::cases() as $layout)
                            <button
                                type="button"
                                x-data
                                x-on:click="$wire.pdfLayout = '{{ $layout->value }}'"
                                x-bind:aria-pressed="($wire.pdfLayout === '{{ $layout->value }}').toString()"
                                x-bind:class="$wire.pdfLayout === '{{ $layout->value }}'
                                    ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500 dark:border-blue-400 dark:bg-blue-950/40 dark:ring-blue-400'
                                    : 'border-zinc-300 hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-500 dark:hover:bg-zinc-800'"
                                class="flex flex-col items-center gap-1.5 rounded-lg border p-2 transition-colors"
                                title="{{ __($layout->description()) }}"
                            >
                                <div class="w-full overflow-hidden rounded">
                                    {{ $layout->icon() }}
                                </div>
                                <span class="line-clamp-2 text-center text-[10px] leading-tight text-zinc-700 dark:text-zinc-400">{{ __($layout->label()) }}</span>
                            </button>
                        @endforeach
                    </div>
                </flux:field>

                <div>
                    <flux:input type="file" wire:model="pdfImage" label="{{ __('Header Image') }}" accept="image/png,image/jpeg,image/gif,image/webp" />
                    <flux:text class="mt-1">{{ __('Optional image displayed above the puzzle grid (max 2 MB).') }}</flux:text>
                    @if ($hasSavedPdfImage && !$pdfRemoveImage)
                        <div class="mt-2 flex items-center gap-2">
                            <flux:text class="text-sm text-green-600 dark:text-green-400">{{ __('Current image saved.') }}</flux:text>
                            <flux:button size="xs" variant="danger" wire:click="$set('pdfRemoveImage', true)">{{ __('Remove') }}</flux:button>
                        </div>
                    @elseif ($pdfRemoveImage)
                        <div class="mt-2">
                            <flux:text class="text-sm text-amber-600 dark:text-amber-400">{{ __('Image will be removed on export.') }}</flux:text>
                        </div>
                    @endif
                </div>

                @if ($canIncludeAnswerKey)
                    <flux:checkbox wire:model="pdfIncludeAnswerKey" label="{{ __('Include answer key') }}" description="{{ __('Adds the solved grid on its own page at the end.') }}" />
                @endif

                <flux:textarea wire:model="pdfNarrative" label="{{ __('Narrative Text') }}" placeholder="{{ __('Add introductory text, theme explanation, or instructions that will appear above the puzzle grid...') }}" rows="4" />
            </div>

            {{-- wire:ignore: the iframe's src and the status overlays belong to Alpine;
                 a re-render (an image upload, Remove) must not morph them away. --}}
            <div wire:ignore x-data="pdfPreview" data-testid="pdf-preview" class="flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <flux:label>{{ __('Preview') }}</flux:label>
                    <a
                        x-show="url"
                        x-cloak
                        x-bind:href="url"
                        target="_blank"
                        rel="noopener"
                        class="text-fg-muted text-xs underline hover:text-zinc-800 dark:hover:text-zinc-200"
                    >{{ __('Open in new tab') }}</a>
                </div>

                <div class="relative h-96 overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 md:h-full md:min-h-[28rem] dark:border-zinc-700 dark:bg-zinc-800">
                    <iframe
                        x-bind:src="frameSource"
                        x-show="url"
                        x-cloak
                        title="{{ __('PDF preview') }}"
                        class="absolute inset-0 size-full"
                    ></iframe>

                    <div
                        x-show="loading"
                        x-cloak
                        class="absolute inset-0 flex items-center justify-center bg-zinc-100/70 dark:bg-zinc-800/70"
                        role="status"
                    >
                        <flux:icon.loading class="size-6 text-zinc-500" />
                        <span class="sr-only">{{ __('Updating preview…') }}</span>
                    </div>

                    <div x-show="failed && !loading" x-cloak class="absolute inset-0 flex items-center justify-center p-6 text-center">
                        <flux:text>{{ __('The preview could not be generated. You can still export the PDF.') }}</flux:text>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button wire:click="cancelPdfExport">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="confirmPdfExport">{{ __('Export PDF') }}</flux:button>
        </div>
    </div>
</flux:modal>
