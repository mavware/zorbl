<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="border-hairline mt-10 space-y-5 border-t pt-8">
        <div class="border-hairline mb-5 border-b pb-3.5">
            <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Download your data') }}</h2>
            <p class="meta-classical mt-1.5 normal-case tracking-normal">{{ __('Get a JSON copy of your profile, puzzles, attempts, clues, comments, favorites, and other account data.') }}</p>
        </div>

    <a href="{{ route('account.export') }}" class="btn-classical btn-classical-muted" data-test="export-account-button">
        <flux:icon name="arrow-down-tray" class="size-4" />
        {{ __('Download data') }}
    </a>
</section>
