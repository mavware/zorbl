<?php

use App\Enums\NotificationType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notification preferences')] class extends Component {
    /** @var array<string, bool> */
    public array $preferences = [];

    public function mount(): void
    {
        $saved = Auth::user()->notification_preferences ?? [];

        foreach (NotificationType::cases() as $type) {
            $this->preferences[$type->value] = $saved[$type->value] ?? true;
        }
    }

    public function toggle(string $key): void
    {
        $type = NotificationType::tryFrom($key);

        if (! $type) {
            return;
        }

        $this->preferences[$key] = ! $this->preferences[$key];

        Auth::user()->update([
            'notification_preferences' => $this->preferences,
        ]);

        $this->dispatch('notification-preferences-updated');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Notifications')" :subheading="__('Choose which notifications you receive')">
        <div class="my-6 space-y-4">
            @foreach(App\Enums\NotificationType::cases() as $type)
                <div
                    wire:key="pref-{{ $type->value }}"
                    class="flex items-center justify-between rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700"
                >
                    <div>
                        <flux:text class="font-medium">{{ $type->label() }}</flux:text>
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $type->description() }}</flux:text>
                    </div>
                    <flux:switch
                        wire:click="toggle('{{ $type->value }}')"
                        :checked="$preferences[$type->value] ?? true"
                    />
                </div>
            @endforeach
        </div>

        <x-action-message on="notification-preferences-updated">
            {{ __('Saved.') }}
        </x-action-message>
    </x-pages::settings.layout>
</section>
