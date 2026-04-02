<?php

use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Services\ClueHarvester;
use App\Services\GridNumberer;
use App\Services\IpuzExporter;
use App\Services\JpzExporter;
use App\Services\PuzExporter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Crossword Editor')] class extends Component {
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

    public bool $isPublished = false;

    public bool $showResizeModal = false;
    public int $resizeWidth;
    public int $resizeHeight;

    public function mount(Crossword $crossword): void
    {
        $this->authorize('update', $crossword);

        $this->crosswordId = $crossword->id;
        $this->title = $crossword->title ?? '';
        $this->author = $crossword->author ?? '';
        $this->width = $crossword->width;
        $this->height = $crossword->height;
        $this->grid = $crossword->grid;
        $this->solution = $crossword->solution;
        $this->cluesAcross = $crossword->clues_across ?? [];
        $this->cluesDown = $crossword->clues_down ?? [];
        $this->styles = $crossword->styles;
        $this->isPublished = $crossword->is_published;
        $this->resizeWidth = $crossword->width;
        $this->resizeHeight = $crossword->height;
    }

    public function save(array $grid, array $solution, ?array $styles, array $cluesAcross, array $cluesDown): void
    {
        $crossword = Crossword::findOrFail($this->crosswordId);
        $this->authorize('update', $crossword);

        $this->grid = $grid;
        $this->solution = $solution;
        $this->styles = $styles;
        $this->cluesAcross = $cluesAcross;
        $this->cluesDown = $cluesDown;

        $crossword->update([
            'grid' => $grid,
            'solution' => $solution,
            'styles' => $styles,
            'clues_across' => $cluesAcross,
            'clues_down' => $cluesDown,
        ]);

        if ($this->isPublished) {
            app(ClueHarvester::class)->harvest($crossword);
        }

        $this->dispatch('saved');
    }

    public function saveMetadata(): void
    {
        $crossword = Crossword::findOrFail($this->crosswordId);
        $this->authorize('update', $crossword);

        $crossword->update([
            'title' => $this->title,
            'author' => $this->author,
        ]);

        $this->dispatch('saved');
    }

    public function togglePublished(): void
    {
        $crossword = Crossword::findOrFail($this->crosswordId);
        $this->authorize('update', $crossword);

        $this->isPublished = ! $this->isPublished;
        $crossword->update(['is_published' => $this->isPublished]);

        $harvester = app(ClueHarvester::class);

        if ($this->isPublished) {
            $harvester->harvest($crossword);
        } else {
            $harvester->purge($crossword);
        }
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
            ->where('crossword_id', '!=', $this->crosswordId)
            ->with(['user:id,name', 'crossword:id,title'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (ClueEntry $entry) => [
                'clue' => $entry->clue,
                'author' => $entry->user->name ?? 'Unknown',
                'puzzle' => $entry->crossword->title ?? 'Untitled',
            ])
            ->all();
    }

    public function resizeGrid(): void
    {
        $this->validate([
            'resizeWidth' => ['required', 'integer', 'min:3', 'max:30'],
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
        $result = $numberer->number($newGrid, $this->resizeWidth, $this->resizeHeight);

        $this->width = $this->resizeWidth;
        $this->height = $this->resizeHeight;
        $this->grid = $result['grid'];
        $this->solution = $newSolution;
        $this->styles = null;
        $this->cluesAcross = [];
        $this->cluesDown = [];

        $crossword = Crossword::findOrFail($this->crosswordId);
        $crossword->update([
            'width' => $this->width,
            'height' => $this->height,
            'grid' => $this->grid,
            'solution' => $this->solution,
            'styles' => null,
            'clues_across' => [],
            'clues_down' => [],
        ]);

        $this->showResizeModal = false;
        $this->dispatch('grid-resized');
    }

    public function exportIpuz()
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

    public function exportPuz()
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

    public function exportJpz()
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
        })"
        x-on:saved.window="onSaved()"
        x-on:grid-resized.window="onGridResized()"
        class="flex h-full flex-col"
    >
        {{-- Toolbar --}}
        <div class="mb-4 flex flex-wrap items-center gap-2">
            {{-- Metadata --}}
            <div class="flex flex-1 gap-2">
                <flux:input
                    size="sm"
                    placeholder="{{ __('Puzzle title') }}"
                    wire:model.blur="title"
                    wire:change="saveMetadata"
                    class="max-w-48"
                />
                <flux:input
                    size="sm"
                    placeholder="{{ __('Author') }}"
                    wire:model.blur="author"
                    wire:change="saveMetadata"
                    class="max-w-36"
                />
            </div>

            <div class="flex items-center gap-1">
                {{-- Mode toggle --}}
                <div class="flex rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <span class="rounded-l-lg bg-zinc-800 px-3 py-1 text-sm font-medium text-white dark:bg-zinc-200 dark:text-zinc-900">{{ __('Edit') }}</span>
                    <a
                        href="{{ route('crosswords.solver', $crosswordId) }}"
                        x-on:click.prevent="saveAndSolve()"
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
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8" />
                            <path d="M21 3v5h-5" />
                        </svg>
                    </button>
                </flux:tooltip>

                {{-- Circle annotation --}}
                <flux:tooltip content="{{ __('Toggle circle') }}">
                    <button
                        x-on:click="toggleCircle()"
                        class="rounded-lg p-1.5 text-zinc-500 transition-colors hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                        </svg>
                    </button>
                </flux:tooltip>

                {{-- Resize --}}
                <flux:tooltip content="{{ __('Resize grid') }}">
                    <flux:button variant="ghost" size="sm" icon="arrows-pointing-out" wire:click="$set('showResizeModal', true)" />
                </flux:tooltip>

                {{-- Clear dropdown --}}
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" size="sm" icon="x-mark" />
                    <flux:menu>
                        <flux:menu.item x-on:click="clearLetters()">{{ __('Clear letters') }}</flux:menu.item>
                        <flux:menu.item x-on:click="clearAll()" class="text-red-600 dark:text-red-400">{{ __('Clear everything') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                {{-- Publish toggle --}}
                <flux:tooltip content="{{ $isPublished ? __('Unpublish puzzle') : __('Publish for others to solve') }}">
                    <flux:button
                        variant="{{ $isPublished ? 'primary' : 'ghost' }}"
                        size="sm"
                        icon="{{ $isPublished ? 'eye' : 'eye-slash' }}"
                        wire:click="togglePublished"
                    />
                </flux:tooltip>

                {{-- Export --}}
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" size="sm" icon="arrow-down-tray">
                        {{ __('Export') }}
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item wire:click="exportIpuz">{{ __('.ipuz') }}</flux:menu.item>
                        <flux:menu.item wire:click="exportPuz">{{ __('.puz (Across Lite)') }}</flux:menu.item>
                        <flux:menu.item wire:click="exportJpz">{{ __('.jpz (Crossword Compiler)') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                {{-- Save status --}}
                <div class="flex items-center gap-1 pl-2 text-sm text-zinc-400">
                    <template x-if="saving">
                        <span>{{ __('Saving...') }}</span>
                    </template>
                    <template x-if="showSaved">
                        <span class="text-emerald-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="inline size-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                            {{ __('Saved') }}
                        </span>
                    </template>
                </div>
            </div>
        </div>

        {{-- Main editor layout --}}
        <div class="flex flex-1 gap-4 overflow-hidden max-lg:flex-col">
            {{-- Across clues panel (desktop) --}}
            <div class="hidden w-64 flex-col overflow-hidden lg:flex">
                <flux:heading size="sm" class="mb-2 shrink-0">{{ __('Across') }}</flux:heading>
                <div class="flex-1 space-y-0.5 overflow-y-auto" x-ref="acrossPanel">
                    <template x-for="clue in computedCluesAcross" :key="'across-' + clue.number">
                        <div
                            x-on:click="selectClue('across', clue.number, $event)"
                            :class="activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100 dark:bg-blue-900/40' : 'hover:bg-zinc-100 dark:hover:bg-zinc-700/50'"
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
                                        x-on:focus="debouncedFetchSuggestions()"
                                        placeholder="{{ __('Enter clue...') }}"
                                        class="w-full border-0 bg-transparent p-0 text-sm text-zinc-700 placeholder-zinc-400 focus:ring-0 dark:text-zinc-300 dark:placeholder-zinc-500"
                                    />
                                    <span class="text-xs text-zinc-400 cursor-text" x-text="'(' + clue.length + ')'" x-on:click="$event.target.previousElementSibling.focus()"></span>
                                </div>
                            </div>

                            {{-- Clue suggestions --}}
                            <template x-if="activeClueNumber === clue.number && direction === 'across' && (clueSuggestions.length > 0 || clueSuggestionsLoading)">
                                <div class="mt-1 ml-5 border-l-2 border-amber-300 pl-2 dark:border-amber-600">
                                    <template x-if="clueSuggestionsLoading">
                                        <span class="text-xs text-zinc-400 italic">{{ __('Loading suggestions...') }}</span>
                                    </template>
                                    <template x-if="!clueSuggestionsLoading">
                                        <div class="space-y-0.5">
                                            <span class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Clue library') }}</span>
                                            <template x-for="(suggestion, idx) in clueSuggestions" :key="'sa-' + idx">
                                                <div
                                                    x-on:click.stop="useClue(clue, suggestion.clue)"
                                                    class="clue-content cursor-pointer rounded px-1 py-0.5 text-xs text-zinc-600 hover:bg-amber-50 dark:text-zinc-400 dark:hover:bg-amber-900/20"
                                                    :title="suggestion.puzzle + ' — ' + suggestion.author"
                                                >
                                                    <span x-text="suggestion.clue"></span>
                                                    <span class="text-zinc-400 dark:text-zinc-500" x-text="' — ' + suggestion.author"></span>
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
                        class="grid border border-zinc-800 dark:border-zinc-300"
                        :style="'grid-template-columns: repeat(' + width + ', 1fr);'"
                    >
                        <template x-for="(row, rowIdx) in grid" :key="'row-' + rowIdx">
                            <template x-for="(cell, colIdx) in row" :key="'cell-' + rowIdx + '-' + colIdx">
                                <div
                                    x-on:click="selectCell(rowIdx, colIdx)"
                                    :class="[cellClasses(rowIdx, colIdx), isVoid(rowIdx, colIdx) ? '' : 'border border-zinc-300 dark:border-zinc-600']"
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
                                        <svg class="pointer-events-none absolute inset-0.5 size-[calc(100%-4px)]" viewBox="0 0 100 100">
                                            <circle cx="50" cy="50" r="46" fill="none" stroke="currentColor" stroke-width="2" class="text-zinc-400 dark:text-zinc-500" />
                                        </svg>
                                    </template>

                                    {{-- Letter --}}
                                    <span
                                        class="font-semibold uppercase text-zinc-900 dark:text-zinc-100"
                                        :style="'font-size: ' + Math.max(12, Math.min(24, 600 / width * 0.55)) + 'px'"
                                        x-text="solution[rowIdx]?.[colIdx] || ''"
                                    ></span>
                                </div>
                            </template>
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
                            :class="activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100 dark:bg-blue-900/40' : 'hover:bg-zinc-100 dark:hover:bg-zinc-700/50'"
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
                                        x-on:focus="debouncedFetchSuggestions()"
                                        placeholder="{{ __('Enter clue...') }}"
                                        class="w-full border-0 bg-transparent p-0 text-sm text-zinc-700 placeholder-zinc-400 focus:ring-0 dark:text-zinc-300 dark:placeholder-zinc-500"
                                    />
                                    <span class="text-xs text-zinc-400 cursor-text" x-text="'(' + clue.length + ')'" x-on:click="$event.target.previousElementSibling.focus()"></span>
                                </div>
                            </div>

                            {{-- Clue suggestions --}}
                            <template x-if="activeClueNumber === clue.number && direction === 'down' && (clueSuggestions.length > 0 || clueSuggestionsLoading)">
                                <div class="mt-1 ml-5 border-l-2 border-amber-300 pl-2 dark:border-amber-600">
                                    <template x-if="clueSuggestionsLoading">
                                        <span class="text-xs text-zinc-400 italic">{{ __('Loading suggestions...') }}</span>
                                    </template>
                                    <template x-if="!clueSuggestionsLoading">
                                        <div class="space-y-0.5">
                                            <span class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Clue library') }}</span>
                                            <template x-for="(suggestion, idx) in clueSuggestions" :key="'sd-' + idx">
                                                <div
                                                    x-on:click.stop="useClue(clue, suggestion.clue)"
                                                    class="clue-content cursor-pointer rounded px-1 py-0.5 text-xs text-zinc-600 hover:bg-amber-50 dark:text-zinc-400 dark:hover:bg-amber-900/20"
                                                    :title="suggestion.puzzle + ' — ' + suggestion.author"
                                                >
                                                    <span x-text="suggestion.clue"></span>
                                                    <span class="text-zinc-400 dark:text-zinc-500" x-text="' — ' + suggestion.author"></span>
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
                                    :class="activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100 dark:bg-blue-900/40' : ''"
                                    class="cursor-pointer rounded px-2 py-1"
                                >
                                    <div class="flex items-start gap-1.5">
                                        <span class="mt-px text-xs font-bold text-zinc-500" x-text="clue.number"></span>
                                        <div class="flex-1">
                                            <input
                                                type="text"
                                                x-model="clue.clue"
                                                x-on:blur="markDirty()"
                                                x-on:focus="debouncedFetchSuggestions()"
                                                placeholder="{{ __('Enter clue...') }}"
                                                class="w-full border-0 bg-transparent p-0 text-sm text-zinc-700 placeholder-zinc-400 focus:ring-0 dark:text-zinc-300 dark:placeholder-zinc-500"
                                            />
                                            <span class="text-xs text-zinc-400" x-text="'(' + clue.length + ')'"></span>
                                        </div>
                                    </div>

                                    {{-- Clue suggestions (mobile) --}}
                                    <template x-if="activeClueNumber === clue.number && direction === 'across' && clueSuggestions.length > 0 && !clueSuggestionsLoading">
                                        <div class="mt-1 ml-5 border-l-2 border-amber-300 pl-2 dark:border-amber-600">
                                            <span class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Clue library') }}</span>
                                            <template x-for="(suggestion, idx) in clueSuggestions.slice(0, 5)" :key="'msa-' + idx">
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
                                    :class="activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100 dark:bg-blue-900/40' : ''"
                                    class="cursor-pointer rounded px-2 py-1"
                                >
                                    <div class="flex items-start gap-1.5">
                                        <span class="mt-px text-xs font-bold text-zinc-500" x-text="clue.number"></span>
                                        <div class="flex-1">
                                            <input
                                                type="text"
                                                x-model="clue.clue"
                                                x-on:blur="markDirty()"
                                                x-on:focus="debouncedFetchSuggestions()"
                                                placeholder="{{ __('Enter clue...') }}"
                                                class="w-full border-0 bg-transparent p-0 text-sm text-zinc-700 placeholder-zinc-400 focus:ring-0 dark:text-zinc-300 dark:placeholder-zinc-500"
                                            />
                                            <span class="text-xs text-zinc-400" x-text="'(' + clue.length + ')'"></span>
                                        </div>
                                    </div>

                                    {{-- Clue suggestions (mobile) --}}
                                    <template x-if="activeClueNumber === clue.number && direction === 'down' && clueSuggestions.length > 0 && !clueSuggestionsLoading">
                                        <div class="mt-1 ml-5 border-l-2 border-amber-300 pl-2 dark:border-amber-600">
                                            <span class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Clue library') }}</span>
                                            <template x-for="(suggestion, idx) in clueSuggestions.slice(0, 5)" :key="'msd-' + idx">
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

        {{-- Resize Modal --}}
        <flux:modal wire:model="showResizeModal">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Resize Grid') }}</flux:heading>
            <flux:text>{{ __('Existing content will be preserved where dimensions overlap. Clues will be reset.') }}</flux:text>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Width') }}</flux:label>
                    <flux:input type="number" wire:model="resizeWidth" min="3" max="30" />
                    <flux:error name="resizeWidth" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Height') }}</flux:label>
                    <flux:input type="number" wire:model="resizeHeight" min="3" max="30" />
                    <flux:error name="resizeHeight" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showResizeModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="resizeGrid">{{ __('Resize') }}</flux:button>
            </div>
            </div>
        </flux:modal>
</div>
