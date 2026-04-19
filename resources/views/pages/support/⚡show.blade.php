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
        return $this->ticket->responses()->with('user:id,name')->oldest()->get();
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
    <div class="flex items-center gap-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('support.index')" wire:navigate />
        <flux:heading size="xl" class="truncate">{{ $ticket->subject }}</flux:heading>
    </div>

    {{-- Status badges --}}
    <div class="flex flex-wrap items-center gap-2">
        <flux:badge size="sm" color="zinc">#{{ $ticket->id }}</flux:badge>
        <flux:badge
            size="sm"
            :color="match($ticket->status) {
                'open' => 'zinc',
                'in_progress' => 'amber',
                'resolved' => 'emerald',
                'closed' => 'zinc',
                default => 'zinc',
            }"
        >
            {{ match($ticket->status) {
                'in_progress' => __('In Progress'),
                default => __(ucfirst($ticket->status)),
            } }}
        </flux:badge>
        <flux:badge
            size="sm"
            :color="match($ticket->priority) {
                'low' => 'zinc',
                'normal' => 'blue',
                'high' => 'amber',
                'urgent' => 'red',
                default => 'zinc',
            }"
        >
            {{ __(ucfirst($ticket->priority)) }} {{ __('Priority') }}
        </flux:badge>
        <flux:badge
            size="sm"
            :color="match($ticket->category) {
                'bug_report' => 'red',
                'feature_request' => 'blue',
                'account_issue' => 'amber',
                'puzzle_issue' => 'violet',
                default => 'zinc',
            }"
        >
            {{ match($ticket->category) {
                'bug_report' => __('Bug Report'),
                'feature_request' => __('Feature Request'),
                'account_issue' => __('Account Issue'),
                'puzzle_issue' => __('Puzzle Issue'),
                default => __('General'),
            } }}
        </flux:badge>
        <flux:text size="sm">&middot; {{ $ticket->created_at->diffForHumans() }}</flux:text>
    </div>

    {{-- Description --}}
    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:text size="sm" class="mb-2 font-medium text-zinc-500">{{ __('Description') }}</flux:text>
        <flux:text class="whitespace-pre-wrap">{{ $ticket->description }}</flux:text>
    </div>

    @if($ticket->assignee)
        <flux:text size="sm">
            {{ __('Assigned to:') }} <span class="font-medium">{{ $ticket->assignee->name }}</span>
        </flux:text>
    @endif

    {{-- Responses --}}
    <div class="space-y-4">
        <div class="flex items-center gap-2">
            <flux:heading size="lg">{{ __('Responses') }}</flux:heading>
            <flux:badge size="sm" color="zinc">{{ $this->responses->count() }}</flux:badge>
        </div>

        @if($this->responses->isEmpty())
            <flux:text class="text-zinc-400">{{ __('No responses yet. Our team will review your ticket soon.') }}</flux:text>
        @else
            <div class="space-y-3">
                @foreach($this->responses as $response)
                    <div class="rounded-xl border p-4 {{ $response->is_admin_response ? 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/30' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <div class="mb-2 flex items-center gap-2">
                            <flux:text size="sm" class="font-medium">{{ $response->user->name }}</flux:text>
                            @if($response->is_admin_response)
                                <flux:badge size="sm" color="blue" variant="pill">{{ __('Staff') }}</flux:badge>
                            @endif
                            <flux:text size="sm" class="text-zinc-400">&middot; {{ $response->created_at->diffForHumans() }}</flux:text>
                        </div>
                        <flux:text class="whitespace-pre-wrap">{{ $response->body }}</flux:text>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Add response form --}}
    @if($ticket->status !== 'closed')
        <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="sm">{{ __('Add a Response') }}</flux:heading>
            <flux:field>
                <flux:textarea wire:model="responseBody" rows="3" placeholder="{{ __('Type your response...') }}" />
                <flux:error name="responseBody" />
            </flux:field>
            <div class="flex justify-end">
                <flux:button variant="primary" wire:click="addResponse">{{ __('Send Response') }}</flux:button>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-zinc-200 p-4 text-center dark:border-zinc-700">
            <flux:text class="text-zinc-400">{{ __('This ticket has been closed.') }}</flux:text>
        </div>
    @endif
</div>
