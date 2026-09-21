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
            <div class="flex items-center justify-between gap-3">
                <p class="text-ink text-sm">{{ __('Your webhook endpoints') }}</p>
                <button type="button" class="btn-classical btn-amber-outline" wire:click="$set('showCreateModal', true)">
                    <flux:icon name="plus" class="size-4" />
                    {{ __('Add endpoint') }}
                </button>
            </div>

            @if ($this->endpoints->isEmpty())
                <div class="border-border-strong flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-10 text-center">
                    <h3 class="font-classical text-ink text-[22px] leading-tight font-medium">{{ __('No webhooks configured') }}</h3>
                    <p class="text-ink-muted mt-2 text-sm">{{ __('Add a webhook endpoint to receive notifications when events happen on your puzzles.') }}</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($this->endpoints as $endpoint)
                        <div class="border-border rounded-sm border p-[18px]">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-ink truncate font-mono text-sm">{{ $endpoint->url }}</span>
                                        @if ($endpoint->is_active)
                                            <span class="chip-classical border-amber-400 text-amber-400">{{ __('Active') }}</span>
                                        @else
                                            <span class="chip-classical border-ink-faint text-ink-faint">{{ __('Inactive') }}</span>
                                        @endif
                                    </div>
                                    @if ($endpoint->description)
                                        <p class="text-ink-muted mt-1 text-sm">{{ $endpoint->description }}</p>
                                    @endif
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($endpoint->events as $event)
                                            <span class="chip-classical border-ink-faint text-ink-faint">{{ App\Enums\WebhookEvent::tryFrom($event)?->label() ?? $event }}</span>
                                        @endforeach
                                    </div>
                                    @if ($endpoint->last_triggered_at)
                                        <p class="meta-classical mt-2">
                                            {{ __('Last triggered :time', ['time' => $endpoint->last_triggered_at->diffForHumans()]) }}
                                        </p>
                                    @endif
                                </div>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <button type="button" class="btn-classical btn-classical-muted h-8 w-8 px-0" wire:click="viewDeliveries({{ $endpoint->id }})" aria-label="{{ __('Recent deliveries') }}">
                                        <flux:icon name="eye" class="size-4" />
                                    </button>
                                    <button type="button" class="btn-classical btn-classical-muted h-8 w-8 px-0" wire:click="toggleEndpoint({{ $endpoint->id }})" aria-label="{{ $endpoint->is_active ? __('Pause') : __('Resume') }}">
                                        <flux:icon :name="$endpoint->is_active ? 'pause' : 'play'" class="size-4" />
                                    </button>
                                    <button type="button" class="btn-classical btn-classical-muted h-8 w-8 px-0" wire:click="deleteEndpoint({{ $endpoint->id }})" wire:confirm="{{ __('Are you sure you want to delete this webhook endpoint?') }}" aria-label="{{ __('Delete') }}">
                                        <flux:icon name="trash" class="size-4" />
                                    </button>
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
