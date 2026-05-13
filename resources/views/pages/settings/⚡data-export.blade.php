<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('Download your data') }}</flux:heading>
        <flux:subheading>{{ __('Get a JSON copy of your profile, puzzles, attempts, clues, comments, favorites, and other account data.') }}</flux:subheading>
    </div>

    <flux:button
        :href="route('account.export')"
        icon="arrow-down-tray"
        variant="filled"
        data-test="export-account-button"
    >
        {{ __('Download data') }}
    </flux:button>
</section>
