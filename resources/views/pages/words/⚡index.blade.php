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

    <x-page-header
        :title="__('Word Catalog')"
        :subtitle="__('Browse :count crossword answers. Search by pattern — use ? for any single letter and * for any run of letters.', ['count' => number_format($this->totalWords)])"
    />

    {{-- Search and Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <label class="relative flex-1">
            <span class="sr-only">{{ __('Search words — ? = any letter (C???T), * = any run (e.g. C?T, S*E)') }}</span>
            <flux:icon name="magnifying-glass" class="text-ink-faint pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
            <input
                type="search"
                placeholder="{{ __('Search words — ? = any letter (C???T), * = any run (e.g. C?T, S*E)') }}"
                wire:model.live.debounce.300ms="search"
                class="field-classical w-full pr-3 pl-9"
            />
        </label>
        <label class="sm:w-28">
            <span class="sr-only">{{ __('Length') }}</span>
            <input
                type="number"
                min="2"
                max="30"
                placeholder="{{ __('Length') }}"
                wire:model.live.debounce.300ms="length"
                class="field-classical tnum w-full px-3.5"
            />
        </label>
    </div>

    {{-- Word Table --}}
    @if($this->words->isEmpty())
        <div class="border-border-strong flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-16 text-center">
            <flux:icon name="language" class="text-ink-faint mb-4 size-10" />
            <h3 class="font-classical text-ink text-[26px] leading-tight font-medium">{{ __('No words found') }}</h3>
            <p class="text-ink-muted mt-2 text-sm">
                @if($search)
                    {{ __('Try a different search term.') }}
                @else
                    {{ __('The word catalog is empty. Run the word list generator to populate it.') }}
                @endif
            </p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-hairline border-b">
                            <th scope="col" class="px-3 py-3 text-left font-normal ">
                                <button type="button" wire:click="sortBy('word')" class="meta-classical hover:text-ink inline-flex items-center gap-1 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                    {{ __('Word') }}
                                    @if($sortField === 'word')
                                        <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3 text-amber-400" />
                                    @endif
                                </button>
                            </th>
                            <th scope="col" class="px-3 py-3 text-left font-normal ">
                                <button type="button" wire:click="sortBy('length')" class="meta-classical hover:text-ink inline-flex items-center gap-1 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                    {{ __('Length') }}
                                    @if($sortField === 'length')
                                        <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3 text-amber-400" />
                                    @endif
                                </button>
                            </th>
                            <th scope="col" class="px-3 py-3 text-left font-normal ">
                                <button type="button" wire:click="sortBy('score')" class="meta-classical hover:text-ink inline-flex items-center gap-1 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                    {{ __('Score') }}
                                    @if($sortField === 'score')
                                        <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3 text-amber-400" />
                                    @endif
                                </button>
                            </th>
                            <th scope="col" class="px-3 py-3 text-left font-normal ">
                                <button type="button" wire:click="sortBy('clue_count')" class="meta-classical hover:text-ink inline-flex items-center gap-1 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                    {{ __('Clues') }}
                                    @if($sortField === 'clue_count')
                                        <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3 text-amber-400" />
                                    @endif
                                </button>
                            </th>
                    </tr>
                </thead>
                <tbody class="divide-hairline divide-y">
                    @foreach($this->words as $word)
                        <tr wire:key="word-{{ $word->id }}" x-on:click="window.location.href = '{{ route('words.show', $word) }}'" class="group cursor-pointer">
                            <td class="px-3 py-3.5 whitespace-nowrap">
                                <a href="{{ route('words.show', $word) }}" wire:navigate class="font-classical text-ink group-hover:text-amber-300 text-[18px] leading-none font-semibold tracking-[0.06em] transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">{{ $word->word }}</a>
                            </td>
                            <td class="font-classical text-ink tnum px-3 py-3.5 text-[15px] font-medium">{{ $word->length }}</td>
                            <td class="font-classical text-ink tnum px-3 py-3.5 text-[15px] font-medium">{{ number_format($word->score, 1) }}</td>
                            <td class="font-classical text-ink tnum px-3 py-3.5 text-[15px] font-medium">{{ number_format($word->clue_count) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($this->words->hasPages())
            <div class="mt-4">
                {{ $this->words->links() }}
            </div>
        @endif
    @endif
</div>
