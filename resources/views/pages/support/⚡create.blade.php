<?php

use App\Models\SupportTicket;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Submit a Ticket')] class extends Component {
    public string $subject = '';
    public string $description = '';
    public string $category = 'general';

    public function submit(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'category' => ['required', 'in:bug_report,feature_request,account_issue,puzzle_issue,general'],
        ]);

        $ticket = Auth::user()->supportTickets()->create([
            'subject' => $this->subject,
            'description' => $this->description,
            'category' => $this->category,
        ]);

        $this->redirect(route('support.show', $ticket), navigate: true);
    }
}
?>

<div class="space-y-6">
    <div class="flex items-center gap-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('support.index')" wire:navigate />
        <flux:heading size="xl">{{ __('Submit a Support Ticket') }}</flux:heading>
    </div>

    <div class="mx-auto max-w-2xl space-y-6">
        <flux:field>
            <flux:label>{{ __('Subject') }}</flux:label>
            <flux:input wire:model="subject" placeholder="{{ __('Brief summary of your issue') }}" />
            <flux:error name="subject" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Category') }}</flux:label>
            <flux:select wire:model="category">
                <option value="general">{{ __('General') }}</option>
                <option value="bug_report">{{ __('Bug Report') }}</option>
                <option value="feature_request">{{ __('Feature Request') }}</option>
                <option value="account_issue">{{ __('Account Issue') }}</option>
                <option value="puzzle_issue">{{ __('Puzzle Issue') }}</option>
            </flux:select>
            <flux:error name="category" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Description') }}</flux:label>
            <flux:description>{{ __('Please provide as much detail as possible so we can help you effectively.') }}</flux:description>
            <flux:textarea wire:model="description" rows="5" placeholder="{{ __('Describe your issue in detail...') }}" />
            <flux:error name="description" />
        </flux:field>

        <div class="flex justify-end gap-2">
            <flux:button :href="route('support.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="submit">{{ __('Submit Ticket') }}</flux:button>
        </div>
    </div>
</div>
