<?php

use App\Models\CrosswordLike;
use App\Models\FavoriteList;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Favorites')] class extends Component {
    #[Url]
    public string $list = 'liked';

    public bool $showNewListModal = false;
    public string $newListName = '';

    public bool $showRenameModal = false;
    public ?int $renamingListId = null;
    public string $renameListName = '';

    public bool $showAddToListModal = false;
    public ?int $addingCrosswordId = null;

    /** @return \Illuminate\Support\Collection<int, FavoriteList> */
    #[Computed]
    public function lists()
    {
        return Auth::user()
            ->favoriteLists()
            ->withCount('crosswords')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function likedCrosswords()
    {
        return Auth::user()
            ->likedCrosswords()
            ->with('user:id,name')
            ->withCount('likes')
            ->latest('crossword_likes.created_at')
            ->get();
    }

    #[Computed]
    public function activeListCrosswords()
    {
        if ($this->list === 'liked') {
            return $this->likedCrosswords;
        }

        $favoriteList = Auth::user()->favoriteLists()->find($this->list);

        if (! $favoriteList) {
            return collect();
        }

        return $favoriteList->crosswords()
            ->with('user:id,name')
            ->withCount('likes')
            ->latest('crossword_favorite_list.created_at')
            ->get();
    }

    #[Computed]
    public function activeListName(): string
    {
        if ($this->list === 'liked') {
            return __('Liked Puzzles');
        }

        return Auth::user()->favoriteLists()->find($this->list)?->name ?? __('Unknown List');
    }

    public function createList(): void
    {
        $this->validate([
            'newListName' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        $user = Auth::user();
        $limits = $user->planLimits();

        if ($user->favoriteLists()->count() >= $limits->maxFavoriteLists()) {
            $this->addError('newListName', __('Free accounts can create up to :count favorite lists. Upgrade to Pro for unlimited.', ['count' => $limits->maxFavoriteLists()]));

            return;
        }

        $name = trim($this->newListName);

        $exists = $user->favoriteLists()->where('name', $name)->exists();

        if ($exists) {
            $this->addError('newListName', __('You already have a list with this name.'));

            return;
        }

        $list = $user->favoriteLists()->create(['name' => $name]);

        $this->newListName = '';
        $this->showNewListModal = false;
        $this->list = (string) $list->id;
        unset($this->lists);
    }

    public function openRenameModal(int $listId): void
    {
        $list = Auth::user()->favoriteLists()->findOrFail($listId);

        $this->renamingListId = $list->id;
        $this->renameListName = $list->name;
        $this->showRenameModal = true;
    }

    public function renameList(): void
    {
        $this->validate([
            'renameListName' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        $list = Auth::user()->favoriteLists()->findOrFail($this->renamingListId);
        $name = trim($this->renameListName);

        $exists = Auth::user()->favoriteLists()
            ->where('name', $name)
            ->where('id', '!=', $list->id)
            ->exists();

        if ($exists) {
            $this->addError('renameListName', __('You already have a list with this name.'));

            return;
        }

        $list->update(['name' => $name]);

        $this->showRenameModal = false;
        $this->renamingListId = null;
        unset($this->lists, $this->activeListName);
    }

    public function deleteList(int $listId): void
    {
        Auth::user()->favoriteLists()->findOrFail($listId)->delete();

        if ($this->list === (string) $listId) {
            $this->list = 'liked';
        }

        unset($this->lists);
    }

    public function unlikeCrossword(int $crosswordId): void
    {
        CrosswordLike::where('user_id', Auth::id())
            ->where('crossword_id', $crosswordId)
            ->delete();

        unset($this->likedCrosswords, $this->activeListCrosswords);
    }

    public function removeFromList(int $crosswordId): void
    {
        if ($this->list === 'liked') {
            $this->unlikeCrossword($crosswordId);

            return;
        }

        $favoriteList = Auth::user()->favoriteLists()->findOrFail($this->list);
        $favoriteList->crosswords()->detach($crosswordId);
        unset($this->activeListCrosswords, $this->lists);
    }

    public function openAddToListModal(int $crosswordId): void
    {
        $this->addingCrosswordId = $crosswordId;
        $this->showAddToListModal = true;
    }

    public function addToList(int $listId): void
    {
        $list = Auth::user()->favoriteLists()->findOrFail($listId);

        if (! $list->crosswords()->where('crossword_id', $this->addingCrosswordId)->exists()) {
            $list->crosswords()->attach($this->addingCrosswordId);
        }

        $this->showAddToListModal = false;
        $this->addingCrosswordId = null;
        unset($this->lists, $this->activeListCrosswords);
    }
}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Favorites') }}</flux:heading>

        <flux:button variant="primary" icon="plus" wire:click="$set('showNewListModal', true)">
            {{ __('New List') }}
        </flux:button>
    </div>

    {{-- List Tabs --}}
    <div class="flex flex-wrap gap-2">
        <flux:button
            size="sm"
            :variant="$list === 'liked' ? 'primary' : 'ghost'"
            icon="heart"
            wire:click="$set('list', 'liked')"
        >
            {{ __('Liked') }} ({{ $this->likedCrosswords->count() }})
        </flux:button>

        @foreach($this->lists as $favoriteList)
            <flux:button
                size="sm"
                :variant="$list === (string) $favoriteList->id ? 'primary' : 'ghost'"
                icon="folder"
                wire:click="$set('list', '{{ $favoriteList->id }}')"
            >
                {{ $favoriteList->name }} ({{ $favoriteList->crosswords_count }})
            </flux:button>
        @endforeach
    </div>

    {{-- Active List Header --}}
    <div class="flex items-center gap-3">
        <flux:heading size="lg">{{ $this->activeListName }}</flux:heading>

        @if($list !== 'liked')
            <flux:dropdown position="bottom" align="start">
                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                <flux:menu>
                    <flux:menu.item icon="pencil" wire:click="openRenameModal({{ $list }})">
                        {{ __('Rename') }}
                    </flux:menu.item>
                    <flux:menu.item icon="trash" variant="danger" wire:click="deleteList({{ $list }})" wire:confirm="{{ __('Delete this list? Puzzles won\'t be removed from the platform.') }}">
                        {{ __('Delete List') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @endif
    </div>

    {{-- Puzzle Grid --}}
    @if($this->activeListCrosswords->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-600">
            <flux:icon name="heart" class="mb-4 size-12 text-zinc-400" />
            <flux:heading size="lg" class="mb-2">{{ __('No puzzles here yet') }}</flux:heading>
            <flux:text>
                @if($list === 'liked')
                    {{ __('Like puzzles while browsing or solving to see them here.') }}
                @else
                    {{ __('Add puzzles to this list from your liked puzzles.') }}
                @endif
            </flux:text>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($this->activeListCrosswords as $crossword)
                <div wire:key="fav-{{ $crossword->id }}" class="group relative rounded-xl border border-zinc-200 p-4 transition-colors hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <a href="{{ route('crosswords.solver', $crossword) }}" wire:navigate class="block">
                        <div class="mb-3 flex justify-center">
                            <x-grid-thumbnail :grid="$crossword->grid" :width="$crossword->width" :height="$crossword->height" />
                        </div>

                        <flux:heading size="sm" class="truncate">{{ $crossword->title ?: __('Untitled Puzzle') }}</flux:heading>
                        <flux:text size="sm" class="mt-1">
                            {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }}
                            &middot;
                            {{ $crossword->width }}&times;{{ $crossword->height }}
                        </flux:text>

                        <div class="mt-1.5 flex items-center gap-2">
                            <span class="flex items-center gap-1 text-xs text-zinc-400">
                                <flux:icon name="heart" class="size-3.5" />
                                {{ $crossword->likes_count }}
                            </span>
                        </div>
                    </a>

                    <div class="absolute top-2 right-2 opacity-0 transition-opacity group-hover:opacity-100">
                        <flux:dropdown position="bottom" align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                            <flux:menu>
                                @if($list === 'liked')
                                    <flux:menu.item icon="folder-plus" wire:click="openAddToListModal({{ $crossword->id }})">
                                        {{ __('Add to List') }}
                                    </flux:menu.item>
                                @endif
                                <flux:menu.item icon="x-mark" variant="danger" wire:click="removeFromList({{ $crossword->id }})">
                                    {{ $list === 'liked' ? __('Unlike') : __('Remove from List') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- New List Modal --}}
    <flux:modal wire:model="showNewListModal">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Create Favorites List') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="newListName" placeholder="{{ __('e.g. Weekend Puzzles') }}" />
                <flux:error name="newListName" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showNewListModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="createList">{{ __('Create') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Rename List Modal --}}
    <flux:modal wire:model="showRenameModal">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Rename List') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="renameListName" />
                <flux:error name="renameListName" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showRenameModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="renameList">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Add to List Modal --}}
    <flux:modal wire:model="showAddToListModal">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Add to List') }}</flux:heading>

            @if($this->lists->isEmpty())
                <flux:text>{{ __('You don\'t have any lists yet. Create one first.') }}</flux:text>
            @else
                <div class="space-y-2">
                    @foreach($this->lists as $favoriteList)
                        <button
                            wire:click="addToList({{ $favoriteList->id }})"
                            class="flex w-full items-center gap-3 rounded-lg border border-zinc-200 px-4 py-3 text-left transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        >
                            <flux:icon name="folder" class="size-5 text-zinc-400" />
                            <span class="flex-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $favoriteList->name }}</span>
                            <flux:badge size="sm">{{ $favoriteList->crosswords_count }}</flux:badge>
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button wire:click="$set('showAddToListModal', false)">{{ __('Cancel') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
