<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Solve Crossword')] class extends Component {
    #[Locked]
    public int $crosswordId;

    #[Locked]
    public int $attemptId;

    #[Locked]
    public bool $isOwner = false;

    public string $title = '';
    public string $authorName = '';
    public int $width;
    public int $height;
    public array $grid = [];
    public array $solution = [];
    public array $progress = [];
    public array $cluesAcross = [];
    public array $cluesDown = [];
    public ?array $styles = null;

    public function mount(Crossword $crossword): void
    {
        $this->authorize('solve', $crossword);

        $user = Auth::user();
        $this->isOwner = $user->id === $crossword->user_id;

        // Find or create the user's attempt for this puzzle
        $attempt = PuzzleAttempt::firstOrCreate(
            ['user_id' => $user->id, 'crossword_id' => $crossword->id],
            ['progress' => Crossword::emptySolution($crossword->width, $crossword->height)],
        );

        $this->crosswordId = $crossword->id;
        $this->attemptId = $attempt->id;
        $this->title = $crossword->title ?? 'Untitled Puzzle';
        $this->authorName = $crossword->user->name ?? '';
        $this->width = $crossword->width;
        $this->height = $crossword->height;
        $this->grid = $crossword->grid;
        $this->solution = $crossword->solution;
        $this->progress = $attempt->progress;
        $this->cluesAcross = $crossword->clues_across ?? [];
        $this->cluesDown = $crossword->clues_down ?? [];
        $this->styles = $crossword->styles;
    }

    public function saveProgress(array $progress, bool $isCompleted = false): void
    {
        $attempt = PuzzleAttempt::findOrFail($this->attemptId);

        if ($attempt->user_id !== Auth::id()) {
            abort(403);
        }

        $this->progress = $progress;
        $attempt->update([
            'progress' => $progress,
            'is_completed' => $isCompleted,
        ]);

        $this->dispatch('progress-saved');
    }
}
?>

<div
    x-data="crosswordSolver({
        width: @js($width),
        height: @js($height),
        grid: @js($grid),
        solution: @js($solution),
        progress: @js($progress),
        styles: @js($styles ?? []),
        cluesAcross: @js($cluesAcross),
        cluesDown: @js($cluesDown),
    })"
    x-on:progress-saved.window="onSaved()"
    class="flex h-full flex-col"
>
    {{-- Toolbar --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="flex flex-1 items-center gap-3">
            <flux:heading size="lg">{{ $title }}</flux:heading>
            @if(!$isOwner && $authorName)
                <flux:text size="sm" class="text-zinc-400">{{ __('by :author', ['author' => $authorName]) }}</flux:text>
            @endif
        </div>

        <div class="flex items-center gap-1">
            {{-- Mode toggle (only show Edit for owners) --}}
            @if($isOwner)
                <div class="flex rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <a
                        href="{{ route('crosswords.editor', $crosswordId) }}"
                        wire:navigate
                        class="rounded-l-lg px-3 py-1 text-sm font-medium text-zinc-600 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                    >{{ __('Edit') }}</a>
                    <span class="rounded-r-lg bg-zinc-800 px-3 py-1 text-sm font-medium text-white dark:bg-zinc-200 dark:text-zinc-900">{{ __('Solve') }}</span>
                </div>
            @endif

            {{-- Check answers --}}
            <flux:tooltip content="{{ __('Check answers') }}">
                <button
                    x-on:click="checkAnswers()"
                    class="rounded-lg p-1.5 text-zinc-500 transition-colors hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5"/>
                    </svg>
                </button>
            </flux:tooltip>

            {{-- Reveal letter --}}
            <flux:tooltip content="{{ __('Reveal letter') }}">
                <button
                    x-on:click="revealLetter()"
                    class="rounded-lg p-1.5 text-zinc-500 transition-colors hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </flux:tooltip>

            {{-- Clear progress --}}
            <flux:dropdown position="bottom" align="end">
                <flux:button variant="ghost" size="sm" icon="x-mark" />
                <flux:menu>
                    <flux:menu.item x-on:click="clearProgress()">{{ __('Clear all letters') }}</flux:menu.item>
                    <flux:menu.item x-on:click="clearErrors()" class="text-amber-600 dark:text-amber-400">{{ __('Clear incorrect letters') }}</flux:menu.item>
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
                <template x-if="solved">
                    <span class="font-semibold text-emerald-500">{{ __('Solved!') }}</span>
                </template>
            </div>
        </div>
    </div>

    {{-- Main solver layout --}}
    <div class="flex flex-1 gap-4 overflow-hidden max-lg:flex-col">
        {{-- Across clues panel (desktop) --}}
        <div class="hidden w-64 flex-col overflow-hidden lg:flex">
            <flux:heading size="sm" class="mb-2 shrink-0">{{ __('Across') }}</flux:heading>
            <div class="flex-1 space-y-0.5 overflow-y-auto" x-ref="acrossPanel">
                <template x-for="clue in computedCluesAcross" :key="'across-' + clue.number">
                    <div
                        x-on:click="selectClue('across', clue.number)"
                        :class="activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100 dark:bg-blue-900/40' : 'hover:bg-zinc-100 dark:hover:bg-zinc-700/50'"
                        class="cursor-pointer rounded px-2 py-1"
                        :id="'clue-across-' + clue.number"
                    >
                        <div class="flex items-start gap-1.5">
                            <span class="mt-px text-xs font-bold text-zinc-500" x-text="clue.number"></span>
                            <div class="flex-1">
                                <span class="text-sm text-zinc-700 dark:text-zinc-300" x-text="clue.clue || '—'"></span>
                                <span class="text-xs text-zinc-400" x-text="'(' + clue.length + ')'"></span>
                            </div>
                        </div>
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
                                    class="font-semibold uppercase"
                                    :class="letterClass(rowIdx, colIdx)"
                                    :style="'font-size: ' + Math.max(12, Math.min(24, 600 / width * 0.55)) + 'px'"
                                    x-text="progress[rowIdx]?.[colIdx] || ''"
                                ></span>

                                {{-- Incorrect marker --}}
                                <template x-if="checked[rowIdx + ',' + colIdx] === 'wrong'">
                                    <span class="absolute top-0 right-0.5 text-red-500 leading-none" :style="'font-size: ' + Math.max(6, Math.min(9, 600 / width * 0.18)) + 'px'">✗</span>
                                </template>

                                {{-- Revealed marker --}}
                                <template x-if="revealed[rowIdx + ',' + colIdx]">
                                    <span class="absolute bottom-0 right-0.5 text-blue-500 leading-none" :style="'font-size: ' + Math.max(6, Math.min(9, 600 / width * 0.18)) + 'px'">◆</span>
                                </template>
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
                        x-on:click="selectClue('down', clue.number)"
                        :class="activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100 dark:bg-blue-900/40' : 'hover:bg-zinc-100 dark:hover:bg-zinc-700/50'"
                        class="cursor-pointer rounded px-2 py-1"
                        :id="'clue-down-' + clue.number"
                    >
                        <div class="flex items-start gap-1.5">
                            <span class="mt-px text-xs font-bold text-zinc-500" x-text="clue.number"></span>
                            <div class="flex-1">
                                <span class="text-sm text-zinc-700 dark:text-zinc-300" x-text="clue.clue || '—'"></span>
                                <span class="text-xs text-zinc-400" x-text="'(' + clue.length + ')'"></span>
                            </div>
                        </div>
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
                                x-on:click="selectClue('across', clue.number)"
                                :class="activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100 dark:bg-blue-900/40' : ''"
                                class="cursor-pointer rounded px-2 py-1"
                            >
                                <div class="flex items-start gap-1.5">
                                    <span class="mt-px text-xs font-bold text-zinc-500" x-text="clue.number"></span>
                                    <div class="flex-1">
                                        <span class="text-sm text-zinc-700 dark:text-zinc-300" x-text="clue.clue || '—'"></span>
                                        <span class="text-xs text-zinc-400" x-text="'(' + clue.length + ')'"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="mobileClueTab === 'down'">
                    <div>
                        <template x-for="clue in computedCluesDown" :key="'m-down-' + clue.number">
                            <div
                                x-on:click="selectClue('down', clue.number)"
                                :class="activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100 dark:bg-blue-900/40' : ''"
                                class="cursor-pointer rounded px-2 py-1"
                            >
                                <div class="flex items-start gap-1.5">
                                    <span class="mt-px text-xs font-bold text-zinc-500" x-text="clue.number"></span>
                                    <div class="flex-1">
                                        <span class="text-sm text-zinc-700 dark:text-zinc-300" x-text="clue.clue || '—'"></span>
                                        <span class="text-xs text-zinc-400" x-text="'(' + clue.length + ')'"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
