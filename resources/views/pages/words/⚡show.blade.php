<?php

use App\Models\ClueEntry;
use App\Models\Word;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Word Details')] class extends Component {
    use WithPagination;

    #[Locked]
    public int $wordId;

    public string $wordText = '';

    #[Url]
    public string $sortField = '';

    #[Url]
    public string $sortDirection = 'asc';

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
        $query = ClueEntry::where('answer', $this->wordText)
            ->with(['user:id,name', 'crossword:id,title']);

        $allowed = ['clue'];
        if ($this->sortField !== '' && in_array($this->sortField, $allowed)) {
            $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';
            $query->orderBy($this->sortField, $direction);
        } else {
            $query->latest('id');
        }

        return $query->paginate(25);
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
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
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-400 py-16 dark:border-zinc-600">
            <flux:icon name="book-open" class="mb-4 size-12 text-zinc-500" />
            <flux:heading size="lg" class="mb-2">{{ __('No clues found') }}</flux:heading>
            <flux:text>{{ __('No clues have been recorded for this word yet.') }}</flux:text>
        </div>
    @else
        <flux:table :paginate="$this->clues">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'clue'" :direction="$sortDirection" wire:click="sortBy('clue')">{{ __('Clue') }}</flux:table.column>
                <flux:table.column class="hidden sm:table-cell">{{ __('Source') }}</flux:table.column>
                <flux:table.column class="hidden md:table-cell">{{ __('Author') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->clues as $entry)
                    <flux:table.row :key="$entry->id">
                        <flux:table.cell>{{ $entry->clue }}</flux:table.cell>
                        <flux:table.cell class="hidden sm:table-cell">
                            @if($entry->crossword)
                                <flux:badge size="sm">{{ Str::limit($entry->crossword->title, 20) }}</flux:badge>
                            @else
                                <flux:badge variant="outline" size="sm" color="lime">{{ __('Standalone') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="hidden md:table-cell">{{ $entry->user->name ?? __('Unknown') }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
