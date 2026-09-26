<x-filament-panels::page>
    <div class="grid gap-6 md:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Export word list</x-slot>
            <x-slot name="description">
                Rebuilds the word list JSON served at <code>/api/v1/words/manifest</code>. Requests rebuild it
                automatically when words change; run this to warm the cache right away.
            </x-slot>

            {{ $this->exportWordsAction }}
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Backfill clues</x-slot>
            <x-slot name="description">
                Writes clues, from AI or Wiktionary definitions, for random catalog words with no clues, up to
                {{ \App\Filament\Pages\Commands::MAX_BACKFILL_WORDS }} words per run.
            </x-slot>

            {{ $this->backfillCluesAction }}
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Import words from Wiktionary</x-slot>
            <x-slot name="description">
                Adds dictionary words and phrases that aren't in the word list yet, up to
                {{ \App\Filament\Pages\Commands::MAX_IMPORT_PAGES }} pages of 500 entries per run.
            </x-slot>

            {{ $this->importWordsAction }}
        </x-filament::section>
    </div>

    @if ($output !== null)
        <x-filament::section>
            <x-slot name="heading">Output: {{ $lastCommand }}</x-slot>

            <pre class="overflow-x-auto whitespace-pre-wrap font-mono text-sm" data-test="command-output">{{ $output === '' ? '(no output)' : $output }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
