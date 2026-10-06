<?php

use App\Models\Crossword;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The Solve section's discovery page: every published puzzle the visitor
 * hasn't attempted yet, with the filters of the discovery component, plus a
 * random pick. Split out of the Solve home so that page stays the visitor's
 * own attempts.
 */
new #[Title('Discover Puzzles')] class extends Component {
    public function surpriseMe(): void
    {
        $query = Crossword::where('is_published', true)
            ->where('user_id', '!=', Auth::id())
            ->safeFor(Auth::user());

        $blockedTagIds = Auth::user()->blockedTags()->pluck('tags.id');

        if ($blockedTagIds->isNotEmpty()) {
            $query->whereDoesntHave('tags', fn ($q) => $q->whereIn('tags.id', $blockedTagIds));
        }

        $crossword = $query->inRandomOrder()->first();

        if (! $crossword) {
            return;
        }

        $this->redirect(route('crosswords.solver', $crossword), navigate: true);
    }
}
?>

<div class="space-y-6">
    <x-page-header :kicker="__('Puzzles to solve')" :title="__('Discover Puzzles')">
        <x-header-button variant="secondary" icon="arrow-left" :href="route('crosswords.solving')" wire:navigate data-test="back-to-solving-button">
            {{ __('Back to Solving') }}
        </x-header-button>
        <x-header-button variant="secondary" icon="sparkles" wire:click="surpriseMe" data-test="surprise-me-button">
            {{ __('Surprise Me') }}
        </x-header-button>
    </x-page-header>

    <livewire:puzzle-discovery :exclude-attempted="true" />
</div>
