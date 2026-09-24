@php($passkeysEnabled = Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::passkeys()) && auth()->user()?->hasPasskeysEnabled())
<x-layouts::auth :title="__('Confirm password')">
    <div class="flex flex-col gap-6" @if($passkeysEnabled) x-data="passkeyConfirm" @endif>
        <x-auth-header
            :title="__('Confirm password')"
            :description="__('This is a secure area of the application. Please confirm your password before continuing.')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        @if($passkeysEnabled)
            <template x-if="supported">
                <div class="flex flex-col gap-3">
                    <flux:button variant="ghost" class="w-full" icon="finger-print" x-on:click="confirm()" x-bind:disabled="loading" data-test="passkey-confirm-button">
                        <span x-show="!loading">{{ __('Confirm with a passkey') }}</span>
                        <span x-show="loading" x-cloak>{{ __('Waiting for your passkey…') }}</span>
                    </flux:button>
                    <p x-show="error" x-cloak x-text="error" class="text-center text-sm text-red-600 dark:text-red-400" role="alert"></p>

                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="border-line w-full border-t"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="bg-white px-2 text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">{{ __('or') }}</span>
                        </div>
                    </div>
                </div>
            </template>
        @endif

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Password')"
                viewable
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="confirm-password-button">
                {{ __('Confirm') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
