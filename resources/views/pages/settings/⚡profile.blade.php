<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $copyrightName = '';
    public string $bio = '';
    public string $email = '';
    public bool $safeSearchEnabled = true;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->copyrightName = Auth::user()->copyright_name ?? '';
        $this->bio = Auth::user()->bio ?? '';
        $this->email = Auth::user()->email;
        $this->safeSearchEnabled = (bool) Auth::user()->safe_search_enabled;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'copyright_name' => $validated['copyrightName'] ?: null,
            'bio' => $validated['bio'] ?: null,
            'safe_search_enabled' => $this->safeSearchEnabled,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <h2 class="sr-only">{{ __('Profile settings') }}</h2>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <label class="block">
                <span class="meta-classical mb-1.5 block">{{ __('Name') }}</span>
                <input type="text" wire:model="name" required autofocus autocomplete="name" class="field-classical w-full px-3.5" />
                @error('name') <p class="mt-1.5 text-xs text-amber-400">{{ $message }}</p> @enderror
            </label>

            <label class="block">
                <span class="meta-classical mb-1.5 block">{{ __('Copyright name') }}</span>
                <input type="text" wire:model="copyrightName" placeholder="{{ Auth::user()->name }}" class="field-classical w-full px-3.5" />
                <span class="text-ink-muted mt-1.5 block text-sm">{{ __('Used as the default copyright holder on your puzzles. Defaults to your name if blank.') }}</span>
                @error('copyrightName') <p class="mt-1.5 text-xs text-amber-400">{{ $message }}</p> @enderror
            </label>

            <label class="block">
                <span class="meta-classical mb-1.5 block">{{ __('Bio') }}</span>
                <textarea wire:model="bio" rows="3" placeholder="{{ __('Tell solvers a little about yourself…') }}" class="field-classical w-full px-3.5"></textarea>
                <span class="text-ink-muted mt-1.5 block text-sm">{{ __('Displayed on your public constructor profile. Max 500 characters.') }}</span>
                @error('bio') <p class="mt-1.5 text-xs text-amber-400">{{ $message }}</p> @enderror
            </label>

            <div>
                <label class="block">
                    <span class="meta-classical mb-1.5 block">{{ __('Email') }}</span>
                    <input type="email" wire:model="email" required autocomplete="email" class="field-classical w-full px-3.5" />
                    @error('email') <p class="mt-1.5 text-xs text-amber-400">{{ $message }}</p> @enderror
                </label>

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <p class="text-ink-muted mt-4 text-sm">
                            {{ __('Your email address is unverified.') }}

                            <button type="button" wire:click="resendVerificationNotification" class="text-amber-400 hover:text-amber-300 cursor-pointer underline underline-offset-4 transition-colors">
                                {{ __('Click here to re-send the verification email.') }}
                            </button>
                        </p>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 text-sm font-medium text-amber-400">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="safeSearchEnabled" data-test="safe-search-toggle" class="check-classical mt-0.5" />
                <span class="min-w-0">
                    <span class="text-ink block text-sm font-medium">{{ __('Safe Search') }}</span>
                    <span class="text-ink-muted mt-0.5 block text-sm">
                        {{ __('Hide puzzles whose title or clues contain profanity or strong language. Recommended for solvers of all ages.') }}
                    </span>
                    @error('safeSearchEnabled') <p class="mt-1.5 text-xs text-amber-400">{{ $message }}</p> @enderror
                </span>
            </label>

            <div class="flex items-center gap-4">
                <button type="submit" class="btn-classical btn-amber-outline btn-classical-primary" data-test="update-profile-button">
                    {{ __('Save') }}
                </button>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <livewire:pages::settings.blocked-tags />

        <livewire:pages::settings.data-export />

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
