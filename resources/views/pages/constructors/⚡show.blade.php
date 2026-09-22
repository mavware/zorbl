<?php

use App\Models\Crossword;
use App\Models\Follow;
use App\Models\User;
use App\Notifications\NewFollower;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Constructor Profile')] class extends Component {
    use WithPagination;

    #[Locked]
    public int $constructorId;

    public string $constructorName = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortBy = 'newest';

    #[Url]
    public string $difficulty = '';

    public function mount(User $constructor): void
    {
        // Guest-builder (anonymous) accounts are never valid public profiles.
        abort_if($constructor->is_anonymous, 404);

        $this->constructorId = $constructor->id;
        $this->constructorName = $constructor->name;
    }

    #[Computed]
    public function constructor(): User
    {
        return User::findOrFail($this->constructorId);
    }

    #[Computed]
    public function publishedPuzzles()
    {
        $query = Crossword::where('user_id', $this->constructorId)
            ->where('is_published', true)
            ->safeFor(Auth::user())
            ->withCount('likes');

        if ($this->search !== '') {
            $query->whereLike('title', "%{$this->search}%");
        }

        if ($this->difficulty !== '') {
            $query->where('difficulty_label', $this->difficulty);
        }

        match ($this->sortBy) {
            'oldest' => $query->oldest(),
            'most_liked' => $query->orderByDesc('likes_count'),
            'most_played' => $query->orderByDesc('cached_attempts_count'),
            default => $query->latest(),
        };

        return $query->paginate(12);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    public function updatedDifficulty(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function followersCount(): int
    {
        return Follow::where('following_id', $this->constructorId)->count();
    }

    #[Computed]
    public function followingCount(): int
    {
        return Follow::where('follower_id', $this->constructorId)->count();
    }

    #[Computed]
    public function isFollowing(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        return Follow::where('follower_id', Auth::id())
            ->where('following_id', $this->constructorId)
            ->exists();
    }

    #[Computed]
    public function totalSolves(): int
    {
        return (int) Crossword::where('user_id', $this->constructorId)
            ->where('is_published', true)
            ->sum('cached_attempts_count');
    }

    public function toggleFollow(): void
    {
        $user = Auth::user();

        if (! $user || $user->id === $this->constructorId) {
            return;
        }

        $existing = Follow::where('follower_id', $user->id)
            ->where('following_id', $this->constructorId)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            Follow::create([
                'follower_id' => $user->id,
                'following_id' => $this->constructorId,
            ]);

            $followedUser = User::find($this->constructorId);

            if ($followedUser) {
                $followedUser->notify(new NewFollower($user));
            }
        }

        unset($this->isFollowing, $this->followersCount);
    }

    /**
     * Public SEO surface: guests get the marketing chrome and the constructor's
     * name as the page title; logged-in users keep the app sidebar layout.
     */
    public function render(): View
    {
        return $this->view()
            ->layout(Auth::check() ? 'layouts.app' : 'layouts.public')
            ->title($this->constructorName);
    }
}
?>

<div class="space-y-6">
    @php
        $puzzleCount = $this->publishedPuzzles->total();
        $profileDescription = $puzzleCount > 0
            ? __(':name has published :count on :app. Solve their crosswords free.', [
                'name' => $constructorName,
                'count' => trans_choice(':count crossword|:count crosswords', $puzzleCount),
                'app' => config('app.name'),
            ])
            : __(':name on :app.', ['name' => $constructorName, 'app' => config('app.name')]);
    @endphp

    <x-seo-meta
        :title="$constructorName"
        :canonical="route('constructors.show', $this->constructor)"
        :description="$profileDescription"
        :noindex="$puzzleCount === 0"
    />

    @push('head_meta')
        @php
            $profileJsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'ProfilePage',
                'url' => route('constructors.show', $this->constructor),
                'isPartOf' => ['@id' => url('/').'#website'],
                'mainEntity' => array_filter([
                    '@type' => 'Person',
                    'name' => $constructorName,
                    'description' => $this->constructor->bio ?: null,
                    'url' => route('constructors.show', $this->constructor),
                ]),
                'about' => [
                    '@type' => 'ItemList',
                    'itemListElement' => collect($this->publishedPuzzles->items())->map(fn ($puzzle, $i) => [
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'name' => $puzzle->displayTitle(),
                        'url' => route('puzzles.solve', $puzzle),
                    ])->values()->all(),
                ],
            ];

            $breadcrumbJsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => __('Constructors'), 'item' => route('constructors.index')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $constructorName, 'item' => route('constructors.show', $this->constructor)],
                ],
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($profileJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <script type="application/ld+json">{!! json_encode($breadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    {{-- Profile Header --}}
    <x-page-header :kicker="__('Constructor')" :subtitle="$this->constructor->bio">
        <x-slot:leading>
            <div class="border-border-strong font-classical text-ink flex size-16 items-center justify-center rounded-full border text-[22px] font-medium tracking-[0.06em]">
                {{ $this->constructor->initials() }}
            </div>
        </x-slot:leading>

        <x-slot:title>
            {{ $constructorName }}
            <x-supporter-badge :user="$this->constructor" class="ml-1" />
        </x-slot:title>

        <x-slot:meta>
            <span class="tnum whitespace-nowrap">{{ trans_choice(':count puzzle|:count puzzles', $this->publishedPuzzles->total()) }}</span>
            <span class="tnum whitespace-nowrap">{{ trans_choice(':count follower|:count followers', $this->followersCount) }}</span>
            <span class="tnum whitespace-nowrap">{{ __(':count total solves', ['count' => $this->totalSolves]) }}</span>
            <span class="whitespace-nowrap">{{ __('Joined :date', ['date' => $this->constructor->created_at->format('M Y')]) }}</span>
        </x-slot:meta>

        @auth
            @if(Auth::id() !== $constructorId)
                <x-header-button
                    wire:click="toggleFollow"
                    :variant="$this->isFollowing ? 'secondary' : 'primary'"
                    :icon="$this->isFollowing ? 'user-minus' : 'user-plus'"
                >
                    {{ $this->isFollowing ? __('Unfollow') : __('Follow') }}
                </x-header-button>
                <livewire:report-button type="profile" :reportable-id="$constructorId" :key="'report-profile-'.$constructorId" />
            @endif
        @endauth
    </x-page-header>

    {{-- Published Puzzles --}}
    <div>
        <div class="border-hairline mb-6 flex flex-col gap-3 border-y py-5 lg:flex-row lg:items-center lg:justify-between">
            <h2 class="font-classical text-ink text-[26px] leading-tight font-medium">{{ __('Published Puzzles') }}</h2>

            <div class="flex flex-wrap items-center gap-3">
                <label class="relative w-full sm:w-56">
                    <span class="sr-only">{{ __('Search puzzles...') }}</span>
                    <flux:icon name="magnifying-glass" class="text-ink-faint pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                    <input
                        type="search"
                        placeholder="{{ __('Search puzzles...') }}"
                        wire:model.live.debounce.300ms="search"
                        class="field-classical w-full pr-3 pl-9"
                    />
                </label>

                <div class="border-border-strong divide-hairline inline-flex h-10 divide-x overflow-hidden rounded-sm border" role="radiogroup" aria-label="{{ __('Difficulty') }}">
                    @foreach (['' => __('All'), 'Easy' => __('Easy'), 'Medium' => __('Medium'), 'Hard' => __('Hard'), 'Expert' => __('Expert')] as $value => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="difficulty" value="{{ $value }}" wire:model.live="difficulty" class="peer sr-only" />
                            <span class="font-classical text-ink-muted hover:text-ink peer-checked:bg-amber-400/10 peer-checked:text-amber-400 peer-focus-visible:outline-2 peer-focus-visible:-outline-offset-2 peer-focus-visible:outline-amber-400 flex h-full items-center px-3.5 text-[15px] font-medium transition-colors">
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>

                <label class="relative">
                    <span class="sr-only">{{ __('Sort') }}</span>
                    <select wire:model.live="sortBy" class="field-classical font-classical appearance-none pr-9 pl-3.5 text-[15px] font-medium">
                        <option value="newest">{{ __('Sort') }}: {{ __('Newest') }}</option>
                        <option value="oldest">{{ __('Sort') }}: {{ __('Oldest') }}</option>
                        <option value="most_liked">{{ __('Sort') }}: {{ __('Most Liked') }}</option>
                        <option value="most_played">{{ __('Sort') }}: {{ __('Most Played') }}</option>
                    </select>
                    <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                </label>
            </div>
        </div>

        @if($this->publishedPuzzles->isEmpty())
            <div class="border-border-strong flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-12 text-center">
                <flux:icon name="puzzle-piece" class="text-ink-faint mb-4 size-10" />
                <h3 class="font-classical text-ink text-[22px] leading-tight font-medium">
                    @if($this->search !== '')
                        {{ __('No puzzles match your search.') }}
                    @elseif($this->difficulty !== '')
                        {{ __('No puzzles match this difficulty.') }}
                    @else
                        {{ __('No published puzzles yet.') }}
                    @endif
                </h3>
            </div>
        @else
            <div class="grid gap-5.5 grid-cols-[repeat(auto-fill,minmax(268px,1fr))]">
                @foreach($this->publishedPuzzles as $puzzle)
                    <a
                        href="{{ Auth::check() ? route('crosswords.solver', $puzzle) : route('puzzles.solve', $puzzle) }}"
                        wire:navigate
                        class="border-border hover:border-border-strong group flex flex-col gap-3.5 rounded-sm border p-4.5 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-classical text-ink group-hover:text-amber-300 truncate text-[21px] leading-tight font-semibold transition-colors">
                                    {{ $puzzle->displayTitle() }}
                                </h3>
                                <div class="meta-classical mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span class="tnum whitespace-nowrap">{{ $puzzle->width }}&times;{{ $puzzle->height }}</span>
                                    <span aria-hidden="true">&middot;</span>
                                    <span class="whitespace-nowrap">{{ $puzzle->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                            @if($puzzle->difficulty_label)
                                <span class="chip-classical border-ink-faint text-ink-faint">{{ $puzzle->difficulty_label }}</span>
                            @endif
                        </div>

                        <div class="flex justify-center py-1">
                            <x-grid-thumbnail
                                :grid="$puzzle->grid"
                                :width="$puzzle->width"
                                :height="$puzzle->height"
                                frame-class="border-hairline bg-hairline rounded-sm border"
                                open-class="bg-panel"
                                block-class="bg-zinc-300"
                            />
                        </div>

                        <div class="meta-classical flex flex-wrap items-center gap-x-4 gap-y-1">
                            <span class="flex items-center gap-1 whitespace-nowrap">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
                                <span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $puzzle->likes_count }}</span>
                            </span>
                            <span class="flex items-center gap-1 whitespace-nowrap">
                                <flux:icon name="play" class="size-3.5" />
                                <span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $puzzle->cached_attempts_count }}</span>
                            </span>
                            @if($puzzle->cached_attempts_count > 0)
                                @php($completionRate = round(($puzzle->cached_completed_count / $puzzle->cached_attempts_count) * 100))
                                <span class="whitespace-nowrap"><span class="font-classical text-ink tnum text-[15px] font-medium tracking-normal">{{ $completionRate }}%</span> {{ __('solved') }}</span>
                            @endif
                        </div>

                        <div class="pt-1">
                            <span class="btn-classical btn-amber-outline">{{ __('Solve') }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            @if($this->publishedPuzzles->hasPages())
                <div class="mt-4">
                    {{ $this->publishedPuzzles->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
