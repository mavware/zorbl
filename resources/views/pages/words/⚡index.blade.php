<?php

use App\Models\Word;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $length = '';

    #[Url]
    public string $sortField = 'word';

    #[Url]
    public string $sortDirection = 'asc';

    /**
     * Total catalog size for the intro copy. Cached for a day since counting
     * the full (large) words table on every render would be wasteful and the
     * number barely moves.
     */
    #[Computed]
    public function totalWords(): int
    {
        return Cache::remember('words:total-count', now()->addDay(), fn (): int => Word::count());
    }

    #[Computed]
    public function words()
    {
        // Count only approved clues so the catalog's "Clues" column matches the
        // approved-only list shown on each word's detail page.
        $query = Word::withCount([
            'clueEntries as clue_count' => fn ($q) => $q->approved(),
        ]);

        if ($this->searchPattern() !== '') {
            $query->where('word', 'like', $this->searchPattern());
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

    /**
     * Build a SQL LIKE pattern from the search box, supporting crossword
     * wildcards: `?` matches any single letter, `*` matches any run of
     * letters. Plain terms keep prefix-match behavior. Input is limited to
     * letters and wildcards, so no SQL wildcard escaping is needed.
     */
    private function searchPattern(): string
    {
        $term = preg_replace('/[^A-Z?*]/', '', mb_strtoupper($this->search));

        if ($term === '') {
            return '';
        }

        $pattern = str_replace(['?', '*'], ['_', '%'], $term);

        // No wildcards → match as a prefix, as before.
        if (! str_contains($term, '?') && ! str_contains($term, '*')) {
            return $pattern.'%';
        }

        return $pattern;
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

    /**
     * Guests get the public chrome; logged-in users keep the app sidebar layout.
     */
    public function render(): View
    {
        return $this->view()
            ->layout(Auth::check() ? 'layouts.app' : 'layouts.public')
            ->title(__('Word Catalog'));
    }
}
?>

<div class="space-y-6">
    <x-seo-meta
        title="Word Catalog"
        :canonical="route('words.index')"
        :description="__('Search a catalog of crossword answers by length and pattern. Use ? for any single letter and * for any run — perfect for filling that last stubborn slot.')"
    />

    @push('head_meta')
        @php
            $wordsJsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => __('Word Catalog'),
                'url' => route('words.index'),
                'isPartOf' => ['@id' => url('/').'#website'],
                'description' => __('A searchable catalog of crossword answers with clue counts and fill scores.'),
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'itemListElement' => collect($this->words->items())->map(fn ($w, $i) => [
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'name' => $w->word,
                        'url' => route('words.show', $w),
                    ])->values()->all(),
                ],
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($wordsJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <flux:heading size="xl">{{ __('Word Catalog') }}</flux:heading>
    <flux:text class="-mt-4 max-w-2xl">
        {{ __('Browse :count crossword answers. Search by pattern — use ? for any single letter and * for any run of letters.', ['count' => number_format($this->totalWords)]) }}
    </flux:text>

    {{-- Search and Filters --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input icon="magnifying-glass" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search words — ? = any letter (C???T), * = any run (e.g. C?T, S*E)') }}" />
        </div>
        <div class="w-28">
            <flux:input wire:model.live.debounce.300ms="length" type="number" min="2" max="30" placeholder="{{ __('Length') }}" />
        </div>
    </div>

    {{-- Word Table --}}
    @if($this->words->isEmpty())
        <div class="border-line-strong flex flex-col items-center justify-center rounded-xl border border-dashed py-16">
            <flux:icon name="language" class="mb-4 size-12 text-zinc-500" />
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
                    <flux:table.row :key="$word->id" x-on:click="window.location.href = '{{ route('words.show', $word) }}'" class="cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-700">
                        <flux:table.cell variant="strong">
                                {{ $word->word }}
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
