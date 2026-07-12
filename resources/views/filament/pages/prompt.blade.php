<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button
                type="submit"
                icon="heroicon-o-paper-airplane"
                wire:loading.attr="disabled"
                wire:target="submit"
            >
                <span wire:loading.remove wire:target="submit">Submit</span>
                <span wire:loading wire:target="submit">Generating...</span>
            </x-filament::button>
        </div>
    </form>

    @if ($result !== null)
        <x-filament::section>
            <x-slot name="heading">Response</x-slot>
            <x-slot name="description">{{ $result['message'] }}</x-slot>

            @if (! $result['success'])
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $result['message'] }}</p>
            @else
                @if ($result['assumptions'] !== '')
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        <strong>Assumptions:</strong> {{ $result['assumptions'] }}
                    </p>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                <th class="px-2 py-2 text-left">Entry</th>
                                <th class="px-2 py-2 text-left w-16">Length</th>
                                <th class="px-2 py-2 text-left">Explanation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result['entries'] as $entry)
                                <tr class="border-b border-gray-100 dark:border-white/5">
                                    <td class="px-2 py-2 align-top font-mono">{{ $entry['entry'] }}</td>
                                    <td class="px-2 py-2 align-top font-mono">{{ $entry['length'] }}</td>
                                    <td class="px-2 py-2 align-top text-gray-600 dark:text-gray-400">
                                        {{ $entry['explanation'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        @if ($result['success'])
            <x-filament::section>
                <x-slot name="heading">Build a puzzle</x-slot>
                <x-slot name="description">
                    Choose the words you want to use, then build a crossword. We'll find a 15×15 template
                    that fits them all and fill in the rest of the grid automatically.
                </x-slot>

                <form wire:submit="buildPuzzle">
                    {{ $this->wordsForm }}

                    <div class="mt-4">
                        <x-filament::button
                            type="submit"
                            icon="heroicon-o-squares-2x2"
                            wire:loading.attr="disabled"
                            wire:target="buildPuzzle"
                        >
                            <span wire:loading.remove wire:target="buildPuzzle">Build puzzle</span>
                            <span wire:loading wire:target="buildPuzzle">Building...</span>
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
