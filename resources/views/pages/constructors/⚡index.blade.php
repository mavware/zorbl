<?php

use App\Models\Crossword;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Constructors')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortBy = 'most_puzzles';

    #[Computed]
    public function constructors()
    {
        $visible = fn ($q) => $q->where('is_published', true)->safeFor(Auth::user());

        $query = User::where('is_anonymous', false)
            ->whereHas('crosswords', $visible)
            ->with('subscriptions')
            ->withCount([
                'crosswords as published_puzzles_count' => $visible,
            ])
            ->addSelect([
                'total_likes' => DB::table('crossword_likes')
                    ->join('crosswords', 'crosswords.id', '=', 'crossword_likes.crossword_id')
                    ->whereColumn('crosswords.user_id', 'users.id')
                    ->where('crosswords.is_published', true)
                    ->selectRaw('count(*)'),
                'total_solves' => DB::table('puzzle_attempts')
                    ->join('crosswords', 'crosswords.id', '=', 'puzzle_attempts.crossword_id')
                    ->whereColumn('crosswords.user_id', 'users.id')
                    ->where('crosswords.is_published', true)
                    ->where('puzzle_attempts.is_completed', true)
                    ->selectRaw('count(*)'),
            ])
            ->withCount('followers');

        if ($this->search !== '') {
            $query->whereLike('name', "%{$this->search}%");
        }

        match ($this->sortBy) {
            'most_liked' => $query->orderByDesc('total_likes'),
            'most_solved' => $query->orderByDesc('total_solves'),
            'most_followers' => $query->orderByDesc('followers_count'),
            'newest' => $query->latest(),
            default => $query->orderByDesc('published_puzzles_count'),
        };

        return $query->paginate(18);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    /**
     * Guests get the marketing/public chrome (this is a public SEO surface);
     * logged-in users keep the app sidebar layout.
     */
    public function render(): View
    {
        return $this->view()
            ->layout(Auth::check() ? 'layouts.app' : 'layouts.public')
            ->title(__('Constructors'));
    }
}
?>

<div class="space-y-6">
    <x-seo-meta
        title="Constructors"
        :canonical="route('constructors.index')"
        :description="__('Browse the crossword constructors publishing free puzzles on :app.', ['app' => config('app.name')])"
    />

    @push('head_meta')
        @php
            $constructorsJsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => __('Constructors'),
                'url' => route('constructors.index'),
                'isPartOf' => ['@id' => url('/').'#website'],
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'itemListElement' => collect($this->constructors->items())->map(fn ($c, $i) => [
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'name' => $c->name,
                        'url' => route('constructors.show', $c),
                    ])->values()->all(),
                ],
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($constructorsJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <x-page-header :kicker="__('Community')" :title="__('Constructors')" />

    {{-- Search & Sort --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <label class="relative flex-1">
            <span class="sr-only">{{ __('Search constructors...') }}</span>
            <flux:icon name="magnifying-glass" class="text-ink-faint pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
            <input
                type="search"
                placeholder="{{ __('Search constructors...') }}"
                wire:model.live.debounce.300ms="search"
                class="field-classical w-full pr-3 pl-9"
            />
        </label>
        <label class="relative sm:w-52">
            <span class="sr-only">{{ __('Sort') }}</span>
            <select wire:model.live="sortBy" class="field-classical font-classical w-full appearance-none pr-9 pl-3.5 text-[15px] font-medium">
                <option value="most_puzzles">{{ __('Sort') }}: {{ __('Most Puzzles') }}</option>
                <option value="most_liked">{{ __('Sort') }}: {{ __('Most Liked') }}</option>
                <option value="most_solved">{{ __('Sort') }}: {{ __('Most Solved') }}</option>
                <option value="most_followers">{{ __('Sort') }}: {{ __('Most Followers') }}</option>
                <option value="newest">{{ __('Sort') }}: {{ __('Newest') }}</option>
            </select>
            <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
        </label>
    </div>

    {{-- Constructor Grid --}}
    @if($this->constructors->isEmpty())
        <div class="border-border-strong flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-16 text-center">
            <flux:icon name="users" class="text-ink-faint mb-4 size-10" />
            <h3 class="font-classical text-ink text-[26px] leading-tight font-medium">{{ __('No constructors found') }}</h3>
            <p class="text-ink-muted mt-2 text-sm">
                @if($search !== '')
                    {{ __('Try a different search term.') }}
                @else
                    {{ __('No constructors have published puzzles yet.') }}
                @endif
            </p>
        </div>
    @else
        <div class="grid gap-[22px] [grid-template-columns:repeat(auto-fill,minmax(268px,1fr))]">
            @foreach($this->constructors as $constructor)
                <a
                    href="{{ route('constructors.show', $constructor) }}"
                    wire:navigate
                    wire:key="constructor-{{ $constructor->id }}"
                    class="border-border hover:border-border-strong group flex flex-col gap-4 rounded-sm border p-[18px] transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                >
                    <div class="flex items-center gap-3.5">
                        <div class="border-border-strong font-classical text-ink flex size-12 shrink-0 items-center justify-center rounded-full border text-[17px] font-medium tracking-[0.06em]">
                            {{ $constructor->initials() }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-classical text-ink group-hover:text-amber-300 truncate text-[21px] leading-tight font-semibold transition-colors">
                                {{ $constructor->name }}
                                <x-supporter-badge :user="$constructor" class="ml-1" />
                            </h3>
                            @if($constructor->bio)
                                <p class="text-ink-muted mt-0.5 line-clamp-1 text-sm">
                                    {{ $constructor->bio }}
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- The row bleeds through the card's padding so its top border and dividers
                         touch the card border; the cells carry the padding instead. --}}
                    <div class="border-hairline divide-hairline -mx-[18px] -mb-[18px] mt-auto grid grid-cols-3 divide-x border-t text-center" data-test="constructor-card-stats">
                        <div class="pt-3.5 pb-[18px]">
                            <div class="font-classical text-ink tnum text-[22px] leading-none font-medium">{{ $constructor->published_puzzles_count }}</div>
                            <div class="meta-classical mt-1.5">{{ __('Puzzles') }}</div>
                        </div>
                        <div class="pt-3.5 pb-[18px]">
                            <div class="font-classical text-ink tnum text-[22px] leading-none font-medium">{{ (int) $constructor->total_solves }}</div>
                            <div class="meta-classical mt-1.5">{{ __('Solves') }}</div>
                        </div>
                        <div class="pt-3.5 pb-[18px]">
                            <div class="font-classical text-ink tnum text-[22px] leading-none font-medium">{{ $constructor->followers_count }}</div>
                            <div class="meta-classical mt-1.5">{{ __('Followers') }}</div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($this->constructors->hasPages())
            <div class="mt-4">
                {{ $this->constructors->links() }}
            </div>
        @endif
    @endif
</div>
