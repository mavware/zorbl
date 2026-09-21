<?php

use App\Models\ClueEntry;
use App\Models\ClueReport;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    // Counting 3.7M+ rows on every initial page load is the dominant cost
    // here. Cache the unfiltered total (the default landing view) since the
    // user is OK with it being slightly stale.
    private const DEFAULT_COUNT_CACHE_KEY = 'clue-entries:default-count';
    private const DEFAULT_COUNT_CACHE_TTL = 600; // 10 minutes
    private const PER_PAGE = 25;

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
        $query = ClueEntry::with(['user:id,name', 'user.subscriptions', 'crossword:id,title,width,height,puzzle_type,grid,styles']);

        // Hide unvetted clues from everyone except the author. Moderators see
        // the queue in Filament; the rest of the library is approved-only.
        $authId = Auth::id();
        $query->where(function ($q) use ($authId) {
            $q->approved();
            if ($authId !== null) {
                $q->orWhere('user_id', $authId);
            }
        });

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

        // Default listing (no search, no filter, no custom sort): paginate
        // manually with a cached total. Counting the full clue_entries table
        // on every load is the dominant cost; the cache keeps subsequent page
        // loads fast at the price of a slightly stale "Showing X of N" total.
        if ($this->search === '' && $this->filter === 'all' && $this->sortField === '') {
            $total = Cache::remember(
                self::DEFAULT_COUNT_CACHE_KEY,
                self::DEFAULT_COUNT_CACHE_TTL,
                fn () => DB::table('clue_entries')->where('status', ClueEntry::STATUS_APPROVED)->count(),
            );

            $page = $this->getPage();
            $items = $query->forPage($page, self::PER_PAGE)->get();

            return new LengthAwarePaginator(
                $items,
                $total,
                self::PER_PAGE,
                $page,
                ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page'],
            );
        }

        return $query->paginate(self::PER_PAGE);
    }

    private function bustDefaultCountCache(): void
    {
        Cache::forget(self::DEFAULT_COUNT_CACHE_KEY);
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
        abort_unless(Auth::check(), 403);

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
            'status' => ClueEntry::STATUS_PENDING,
        ]);

        $this->bustDefaultCountCache();
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
        $this->bustDefaultCountCache();
        unset($this->clues);
    }

    public function openReportModal(int $id): void
    {
        abort_unless(Auth::check(), 403);

        $this->reportingClueId = $id;
        $this->reportReason = '';
        $this->reportNotes = '';
        $this->reportError = '';
        $this->showReportModal = true;
    }

    public function submitReport(): void
    {
        abort_unless(Auth::check(), 403);

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

    /**
     * Guests get the public chrome; logged-in users keep the app sidebar layout.
     */
    public function render(): View
    {
        return $this->view()
            ->layout(Auth::check() ? 'layouts.app' : 'layouts.public')
            ->title(__('Clue Library'));
    }
}
?>

<div class="space-y-6">
    <x-seo-meta
        title="Clue Library"
        :canonical="route('clues.index')"
        :description="__('Search a community library of crossword clues and their answers. See how constructors have clued any word, or contribute your own.')"
    />

    @push('head_meta')
        @php
            $cluesJsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => __('Clue Library'),
                'url' => route('clues.index'),
                'isPartOf' => ['@id' => url('/').'#website'],
                'description' => __('A searchable, community-built library of crossword clues and answers.'),
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($cluesJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <x-page-header :kicker="__('Reference')" :title="__('Clue Library')">
        @auth
            <x-header-button icon="plus" wire:click="$set('showAddModal', true)">
                {{ __('Add Clue') }}
            </x-header-button>
        @endauth
    </x-page-header>

    {{-- Search and Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <label class="relative flex-1">
            <span class="sr-only">{{ __('Search by answer or clue...') }}</span>
            <flux:icon name="magnifying-glass" class="text-ink-faint pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
            <input
                type="search"
                placeholder="{{ __('Search by answer or clue...') }}"
                wire:model.live.debounce.300ms="search"
                class="field-classical w-full pr-3 pl-9"
            />
        </label>
        <label class="relative sm:w-44">
            <span class="sr-only">{{ __('Filter') }}</span>
            <select wire:model.live="filter" class="field-classical font-classical w-full appearance-none pr-9 pl-3.5 text-[15px] font-medium">
                <option value="all">{{ __('All Clues') }}</option>
                @auth
                    <option value="mine">{{ __('My Clues') }}</option>
                @endauth
                <option value="standalone">{{ __('Standalone') }}</option>
                <option value="flagged">{{ __('Flagged') }}</option>
                <option value="duplicates">{{ __('Duplicates') }}</option>
            </select>
            <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
        </label>
    </div>

    {{-- Clue Table --}}
    @if($this->clues->isEmpty())
        <div class="border-border-strong flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-16 text-center">
            <flux:icon name="book-open" class="text-ink-faint mb-4 size-10" />
            <h3 class="font-classical text-ink text-[26px] leading-tight font-medium">{{ __('No clues found') }}</h3>
            <p class="text-ink-muted mt-2 text-sm">
                @if($search)
                    {{ __('Try a different search term.') }}
                @else
                    {{ __('Add clues to build your library, or publish puzzles to harvest clues automatically.') }}
                @endif
            </p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-hairline border-b">
                            <th scope="col" class="px-3 py-3 text-left font-normal ">
                                <button type="button" wire:click="sortBy('answer')" class="meta-classical hover:text-ink inline-flex items-center gap-1 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                    {{ __('Answer') }}
                                    @if($sortField === 'answer')
                                        <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3 text-amber-400" />
                                    @endif
                                </button>
                            </th>
                            <th scope="col" class="px-3 py-3 text-left font-normal ">
                                <button type="button" wire:click="sortBy('clue')" class="meta-classical hover:text-ink inline-flex items-center gap-1 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                    {{ __('Clue') }}
                                    @if($sortField === 'clue')
                                        <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3 text-amber-400" />
                                    @endif
                                </button>
                            </th>
                        <th scope="col" class="meta-classical hidden px-3 py-3 text-left font-normal sm:table-cell">{{ __('Source') }}</th>
                        <th scope="col" class="meta-classical hidden px-3 py-3 text-left font-normal md:table-cell">{{ __('Author') }}</th>
                        <th scope="col" class="meta-classical px-3 py-3 text-right font-normal">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-hairline divide-y">
                    @foreach($this->clues as $entry)
                        <tr wire:key="clue-{{ $entry->id }}" class="align-middle">
                            @if($editingClueId === $entry->id)
                                <td class="px-3 py-2.5">
                                    <input type="text" wire:model="editAnswer" class="field-classical h-9 w-full px-3 uppercase" />
                                </td>
                                <td class="px-3 py-2.5">
                                    <input type="text" wire:model="editClue" class="field-classical h-9 w-full px-3" />
                                </td>
                                <td class="hidden px-3 py-2.5 sm:table-cell"></td>
                                <td class="hidden px-3 py-2.5 md:table-cell"></td>
                                <td class="px-3 py-2.5">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="btn-classical btn-amber-outline h-8 px-3 text-[14px]" wire:click="saveEdit">{{ __('Save') }}</button>
                                        <button type="button" class="btn-classical btn-classical-muted h-8 px-3 text-[14px]" wire:click="cancelEdit">{{ __('Cancel') }}</button>
                                    </div>
                                </td>
                            @else
                                <td class="px-3 py-3.5 whitespace-nowrap">
                                    <a href="{{ route('words.show', $entry->answer) }}" wire:navigate class="font-classical text-ink hover:text-amber-300 text-[18px] leading-none font-semibold tracking-[0.06em] transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">{{ $entry->answer }}</a>
                                    <span class="meta-classical tnum ml-1.5">({{ mb_strlen($entry->answer) }})</span>
                                </td>
                                <td class="text-ink px-3 py-3.5">{{ $entry->clue }}</td>
                                <td class="hidden px-3 py-3.5 sm:table-cell">
                                    @if($entry->crossword)
                                        <span class="chip-classical border-ink-faint text-ink-faint max-w-full truncate">{{ Str::limit($entry->crossword->displayTitle(), 24) }}</span>
                                    @else
                                        <span class="chip-classical border-ink-faint text-ink-faint">{{ __('Standalone') }}</span>
                                    @endif
                                </td>
                                <td class="text-ink-muted hidden px-3 py-3.5 md:table-cell">{{ $entry->user->name ?? __('Unknown') }} <x-supporter-badge :user="$entry->user" /></td>
                                <td class="px-3 py-3.5">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($entry->status === \App\Models\ClueEntry::STATUS_PENDING)
                                            <span class="chip-classical border-amber-400 text-amber-400">{{ __('Pending review') }}</span>
                                        @endif

                                        @if($entry->reports_count > 0)
                                            <span class="chip-classical border-amber-400 text-amber-400 tnum">
                                                {{ $entry->reports_count }} {{ trans_choice('report|reports', $entry->reports_count) }}
                                            </span>
                                        @endif

                                        @auth
                                            <flux:dropdown position="bottom" align="end">
                                                <button type="button" class="btn-classical btn-classical-muted h-8 w-8 px-0" aria-label="{{ __('More actions') }}">
                                                    <flux:icon name="ellipsis-vertical" class="size-4" />
                                                </button>
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
                                        @endauth
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($this->clues->hasPages())
            <div class="mt-4">
                {{ $this->clues->links() }}
            </div>
        @endif
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
