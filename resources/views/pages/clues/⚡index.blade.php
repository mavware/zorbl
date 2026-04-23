<?php

use App\Models\ClueEntry;
use App\Models\ClueReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    #[Url]
    public string $sortField = '';

    #[Url]
    public string $sortDirection = 'asc';

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
        $query = ClueEntry::with(['user:id,name', 'crossword:id,title']);

        // Only count reports when needed (flagged filter or to show badges)
        if ($this->filter === 'flagged') {
            $query->has('reports')->withCount('reports');
        } else {
            $query->withCount('reports');
        }

        if ($this->search !== '') {
            $term = $this->search;

            if (in_array(DB::getDriverName(), ['pgsql', 'mysql', 'mariadb'])) {
                $query->whereFullText(['clue', 'answer'], $term);
            } else {
                $query->where(function ($q) use ($term) {
                    $q->whereLike('answer', '%'.mb_strtoupper($term).'%')
                        ->orWhereLike('clue', '%'.$term.'%');
                });
            }
        }

        if ($this->filter === 'mine') {
            $query->where('user_id', Auth::id());
        } elseif ($this->filter === 'standalone') {
            $query->whereNull('crossword_id');
        } elseif ($this->filter === 'duplicates') {
            // Find answer+clue combos that appear more than once, then filter to those
            $query->whereIn(
                DB::raw('(answer, clue)'),
                function ($sub) {
                    $sub->select('answer', 'clue')
                        ->from('clue_entries')
                        ->groupBy('answer', 'clue')
                        ->havingRaw('count(*) > 1');
                }
            );
        }

        $allowed = ['answer', 'clue'];
        if ($this->sortField !== '' && in_array($this->sortField, $allowed)) {
            $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';
            $query->orderBy($this->sortField, $direction);
        } else {
            $query->latest('id');
        }

        return $query->paginate(25);
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
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
        <div class="border-line-strong flex flex-col items-center justify-center rounded-xl border border-dashed py-16">
            <flux:icon name="book-open" class="mb-4 size-12 text-zinc-500" />
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
        <flux:table :paginate="$this->clues">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'answer'" :direction="$sortDirection" wire:click="sortBy('answer')">{{ __('Answer') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'clue'" :direction="$sortDirection" wire:click="sortBy('clue')">{{ __('Clue') }}</flux:table.column>
                <flux:table.column class="hidden sm:table-cell">{{ __('Source') }}</flux:table.column>
                <flux:table.column class="hidden md:table-cell">{{ __('Author') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->clues as $entry)
                    <flux:table.row :key="$entry->id" class="{{ $entry->reports_count > 0 ? 'bg-red-50/50 dark:bg-red-900/10' : '' }}">
                        @if($editingClueId === $entry->id)
                            <flux:table.cell>
                                <flux:input wire:model="editAnswer" size="sm" class="w-full" />
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:input wire:model="editClue" size="sm" class="w-full" />
                            </flux:table.cell>
                            <flux:table.cell class="hidden sm:table-cell" />
                            <flux:table.cell class="hidden md:table-cell" />
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-1">
                                    <flux:button variant="primary" size="sm" wire:click="saveEdit">{{ __('Save') }}</flux:button>
                                    <flux:button size="sm" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                                </div>
                            </flux:table.cell>
                        @else
                            <flux:table.cell variant="strong">
                                <span class="font-mono font-semibold tracking-wide">{{ $entry->answer }}</span>
                                <span class="ml-1 text-xs text-zinc-500">({{ mb_strlen($entry->answer) }})</span>
                            </flux:table.cell>
                            <flux:table.cell>{{ $entry->clue }}</flux:table.cell>
                            <flux:table.cell class="hidden sm:table-cell">
                                @if($entry->crossword)
                                    <flux:badge size="sm">{{ Str::limit($entry->crossword->title, 20) }}</flux:badge>
                                @else
                                    <flux:badge variant="outline" size="sm" color="lime">{{ __('Standalone') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="hidden md:table-cell">{{ $entry->user->name ?? __('Unknown') }}</flux:table.cell>
                            <flux:table.cell align="end">
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
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
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
                <flux:label>{{ __('Notes') }} <span class="text-zinc-500">({{ __('optional') }})</span></flux:label>
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
