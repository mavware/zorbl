<?php

use App\Models\Tag;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /** @var array<int, int> */
    public array $blockedTagIds = [];

    public function mount(): void
    {
        $this->blockedTagIds = Auth::user()->blockedTags()->pluck('tags.id')->all();
    }

    public function toggleTag(int $tagId): void
    {
        $user = Auth::user();

        if (in_array($tagId, $this->blockedTagIds)) {
            $user->blockedTags()->detach($tagId);
            $this->blockedTagIds = array_values(array_diff($this->blockedTagIds, [$tagId]));
        } else {
            $user->blockedTags()->attach($tagId);
            $this->blockedTagIds[] = $tagId;
        }

        $this->dispatch('blocked-tags-updated');
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Tag> */
    #[Computed]
    public function tags(): \Illuminate\Database\Eloquent\Collection
    {
        return Tag::orderBy('name')->get(['id', 'name', 'slug']);
    }
}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('Blocked tags') }}</flux:heading>
        <flux:subheading>{{ __('Puzzles with these tags will be hidden from your browse results.') }}</flux:subheading>
    </div>

    @if($this->tags->isEmpty())
        <flux:text class="text-zinc-400">{{ __('No tags available yet.') }}</flux:text>
    @else
        <div class="flex flex-wrap gap-2">
            @foreach($this->tags as $tag)
                <button
                    wire:click="toggleTag({{ $tag->id }})"
                    wire:key="blocked-tag-{{ $tag->id }}"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors',
                        'border-red-300 bg-red-50 text-red-700 dark:border-red-700 dark:bg-red-900/30 dark:text-red-400' => in_array($tag->id, $blockedTagIds),
                        'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:border-zinc-500' => ! in_array($tag->id, $blockedTagIds),
                    ])
                >
                    @if(in_array($tag->id, $blockedTagIds))
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" />
                        </svg>
                    @endif
                    {{ $tag->name }}
                </button>
            @endforeach
        </div>

        @if(count($blockedTagIds) > 0)
            <flux:text size="sm" class="text-zinc-500">
                {{ trans_choice(':count tag blocked|:count tags blocked', count($blockedTagIds)) }}
            </flux:text>
        @endif
    @endif

    <x-action-message on="blocked-tags-updated">
        {{ __('Saved.') }}
    </x-action-message>
</section>
