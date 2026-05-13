<?php

use App\Models\ContentReport;
use App\Models\Crossword;
use App\Models\PuzzleComment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    /** One of the keys in ContentReport::REPORTABLE_TYPES (puzzle/comment/profile). */
    #[Locked]
    public string $type = '';

    #[Locked]
    public int $reportableId = 0;

    public string $reason = '';

    public string $details = '';

    public bool $showModal = false;

    public bool $submitted = false;

    public function mount(string $type, int $reportableId): void
    {
        if (! in_array($type, ContentReport::REPORTABLE_TYPES, true)) {
            abort(400, 'Unknown reportable type.');
        }

        $this->type = $type;
        $this->reportableId = $reportableId;
    }

    #[Computed]
    public function reportableClass(): string
    {
        return (string) array_search($this->type, ContentReport::REPORTABLE_TYPES, true);
    }

    #[Computed]
    public function alreadyReported(): bool
    {
        $userId = Auth::id();
        if ($userId === null) {
            return false;
        }

        return ContentReport::query()
            ->where('reporter_id', $userId)
            ->where('reportable_type', $this->reportableClass)
            ->where('reportable_id', $this->reportableId)
            ->exists();
    }

    public function open(): void
    {
        if (! Auth::check()) {
            $this->redirect(route('login'));

            return;
        }
        $this->resetValidation();
        $this->reason = '';
        $this->details = '';
        $this->submitted = false;
        $this->showModal = true;
    }

    public function submit(): void
    {
        if (! Auth::check()) {
            $this->redirect(route('login'));

            return;
        }

        $reasonKeys = array_keys(ContentReport::REASONS);

        $this->validate([
            'reason' => ['required', 'in:'.implode(',', $reasonKeys)],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $class = $this->reportableClass;
        $exists = $class::query()->whereKey($this->reportableId)->exists();
        if (! $exists) {
            abort(404);
        }

        ContentReport::query()->firstOrCreate(
            [
                'reporter_id' => Auth::id(),
                'reportable_type' => $class,
                'reportable_id' => $this->reportableId,
            ],
            [
                'reason' => $this->reason,
                'details' => $this->details !== '' ? $this->details : null,
                'status' => ContentReport::STATUS_PENDING,
            ],
        );

        unset($this->alreadyReported);
        $this->submitted = true;
        $this->dispatch('content-report-submitted');
    }

    public function close(): void
    {
        $this->showModal = false;
    }
}
?>

<div>
    @if ($this->alreadyReported && ! $submitted)
        <flux:tooltip :content="__('You\'ve already reported this.')">
            <flux:button variant="ghost" size="sm" icon="flag" disabled>
                {{ __('Reported') }}
            </flux:button>
        </flux:tooltip>
    @else
        <flux:button variant="ghost" size="sm" icon="flag" wire:click="open">
            {{ __('Report') }}
        </flux:button>
    @endif

    <flux:modal wire:model.self="showModal" class="max-w-md">
        @if ($submitted)
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Report received') }}</flux:heading>
                <flux:subheading>
                    {{ __('Thanks — our moderation team will review it and take action if necessary.') }}
                </flux:subheading>
                <div class="flex justify-end">
                    <flux:button wire:click="close">{{ __('Close') }}</flux:button>
                </div>
            </div>
        @else
            <form wire:submit="submit" class="space-y-4">
                <flux:heading size="lg">{{ __('Report this :type', ['type' => $type]) }}</flux:heading>
                <flux:subheading>
                    {{ __('Tell us what\'s wrong. Reports are anonymous to the user being reported.') }}
                </flux:subheading>

                <flux:field>
                    <flux:label>{{ __('Reason') }}</flux:label>
                    <flux:select wire:model="reason" placeholder="{{ __('Choose a reason…') }}">
                        @foreach (\App\Models\ContentReport::REASONS as $key => $label)
                            <option value="{{ $key }}">{{ __($label) }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="reason" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Details (optional)') }}</flux:label>
                    <flux:textarea
                        wire:model="details"
                        rows="3"
                        :placeholder="__('Add any helpful context for the moderation team…')"
                        maxlength="2000"
                    />
                    <flux:error name="details" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="close">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary" data-test="submit-report">
                        {{ __('Submit report') }}
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
