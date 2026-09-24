<?php

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

    /**
     * Refresh the list. Also runs after the browser finishes registering a
     * new passkey, which happens over XHR outside of Livewire.
     */
    #[On('passkey-registered')]
    public function loadPasskeys(): void
    {
        $this->passkeys = auth()->user()->passkeys()
            ->latest()
            ->get()
            ->map(fn (Passkey $passkey): array => [
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
        auth()->user()->passkeys()->findOrFail($passkeyId)->delete();

        $this->loadPasskeys();
    }
}; ?>

<section class="border-hairline mt-12 border-t pt-8">
    <div class="border-hairline mb-5 border-b pb-3.5">
        <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Passkeys') }}</h2>
        <p class="meta-classical mt-1.5 normal-case tracking-normal">{{ __('Sign in without a password using Face ID, Touch ID, Windows Hello, or a hardware security key.') }}</p>
    </div>

    <div class="space-y-6" x-data="passkeyManager" wire:cloak>
        @if (count($passkeys) > 0)
            <ul class="space-y-3" data-test="passkey-list">
                @foreach ($passkeys as $passkey)
                    <li class="border-border flex items-center justify-between gap-4 rounded-sm border p-4" wire:key="passkey-{{ $passkey['id'] }}">
                        <div class="flex min-w-0 items-center gap-3">
                            <flux:icon name="finger-print" variant="outline" class="text-ink-faint size-5 shrink-0" />
                            <div class="min-w-0">
                                <p class="text-ink truncate text-sm font-medium">{{ $passkey['name'] }}</p>
                                <p class="text-ink-muted text-xs leading-[1.65]">
                                    {{ __('Added :time', ['time' => $passkey['created_at']]) }}
                                    @if ($passkey['last_used_at'])
                                        &middot; {{ __('Last used :time', ['time' => $passkey['last_used_at']]) }}
                                    @endif
                                    @if ($passkey['authenticator'])
                                        &middot; {{ $passkey['authenticator'] }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="btn-classical btn-classical-muted shrink-0"
                            wire:click="deletePasskey({{ $passkey['id'] }})"
                            wire:confirm="{{ __('Remove this passkey? You will no longer be able to sign in with it.') }}"
                        >
                            {{ __('Remove') }}
                        </button>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-ink-muted text-sm leading-[1.65]">
                {{ __('You haven\'t added any passkeys yet. Add one to sign in faster and skip your password.') }}
            </p>
        @endif

        <template x-if="supported">
            <div class="space-y-4">
                <label class="block">
                    <span class="meta-classical mb-1.5 block">{{ __('Passkey name') }}</span>
                    <input
                        type="text"
                        x-model="passkeyName"
                        x-on:keydown.enter.prevent="register()"
                        maxlength="255"
                        placeholder="{{ __('e.g. MacBook Pro, iPhone') }}"
                        class="field-classical w-full px-3.5"
                    />
                    <p x-show="error" x-cloak x-text="error" class="mt-1.5 text-xs text-amber-400" role="alert"></p>
                </label>

                <div class="flex items-center gap-4">
                    <button
                        type="button"
                        class="btn-classical btn-amber-outline"
                        x-on:click="register()"
                        x-bind:disabled="registering || !passkeyName.trim()"
                        data-test="add-passkey-button"
                    >
                        <span x-show="!registering">{{ __('Add passkey') }}</span>
                        <span x-show="registering" x-cloak>{{ __('Waiting for your device…') }}</span>
                    </button>
                </div>
            </div>
        </template>

        <template x-if="!supported">
            <p class="text-ink-muted text-sm leading-[1.65]">
                {{ __('This browser doesn\'t support passkeys. Try a current version of Chrome, Safari, Edge, or Firefox.') }}
            </p>
        </template>
    </div>
</section>
