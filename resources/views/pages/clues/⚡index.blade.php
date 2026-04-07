<?php

use App\Models\ClueEntry;
use App\Models\ClueReport;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Clue Library')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filter = 'all';

    public bool $showAddModal = false;
    public string $newAnswer = '';
    public string $newClue = '';
    public string $addError = '';

    public bool $showReportModal = false;
    public ?int $reportingClueId = null;
    public string $reportReason = '';
    public string $reportNotes = '';
    public string $reportError = '';

    public ?int $editingClueId = null;
    public string $editAnswer = '';
    public string $editClue = '';

    #[Computed]
    public function clues()
    {
        $query = ClueEntry::with(['user:id,name', 'crossword:id,title'])
            ->withCount('reports');

        if ($this->search !== '') {
            $term = $this->search;
            $query->where(function ($q) use ($term) {
                $q->whereLike('answer', '%'.mb_strtoupper($term).'%')
                    ->orWhereLike('clue', '%'.$term.'%');
            });
        }

        if ($this->filter === 'mine') {
            $query->where('user_id', Auth::id());
        } elseif ($this->filter === 'standalone') {
            $query->whereNull('crossword_id');
        } elseif ($this->filter === 'flagged') {
            $query->has('reports');
        } elseif ($this->filter === 'duplicates') {
            $query->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('clue_entries as ce2')
                    ->whereColumn('ce2.answer', 'clue_entries.answer')
                    ->whereColumn('ce2.clue', 'clue_entries.clue')
                    ->whereColumn('ce2.id', '!=', 'clue_entries.id');
            });
        }

        return $query->latest()->paginate(25);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function addClue(): void
    {
        $this->addError = '';

        $this->validate([
            'newAnswer' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z]+$/'],
            'newClue' => ['required', 'string', 'min:2', 'max:500'],
        ], [
            'newAnswer.regex' => 'Answer must contain only letters.',
        ]);

        $answer = mb_strtoupper(trim($this->newAnswer));
        $clue = trim($this->newClue);

        $exists = ClueEntry::where('answer', $answer)
            ->where('clue', $clue)
            ->where('user_id', Auth::id())
            ->whereNull('crossword_id')
            ->exists();

        if ($exists) {
            $this->addError = 'You already have this exact answer/clue combination in the library.';

            return;
        }

        ClueEntry::create([
            'answer' => $answer,
            'clue' => $clue,
            'user_id' => Auth::id(),
        ]);

        $this->newAnswer = '';
        $this->newClue = '';
        $this->showAddModal = false;
        unset($this->clues);
    }

    public function startEditing(int $id): void
    {
        $entry = ClueEntry::findOrFail($id);
        $this->authorize('update', $entry);

        $this->editingClueId = $id;
        $this->editAnswer = $entry->answer;
        $this->editClue = $entry->clue;
    }

    public function saveEdit(): void
    {
        $entry = ClueEntry::findOrFail($this->editingClueId);
        $this->authorize('update', $entry);

        $this->validate([
            'editAnswer' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z]+$/'],
            'editClue' => ['required', 'string', 'min:2', 'max:500'],
        ], [
            'editAnswer.regex' => 'Answer must contain only letters.',
        ]);

        $entry->update([
            'answer' => mb_strtoupper(trim($this->editAnswer)),
            'clue' => trim($this->editClue),
        ]);

        $this->cancelEdit();
        unset($this->clues);
    }

    public function cancelEdit(): void
    {
        $this->editingClueId = null;
        $this->editAnswer = '';
        $this->editClue = '';
    }

    public function deleteClue(int $id): void
    {
        $entry = ClueEntry::findOrFail($id);
        $this->authorize('delete', $entry);
        $entry->delete();
        unset($this->clues);
    }

    public function openReportModal(int $id): void
    {
        $this->reportingClueId = $id;
        $this->reportReason = '';
        $this->reportNotes = '';
        $this->reportError = '';
        $this->showReportModal = true;
    }

    public function submitReport(): void
    {
        $this->reportError = '';

        $this->validate([
            'reportReason' => ['required', 'string', 'in:duplicate,invalid,inappropriate,other'],
            'reportNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $entry = ClueEntry::findOrFail($this->reportingClueId);

        $alreadyReported = ClueReport::where('clue_entry_id', $entry->id)
            ->where('user_id', Auth::id())
            ->exists();

        if ($alreadyReported) {
            $this->reportError = 'You have already reported this clue.';

            return;
        }

        ClueReport::create([
            'clue_entry_id' => $entry->id,
            'user_id' => Auth::id(),
            'reason' => $this->reportReason,
            'notes' => $this->reportNotes ?: null,
        ]);

        $this->showReportModal = false;
        $this->reportingClueId = null;
        unset($this->clues);
    }
}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Clue Library') }}</flux:heading>

        <flux:button variant="primary" icon="plus" wire:click="$set('showAddModal', true)">
            {{ __('Add Clue') }}
        </flux:button>
    </div>

    {{-- Search and Filters --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input icon="magnifying-glass" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by answer or clue...') }}" />
        </div>
        <div class="flex gap-2">
            <flux:select wire:model.live="filter" class="w-40">
                <flux:select.option value="all">{{ __('All Clues') }}</flux:select.option>
                <flux:select.option value="mine">{{ __('My Clues') }}</flux:select.option>
                <flux:select.option value="standalone">{{ __('Standalone') }}</flux:select.option>
                <flux:select.option value="flagged">{{ __('Flagged') }}</flux:select.option>
                <flux:select.option value="duplicates">{{ __('Duplicates') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Clue Table --}}
    @if($this->clues->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-600">
            <flux:icon name="book-open" class="mb-4 size-12 text-zinc-400" />
            <flux:heading size="lg" class="mb-2">{{ __('No clues found') }}</flux:heading>
            <flux:text class="mb-6">
                @if($search)
                    {{ __('Try a different search term.') }}
                @else
                    {{ __('Add clues to build your library, or publish puzzles to harvest clues automatically.') }}
                @endif
            </flux:text>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3 font-medium text-zinc-600 dark:text-zinc-300">{{ __('Answer') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-600 dark:text-zinc-300">{{ __('Clue') }}</th>
                        <th class="hidden px-4 py-3 font-medium text-zinc-600 dark:text-zinc-300 sm:table-cell">{{ __('Source') }}</th>
                        <th class="hidden px-4 py-3 font-medium text-zinc-600 dark:text-zinc-300 md:table-cell">{{ __('Author') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-300">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($this->clues as $entry)
                        <tr wire:key="clue-{{ $entry->id }}" class="{{ $entry->reports_count > 0 ? 'bg-red-50/50 dark:bg-red-900/10' : '' }}">
                            @if($editingClueId === $entry->id)
                                <td class="px-4 py-2">
                                    <flux:input wire:model="editAnswer" size="sm" class="w-full" />
                                </td>
                                <td class="px-4 py-2">
                                    <flux:input wire:model="editClue" size="sm" class="w-full" />
                                </td>
                                <td class="hidden px-4 py-2 sm:table-cell"></td>
                                <td class="hidden px-4 py-2 md:table-cell"></td>
                                <td class="px-4 py-2 text-right">
                                    <div class="flex justify-end gap-1">
                                        <flux:button variant="primary" size="sm" wire:click="saveEdit">{{ __('Save') }}</flux:button>
                                        <flux:button size="sm" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                                    </div>
                                </td>
                            @else
                                <td class="px-4 py-3">
                                    <span class="font-mono font-semibold tracking-wide text-zinc-900 dark:text-zinc-100">{{ $entry->answer }}</span>
                                    <span class="ml-1 text-xs text-zinc-400">({{ mb_strlen($entry->answer) }})</span>
                                </td>
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                    {{ $entry->clue }}
                                </td>
                                <td class="hidden px-4 py-3 sm:table-cell">
                                    @if($entry->crossword)
                                        <flux:badge size="sm">{{ Str::limit($entry->crossword->title, 20) }}</flux:badge>
                                    @else
                                        <flux:badge variant="outline" size="sm" color="lime">{{ __('Standalone') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="hidden px-4 py-3 text-zinc-500 dark:text-zinc-400 md:table-cell">
                                    {{ $entry->user->name ?? __('Unknown') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @if($entry->reports_count > 0)
                                            <flux:badge size="sm" color="red">
                                                {{ $entry->reports_count }} {{ trans_choice('report|reports', $entry->reports_count) }}
                                            </flux:badge>
                                        @endif

                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                                            <flux:menu>
                                                @can('update', $entry)
                                                    <flux:menu.item icon="pencil" wire:click="startEditing({{ $entry->id }})">
                                                        {{ __('Edit') }}
                                                    </flux:menu.item>
                                                @endcan
                                                <flux:menu.item icon="flag" wire:click="openReportModal({{ $entry->id }})">
                                                    {{ __('Report') }}
                                                </flux:menu.item>
                                                @can('delete', $entry)
                                                    <flux:menu.item icon="trash" variant="danger" wire:click="deleteClue({{ $entry->id }})" wire:confirm="{{ __('Are you sure you want to delete this clue?') }}">
                                                        {{ __('Delete') }}
                                                    </flux:menu.item>
                                                @endcan
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->clues->links() }}
        </div>
    @endif

    {{-- Add Clue Modal --}}
    <flux:modal wire:model="showAddModal">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Add Clue') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Answer') }}</flux:label>
                <flux:input wire:model="newAnswer" placeholder="{{ __('e.g. OCEAN') }}" class="uppercase" />
                <flux:error name="newAnswer" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Clue') }}</flux:label>
                <flux:input wire:model="newClue" placeholder="{{ __('e.g. Large body of water') }}" />
                <flux:error name="newClue" />
            </flux:field>

            @if($addError)
                <flux:callout variant="danger">
                    <flux:text>{{ $addError }}</flux:text>
                </flux:callout>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showAddModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="addClue">{{ __('Add') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Report Modal --}}
    <flux:modal wire:model="showReportModal">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Report Clue') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Reason') }}</flux:label>
                <flux:select wire:model="reportReason">
                    <flux:select.option value="">{{ __('Select a reason...') }}</flux:select.option>
                    <flux:select.option value="duplicate">{{ __('Duplicate') }}</flux:select.option>
                    <flux:select.option value="invalid">{{ __('Invalid / Incorrect') }}</flux:select.option>
                    <flux:select.option value="inappropriate">{{ __('Inappropriate') }}</flux:select.option>
                    <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
                </flux:select>
                <flux:error name="reportReason" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Notes') }} <span class="text-zinc-400">({{ __('optional') }})</span></flux:label>
                <flux:textarea wire:model="reportNotes" placeholder="{{ __('Describe the issue...') }}" rows="3" />
                <flux:error name="reportNotes" />
            </flux:field>

            @if($reportError)
                <flux:callout variant="danger">
                    <flux:text>{{ $reportError }}</flux:text>
                </flux:callout>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showReportModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="submitReport">{{ __('Submit Report') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
