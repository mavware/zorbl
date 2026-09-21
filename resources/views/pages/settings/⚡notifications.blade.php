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
        <div class="border-border divide-hairline my-6 divide-y rounded-sm border">
            @foreach(App\Enums\NotificationType::cases() as $type)
                <div wire:key="pref-{{ $type->value }}" class="px-[18px] py-3.5">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-ink text-sm font-medium">{{ $type->label() }}</div>
                            <div class="text-ink-muted mt-0.5 text-sm">{{ $type->description() }}</div>
                        </div>
                        <x-switch-classical
                            wire:click="toggle('{{ $type->value }}')"
                            :checked="$preferences[$type->value] ?? true"
                            aria-label="{{ $type->label() }}"
                        />
                    </div>

                    @if($this->supportsEmail($type) && ($preferences[$type->value] ?? true))
                        <div class="border-hairline mt-3 flex items-center justify-between gap-4 border-t pt-3 pl-4">
                            <div class="min-w-0">
                                <div class="text-ink text-sm font-medium">{{ __('Email notifications') }}</div>
                                <div class="text-ink-muted mt-0.5 text-sm">{{ __('Also receive an email when this happens') }}</div>
                            </div>
                            <x-switch-classical
                                wire:click="toggle('{{ $type->value }}_email')"
                                :checked="$preferences[$type->value.'_email'] ?? false"
                                aria-label="{{ __('Email notifications') }}"
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
