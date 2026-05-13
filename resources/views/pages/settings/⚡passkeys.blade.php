<?php

use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    /** @var array<int, array{id: int, name: string, authenticator: string|null, last_used_at: string|null, created_at: string}> */
    #[Locked]
    public array $passkeys = [];

    public function mount(): void
    {
        $this->loadPasskeys();
    }

    #[On('passkey-registered')]
    public function loadPasskeys(): void
    {
        $this->passkeys = auth()->user()->passkeys()
            ->latest()
            ->get()
            ->map(fn (Passkey $passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'last_used_at' => $passkey->last_used_at?->diffForHumans(),
                'created_at' => $passkey->created_at->diffForHumans(),
            ])
            ->all();
    }

    public function deletePasskey(int $passkeyId): void
    {
        $passkey = auth()->user()->passkeys()->findOrFail($passkeyId);
        $passkey->delete();

        $this->loadPasskeys();
    }
}; ?>

<section>
    <flux:heading>{{ __('Passkeys') }}</flux:heading>
    <flux:subheading>{{ __('Passkeys let you sign in without a password using biometrics, security keys, or your device lock screen.') }}</flux:subheading>

    <div class="mt-6 space-y-6" x-data="passkeyManager" wire:cloak>
        @if (count($passkeys) > 0)
            <div class="space-y-3">
                @foreach ($passkeys as $passkey)
                    <div class="flex items-center justify-between p-4 border rounded-lg border-zinc-200 dark:border-white/10">
                        <div class="flex items-center gap-3">
                            <flux:icon.finger-print variant="outline" class="text-zinc-500 dark:text-zinc-400 size-5" />
                            <div>
                                <flux:text class="font-medium">{{ $passkey['name'] }}</flux:text>
                                <flux:text variant="subtle" class="text-xs">
                                    {{ __('Added :time', ['time' => $passkey['created_at']]) }}
                                    @if ($passkey['last_used_at'])
                                        &middot; {{ __('Last used :time', ['time' => $passkey['last_used_at']]) }}
                                    @endif
                                    @if ($passkey['authenticator'])
                                        &middot; {{ $passkey['authenticator'] }}
                                    @endif
                                </flux:text>
                            </div>
                        </div>
                        <flux:button
                            variant="danger"
                            size="sm"
                            icon="trash"
                            wire:click="deletePasskey({{ $passkey['id'] }})"
                            wire:confirm="{{ __('Are you sure you want to remove this passkey?') }}"
                        />
                    </div>
                @endforeach
            </div>
        @else
            <flux:text variant="subtle">
                {{ __('You haven\'t registered any passkeys yet.') }}
            </flux:text>
        @endif

        <template x-if="supported">
            <div class="space-y-3">
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <flux:input
                            x-model="passkeyName"
                            :label="__('Passkey name')"
                            :placeholder="__('e.g. MacBook Pro, iPhone')"
                            x-on:keydown.enter.prevent="register()"
                        />
                    </div>
                    <flux:button
                        variant="primary"
                        icon="plus"
                        x-on:click="register()"
                        x-bind:disabled="registering || !passkeyName.trim()"
                    >
                        <span x-show="!registering">{{ __('Add passkey') }}</span>
                        <span x-show="registering" x-cloak>{{ __('Registering...') }}</span>
                    </flux:button>
                </div>

                <template x-if="error">
                    <flux:callout variant="danger" icon="x-circle">
                        <flux:callout.heading x-text="error" />
                    </flux:callout>
                </template>
            </div>
        </template>

        <template x-if="!supported">
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>{{ __('Passkeys are not supported by this browser.') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Try using a modern browser like Chrome, Safari, or Edge to register passkeys.') }}</flux:callout.text>
            </flux:callout>
        </template>
    </div>
</section>
