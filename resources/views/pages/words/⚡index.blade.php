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
    public string $sort = 'alpha';

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

        $query = match ($this->sort) {
            'score' => $query->orderByDesc('score'),
            'length' => $query->orderBy('length')->orderBy('word'),
            default => $query->orderBy('word'),
        };

        return $query->paginate(50);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLength(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
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
        <div class="flex gap-2">
            <flux:select wire:model.live="length" class="w-36">
                <flux:select.option value="">{{ __('All Lengths') }}</flux:select.option>
                @for ($i = 3; $i <= 21; $i++)
                    <flux:select.option value="{{ $i }}">{{ $i }} {{ __('letters') }}</flux:select.option>
                @endfor
            </flux:select>
            <flux:select wire:model.live="sort" class="w-40">
                <flux:select.option value="alpha">{{ __('Alphabetical') }}</flux:select.option>
                <flux:select.option value="score">{{ __('By Score') }}</flux:select.option>
                <flux:select.option value="length">{{ __('By Length') }}</flux:select.option>
            </flux:select>
        </div>
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
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3 font-medium text-zinc-600 dark:text-zinc-300">{{ __('Word') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-600 dark:text-zinc-300">{{ __('Length') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-600 dark:text-zinc-300">{{ __('Score') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-600 dark:text-zinc-300">{{ __('Clues') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($this->words as $word)
                        <tr wire:key="word-{{ $word->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('words.show', $word) }}" wire:navigate class="font-mono font-semibold tracking-wide text-zinc-900 hover:text-blue-600 dark:text-zinc-100 dark:hover:text-blue-400">
                                    {{ $word->word }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $word->length }}</td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ number_format($word->score, 1) }}</td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ number_format($word->clue_count) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->words->links() }}
        </div>
    @endif
</div>
