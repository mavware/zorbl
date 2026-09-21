<?php

use App\Models\ClueEntry;
use App\Models\Word;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
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
        return Word::findOrFail($this->wordId);
    }

    #[Computed]
    public function clues()
    {
        // Hide unvetted clues from everyone except their author, matching the
        // clue library. Keeps unapproved submissions off this public page.
        $authId = Auth::id();
        $query = ClueEntry::where('answer', $this->wordText)
            ->where(function ($q) use ($authId) {
                $q->approved();
                if ($authId !== null) {
                    $q->orWhere('user_id', $authId);
                }
            })
            ->with(['user:id,name', 'user.subscriptions', 'crossword:id,title,width,height,puzzle_type,grid,styles']);

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

    /**
     * Guests get the public chrome; logged-in users keep the app sidebar layout.
     */
    public function render(): View
    {
        return $this->view()
            ->layout(Auth::check() ? 'layouts.app' : 'layouts.public')
            ->title(__(':word — Crossword Clues & Answers', ['word' => $this->wordText]));
    }
}
?>

@php
    $clueTotal = $this->clues->total();
    $metaTitle = $this->word->word.' — Crossword Clues & Answers';
    $metaDescription = $clueTotal > 0
        ? __(':word is a :length-letter crossword answer with :count published clue(s). See how constructors have clued it.', ['word' => $this->word->word, 'length' => $this->word->length, 'count' => $clueTotal])
        : __(':word is a :length-letter crossword answer.', ['word' => $this->word->word, 'length' => $this->word->length]);
@endphp
<div class="space-y-6">
    {{-- Rich, indexable pages for words that actually have clues; thin
         (clue-less) pages are kept out of the index to avoid low-value URLs. --}}
    <x-seo-meta
        :title="$metaTitle"
        :canonical="route('words.show', $this->word)"
        :noindex="$clueTotal === 0"
        :description="$metaDescription"
    />

    @if($clueTotal > 0)
        @push('head_meta')
            @php
                $wordJsonLd = [
                    '@context' => 'https://schema.org',
                    '@type' => 'DefinedTerm',
                    'name' => $this->word->word,
                    'url' => route('words.show', $this->word),
                    'inDefinedTermSet' => [
                        '@type' => 'DefinedTermSet',
                        'name' => __('Crossword Answers'),
                        'url' => route('words.index'),
                    ],
                    'description' => __(':count crossword clue(s) for the answer :word.', ['count' => $clueTotal, 'word' => $this->word->word]),
                ];
            @endphp
            <script type="application/ld+json">{!! json_encode($wordJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endpush
    @endif

    {{-- Back Link --}}
    <div>
        <a href="{{ route('words.index') }}" wire:navigate class="btn-classical btn-classical-muted h-8 px-3 text-[14px]">
            <flux:icon name="arrow-left" class="size-4" />
            {{ __('Word Catalog') }}
        </a>
    </div>

    {{-- Word Header --}}
    <div class="border-hairline flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
        <h1 class="font-classical text-ink text-[40px] leading-none font-semibold tracking-[0.08em]">{{ $this->word->word }}</h1>
        <div class="flex flex-wrap gap-2">
            <span class="chip-classical border-ink-faint text-ink-faint tnum h-7 px-2.5">{{ $this->word->length }} {{ __('letters') }}</span>
            <span class="chip-classical border-ink-faint text-ink-faint tnum h-7 px-2.5">{{ __('Score') }}: {{ number_format($this->word->score, 1) }}</span>
            <span class="chip-classical border-amber-400 text-amber-400 tnum h-7 px-2.5">{{ number_format($this->clues->total()) }} {{ trans_choice('clue|clues', $this->clues->total()) }}</span>
        </div>
    </div>

    {{-- Clues Table --}}
    @if($this->clues->isEmpty())
        <div class="border-border-strong flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-16 text-center">
            <flux:icon name="book-open" class="text-ink-faint mb-4 size-10" />
            <h3 class="font-classical text-ink text-[26px] leading-tight font-medium">{{ __('No clues found') }}</h3>
            <p class="text-ink-muted mt-2 text-sm">{{ __('No clues have been recorded for this word yet.') }}</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-hairline border-b">
                        <th scope="col" class="px-3 py-3 text-left font-normal">
                            <button type="button" wire:click="sortBy('clue')" class="meta-classical hover:text-ink inline-flex items-center gap-1 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                {{ __('Clue') }}
                                @if($sortField === 'clue')
                                    <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3 text-amber-400" />
                                @endif
                            </button>
                        </th>
                        <th scope="col" class="meta-classical hidden px-3 py-3 text-left font-normal sm:table-cell">{{ __('Source') }}</th>
                        <th scope="col" class="meta-classical hidden px-3 py-3 text-left font-normal md:table-cell">{{ __('Author') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-hairline divide-y">
                    @foreach($this->clues as $entry)
                        <tr wire:key="clue-{{ $entry->id }}" class="align-middle">
                            <td class="text-ink px-3 py-3.5">{{ $entry->clue }}</td>
                            <td class="hidden px-3 py-3.5 sm:table-cell">
                                @if($entry->crossword)
                                    <span class="chip-classical border-ink-faint text-ink-faint max-w-full truncate">{{ Str::limit($entry->crossword->displayTitle(), 24) }}</span>
                                @else
                                    <span class="chip-classical border-ink-faint text-ink-faint">{{ __('Standalone') }}</span>
                                @endif
                            </td>
                            <td class="text-ink-muted hidden px-3 py-3.5 md:table-cell">{{ $entry->user->name ?? __('Unknown') }} <x-supporter-badge :user="$entry->user" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($this->clues->hasPages())
            <div class="mt-4">
                {{ $this->clues->links() }}
            </div>
        @endif
    @endif
</div>
