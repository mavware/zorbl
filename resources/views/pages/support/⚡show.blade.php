<?php

use App\Models\SupportTicket;
use App\Models\TicketResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ticket Detail')] class extends Component {
    public SupportTicket $ticket;

    public string $responseBody = '';

    public function mount(): void
    {
        Gate::authorize('view', $this->ticket);
    }

    #[Computed]
    public function responses()
    {
        return $this->ticket->responses()->with(['user:id,name', 'user.subscriptions'])->oldest()->get();
    }

    public function addResponse(): void
    {
        Gate::authorize('respond', $this->ticket);

        $this->validate([
            'responseBody' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $this->ticket->responses()->create([
            'user_id' => Auth::id(),
            'body' => $this->responseBody,
            'is_admin_response' => false,
        ]);

        $this->reset('responseBody');
        unset($this->responses);
    }
}
?>

<div class="space-y-6">
    <x-page-header :kicker="__('Support ticket')" :title="$ticket->subject">
        <x-header-button variant="secondary" icon="arrow-left" :href="route('support.index')" wire:navigate aria-label="{{ __('Support Tickets') }}" />
    </x-page-header>

    {{-- Status badges --}}
    <div class="flex flex-wrap items-center gap-2">
        <span class="chip-classical border-ink-faint text-ink-faint tnum">#{{ $ticket->id }}</span>
        <span class="chip-classical {{ match($ticket->status) { 'in_progress', 'resolved' => 'border-amber-400 text-amber-400', default => 'border-ink-faint text-ink-faint' } }}">
            {{ match($ticket->status) {
                'in_progress' => __('In Progress'),
                default => __(ucfirst($ticket->status)),
            } }}
        </span>
        <span class="chip-classical {{ match($ticket->priority) { 'high', 'urgent' => 'border-amber-400 text-amber-400', default => 'border-ink-faint text-ink-faint' } }}">
            {{ __(ucfirst($ticket->priority)) }} {{ __('Priority') }}
        </span>
        <span class="chip-classical border-ink-faint text-ink-faint">
            {{ match($ticket->category) {
                'bug_report' => __('Bug Report'),
                'feature_request' => __('Feature Request'),
                'account_issue' => __('Account Issue'),
                'puzzle_issue' => __('Puzzle Issue'),
                'copyright' => __('Copyright (DMCA)'),
                default => __('General'),
            } }}
        </span>
        <span class="meta-classical">&middot; {{ $ticket->created_at->diffForHumans() }}</span>
    </div>

    {{-- Description --}}
    <div class="border-border rounded-sm border p-[18px]">
        <div class="meta-classical mb-2">{{ __('Description') }}</div>
        <p class="text-ink text-sm leading-[1.65] whitespace-pre-wrap">{{ $ticket->description }}</p>
    </div>

    @if($ticket->assignee)
        <p class="meta-classical">
            {{ __('Assigned to:') }} <span class="text-ink">{{ $ticket->assignee->name }}</span>
        </p>
    @endif

    {{-- Responses --}}
    <div class="space-y-4">
        <div class="border-hairline flex items-center gap-2.5 border-b pb-3.5">
            <h2 class="font-classical text-ink text-[26px] leading-tight font-medium">{{ __('Responses') }}</h2>
            <span class="chip-classical border-ink-faint text-ink-faint tnum">{{ $this->responses->count() }}</span>
        </div>

        @if($this->responses->isEmpty())
            <p class="text-ink-muted text-sm">{{ __('No responses yet. Our team will review your ticket soon.') }}</p>
        @else
            <div class="space-y-3">
                @foreach($this->responses as $response)
                    <div class="rounded-sm border p-[18px] {{ $response->is_admin_response ? 'border-amber-400/60' : 'border-border' }}">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="font-classical text-ink text-[16px] leading-none font-semibold">{{ $response->user->name }}</span>
                            <x-supporter-badge :user="$response->user" />
                            @if($response->is_admin_response)
                                <span class="chip-classical border-amber-400 text-amber-400">{{ __('Staff') }}</span>
                            @endif
                            <span class="meta-classical">&middot; {{ $response->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-ink text-sm leading-[1.65] whitespace-pre-wrap">{{ $response->body }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Add response form --}}
    @if($ticket->status !== 'closed')
        <div class="border-border space-y-4 rounded-sm border p-[18px]">
            <h3 class="font-classical text-ink text-[19px] leading-tight font-semibold">{{ __('Add a Response') }}</h3>
            <label class="block">
                <span class="sr-only">{{ __('Add a Response') }}</span>
                <textarea wire:model="responseBody" rows="3" placeholder="{{ __('Type your response...') }}" class="field-classical h-auto w-full px-3.5 py-2.5 leading-[1.65]"></textarea>
                @error('responseBody') <p class="mt-1.5 text-xs text-amber-400">{{ $message }}</p> @enderror
            </label>
            <div class="flex justify-end">
                <button type="button" class="btn-classical btn-amber-outline" wire:click="addResponse">{{ __('Send Response') }}</button>
            </div>
        </div>
    @else
        <div class="border-border rounded-sm border p-[18px] text-center">
            <p class="text-ink-muted text-sm">{{ __('This ticket has been closed.') }}</p>
        </div>
    @endif
</div>
