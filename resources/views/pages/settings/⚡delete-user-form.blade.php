<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="border-hairline mt-10 space-y-5 border-t pt-8">
        <div class="border-hairline mb-5 border-b pb-3.5">
            <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Delete account') }}</h2>
            <p class="meta-classical mt-1.5 normal-case tracking-normal">{{ __('Delete your account and all of its resources') }}</p>
        </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="ghost" class="btn-classical btn-classical-muted" data-test="delete-user-button">
            {{ __('Delete account') }}
        </flux:button>
    </flux:modal.trigger>

    <livewire:pages::settings.delete-user-modal />
</section>
