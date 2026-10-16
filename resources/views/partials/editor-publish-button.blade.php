{{-- Publish toggle. Outline until every cell and clue is filled, then solid
     amber so the finished puzzle invites publishing. A published puzzle
     shows a quiet Unpublish instead, and an anonymous builder is sent to
     sign up. Rendered twice: in the toolbar for desktop, and beside the
     Across / Down tabs on mobile (editor-mobile-clues), where it also
     carries the total completion ring that replaces the toolbar's two
     progress meters.

     $testId   data-test for the button, distinct per surface.
     $withRing whether to show the completion ring inside the button. --}}
@if(auth()->user()?->isAnonymous())
    <flux:tooltip content="{{ __('Sign up to publish') }}">
        <flux:button
            variant="ghost"
            size="sm"
            class="btn-amber-outline"
            :href="route('register')"
            wire:navigate.hover
            data-test="{{ $testId }}"
        >
            <span class="inline-flex items-center gap-1.5">
                @if ($withRing) @include('partials.editor-publish-progress-ring') @endif
                {{ __('Publish') }}
            </span>
        </flux:button>
    </flux:tooltip>
@elseif($isPublished)
    <flux:tooltip content="{{ __('Unpublish puzzle') }}">
        <flux:button
            variant="ghost"
            size="sm"
            class="btn-muted-outline"
            wire:click="attemptPublish"
            data-test="{{ $testId }}"
        >
            <span class="inline-flex items-center gap-1.5">
                @if ($withRing) @include('partials.editor-publish-progress-ring') @endif
                {{ __('Unpublish') }}
            </span>
        </flux:button>
    </flux:tooltip>
@else
    <flux:tooltip content="{{ __('Publish for others to solve') }}">
        <flux:button
            variant="ghost"
            size="sm"
            x-bind:class="isReadyToPublish ? 'btn-amber-solid' : 'btn-amber-outline'"
            wire:click="attemptPublish"
            data-test="{{ $testId }}"
        >
            <span class="inline-flex items-center gap-1.5">
                @if ($withRing) @include('partials.editor-publish-progress-ring') @endif
                {{ __('Publish') }}
            </span>
        </flux:button>
    </flux:tooltip>
@endif
