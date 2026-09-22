<?php

use App\Models\SupportTicket;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Submit a Ticket')] class extends Component {
    public string $subject = '';
    public string $description = '';

    #[Url]
    public string $category = 'general';

    public function submit(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'category' => ['required', 'in:bug_report,feature_request,account_issue,puzzle_issue,copyright,general'],
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
    <x-page-header :kicker="__('Help')" :title="__('Submit a Support Ticket')">
        <x-header-button variant="secondary" icon="arrow-left" :href="route('support.index')" wire:navigate aria-label="{{ __('Support Tickets') }}" />
    </x-page-header>

    <div class="mx-auto max-w-2xl space-y-6">
        <div class="border-amber-400/60 flex items-start gap-3.5 rounded-sm border p-4.5">
            <div class="border-amber-400 flex size-9 shrink-0 items-center justify-center rounded-sm border text-amber-400">
                <flux:icon name="question-mark-circle" class="size-4" />
            </div>
            <div class="min-w-0">
                <div class="font-classical text-ink text-[19px] leading-tight font-semibold">{{ __('Try the Help Center first') }}</div>
                <p class="text-ink-muted mt-1.5 text-sm leading-[1.65]">
                    {{ __('Many common questions are answered in our guides — you may get a faster answer there.') }}
                    <a href="{{ route('help.index') }}" wire:navigate class="text-amber-400 hover:text-amber-300 underline underline-offset-4 transition-colors">{{ __('Browse the Help Center →') }}</a>
                </p>
            </div>
        </div>

        <label class="block">
            <span class="meta-classical mb-1.5 block">{{ __('Subject') }}</span>
            <input type="text" wire:model="subject" placeholder="{{ __('Brief summary of your issue') }}" class="field-classical w-full px-3.5" />
            @error('subject') <p class="mt-1.5 text-xs text-amber-400">{{ $message }}</p> @enderror
        </label>

        <label class="block">
            <span class="meta-classical mb-1.5 block">{{ __('Category') }}</span>
            <span class="relative block">
                <select wire:model="category" class="field-classical w-full appearance-none pr-9 pl-3.5">
                    <option value="general">{{ __('General') }}</option>
                    <option value="bug_report">{{ __('Bug Report') }}</option>
                    <option value="feature_request">{{ __('Feature Request') }}</option>
                    <option value="account_issue">{{ __('Account Issue') }}</option>
                    <option value="puzzle_issue">{{ __('Puzzle Issue') }}</option>
                    <option value="copyright">{{ __('Copyright (DMCA)') }}</option>
                </select>
                <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
            </span>
            @error('category') <p class="mt-1.5 text-xs text-amber-400">{{ $message }}</p> @enderror
        </label>

        <label class="block">
            <span class="meta-classical mb-1.5 block">{{ __('Description') }}</span>
            <span class="text-ink-muted mb-2 block text-sm">{{ __('Please provide as much detail as possible so we can help you effectively.') }}</span>
            <textarea wire:model="description" rows="5" placeholder="{{ __('Describe your issue in detail...') }}" class="field-classical h-auto w-full px-3.5 py-2.5 leading-[1.65]"></textarea>
            @error('description') <p class="mt-1.5 text-xs text-amber-400">{{ $message }}</p> @enderror
        </label>

        <div class="flex justify-end gap-3">
            <a href="{{ route('support.index') }}" wire:navigate class="btn-classical btn-classical-muted">{{ __('Cancel') }}</a>
            <button type="button" class="btn-classical btn-amber-outline" wire:click="submit">{{ __('Submit Ticket') }}</button>
        </div>
    </div>
</div>
