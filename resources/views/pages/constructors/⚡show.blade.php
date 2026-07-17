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
    <div class="flex items-start justify-between">
        <div class="flex items-center gap-4">
            <div class="flex size-16 items-center justify-center rounded-full bg-zinc-200 text-xl font-bold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                {{ $this->constructor->initials() }}
            </div>
            <div>
                <flux:heading size="xl">
                    {{ $constructorName }}
                    @if ($this->constructor->isPro())
                        <flux:badge color="purple" size="sm" class="ml-1 align-middle">{{ __('Pro') }}</flux:badge>
                    @endif
                </flux:heading>
                <div class="mt-1 flex items-center gap-4 text-sm text-zinc-600">
                    <span>{{ trans_choice(':count puzzle|:count puzzles', $this->publishedPuzzles->total()) }}</span>
                    <span>{{ trans_choice(':count follower|:count followers', $this->followersCount) }}</span>
                    <span>{{ __(':count total solves', ['count' => $this->totalSolves]) }}</span>
                </div>
                @if($this->constructor->bio)
                    <flux:text size="sm" class="mt-1.5 max-w-xl text-zinc-600 dark:text-zinc-400">
                        {{ $this->constructor->bio }}
                    </flux:text>
                @endif
                <flux:text size="sm" class="mt-0.5 text-zinc-500">
                    {{ __('Joined :date', ['date' => $this->constructor->created_at->format('M Y')]) }}
                </flux:text>
            </div>
        </div>

        @auth
            @if(Auth::id() !== $constructorId)
                <div class="flex items-center gap-2">
                    <flux:button
                        wire:click="toggleFollow"
                        :variant="$this->isFollowing ? 'ghost' : 'primary'"
                        size="sm"
                        :icon="$this->isFollowing ? 'user-minus' : 'user-plus'"
                    >
                        {{ $this->isFollowing ? __('Unfollow') : __('Follow') }}
                    </flux:button>
                    <livewire:report-button type="profile" :reportable-id="$constructorId" :key="'report-profile-'.$constructorId" />
                </div>
            @endif
        @endauth
    </div>

    {{-- Published Puzzles --}}
    <div>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="lg">{{ __('Published Puzzles') }}</flux:heading>

            <div class="flex flex-wrap items-center gap-3">
                <flux:input
                    icon="magnifying-glass"
                    placeholder="{{ __('Search puzzles...') }}"
                    wire:model.live.debounce.300ms="search"
                    size="sm"
                    class="w-48"
                />

                <flux:radio.group wire:model.live="difficulty" variant="segmented" size="sm">
                    <flux:radio value="" label="{{ __('All') }}" />
                    <flux:radio value="Easy" label="{{ __('Easy') }}" />
                    <flux:radio value="Medium" label="{{ __('Medium') }}" />
                    <flux:radio value="Hard" label="{{ __('Hard') }}" />
                    <flux:radio value="Expert" label="{{ __('Expert') }}" />
                </flux:radio.group>

                <flux:select wire:model.live="sortBy" size="sm" class="w-36">
                    <flux:select.option value="newest">{{ __('Newest') }}</flux:select.option>
                    <flux:select.option value="oldest">{{ __('Oldest') }}</flux:select.option>
                    <flux:select.option value="most_liked">{{ __('Most Liked') }}</flux:select.option>
                    <flux:select.option value="most_played">{{ __('Most Played') }}</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if($this->publishedPuzzles->isEmpty())
            <div class="border-line-strong flex flex-col items-center justify-center rounded-lg border border-dashed py-8">
                <flux:icon name="puzzle-piece" class="mb-2 size-8 text-zinc-500" />
                <flux:text size="sm" class="text-zinc-500">
                    @if($this->search !== '')
                        {{ __('No puzzles match your search.') }}
                    @elseif($this->difficulty !== '')
                        {{ __('No puzzles match this difficulty.') }}
                    @else
                        {{ __('No published puzzles yet.') }}
                    @endif
                </flux:text>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($this->publishedPuzzles as $puzzle)
                    <a
                        href="{{ Auth::check() ? route('crosswords.solver', $puzzle) : route('puzzles.solve', $puzzle) }}"
                        wire:navigate
                        class="border-line group rounded-xl border p-4 transition-colors hover:border-zinc-400 dark:hover:border-zinc-600"
                    >
                        <div class="mb-3 flex justify-center">
                            <x-grid-thumbnail :grid="$puzzle->grid" :width="$puzzle->width" :height="$puzzle->height" />
                        </div>
                        <div class="mb-2 flex items-start justify-between">
                            <flux:heading size="sm" class="group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                {{ $puzzle->displayTitle() }}
                            </flux:heading>
                            @if($puzzle->difficulty_label)
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' => $puzzle->difficulty_label === 'Easy',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $puzzle->difficulty_label === 'Medium',
                                    'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' => $puzzle->difficulty_label === 'Hard',
                                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $puzzle->difficulty_label === 'Expert',
                                ])>{{ $puzzle->difficulty_label }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 text-xs text-zinc-500">
                            <span>{{ $puzzle->width }}&times;{{ $puzzle->height }}</span>
                            <span class="flex items-center gap-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
                                {{ $puzzle->likes_count }}
                            </span>
                            <span class="flex items-center gap-0.5">
                                <flux:icon name="play" class="size-3.5" />
                                {{ $puzzle->cached_attempts_count }}
                            </span>
                            @if($puzzle->cached_attempts_count > 0)
                                @php($completionRate = round(($puzzle->cached_completed_count / $puzzle->cached_attempts_count) * 100))
                                <span @class([
                                    'text-emerald-600 dark:text-emerald-400' => $completionRate >= 75,
                                    'text-amber-600 dark:text-amber-400' => $completionRate >= 40 && $completionRate < 75,
                                    'text-zinc-600' => $completionRate < 40,
                                ])>{{ $completionRate }}% {{ __('solved') }}</span>
                            @endif
                        </div>
                        <flux:text size="sm" class="mt-1 text-zinc-500">
                            {{ $puzzle->created_at->diffForHumans() }}
                        </flux:text>
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
