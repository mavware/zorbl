<?php

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Security settings')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public string $logout_other_password = '';

    public bool $canManageTwoFactor;

    public bool $twoFactorEnabled;

    public bool $requiresConfirmation;

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        $user = Auth::user();
        $user->update([
            'password' => $validated['password'],
        ]);
        $this->revokeOtherSessions($user);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }

    /**
     * Sign the user out of all other browser sessions on demand. Requires
     * the current password as a guard against a walked-away laptop.
     */
    public function logoutOtherBrowserSessions(): void
    {
        try {
            $this->validate([
                'logout_other_password' => ['required', 'string', 'current_password'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('logout_other_password');

            throw $e;
        }

        $this->revokeOtherSessions(Auth::user());

        $this->reset('logout_other_password');

        $this->dispatch('other-sessions-revoked');
    }

    /**
     * Rotate the remember-me token and drop any other active sessions
     * belonging to the user, keeping only the current session alive.
     */
    protected function revokeOtherSessions(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->where('id', '!=', session()->getId())
            ->delete();
    }

    /**
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <h2 class="sr-only">{{ __('Security settings') }}</h2>

    <x-pages::settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            <label class="block">
                <span class="meta-classical mb-1.5 block">{{ __('Current password') }}</span>
                <input type="password" wire:model="current_password" required autocomplete="current-password" class="field-classical w-full px-3.5" />
                @error('current_password') <p class="mt-1.5 text-xs text-amber-400">{{ $message }}</p> @enderror
            </label>
            <label class="block">
                <span class="meta-classical mb-1.5 block">{{ __('New password') }}</span>
                <input type="password" wire:model="password" required autocomplete="new-password" class="field-classical w-full px-3.5" />
                @error('password') <p class="mt-1.5 text-xs text-amber-400">{{ $message }}</p> @enderror
            </label>
            <label class="block">
                <span class="meta-classical mb-1.5 block">{{ __('Confirm password') }}</span>
                <input type="password" wire:model="password_confirmation" required autocomplete="new-password" class="field-classical w-full px-3.5" />
                @error('password_confirmation') <p class="mt-1.5 text-xs text-amber-400">{{ $message }}</p> @enderror
            </label>

            <div class="flex items-center gap-4">
                <button type="submit" class="btn-classical btn-amber-outline" data-test="update-password-button">
                    {{ __('Save') }}
                </button>

                <x-action-message class="me-3" on="password-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <section class="border-hairline mt-12 border-t pt-8">
        <div class="border-hairline mb-5 border-b pb-3.5">
            <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Browser sessions') }}</h2>
            <p class="meta-classical mt-1.5 normal-case tracking-normal">{{ __('If you suspect your account has been compromised, log out of every other browser and device you may have signed in from.') }}</p>
        </div>

            <form method="POST" wire:submit="logoutOtherBrowserSessions" class="mt-6 space-y-6">
            <label class="block">
                <span class="meta-classical mb-1.5 block">{{ __('Current password') }}</span>
                <input type="password" wire:model="logout_other_password" required autocomplete="current-password" class="field-classical w-full px-3.5" />
                @error('logout_other_password') <p class="mt-1.5 text-xs text-amber-400">{{ $message }}</p> @enderror
            </label>

                <div class="flex items-center gap-4">
                    <button type="submit" class="btn-classical btn-classical-muted" data-test="logout-other-sessions-button">
                        {{ __('Log out other browser sessions') }}
                    </button>

                    <x-action-message class="me-3" on="other-sessions-revoked">
                        {{ __('Done.') }}
                    </x-action-message>
                </div>
            </form>
        </section>

        @if ($canManageTwoFactor)
            <section class="border-hairline mt-12 border-t pt-8">
        <div class="border-hairline mb-5 border-b pb-3.5">
            <h2 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('Two-factor authentication') }}</h2>
            <p class="meta-classical mt-1.5 normal-case tracking-normal">{{ __('Manage your two-factor authentication settings') }}</p>
        </div>

                <div class="mx-auto flex w-full flex-col space-y-6 text-sm" wire:cloak>
                    @if ($twoFactorEnabled)
                        <div class="space-y-4">
                            <p class="text-ink-muted text-sm leading-[1.65]">
                                {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                            </p>

                            <div class="flex justify-start">
                                <button type="button" class="btn-classical btn-classical-muted" wire:click="disable">
                                    {{ __('Disable 2FA') }}
                                </button>
                            </div>

                            <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                        </div>
                    @else
                        <div class="space-y-4">
                            <p class="text-ink-muted text-sm leading-[1.65]">
                                {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                            </p>

                            <flux:modal.trigger name="two-factor-setup-modal">
                                <flux:button variant="ghost" class="btn-classical btn-amber-outline" wire:click="$dispatch('start-two-factor-setup')">
                                    {{ __('Enable 2FA') }}
                                </flux:button>
                            </flux:modal.trigger>

                            <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                        </div>
                    @endif
                </div>
            </section>
        @endif
    </x-pages::settings.layout>
</section>
