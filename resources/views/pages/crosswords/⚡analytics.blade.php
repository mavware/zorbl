<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Puzzle Stats')] class extends Component {
    //
}
?>

<div class="space-y-6">
    <x-page-header :kicker="__('Your workshop')" :title="__('Stats')">
        <x-header-button variant="secondary" icon="arrow-left" :href="route('crosswords.index')" wire:navigate>
            {{ __('Back to Build') }}
        </x-header-button>

        <x-slot:footer>
            {{-- Builder Stats — flush under the header rule --}}
            <livewire:constructor-stats key="constructor-stats" />
        </x-slot:footer>
    </x-page-header>

    <livewire:constructor-analytics key="constructor-analytics" />
</div>
