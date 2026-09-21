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
    <x-page-header :kicker="__('Help')" :title="__('Support Tickets')">
        <x-header-button icon="plus" :href="route('support.create')" wire:navigate>
            {{ __('New Ticket') }}
        </x-header-button>
    </x-page-header>

    <div class="flex items-center gap-4">
        <label class="relative w-52">
            <span class="sr-only">{{ __('Status') }}</span>
            <select wire:model.live="statusFilter" class="field-classical font-classical w-full appearance-none pr-9 pl-3.5 text-[15px] font-medium">
                <option value="all">{{ __('All Statuses') }}</option>
                <option value="open">{{ __('Open') }}</option>
                <option value="in_progress">{{ __('In Progress') }}</option>
                <option value="resolved">{{ __('Resolved') }}</option>
                <option value="closed">{{ __('Closed') }}</option>
            </select>
            <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
        </label>
    </div>

    @if($this->tickets->isEmpty())
        <div class="border-border-strong flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-16 text-center">
            <flux:icon name="chat-bubble-left-right" class="text-ink-faint mb-4 size-10" />
            <h3 class="font-classical text-ink text-[26px] leading-tight font-medium">{{ __('No tickets yet') }}</h3>
            <p class="text-ink-muted mt-2 mb-6 text-sm">{{ __('Need help? Submit a support ticket and our team will assist you.') }}</p>
            <a href="{{ route('support.create') }}" wire:navigate class="btn-classical btn-amber-outline">
                <flux:icon name="plus" class="size-4" />
                {{ __('New Ticket') }}
            </a>
        </div>
    @else
        <div class="border-border divide-hairline divide-y rounded-sm border">
            @foreach($this->tickets as $ticket)
                <a
                    href="{{ route('support.show', $ticket) }}"
                    wire:navigate
                    wire:key="ticket-{{ $ticket->id }}"
                    class="group flex flex-wrap items-center justify-between gap-3 px-[18px] py-3.5 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-amber-400"
                >
                    <div class="min-w-0 flex-1">
                        <div class="font-classical text-ink group-hover:text-amber-300 truncate text-[18px] leading-tight font-semibold transition-colors">{{ $ticket->subject }}</div>
                        <div class="meta-classical mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <span class="tnum whitespace-nowrap">#{{ $ticket->id }}</span>
                            <span aria-hidden="true">&middot;</span>
                            <span class="whitespace-nowrap">{{ $ticket->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-1.5">
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
                        <span class="chip-classical {{ match($ticket->status) { 'in_progress', 'resolved' => 'border-amber-400 text-amber-400', default => 'border-ink-faint text-ink-faint' } }}">
                            {{ match($ticket->status) {
                                'in_progress' => __('In Progress'),
                                default => __(ucfirst($ticket->status)),
                            } }}
                        </span>
                        @if($ticket->priority !== 'normal')
                            <span class="chip-classical {{ match($ticket->priority) { 'high', 'urgent' => 'border-amber-400 text-amber-400', default => 'border-ink-faint text-ink-faint' } }}">
                                {{ __(ucfirst($ticket->priority)) }}
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $this->tickets->links() }}
        </div>
    @endif
</div>
