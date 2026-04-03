<?php

use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Services\ClueHarvester;
use App\Services\GridNumberer;
use App\Services\IpuzExporter;
use App\Services\JpzExporter;
use App\Services\PdfExporter;
use App\Services\PuzExporter;
use App\Services\DifficultyRater;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Crossword Editor')]
class extends Component {
    #[Locked]
    public int $crosswordId;

    public string $title = '';
    public string $author = '';
    public int $width;
    public int $height;
    public array $grid = [];
    public array $solution = [];
    public array $cluesAcross = [];
    public array $cluesDown = [];
    public ?array $styles = null;

    public string $copyright = '';
    public string $notes = '';
    public int $minAnswerLength = 3;

    public bool $isPublished = false;

    public bool $showPublishWarning = false;
    public array $incompleteChecks = [];

    public ?array $prefilled = null;

    public bool $showSettingsModal = false;
    public int $resizeWidth;
    public int $resizeHeight;

    public function mount(Crossword $crossword): void
    {
        $this->authorize('update', $crossword);

        $this->crosswordId = $crossword->id;
        $this->title = $crossword->title ?? '';
        $this->author = $crossword->author ?? Auth::user()->name ?? '';
        $this->width = $crossword->width;
        $this->height = $crossword->height;
        $this->grid = $crossword->grid;
        $this->solution = $crossword->solution;
        $this->cluesAcross = $crossword->clues_across ?? [];
        $this->cluesDown = $crossword->clues_down ?? [];
        $this->styles = $crossword->styles;
        $this->prefilled = $crossword->prefilled;
        $this->copyright = $crossword->copyright ?? copyright(Auth::user()->copyright_name ?? Auth::user()->name ?? '');
        $this->notes = $crossword->notes ?? '';
        $this->minAnswerLength = $crossword->metadata['min_answer_length'] ?? 3;
        $this->isPublished = $crossword->is_published;
        $this->resizeWidth = $crossword->width;
        $this->resizeHeight = $crossword->height;
    }

    public function save(array $grid, array $solution, ?array $styles, array $cluesAcross, array $cluesDown): void
    {
        $crossword = Crossword::findOrFail($this->crosswordId);
        $this->authorize('update', $crossword);

        $crossword->update([
            'grid'         => $grid,
            'solution'     => $solution,
            'styles'       => $styles,
            'clues_across' => $cluesAcross,
            'clues_down'   => $cluesDown,
        ]);

        if ($this->isPublished) {
            app(ClueHarvester::class)->harvest($crossword);
        }

        $this->skipRender();
        $this->dispatch('saved');
    }

    public function savePrefilled(array $prefilled): void
    {
        $crossword = Crossword::findOrFail($this->crosswordId);
        $this->authorize('update', $crossword);

        // Check if the prefilled grid has any non-empty values
        $hasValues = false;
        foreach ($prefilled as $row) {
            foreach ($row as $cell) {
                if (filled($cell)) {
                    $hasValues = true;

                    break 2;
                }
            }
        }

        $crossword->update([
            'prefilled' => $hasValues ? $prefilled : null,
        ]);

        $this->prefilled = $hasValues ? $prefilled : null;
        $this->skipRender();
        $this->dispatch('prefilled-saved');
    }

    public function saveMetadata(): void
    {
        $this->validate([
            'title'           => ['nullable', 'string', 'max:255'],
            'author'          => ['nullable', 'string', 'max:255'],
            'copyright'       => ['nullable', 'string', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:1000'],
            'minAnswerLength' => ['required', 'integer', 'min:1', 'max:15'],
        ]);

        $crossword = Crossword::findOrFail($this->crosswordId);
        $this->authorize('update', $crossword);

        $metadata = $crossword->metadata ?? [];
        $metadata['min_answer_length'] = $this->minAnswerLength;

        $crossword->update([
            'title'     => $this->title,
            'author'    => $this->author,
            'copyright' => $this->copyright,
            'notes'     => $this->notes,
            'metadata'  => $metadata,
        ]);

        $this->showSettingsModal = false;
        $this->dispatch('settings-updated');
        $this->dispatch('saved');
    }

    public function attemptPublish(): void
    {
        if ($this->isPublished) {
            $this->togglePublished();

            return;
        }

        $crossword = Crossword::findOrFail($this->crosswordId);
        $completeness = $crossword->completeness();

        if ($completeness['percentage'] === 100) {
            $this->togglePublished();

            return;
        }

        $this->incompleteChecks = array_keys(array_filter($completeness['checks'], fn($v) => !$v));
        $this->showPublishWarning = true;
    }

    public function togglePublished(): void
    {
        $crossword = Crossword::findOrFail($this->crosswordId);
        $this->authorize('update', $crossword);

        $this->isPublished = !$this->isPublished;
        $crossword->update(['is_published' => $this->isPublished]);

        $harvester = app(ClueHarvester::class);

        if ($this->isPublished) {
            $harvester->harvest($crossword);

            // Calculate and store difficulty rating
            $rater = app(DifficultyRater::class);
            $rating = $rater->rate($crossword);
            $crossword->update([
                'difficulty_score' => $rating['score'],
                'difficulty_label' => $rating['label'],
            ]);
        } else {
            $harvester->purge($crossword);
        }

        $this->showPublishWarning = false;
        $this->incompleteChecks = [];
    }

    public function cancelPublish(): void
    {
        $this->showPublishWarning = false;
        $this->dispatch('highlight-incomplete', checks: $this->incompleteChecks);
    }

    /**
     * Look up existing clues for a given answer word from other published puzzles.
     *
     * @return array<int, array{clue: string, author: string, puzzle: string}>
     */
    public function lookupClues(string $answer): array
    {
        $answer = mb_strtoupper(trim($answer));

        if (mb_strlen($answer) < 2) {
            return [];
        }

        return ClueEntry::where('answer', $answer)
            ->where(fn($q) => $q->whereNull('crossword_id')->orWhere('crossword_id', '!=', $this->crosswordId))
            ->with(['user:id,name', 'crossword:id,title'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn(ClueEntry $entry) => [
                'clue'   => $entry->clue,
                'author' => $entry->user->name ?? 'Unknown',
                'puzzle' => $entry->crossword->title ?? 'Untitled',
            ])
            ->all();
    }

    public function resizeGrid(): void
    {
        $this->validate([
            'resizeWidth'  => ['required', 'integer', 'min:3', 'max:30'],
            'resizeHeight' => ['required', 'integer', 'min:3', 'max:30'],
        ]);

        $newGrid = Crossword::emptyGrid($this->resizeWidth, $this->resizeHeight);
        $newSolution = Crossword::emptySolution($this->resizeWidth, $this->resizeHeight);

        // Preserve existing content where dimensions overlap
        $minHeight = min($this->height, $this->resizeHeight);
        $minWidth = min($this->width, $this->resizeWidth);

        for ($row = 0; $row < $minHeight; $row++) {
            for ($col = 0; $col < $minWidth; $col++) {
                $newGrid[$row][$col] = $this->grid[$row][$col];
                $newSolution[$row][$col] = $this->solution[$row][$col];
            }
        }

        // Renumber the grid
        $numberer = app(GridNumberer::class);
        $result = $numberer->number($newGrid, $this->resizeWidth, $this->resizeHeight, [], $this->minAnswerLength);

        $this->width = $this->resizeWidth;
        $this->height = $this->resizeHeight;
        $this->grid = $result['grid'];
        $this->solution = $newSolution;
        $this->styles = null;
        $this->cluesAcross = [];
        $this->cluesDown = [];

        $crossword = Crossword::findOrFail($this->crosswordId);
        $crossword->update([
            'width'        => $this->width,
            'height'       => $this->height,
            'grid'         => $this->grid,
            'solution'     => $this->solution,
            'styles'       => null,
            'clues_across' => [],
            'clues_down'   => [],
        ]);

        $this->showSettingsModal = false;
        $this->dispatch('grid-resized');
    }

    public function exportIpuz(): StreamedResponse
    {
        $crossword = Crossword::findOrFail($this->crosswordId);
        $this->authorize('view', $crossword);

        $exporter = new IpuzExporter;
        $json = $exporter->toJson($crossword);
        $filename = str($crossword->title ?: 'crossword')->slug()->append('.ipuz')->toString();

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function exportPuz(): StreamedResponse
    {
        $crossword = Crossword::findOrFail($this->crosswordId);
        $this->authorize('view', $crossword);

        $exporter = app(PuzExporter::class);
        $binary = $exporter->export($crossword);
        $filename = str($crossword->title ?: 'crossword')->slug()->append('.puz')->toString();

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, ['Content-Type' => 'application/octet-stream']);
    }

    public function exportJpz(): StreamedResponse
    {
        $crossword = Crossword::findOrFail($this->crosswordId);
        $this->authorize('view', $crossword);

        $exporter = app(JpzExporter::class);
        $compressed = $exporter->export($crossword);
        $filename = str($crossword->title ?: 'crossword')->slug()->append('.jpz')->toString();

        return response()->streamDownload(function () use ($compressed) {
            echo $compressed;
        }, $filename, ['Content-Type' => 'application/octet-stream']);
    }

    public function exportPdf(): StreamedResponse
    {
        $crossword = Crossword::findOrFail($this->crosswordId);
        $this->authorize('view', $crossword);

        $exporter = app(PdfExporter::class);
        $pdf = $exporter->export($crossword, includeSolution: true);
        $filename = str($crossword->title ?: 'crossword')->slug()->append('.pdf')->toString();

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }
}
?>

<div
    x-data="crosswordGrid({
            width: @js($width),
            height: @js($height),
            grid: @js($grid),
            solution: @js($solution),
            styles: @js($styles ?? []),
            cluesAcross: @js($cluesAcross),
            cluesDown: @js($cluesDown),
            minAnswerLength: @js($minAnswerLength),
            prefilled: @js($prefilled),
        })"
    x-on:saved.window="onSaved()"
    x-on:prefilled-saved.window="onSaved()"
    x-on:grid-resized.window="onGridResized()"
    x-on:settings-updated.window="onSettingsUpdated()"
    x-on:highlight-incomplete.window="highlightIncomplete($event.detail.checks)"
    class="flex h-full flex-col"
>
    {{-- Toolbar --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        {{-- Title --}}
        <div class="flex flex-1 items-center gap-2">
            <flux:input
                size="sm"
                placeholder="{{ __('Puzzle title') }}"
                wire:model.blur="title"
                wire:change="saveMetadata"
                class="max-w-48"
            />
        </div>

        <div class="flex items-center gap-2">
            {{-- Save status --}}
            <div class="flex items-center gap-1 pl-2 text-sm text-zinc-400">
                <template x-if="saving">
                    <span>{{ __('Saving...') }}</span>
                </template>
                <template x-if="showSaved">
                        <span class="text-emerald-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="inline size-4" viewBox="0 0 20 20"
                                 fill="currentColor"><path fill-rule="evenodd"
                                                           d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                                           clip-rule="evenodd"/></svg>
                            {{ __('Saved') }}
                        </span>
                </template>
            </div>

            {{-- Mode toggle --}}
            <div class="flex rounded-lg border border-zinc-200 dark:border-zinc-700">
                <span
                    class="rounded-l-lg bg-zinc-800 px-3 py-1 text-sm font-medium text-white dark:bg-zinc-200 dark:text-zinc-900">{{ __('Edit') }}</span>
                <a
                    href="{{ route('crosswords.solver', $crosswordId) }}"
                    wire:navigate
                    class="rounded-r-lg px-3 py-1 text-sm font-medium text-zinc-600 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                >{{ __('Solve') }}</a>
            </div>

            {{-- Symmetry --}}
            <flux:tooltip content="{{ __('Rotational symmetry') }}">
                <button
                    x-on:click="symmetry = !symmetry"
                    :class="symmetry ? 'bg-zinc-800 text-white dark:bg-zinc-200 dark:text-zinc-900' : 'text-zinc-500 dark:text-zinc-400'"
                    class="rounded-lg p-1.5 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/>
                        <path d="M21 3v5h-5"/>
                    </svg>
                </button>
            </flux:tooltip>

            {{-- Clear dropdown --}}
            <flux:dropdown position="bottom" align="end">
                <flux:tooltip content="{{ __('Clear Options') }}">
                    <flux:button variant="ghost" size="sm" icon="x-mark"/>
                </flux:tooltip>
                <flux:menu>
                    <flux:menu.item x-on:click="clearLetters()">{{ __('Clear letters') }}</flux:menu.item>
                    <flux:menu.item x-on:click="clearAll()"
                                    class="text-red-600 dark:text-red-400">{{ __('Clear everything') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            {{-- Publish toggle --}}
            <flux:tooltip content="{{ $isPublished ? __('Unpublish puzzle') : __('Publish for others to solve') }}">
                <flux:button
                    variant="{{ $isPublished ? 'primary' : 'ghost' }}"
                    size="sm"
                    icon="{{ $isPublished ? 'eye' : 'eye-slash' }}"
                    wire:click="attemptPublish"
                />
            </flux:tooltip>

            {{-- Prefill mode --}}
            <flux:tooltip content="{{ __('Pre-fill cells for solvers') }}">
                <button
                    x-on:click="togglePrefillMode()"
                    :class="prefillMode ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300' : 'text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200'"
                    class="rounded-lg p-1.5 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="18" height="18" x="3" y="3" rx="2"/>
                        <path d="M7 7h.01"/><path d="M17 7h.01"/><path d="M7 17h.01"/><path d="M17 17h.01"/>
                    </svg>
                </button>
            </flux:tooltip>
            <template x-if="prefillMode">
                <flux:button size="sm" variant="primary" x-on:click="savePrefilled()">{{ __('Save Prefills') }}</flux:button>
            </template>

            {{-- Export --}}
            <flux:dropdown position="bottom" align="end">
                <flux:button variant="ghost" size="sm" icon="arrow-down-tray">
                    {{ __('Export') }}
                </flux:button>
                <flux:menu>
                    <flux:menu.item wire:click="exportIpuz">{{ __('.ipuz') }}</flux:menu.item>
                    <flux:menu.item wire:click="exportPuz">{{ __('.puz (Across Lite)') }}</flux:menu.item>
                    <flux:menu.item wire:click="exportJpz">{{ __('.jpz (Crossword Compiler)') }}</flux:menu.item>
                    <flux:menu.item wire:click="exportPdf">{{ __('.pdf (Print-Ready)') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            {{-- Settings --}}
            <flux:tooltip content="{{ __('Puzzle settings') }}">
                <flux:button variant="ghost" size="sm" icon="cog-6-tooth" wire:click="$set('showSettingsModal', true)"/>
            </flux:tooltip>
        </div>
    </div>

    {{-- Main editor layout --}}
    <div class="flex flex-1 gap-4 overflow-hidden max-lg:flex-col lg:max-h-[calc(100dvh-8rem)]">
        {{-- Across clues panel (desktop) --}}
        <div class="hidden w-64 flex-col overflow-hidden lg:flex">
            <flux:heading size="sm" class="mb-2 shrink-0">{{ __('Across') }}</flux:heading>
            <div class="flex-1 space-y-0.5 overflow-y-auto" x-ref="acrossPanel">
                <template x-for="clue in computedCluesAcross" :key="'across-' + clue.number">
                    <div
                        x-on:click="selectClue('across', clue.number, $event)"
                        x-on:focusin="selectClue('across', clue.number, $event)"
                        x-on:keydown.tab.prevent="focusNextClue($el, 'across', false)"
                        x-on:keydown.shift.tab.prevent="focusNextClue($el, 'across', true)"
                        :class="[
                                activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100 dark:bg-blue-900/40' : 'hover:bg-zinc-100 dark:hover:bg-zinc-700/50',
                                isClueIncomplete('across') && !clue.clue?.trim() ? 'ring-2 ring-amber-400 dark:ring-amber-500' : ''
                            ]"
                        class="cursor-pointer rounded px-2 py-1"
                        :id="'clue-across-' + clue.number"
                    >
                        <div class="flex items-start gap-1.5">
                            <span class="mt-px text-xs font-bold text-zinc-500" x-text="clue.number"></span>
                            <div class="clue-content flex-1">
                                <input
                                    type="text"
                                    x-model="clue.clue"
                                    x-on:blur="markDirty()"
                                    placeholder="{{ __('Enter clue...') }}"
                                    class="w-full border-0 bg-transparent p-0 text-sm text-zinc-700 placeholder-zinc-400 focus:ring-0 dark:text-zinc-300 dark:placeholder-zinc-500"
                                />
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-zinc-400 cursor-text" x-text="'(' + clue.length + ')'"
                                          x-on:click="$event.target.closest('.clue-content').querySelector('input').focus()"></span>
                                    <button
                                        type="button"
                                        x-on:click.stop="toggleSuggestions()"
                                        x-show="activeClueNumber === clue.number && direction === 'across'"
                                        class="inline-flex items-center rounded px-1 py-0.5 text-amber-500 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:text-amber-400 dark:hover:bg-amber-900/20 dark:hover:text-amber-300"
                                        :title="showSuggestions ? '{{ __('Hide clue library') }}' : '{{ __('Show clue library') }}'"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 20 20"
                                             fill="currentColor">
                                            <path
                                                d="M9 4.804A7.968 7.968 0 0 0 5.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 0 1 5.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0 1 14.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0 0 14.5 4c-1.669 0-3.218.51-4.5 1.385V15"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Clue suggestions --}}
                        <template
                            x-if="activeClueNumber === clue.number && direction === 'across' && showSuggestions && (clueSuggestions.length > 0 || clueSuggestionsLoading)">
                            <div class="mt-1 ml-5 border-l-2 border-amber-300 pl-2 dark:border-amber-600">
                                <template x-if="clueSuggestionsLoading">
                                    <span class="text-xs text-zinc-400 italic">{{ __('Loading suggestions...') }}</span>
                                </template>
                                <template x-if="!clueSuggestionsLoading">
                                    <div class="space-y-0.5">
                                        <span
                                            class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Clue library') }}</span>
                                        <template x-for="(suggestion, idx) in clueSuggestions" :key="'sa-' + idx">
                                            <div
                                                x-on:click.stop="useClue(clue, suggestion.clue)"
                                                class="clue-content cursor-pointer rounded px-1 py-0.5 text-xs text-zinc-600 hover:bg-amber-50 dark:text-zinc-400 dark:hover:bg-amber-900/20"
                                                :title="suggestion.puzzle + ' — ' + suggestion.author"
                                            >
                                                <span x-text="suggestion.clue"></span>
                                                <span class="text-zinc-400 dark:text-zinc-500"
                                                      x-text="' — ' + suggestion.author"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- Grid --}}
        <div class="flex min-w-0 flex-1 items-start justify-center overflow-hidden">
            <div
                class="relative"
                :style="'width: ' + Math.min(600, width * 40) + 'px;'"
                x-on:keydown="handleKeydown($event)"
                tabindex="0"
                x-ref="gridContainer"
                role="grid"
                :aria-label="'Crossword grid, ' + width + ' columns by ' + height + ' rows'"
            >
                <div
                    class="grid border border-zinc-800 dark:border-zinc-300 [--bar-color:var(--color-zinc-800)] dark:[--bar-color:var(--color-zinc-300)]"
                    :style="'grid-template-columns: repeat(' + width + ', 1fr);'"
                >
                    <template x-for="(row, rowIdx) in grid" :key="'row-' + rowIdx">
                        <template x-for="(cell, colIdx) in row" :key="'cell-' + rowIdx + '-' + colIdx">
                            <div
                                x-on:click="prefillMode ? togglePrefillCell(rowIdx, colIdx) : selectCell(rowIdx, colIdx)"
                                x-on:contextmenu.prevent="openContextMenu(rowIdx, colIdx, $event)"
                                x-on:touchstart.passive="startLongPress(rowIdx, colIdx, $event)"
                                x-on:touchend="cancelLongPress()"
                                x-on:touchmove="cancelLongPress()"
                                :class="[cellClasses(rowIdx, colIdx), isVoid(rowIdx, colIdx) ? '' : 'border border-zinc-300 dark:border-zinc-600']"
                                :style="cellBarStyles(rowIdx, colIdx)"
                                class="relative box-border flex aspect-square items-center justify-center select-none"
                                role="gridcell"
                            >
                                {{-- Clue number --}}
                                <template x-if="typeof cell === 'number' && cell > 0">
                                        <span
                                            class="absolute top-0 left-0.5 text-zinc-700 dark:text-zinc-400 leading-none"
                                            :style="'font-size: ' + Math.max(8, Math.min(11, 600 / width * 0.22)) + 'px'"
                                            x-text="cell"
                                        ></span>
                                </template>

                                {{-- Circle annotation --}}
                                <template x-if="hasCircle(rowIdx, colIdx)">
                                    <svg class="pointer-events-none absolute inset-0.5 size-[calc(100%-4px)]"
                                         viewBox="0 0 100 100">
                                        <circle cx="50" cy="50" r="46" fill="none" stroke="currentColor"
                                                stroke-width="2" class="text-zinc-400 dark:text-zinc-500"/>
                                    </svg>
                                </template>

                                {{-- Rebus indicator --}}
                                <template x-if="rebusMode && rowIdx === selectedRow && colIdx === selectedCol">
                                    <span class="absolute top-0 right-0.5 text-xs leading-none text-blue-500">R</span>
                                </template>

                                {{-- Prefilled indicator --}}
                                <template x-if="isPrefilled(rowIdx, colIdx)">
                                    <div class="absolute inset-0 bg-violet-200/40 dark:bg-violet-800/30"></div>
                                </template>

                                {{-- Letter --}}
                                <span
                                    class="font-semibold uppercase"
                                    :class="isPrefilled(rowIdx, colIdx) ? 'text-violet-700 dark:text-violet-300' : 'text-zinc-900 dark:text-zinc-100'"
                                    :style="letterFontStyle(rowIdx, colIdx)"
                                    x-text="isBlock(rowIdx, colIdx) ? '' : (solution[rowIdx]?.[colIdx] || '')"
                                ></span>
                            </div>
                        </template>
                    </template>
                </div>
            </div>

            {{-- Context menu --}}
            <div
                x-show="contextMenu.show"
                x-on:click.stop
                :style="'position: fixed; left: ' + contextMenu.x + 'px; top: ' + contextMenu.y + 'px; z-index: 50;'"
                class="min-w-44 rounded-lg border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
                x-transition
                x-cloak
            >
                <button
                    x-on:click="contextToggleBlock()"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                >
                    <span
                        x-text="isBlock(contextMenu.row, contextMenu.col) ? '{{ __('Make white') }}' : '{{ __('Make black') }}'"></span>
                </button>

                <button
                    x-show="!isBlock(contextMenu.row, contextMenu.col)"
                    x-on:click="contextToggleCircle()"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                >
                    <span
                        x-text="hasCircle(contextMenu.row, contextMenu.col) ? '{{ __('Remove circle') }}' : '{{ __('Add circle') }}'"></span>
                </button>

                <div x-show="!isBlock(contextMenu.row, contextMenu.col)">
                    <div class="my-1 border-t border-zinc-200 dark:border-zinc-700"></div>
                    <div class="px-3 py-1 text-xs font-medium text-zinc-400">{{ __('Bars') }}</div>
                    <template x-for="edge in ['top', 'right', 'bottom', 'left']" :key="'bar-' + edge">
                        <button
                            x-on:click.stop="contextToggleBar(edge)"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                        >
                            <svg x-show="hasBar(contextMenu.row, contextMenu.col, edge)"
                                 xmlns="http://www.w3.org/2000/svg" class="size-4 text-zinc-500" viewBox="0 0 20 20"
                                 fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                      clip-rule="evenodd"/>
                            </svg>
                            <span x-show="!hasBar(contextMenu.row, contextMenu.col, edge)" class="size-4"></span>
                            <span x-text="edge.charAt(0).toUpperCase() + edge.slice(1)"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        {{-- Down clues panel (desktop) --}}
        <div class="hidden w-64 flex-col overflow-hidden lg:flex">
            <flux:heading size="sm" class="mb-2 shrink-0">{{ __('Down') }}</flux:heading>
            <div class="flex-1 space-y-0.5 overflow-y-auto" x-ref="downPanel">
                <template x-for="clue in computedCluesDown" :key="'down-' + clue.number">
                    <div
                        x-on:click="selectClue('down', clue.number, $event)"
                        x-on:focusin="selectClue('down', clue.number, $event)"
                        x-on:keydown.tab.prevent="focusNextClue($el, 'down', false)"
                        x-on:keydown.shift.tab.prevent="focusNextClue($el, 'down', true)"
                        :class="[
                                activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100 dark:bg-blue-900/40' : 'hover:bg-zinc-100 dark:hover:bg-zinc-700/50',
                                isClueIncomplete('down') && !clue.clue?.trim() ? 'ring-2 ring-amber-400 dark:ring-amber-500' : ''
                            ]"
                        class="cursor-pointer rounded px-2 py-1"
                        :id="'clue-down-' + clue.number"
                    >
                        <div class="flex items-start gap-1.5">
                            <span class="mt-px text-xs font-bold text-zinc-500" x-text="clue.number"></span>
                            <div class="clue-content flex-1">
                                <input
                                    type="text"
                                    x-model="clue.clue"
                                    x-on:blur="markDirty()"
                                    placeholder="{{ __('Enter clue...') }}"
                                    class="w-full border-0 bg-transparent p-0 text-sm text-zinc-700 placeholder-zinc-400 focus:ring-0 dark:text-zinc-300 dark:placeholder-zinc-500"
                                />
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-zinc-400 cursor-text" x-text="'(' + clue.length + ')'"
                                          x-on:click="$event.target.closest('.clue-content').querySelector('input').focus()"></span>
                                    <button
                                        type="button"
                                        x-on:click.stop="toggleSuggestions()"
                                        x-show="activeClueNumber === clue.number && direction === 'down'"
                                        class="inline-flex items-center rounded px-1 py-0.5 text-amber-500 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:text-amber-400 dark:hover:bg-amber-900/20 dark:hover:text-amber-300"
                                        :title="showSuggestions ? '{{ __('Hide clue library') }}' : '{{ __('Show clue library') }}'"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 20 20"
                                             fill="currentColor">
                                            <path
                                                d="M9 4.804A7.968 7.968 0 0 0 5.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 0 1 5.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0 1 14.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0 0 14.5 4c-1.669 0-3.218.51-4.5 1.385V15"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Clue suggestions --}}
                        <template
                            x-if="activeClueNumber === clue.number && direction === 'down' && showSuggestions && (clueSuggestions.length > 0 || clueSuggestionsLoading)">
                            <div class="mt-1 ml-5 border-l-2 border-amber-300 pl-2 dark:border-amber-600">
                                <template x-if="clueSuggestionsLoading">
                                    <span class="text-xs text-zinc-400 italic">{{ __('Loading suggestions...') }}</span>
                                </template>
                                <template x-if="!clueSuggestionsLoading">
                                    <div class="space-y-0.5">
                                        <span
                                            class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Clue library') }}</span>
                                        <template x-for="(suggestion, idx) in clueSuggestions" :key="'sd-' + idx">
                                            <div
                                                x-on:click.stop="useClue(clue, suggestion.clue)"
                                                class="clue-content cursor-pointer rounded px-1 py-0.5 text-xs text-zinc-600 hover:bg-amber-50 dark:text-zinc-400 dark:hover:bg-amber-900/20"
                                                :title="suggestion.puzzle + ' — ' + suggestion.author"
                                            >
                                                <span x-text="suggestion.clue"></span>
                                                <span class="text-zinc-400 dark:text-zinc-500"
                                                      x-text="' — ' + suggestion.author"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- Mobile clue panels --}}
        <div class="lg:hidden">
            <div class="flex border-b border-zinc-200 dark:border-zinc-700">
                <button
                    x-on:click="mobileClueTab = 'across'"
                    :class="mobileClueTab === 'across' ? 'border-zinc-800 text-zinc-900 dark:border-zinc-200 dark:text-zinc-100' : 'border-transparent text-zinc-500'"
                    class="border-b-2 px-4 py-2 text-sm font-medium"
                >{{ __('Across') }}</button>
                <button
                    x-on:click="mobileClueTab = 'down'"
                    :class="mobileClueTab === 'down' ? 'border-zinc-800 text-zinc-900 dark:border-zinc-200 dark:text-zinc-100' : 'border-transparent text-zinc-500'"
                    class="border-b-2 px-4 py-2 text-sm font-medium"
                >{{ __('Down') }}</button>
            </div>
            <div class="max-h-48 space-y-0.5 overflow-y-auto py-2">
                <template x-if="mobileClueTab === 'across'">
                    <div>
                        <template x-for="clue in computedCluesAcross" :key="'m-across-' + clue.number">
                            <div
                                x-on:click="selectClue('across', clue.number, $event)"
                                x-on:focusin="selectClue('across', clue.number, $event)"
                                x-on:keydown.tab.prevent="focusNextClue($el, 'across', false)"
                                x-on:keydown.shift.tab.prevent="focusNextClue($el, 'across', true)"
                                :class="[
                                        activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100 dark:bg-blue-900/40' : '',
                                        isClueIncomplete('across') && !clue.clue?.trim() ? 'ring-2 ring-amber-400 dark:ring-amber-500' : ''
                                    ]"
                                class="cursor-pointer rounded px-2 py-1"
                            >
                                <div class="flex items-start gap-1.5">
                                    <span class="mt-px text-xs font-bold text-zinc-500" x-text="clue.number"></span>
                                    <div class="flex-1">
                                        <input
                                            type="text"
                                            x-model="clue.clue"
                                            x-on:blur="markDirty()"
                                            placeholder="{{ __('Enter clue...') }}"
                                            class="w-full border-0 bg-transparent p-0 text-sm text-zinc-700 placeholder-zinc-400 focus:ring-0 dark:text-zinc-300 dark:placeholder-zinc-500"
                                        />
                                        <div class="flex items-center gap-1">
                                            <span class="text-xs text-zinc-400" x-text="'(' + clue.length + ')'"></span>
                                            <button
                                                type="button"
                                                x-on:click.stop="toggleSuggestions()"
                                                x-show="activeClueNumber === clue.number && direction === 'across'"
                                                class="inline-flex items-center rounded px-1 py-0.5 text-amber-500 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:text-amber-400 dark:hover:bg-amber-900/20 dark:hover:text-amber-300"
                                                :title="showSuggestions ? '{{ __('Hide clue library') }}' : '{{ __('Show clue library') }}'"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5"
                                                     viewBox="0 0 20 20" fill="currentColor">
                                                    <path
                                                        d="M9 4.804A7.968 7.968 0 0 0 5.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 0 1 5.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0 1 14.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0 0 14.5 4c-1.669 0-3.218.51-4.5 1.385V15"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Clue suggestions (mobile) --}}
                                <template
                                    x-if="activeClueNumber === clue.number && direction === 'across' && showSuggestions && clueSuggestions.length > 0 && !clueSuggestionsLoading">
                                    <div class="mt-1 ml-5 border-l-2 border-amber-300 pl-2 dark:border-amber-600">
                                        <span
                                            class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Clue library') }}</span>
                                        <template x-for="(suggestion, idx) in clueSuggestions.slice(0, 5)"
                                                  :key="'msa-' + idx">
                                            <div
                                                x-on:click.stop="useClue(clue, suggestion.clue)"
                                                class="cursor-pointer rounded px-1 py-0.5 text-xs text-zinc-600 hover:bg-amber-50 dark:text-zinc-400 dark:hover:bg-amber-900/20"
                                            >
                                                <span x-text="suggestion.clue"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="mobileClueTab === 'down'">
                    <div>
                        <template x-for="clue in computedCluesDown" :key="'m-down-' + clue.number">
                            <div
                                x-on:click="selectClue('down', clue.number, $event)"
                                x-on:focusin="selectClue('down', clue.number, $event)"
                                x-on:keydown.tab.prevent="focusNextClue($el, 'down', false)"
                                x-on:keydown.shift.tab.prevent="focusNextClue($el, 'down', true)"
                                :class="[
                                        activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100 dark:bg-blue-900/40' : '',
                                        isClueIncomplete('down') && !clue.clue?.trim() ? 'ring-2 ring-amber-400 dark:ring-amber-500' : ''
                                    ]"
                                class="cursor-pointer rounded px-2 py-1"
                            >
                                <div class="flex items-start gap-1.5">
                                    <span class="mt-px text-xs font-bold text-zinc-500" x-text="clue.number"></span>
                                    <div class="flex-1">
                                        <input
                                            type="text"
                                            x-model="clue.clue"
                                            x-on:blur="markDirty()"
                                            placeholder="{{ __('Enter clue...') }}"
                                            class="w-full border-0 bg-transparent p-0 text-sm text-zinc-700 placeholder-zinc-400 focus:ring-0 dark:text-zinc-300 dark:placeholder-zinc-500"
                                        />
                                        <div class="flex items-center gap-1">
                                            <span class="text-xs text-zinc-400" x-text="'(' + clue.length + ')'"></span>
                                            <button
                                                type="button"
                                                x-on:click.stop="toggleSuggestions()"
                                                x-show="activeClueNumber === clue.number && direction === 'down'"
                                                class="inline-flex items-center rounded px-1 py-0.5 text-amber-500 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:text-amber-400 dark:hover:bg-amber-900/20 dark:hover:text-amber-300"
                                                :title="showSuggestions ? '{{ __('Hide clue library') }}' : '{{ __('Show clue library') }}'"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5"
                                                     viewBox="0 0 20 20" fill="currentColor">
                                                    <path
                                                        d="M9 4.804A7.968 7.968 0 0 0 5.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 0 1 5.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0 1 14.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0 0 14.5 4c-1.669 0-3.218.51-4.5 1.385V15"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Clue suggestions (mobile) --}}
                                <template
                                    x-if="activeClueNumber === clue.number && direction === 'down' && showSuggestions && clueSuggestions.length > 0 && !clueSuggestionsLoading">
                                    <div class="mt-1 ml-5 border-l-2 border-amber-300 pl-2 dark:border-amber-600">
                                        <span
                                            class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Clue library') }}</span>
                                        <template x-for="(suggestion, idx) in clueSuggestions.slice(0, 5)"
                                                  :key="'msd-' + idx">
                                            <div
                                                x-on:click.stop="useClue(clue, suggestion.clue)"
                                                class="cursor-pointer rounded px-1 py-0.5 text-xs text-zinc-600 hover:bg-amber-50 dark:text-zinc-400 dark:hover:bg-amber-900/20"
                                            >
                                                <span x-text="suggestion.clue"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Publish Warning Modal --}}
    <flux:modal wire:model="showPublishWarning">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Publish Incomplete Puzzle?') }}</flux:heading>
            <flux:text>{{ __('This puzzle is missing the following:') }}</flux:text>

            <ul class="space-y-1.5 text-sm">
                @foreach($incompleteChecks as $check)
                    <li class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 20 20"
                             fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                                  clip-rule="evenodd"/>
                        </svg>
                        @switch($check)
                            @case('title')
                                {{ __('Puzzle title') }}
                                @break
                            @case('author')
                                {{ __('Constructor name') }}
                                @break
                            @case('fill')
                                {{ __('Not all cells have letters') }}
                                @break
                            @case('clues_across')
                                {{ __('Missing across clues') }}
                                @break
                            @case('clues_down')
                                {{ __('Missing down clues') }}
                                @break
                        @endswitch
                    </li>
                @endforeach
            </ul>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelPublish">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="togglePublished">{{ __('Publish Anyway') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Settings Modal --}}
    <flux:modal wire:model="showSettingsModal">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Puzzle Settings') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:input wire:model="title" placeholder="{{ __('Puzzle title') }}"/>
                <flux:error name="title"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Constructor') }}</flux:label>
                <flux:input wire:model="author" placeholder="{{ __('Constructor name') }}"/>
                <flux:error name="author"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Copyright') }}</flux:label>
                <flux:input wire:model="copyright" placeholder="{{ copyright(__('Your Name')) }}"/>
                <flux:error name="copyright"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Minimum Answer Length') }}</flux:label>
                <flux:input type="number" wire:model="minAnswerLength" min="1" max="15"/>
                <flux:description>{{ __('Shortest allowed word length in the grid.') }}</flux:description>
                <flux:error name="minAnswerLength"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Notes') }}</flux:label>
                <flux:textarea wire:model="notes" placeholder="{{ __('Notes for solvers (shown before solving)') }}"
                               rows="3"/>
                <flux:error name="notes"/>
            </flux:field>

            <flux:separator/>

            <div>
                <flux:heading size="sm">{{ __('Grid Size') }}</flux:heading>
                <flux:text size="sm"
                           class="mt-1">{{ __('Existing content will be preserved where dimensions overlap. Clues will be reset.') }}</flux:text>

                <div class="mt-3 grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Width') }}</flux:label>
                        <flux:input type="number" wire:model="resizeWidth" min="3" max="30"/>
                        <flux:error name="resizeWidth"/>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Height') }}</flux:label>
                        <flux:input type="number" wire:model="resizeHeight" min="3" max="30"/>
                        <flux:error name="resizeHeight"/>
                    </flux:field>
                </div>

                <div class="mt-3 flex justify-end">
                    <flux:button variant="danger" size="sm"
                                 wire:click="resizeGrid">{{ __('Resize Grid') }}</flux:button>
                </div>
            </div>

            <flux:separator/>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showSettingsModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveMetadata">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
