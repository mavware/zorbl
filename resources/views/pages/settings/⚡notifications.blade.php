<?php

use App\Enums\NotificationType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notification preferences')] class extends Component {
    /** @var array<string, bool> */
    public array $preferences = [];

    private const EMAIL_ELIGIBLE_TYPES = [
        NotificationType::NewPuzzlePublished,
    ];

    public function mount(): void
    {
        $saved = Auth::user()->notification_preferences ?? [];

        foreach (NotificationType::cases() as $type) {
            $this->preferences[$type->value] = $saved[$type->value] ?? true;
        }

        foreach (self::EMAIL_ELIGIBLE_TYPES as $type) {
            $this->preferences[$type->value.'_email'] = $saved[$type->value.'_email'] ?? false;
        }
    }

    public function toggle(string $key): void
    {
        $isEmailToggle = str_ends_with($key, '_email');
        $baseKey = $isEmailToggle ? substr($key, 0, -6) : $key;

        $type = NotificationType::tryFrom($baseKey);

        if (! $type) {
            return;
        }

        if ($isEmailToggle && ! in_array($type, self::EMAIL_ELIGIBLE_TYPES, true)) {
            return;
        }

        $this->preferences[$key] = ! $this->preferences[$key];

        Auth::user()->update([
            'notification_preferences' => $this->preferences,
        ]);

        $this->dispatch('notification-preferences-updated');
    }

    public function supportsEmail(NotificationType $type): bool
    {
        return in_array($type, self::EMAIL_ELIGIBLE_TYPES, true);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Notifications')" :subheading="__('Choose which notifications you receive')">
        <div class="my-6 space-y-4">
            @foreach(App\Enums\NotificationType::cases() as $type)
                <div
                    wire:key="pref-{{ $type->value }}"
                    class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:text class="font-medium">{{ $type->label() }}</flux:text>
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $type->description() }}</flux:text>
                        </div>
                        <flux:switch
                            wire:click="toggle('{{ $type->value }}')"
                            :checked="$preferences[$type->value] ?? true"
                        />
                    </div>

                    @if($this->supportsEmail($type) && ($preferences[$type->value] ?? true))
                        <div class="mt-3 flex items-center justify-between border-t border-zinc-100 pt-3 pl-4 dark:border-zinc-700/50">
                            <div>
                                <flux:text size="sm" class="font-medium">{{ __('Email notifications') }}</flux:text>
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Also receive an email when this happens') }}</flux:text>
                            </div>
                            <flux:switch
                                wire:click="toggle('{{ $type->value }}_email')"
                                :checked="$preferences[$type->value.'_email'] ?? false"
                            />
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <x-action-message on="notification-preferences-updated">
            {{ __('Saved.') }}
        </x-action-message>
    </x-pages::settings.layout>
</section>
