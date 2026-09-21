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

<section class="border-hairline mt-10 space-y-5 border-t pt-8">
        <div class="border-hairline mb-5 border-b pb-3.5">
            <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Blocked tags') }}</h2>
            <p class="meta-classical mt-1.5 normal-case tracking-normal">{{ __('Puzzles with these tags will be hidden from your browse results.') }}</p>
        </div>

    @if($this->tags->isEmpty())
        <p class="text-ink-muted text-sm">{{ __('No tags available yet.') }}</p>
    @else
        <div class="flex flex-wrap gap-2">
            @foreach($this->tags as $tag)
                <button
                    type="button"
                    wire:click="toggleTag({{ $tag->id }})"
                    wire:key="blocked-tag-{{ $tag->id }}"
                    aria-pressed="{{ in_array($tag->id, $blockedTagIds) ? 'true' : 'false' }}"
                    @class([
                        'font-classical inline-flex h-8 items-center gap-1.5 rounded-sm border px-3 text-[15px] font-medium transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400',
                        'border-amber-400 text-amber-400' => in_array($tag->id, $blockedTagIds),
                        'border-border-strong text-ink-muted hover:border-border-hover hover:text-ink' => ! in_array($tag->id, $blockedTagIds),
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
            <p class="meta-classical tnum">
                {{ trans_choice(':count tag blocked|:count tags blocked', count($blockedTagIds)) }}
            </p>
        @endif
    @endif

    <x-action-message on="blocked-tags-updated">
        {{ __('Saved.') }}
    </x-action-message>
</section>
