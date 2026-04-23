<?php

use App\Models\SupportTicket;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Support Tickets')] class extends Component {
    use WithPagination;

    #[Url]
    public string $statusFilter = 'all';

    #[Computed]
    public function tickets()
    {
        $query = Auth::user()->supportTickets()->latest();

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $query->paginate(15);
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }
}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Support Tickets') }}</flux:heading>

        <flux:button variant="primary" icon="plus" :href="route('support.create')" wire:navigate>
            {{ __('New Ticket') }}
        </flux:button>
    </div>

    <div class="flex items-center gap-4">
        <flux:select wire:model.live="statusFilter" class="w-48">
            <option value="all">{{ __('All Statuses') }}</option>
            <option value="open">{{ __('Open') }}</option>
            <option value="in_progress">{{ __('In Progress') }}</option>
            <option value="resolved">{{ __('Resolved') }}</option>
            <option value="closed">{{ __('Closed') }}</option>
        </flux:select>
    </div>

    @if($this->tickets->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-400 py-16 dark:border-zinc-600">
            <flux:icon name="chat-bubble-left-right" class="mb-4 size-12 text-zinc-500" />
            <flux:heading size="lg" class="mb-2">{{ __('No tickets yet') }}</flux:heading>
            <flux:text class="mb-6">{{ __('Need help? Submit a support ticket and our team will assist you.') }}</flux:text>
            <flux:button variant="primary" icon="plus" :href="route('support.create')" wire:navigate>
                {{ __('New Ticket') }}
            </flux:button>
        </div>
    @else
        <div class="space-y-3">
            @foreach($this->tickets as $ticket)
                <a
                    href="{{ route('support.show', $ticket) }}"
                    wire:navigate
                    wire:key="ticket-{{ $ticket->id }}"
                    class="block rounded-xl border border-zinc-300 p-4 transition-colors hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <flux:heading size="sm" class="truncate">{{ $ticket->subject }}</flux:heading>
                            <flux:text size="sm" class="mt-1">
                                #{{ $ticket->id }} &middot; {{ $ticket->created_at->diffForHumans() }}
                            </flux:text>
                        </div>
                        <div class="flex flex-shrink-0 items-center gap-2">
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
                            @if($ticket->priority !== 'normal')
                                <flux:badge
                                    size="sm"
                                    :color="match($ticket->priority) {
                                        'low' => 'zinc',
                                        'high' => 'amber',
                                        'urgent' => 'red',
                                        default => 'zinc',
                                    }"
                                >
                                    {{ __(ucfirst($ticket->priority)) }}
                                </flux:badge>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $this->tickets->links() }}
        </div>
    @endif
</div>
