<?php

use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes(auth()->user());

        $this->loadRecoveryCodes();
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
            try {
                $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            } catch (Exception) {
                $this->addError('recoveryCodes', 'Failed to load recovery codes');

                $this->recoveryCodes = [];
            }
        }
    }
}; ?>

<div
    class="border-border space-y-5 rounded-sm border p-4.5"
    wire:cloak
    x-data="{ showRecoveryCodes: false }"
>
    <div class="space-y-1.5">
        <div class="flex items-center gap-2">
            <flux:icon name="lock-closed" variant="outline" class="text-ink-faint size-4" />
            <h3 class="font-classical text-ink text-[19px] leading-tight font-semibold">{{ __('2FA recovery codes') }}</h3>
        </div>
        <p class="text-ink-muted text-sm leading-[1.65]">
            {{ __('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.') }}
        </p>
    </div>

    <div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <button
                type="button"
                x-show="!showRecoveryCodes"
                class="btn-classical btn-amber-outline"
                @click="showRecoveryCodes = true;"
                aria-expanded="false"
                aria-controls="recovery-codes-section"
            >
                <flux:icon name="eye" variant="outline" class="size-4" />
                {{ __('View recovery codes') }}
            </button>

            <button
                type="button"
                x-show="showRecoveryCodes"
                class="btn-classical btn-amber-outline"
                @click="showRecoveryCodes = false"
                aria-expanded="true"
                aria-controls="recovery-codes-section"
            >
                <flux:icon name="eye-slash" variant="outline" class="size-4" />
                {{ __('Hide recovery codes') }}
            </button>

            @if (filled($recoveryCodes))
                <button type="button" x-show="showRecoveryCodes" class="btn-classical btn-classical-muted" wire:click="regenerateRecoveryCodes">
                    <flux:icon name="arrow-path" class="size-4" />
                    {{ __('Regenerate codes') }}
                </button>
            @endif
        </div>

        <div
            x-show="showRecoveryCodes"
            x-transition
            id="recovery-codes-section"
            class="relative overflow-hidden"
            x-bind:aria-hidden="!showRecoveryCodes"
        >
            <div class="mt-3 space-y-3">
                @error('recoveryCodes')
                    <div class="border-amber-400/60 rounded-sm border p-3.5 text-sm text-amber-400">{{ $message }}</div>
                @enderror

                @if (filled($recoveryCodes))
                    <div
                        class="border-border text-ink grid gap-1 rounded-sm border p-4 font-mono text-sm"
                        role="list"
                        aria-label="{{ __('Recovery codes') }}"
                    >
                        @foreach($recoveryCodes as $code)
                            <div role="listitem" class="select-text" wire:loading.class="opacity-50 animate-pulse">
                                {{ $code }}
                            </div>
                        @endforeach
                    </div>
                    <p class="text-ink-muted text-xs leading-[1.65]">
                        {{ __('Each recovery code can be used once to access your account and will be removed after use. If you need more, click Regenerate codes above.') }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
