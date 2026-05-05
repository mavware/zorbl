<?php

use App\Enums\WebhookEvent;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Webhook settings')] class extends Component {
    public bool $showCreateModal = false;
    public bool $showDeliveriesModal = false;

    #[Validate('required|url|max:2048')]
    public string $url = '';

    #[Validate('nullable|string|max:255')]
    public ?string $description = '';

    /** @var array<int, string> */
    #[Validate('required|array|min:1')]
    public array $events = [];

    public ?int $viewingEndpointId = null;

    public function createEndpoint(): void
    {
        $this->validate();

        Auth::user()->webhookEndpoints()->create([
            'url' => $this->url,
            'description' => $this->description ?: null,
            'secret' => Str::random(32),
            'events' => $this->events,
            'is_active' => true,
        ]);

        $this->reset('url', 'description', 'events', 'showCreateModal');
    }

    public function toggleEndpoint(int $endpointId): void
    {
        $endpoint = Auth::user()->webhookEndpoints()->findOrFail($endpointId);
        $endpoint->update(['is_active' => ! $endpoint->is_active]);
    }

    public function deleteEndpoint(int $endpointId): void
    {
        Auth::user()->webhookEndpoints()->findOrFail($endpointId)->delete();

        if ($this->viewingEndpointId === $endpointId) {
            $this->viewingEndpointId = null;
            $this->showDeliveriesModal = false;
        }
    }

    public function viewDeliveries(int $endpointId): void
    {
        $this->viewingEndpointId = $endpointId;
        $this->showDeliveriesModal = true;
    }

    #[Computed]
    public function endpoints()
    {
        return Auth::user()->webhookEndpoints()->withCount('deliveries')->latest()->get();
    }

    #[Computed]
    public function recentDeliveries()
    {
        if (! $this->viewingEndpointId) {
            return collect();
        }

        return WebhookEndpoint::query()
            ->where('user_id', Auth::id())
            ->findOrFail($this->viewingEndpointId)
            ->deliveries()
            ->latest()
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function availableEvents(): array
    {
        return WebhookEvent::labels();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Webhooks')" :subheading="__('Receive HTTP callbacks when events happen on your puzzles')">
        <div class="my-6 space-y-6">
            <div class="flex items-center justify-between">
                <flux:text>{{ __('Your webhook endpoints') }}</flux:text>
                <flux:button variant="primary" size="sm" icon="plus" wire:click="$set('showCreateModal', true)">
                    {{ __('Add endpoint') }}
                </flux:button>
            </div>

            @if ($this->endpoints->isEmpty())
                <flux:callout>
                    <x-slot:heading>{{ __('No webhooks configured') }}</x-slot:heading>
                    {{ __('Add a webhook endpoint to receive notifications when events happen on your puzzles.') }}
                </flux:callout>
            @else
                <div class="space-y-3">
                    @foreach ($this->endpoints as $endpoint)
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:text class="truncate font-mono text-sm">{{ $endpoint->url }}</flux:text>
                                        @if ($endpoint->is_active)
                                            <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                                        @endif
                                    </div>
                                    @if ($endpoint->description)
                                        <flux:text class="mt-1 text-xs text-zinc-500">{{ $endpoint->description }}</flux:text>
                                    @endif
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach ($endpoint->events as $event)
                                            <flux:badge size="sm">{{ App\Enums\WebhookEvent::tryFrom($event)?->label() ?? $event }}</flux:badge>
                                        @endforeach
                                    </div>
                                    @if ($endpoint->last_triggered_at)
                                        <flux:text class="mt-1 text-xs text-zinc-400">
                                            {{ __('Last triggered :time', ['time' => $endpoint->last_triggered_at->diffForHumans()]) }}
                                        </flux:text>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1">
                                    <flux:button size="sm" variant="subtle" icon="eye" wire:click="viewDeliveries({{ $endpoint->id }})" />
                                    <flux:button size="sm" variant="subtle" icon="{{ $endpoint->is_active ? 'pause' : 'play' }}" wire:click="toggleEndpoint({{ $endpoint->id }})" />
                                    <flux:button size="sm" variant="subtle" icon="trash" wire:click="deleteEndpoint({{ $endpoint->id }})" wire:confirm="{{ __('Are you sure you want to delete this webhook endpoint?') }}" />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Create Endpoint Modal --}}
        <flux:modal wire:model="showCreateModal">
            <form wire:submit="createEndpoint" class="space-y-4">
                <flux:heading>{{ __('Add webhook endpoint') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('URL') }}</flux:label>
                    <flux:input wire:model="url" type="url" placeholder="https://example.com/webhook" required />
                    <flux:error name="url" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:input wire:model="description" type="text" :placeholder="__('Optional description')" />
                    <flux:error name="description" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Events') }}</flux:label>
                    <div class="space-y-2">
                        @foreach ($this->availableEvents as $value => $label)
                            <flux:checkbox wire:model="events" :value="$value" :label="$label" />
                        @endforeach
                    </div>
                    <flux:error name="events" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- Deliveries Modal --}}
        <flux:modal wire:model="showDeliveriesModal">
            <flux:heading>{{ __('Recent deliveries') }}</flux:heading>

            @if ($this->recentDeliveries->isEmpty())
                <flux:text class="mt-4">{{ __('No deliveries yet for this endpoint.') }}</flux:text>
            @else
                <div class="mt-4 max-h-96 space-y-2 overflow-y-auto">
                    @foreach ($this->recentDeliveries as $delivery)
                        <div class="rounded border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm">{{ $delivery->event }}</flux:badge>
                                    @if ($delivery->success)
                                        <flux:badge color="green" size="sm">{{ $delivery->response_code }}</flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm">{{ $delivery->response_code ?? __('Failed') }}</flux:badge>
                                    @endif
                                </div>
                                <flux:text class="text-xs text-zinc-400">{{ $delivery->created_at->diffForHumans() }}</flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-4 flex justify-end">
                <flux:button variant="ghost" wire:click="$set('showDeliveriesModal', false)">{{ __('Close') }}</flux:button>
            </div>
        </flux:modal>
    </x-pages::settings.layout>
</section>
