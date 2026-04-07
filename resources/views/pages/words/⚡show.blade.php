<?php

use App\Models\ClueEntry;
use App\Models\Word;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Word Details')] class extends Component {
    use WithPagination;

    #[Locked]
    public int $wordId;

    public string $wordText = '';

    public function mount(Word $word): void
    {
        $this->wordId = $word->id;
        $this->wordText = $word->word;
    }

    #[Computed]
    public function word(): Word
    {
        return Word::withCount('clueEntries as clue_count')->findOrFail($this->wordId);
    }

    #[Computed]
    public function clues()
    {
        return ClueEntry::where('answer', $this->wordText)
            ->with(['user:id,name', 'crossword:id,title'])
            ->latest('id')
            ->paginate(25);
    }
}
?>

<div class="space-y-6">
    {{-- Back Link --}}
    <div>
        <flux:button variant="ghost" icon="arrow-left" :href="route('words.index')" wire:navigate>
            {{ __('Word Catalog') }}
        </flux:button>
    </div>

    {{-- Word Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" class="font-mono tracking-wider">{{ $this->word->word }}</flux:heading>
        <div class="flex gap-2">
            <flux:badge size="lg">{{ $this->word->length }} {{ __('letters') }}</flux:badge>
            <flux:badge size="lg" variant="outline">{{ __('Score') }}: {{ number_format($this->word->score, 1) }}</flux:badge>
            <flux:badge size="lg" variant="outline" color="lime">{{ number_format($this->word->clue_count) }} {{ trans_choice('clue|clues', $this->word->clue_count) }}</flux:badge>
        </div>
    </div>

    {{-- Clues Table --}}
    @if($this->clues->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-600">
            <flux:icon name="book-open" class="mb-4 size-12 text-zinc-400" />
            <flux:heading size="lg" class="mb-2">{{ __('No clues found') }}</flux:heading>
            <flux:text>{{ __('No clues have been recorded for this word yet.') }}</flux:text>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3 font-medium text-zinc-600 dark:text-zinc-300">{{ __('Clue') }}</th>
                        <th class="hidden px-4 py-3 font-medium text-zinc-600 dark:text-zinc-300 sm:table-cell">{{ __('Source') }}</th>
                        <th class="hidden px-4 py-3 font-medium text-zinc-600 dark:text-zinc-300 md:table-cell">{{ __('Author') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($this->clues as $entry)
                        <tr wire:key="clue-{{ $entry->id }}">
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $entry->clue }}</td>
                            <td class="hidden px-4 py-3 sm:table-cell">
                                @if($entry->crossword)
                                    <flux:badge size="sm">{{ Str::limit($entry->crossword->title, 20) }}</flux:badge>
                                @else
                                    <flux:badge variant="outline" size="sm" color="lime">{{ __('Standalone') }}</flux:badge>
                                @endif
                            </td>
                            <td class="hidden px-4 py-3 text-zinc-500 dark:text-zinc-400 md:table-cell">
                                {{ $entry->user->name ?? __('Unknown') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->clues->links() }}
        </div>
    @endif
</div>
