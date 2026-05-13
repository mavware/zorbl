<x-filament-panels::page>
    {{ $this->form }}

    @if (count($results) > 0)
        @php($typeEnum = $this->getResultsTypeEnum())
        <x-filament::section>
            <x-slot name="heading">Results ({{ count($results) }})</x-slot>
            <x-slot name="description">
                Tick the entries you want to keep, then press <strong>Save selected</strong>. Already-saved
                entries (matched by word + type) are skipped automatically.
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="px-2 py-2 text-left w-10">
                                <input
                                    type="checkbox"
                                    class="fi-checkbox-input rounded border-gray-300 dark:border-white/20"
                                    @if (count($selected) === count($results)) checked @endif
                                    x-on:change="
                                        if ($event.target.checked) {
                                            $wire.set('selected', Array.from({length: {{ count($results) }}}, (_, i) => i));
                                        } else {
                                            $wire.set('selected', []);
                                        }
                                    "
                                />
                            </th>
                            <th class="px-2 py-2 text-left">Word</th>
                            <th class="px-2 py-2 text-left">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results as $index => $result)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="px-2 py-2 align-top">
                                    <input
                                        type="checkbox"
                                        class="fi-checkbox-input rounded border-gray-300 dark:border-white/20"
                                        value="{{ $index }}"
                                        wire:model.live="selected"
                                    />
                                </td>
                                <td class="px-2 py-2 align-top font-mono">{{ $result['word'] }}</td>
                                <td class="px-2 py-2 align-top font-mono text-gray-600 dark:text-gray-400">
                                    {{ $typeEnum?->describeNotes(\Illuminate\Support\Arr::except($result, 'word')) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-slot name="footer">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">{{ count($selected) }} selected</span>
                    <x-filament::button
                        wire:click="saveSelected"
                        :disabled="count($selected) === 0"
                        icon="heroicon-o-bookmark"
                    >
                        Save selected
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::section>
    @endif
</x-filament-panels::page>
