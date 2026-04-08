<?php

use App\Models\Word;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Word Catalog')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $length = '';

    #[Url]
    public string $sortField = 'word';

    #[Url]
    public string $sortDirection = 'asc';

    #[Computed]
    public function words()
    {
        $query = Word::withCount('clueEntries as clue_count');

        if ($this->search !== '') {
            $query->where('word', 'like', mb_strtoupper($this->search).'%');
        }

        if ($this->length !== '') {
            $query->where('length', (int) $this->length);
        }

        $allowed = ['word', 'length', 'score', 'clue_count'];
        $field = in_array($this->sortField, $allowed) ? $this->sortField : 'word';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        // clue_count is an aggregate alias — needs special handling
        if ($field === 'clue_count') {
            $query->orderBy('clue_entries_count', $direction);
        } else {
            $query->orderBy($field, $direction);
        }

        return $query->paginate(50);
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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLength(): void
    {
        $this->resetPage();
    }
}
?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Word Catalog') }}</flux:heading>

    {{-- Search and Filters --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input icon="magnifying-glass" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search words (prefix match)...') }}" />
        </div>
        <flux:input wire:model.live.debounce.300ms="length" type="number" min="2" max="30" placeholder="{{ __('Length') }}" class="w-28" />
    </div>

    {{-- Word Table --}}
    @if($this->words->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-600">
            <flux:icon name="language" class="mb-4 size-12 text-zinc-400" />
            <flux:heading size="lg" class="mb-2">{{ __('No words found') }}</flux:heading>
            <flux:text>
                @if($search)
                    {{ __('Try a different search term.') }}
                @else
                    {{ __('The word catalog is empty. Run the word list generator to populate it.') }}
                @endif
            </flux:text>
        </div>
    @else
        <flux:table :paginate="$this->words">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'word'" :direction="$sortDirection" wire:click="sortBy('word')">{{ __('Word') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'length'" :direction="$sortDirection" wire:click="sortBy('length')">{{ __('Length') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'score'" :direction="$sortDirection" wire:click="sortBy('score')">{{ __('Score') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'clue_count'" :direction="$sortDirection" wire:click="sortBy('clue_count')">{{ __('Clues') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->words as $word)
                    <flux:table.row :key="$word->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('words.show', $word) }}" wire:navigate class="font-mono font-semibold tracking-wide hover:text-blue-600 dark:hover:text-blue-400">
                                {{ $word->word }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $word->length }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($word->score, 1) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($word->clue_count) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
