<?php

use App\Models\Crossword;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Solve Crossword')]
#[Layout('layouts.public')]
class extends Component {
    #[Locked]
    public int $crosswordId;

    public string $title = '';
    public string $authorName = '';
    public int $width;
    public int $height;
    public array $grid = [];
    public array $cluesAcross = [];
    public array $cluesDown = [];
    public ?array $styles = null;
    public ?array $prefilled = null;
    public string $obfuscatedSolution = '';

    public function mount(Crossword $crossword): void
    {
        abort_unless($crossword->is_published, 404);

        // Authenticated users should use the full solver
        if (Auth::check()) {
            $this->redirect(route('crosswords.solver', $crossword), navigate: true);

            return;
        }

        // Check guest solve cookie — allow only one puzzle
        $solved = json_decode(request()->cookie('zorbl_guest_solved', '[]'), true) ?: [];

        if (count($solved) > 0 && ! in_array($crossword->id, $solved)) {
            session()->flash('message', __('Create a free account to solve unlimited puzzles.'));
            $this->redirect(route('register'));

            return;
        }

        // Record this puzzle in the guest cookie (90 days)
        $solved = array_values(array_unique(array_merge($solved, [$crossword->id])));
        cookie()->queue('zorbl_guest_solved', json_encode($solved), 60 * 24 * 90);

        $this->crosswordId = $crossword->id;
        $this->title = $crossword->title ?? 'Untitled Puzzle';
        $this->authorName = $crossword->user->name ?? '';
        $this->width = $crossword->width;
        $this->height = $crossword->height;
        $this->grid = $crossword->grid;
        $this->cluesAcross = $crossword->clues_across ?? [];
        $this->cluesDown = $crossword->clues_down ?? [];
        $this->styles = $crossword->styles;
        $this->prefilled = $crossword->prefilled;
        $this->obfuscatedSolution = $crossword->obfuscateSolution();
    }
}
?>

<div>
    {{-- Inline scripts for guest persistence and solution decoding --}}
    <script>
        window.zorblGuestPersistence = (function() {
            const key = 'zorbl_guest_{{ $crosswordId }}';
            return {
                save(progress, isCompleted, elapsed, pencilCells) {
                    try {
                        localStorage.setItem(key, JSON.stringify({
                            progress, isCompleted, elapsed, pencilCells, savedAt: Date.now()
                        }));
                    } catch {}
                    return Promise.resolve();
                },
                load() {
                    try {
                        const raw = localStorage.getItem(key);
                        return raw ? JSON.parse(raw) : null;
                    } catch { return null; }
                }
            };
        })();

        window.zorblDecodeSolution = function(encoded, crosswordId) {
            const key = 'zorbl_' + crosswordId;
            const decoded = atob(encoded);
            let result = '';
            for (let i = 0; i < decoded.length; i++) {
                result += String.fromCharCode(decoded.charCodeAt(i) ^ key.charCodeAt(i % key.length));
            }
            return JSON.parse(result);
        };
    </script>

    @php
        $savedProgress = null;
        $initialElapsed = 0;
        $initialSolved = false;
        $initialPencilCells = [];
    @endphp

    <div
        x-data="(() => {
            const saved = window.zorblGuestPersistence.load();
            const solution = window.zorblDecodeSolution(@js($obfuscatedSolution), {{ $crosswordId }});
            const progress = saved?.progress ?? @js($prefilled ?? Crossword::emptySolution($width, $height));
            return crosswordSolver({
                width: @js($width),
                height: @js($height),
                grid: @js($grid),
                solution: solution,
                progress: progress,
                styles: @js($styles ?? []),
                prefilled: @js($prefilled),
                cluesAcross: @js($cluesAcross),
                cluesDown: @js($cluesDown),
                initialElapsed: saved?.elapsed ?? 0,
                initialSolved: saved?.isCompleted ?? false,
                initialPencilCells: saved?.pencilCells ?? [],
                persistence: window.zorblGuestPersistence,
                puzzleTitle: @js($title),
            });
        })()"
        x-init="$watch('solved', val => { if (val) $dispatch('show-guest-signup', { time: formattedTime() }) })"
        class="relative flex h-full flex-col"
    >
        {{-- Skip to content link --}}
        <a href="#crossword-grid" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:rounded focus:bg-blue-600 focus:px-4 focus:py-2 focus:text-white">
            {{ __('Skip to crossword grid') }}
        </a>

        {{-- Toolbar --}}
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <div class="flex flex-1 items-center gap-3">
                <flux:heading size="lg">{{ $title }}</flux:heading>
                @if($authorName)
                    <flux:text size="sm" class="text-zinc-500">
                        {{ __('by') }} {{ $authorName }}
                    </flux:text>
                @endif
            </div>

            <div class="flex items-center gap-1">
                {{-- Pencil mode toggle --}}
                <flux:tooltip content="{{ __('Pencil mode (P)') }}">
                    <button
                        x-on:click="pencilMode = !pencilMode"
                        :class="['text-fg-muted', pencilMode ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : 'hover:text-zinc-800 dark:hover:text-zinc-200']"
                        class="rounded-lg p-1.5 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
                            <path d="m15 5 4 4"/>
                        </svg>
                    </button>
                </flux:tooltip>

                {{-- Timer --}}
                <div class="bg-page mr-2 flex items-center gap-1.5 rounded-lg px-2.5 py-1 font-mono text-sm tabular-nums">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <span x-text="formattedTime()" :class="solved ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-800 dark:text-zinc-300'"></span>
                </div>

                {{-- Check answers --}}
                <flux:tooltip content="{{ __('Check answers') }}">
                    <button
                        x-on:click="checkAnswers()"
                        class="text-fg-muted rounded-lg p-1.5 transition-colors hover:text-zinc-800 dark:hover:text-zinc-200"
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
                        class="text-fg-muted rounded-lg p-1.5 transition-colors hover:text-zinc-800 dark:hover:text-zinc-200"
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

                {{-- Status --}}
                <div class="flex items-center gap-1 pl-2 text-sm text-zinc-500">
                    <template x-if="pencilMode && !solved">
                        <span class="mr-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Pencil') }}</span>
                    </template>
                    <template x-if="solved">
                        <span class="font-semibold text-emerald-500">{{ __('Solved!') }}</span>
                    </template>
                </div>
            </div>
        </div>

        {{-- Main solver layout --}}
        <div class="flex flex-1 gap-4 overflow-hidden max-lg:flex-col lg:max-h-[calc(100dvh-8rem)]">
            {{-- Across clues panel (desktop) --}}
            <div class="hidden w-64 flex-col overflow-hidden lg:flex">
                <flux:heading size="sm" class="mb-2 shrink-0">{{ __('Across') }}</flux:heading>
                <div class="flex-1 space-y-0.5 overflow-y-auto" x-ref="acrossPanel">
                    <template x-for="clue in computedCluesAcross" :key="'across-' + clue.number">
                        <div
                            x-on:click="selectClue('across', clue.number)"
                            x-on:focus="selectClue('across', clue.number)"
                            x-on:keydown.tab.prevent="focusNextClue($el, 'across', false)"
                            x-on:keydown.shift.tab.prevent="focusNextClue($el, 'across', true)"
                            :class="activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100 dark:bg-blue-900/40' : 'hover:bg-zinc-100 dark:hover:bg-zinc-700/50'"
                            class="cursor-pointer rounded px-2 py-1"
                            :id="'clue-across-' + clue.number"
                            tabindex="0"
                        >
                            <div class="flex items-start gap-1.5">
                                <span class="mt-px text-xs font-bold text-zinc-600" x-text="clue.number"></span>
                                <div class="flex-1">
                                    <span class="text-sm text-zinc-800 dark:text-zinc-300" x-text="clue.clue || '—'"></span>
                                    <span class="text-xs text-zinc-500" x-text="'(' + clue.length + ')'"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Screen reader live region for active clue --}}
            <div class="sr-only" aria-live="polite" aria-atomic="true" x-text="activeClueAnnouncement()"></div>

            {{-- Grid --}}
            <div class="flex min-w-0 flex-1 items-start justify-center overflow-hidden">
                <div
                    class="relative"
                    :style="'width: ' + Math.min(600, width * 40) + 'px;'"
                    x-on:keydown="handleKeydown($event)"
                    tabindex="0"
                    x-ref="gridContainer"
                    id="crossword-grid"
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
                                    x-on:click="selectCell(rowIdx, colIdx)"
                                    :class="[cellClasses(rowIdx, colIdx), isVoid(rowIdx, colIdx) ? '' : 'border border-line-strong']"
                                    :style="cellBarStyles(rowIdx, colIdx)"
                                    class="relative box-border flex aspect-square items-center justify-center select-none"
                                    role="gridcell"
                                    :aria-selected="rowIdx === selectedRow && colIdx === selectedCol ? 'true' : 'false'"
                                    :aria-label="isBlock(rowIdx, colIdx) ? 'Black cell' : (typeof cell === 'number' && cell > 0 ? cell + ' ' : '') + (progress[rowIdx]?.[colIdx] || 'empty') + (isPencil(rowIdx, colIdx) ? ' pencil' : '')"
                                >
                                    {{-- Clue number --}}
                                    <template x-if="typeof cell === 'number' && cell > 0">
                                        <span
                                            class="absolute top-0 left-0.5 text-zinc-800 dark:text-zinc-400 leading-none"
                                            :style="'font-size: ' + Math.max(8, Math.min(11, 600 / width * 0.22)) + 'px'"
                                            x-text="cell"
                                        ></span>
                                    </template>

                                    {{-- Circle annotation --}}
                                    <template x-if="hasCircle(rowIdx, colIdx)">
                                        <svg class="pointer-events-none absolute inset-0.5 size-[calc(100%-4px)]" viewBox="0 0 100 100">
                                            <circle cx="50" cy="50" r="46" fill="none" stroke="currentColor" stroke-width="2" class="text-fg-subtle" />
                                        </svg>
                                    </template>

                                    {{-- Rebus indicator --}}
                                    <template x-if="rebusMode && rowIdx === selectedRow && colIdx === selectedCol">
                                        <span class="absolute top-0 right-0.5 text-xs leading-none text-blue-500">R</span>
                                    </template>

                                    {{-- Letter --}}
                                    <span
                                        class="font-semibold uppercase"
                                        :class="letterClass(rowIdx, colIdx)"
                                        :style="letterFontStyle(rowIdx, colIdx)"
                                        x-text="isBlock(rowIdx, colIdx) ? '' : (progress[rowIdx]?.[colIdx] || '')"
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
                            x-on:focus="selectClue('down', clue.number)"
                            x-on:keydown.tab.prevent="focusNextClue($el, 'down', false)"
                            x-on:keydown.shift.tab.prevent="focusNextClue($el, 'down', true)"
                            :class="activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100 dark:bg-blue-900/40' : 'hover:bg-zinc-100 dark:hover:bg-zinc-700/50'"
                            class="cursor-pointer rounded px-2 py-1"
                            :id="'clue-down-' + clue.number"
                            tabindex="0"
                        >
                            <div class="flex items-start gap-1.5">
                                <span class="mt-px text-xs font-bold text-zinc-600" x-text="clue.number"></span>
                                <div class="flex-1">
                                    <span class="text-sm text-zinc-800 dark:text-zinc-300" x-text="clue.clue || '—'"></span>
                                    <span class="text-xs text-zinc-500" x-text="'(' + clue.length + ')'"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Mobile clue panels --}}
            <div class="lg:hidden">
                <div class="flex border-b border-line">
                    <button
                        x-on:click="mobileClueTab = 'across'"
                        :class="['text-fg', mobileClueTab === 'across' ? 'border-zinc-800 dark:border-zinc-200' : 'border-transparent text-zinc-600']"
                        class="border-b-2 px-4 py-2 text-sm font-medium"
                    >{{ __('Across') }}</button>
                    <button
                        x-on:click="mobileClueTab = 'down'"
                        :class="['text-fg', mobileClueTab === 'down' ? 'border-zinc-800 dark:border-zinc-200' : 'border-transparent text-zinc-600']"
                        class="border-b-2 px-4 py-2 text-sm font-medium"
                    >{{ __('Down') }}</button>
                </div>
                <div class="max-h-48 space-y-0.5 overflow-y-auto py-2">
                    <template x-if="mobileClueTab === 'across'">
                        <div>
                            <template x-for="clue in computedCluesAcross" :key="'m-across-' + clue.number">
                                <div
                                    x-on:click="selectClue('across', clue.number)"
                                    x-on:focus="selectClue('across', clue.number)"
                                    x-on:keydown.tab.prevent="focusNextClue($el, 'across', false)"
                                    x-on:keydown.shift.tab.prevent="focusNextClue($el, 'across', true)"
                                    :class="activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100 dark:bg-blue-900/40' : ''"
                                    class="cursor-pointer rounded px-2 py-1"
                                    tabindex="0"
                                >
                                    <div class="flex items-start gap-1.5">
                                        <span class="mt-px text-xs font-bold text-zinc-600" x-text="clue.number"></span>
                                        <div class="flex-1">
                                            <span class="text-sm text-zinc-800 dark:text-zinc-300" x-text="clue.clue || '—'"></span>
                                            <span class="text-xs text-zinc-500" x-text="'(' + clue.length + ')'"></span>
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
                                    x-on:focus="selectClue('down', clue.number)"
                                    x-on:keydown.tab.prevent="focusNextClue($el, 'down', false)"
                                    x-on:keydown.shift.tab.prevent="focusNextClue($el, 'down', true)"
                                    :class="activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100 dark:bg-blue-900/40' : ''"
                                    class="cursor-pointer rounded px-2 py-1"
                                    tabindex="0"
                                >
                                    <div class="flex items-start gap-1.5">
                                        <span class="mt-px text-xs font-bold text-zinc-600" x-text="clue.number"></span>
                                        <div class="flex-1">
                                            <span class="text-sm text-zinc-800 dark:text-zinc-300" x-text="clue.clue || '—'"></span>
                                            <span class="text-xs text-zinc-500" x-text="'(' + clue.length + ')'"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Signup banner --}}
        <div class="mt-4 flex items-center justify-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 dark:border-amber-800/50 dark:bg-amber-900/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 shrink-0 text-amber-600 dark:text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
            </svg>
            <p class="text-sm text-amber-800 dark:text-amber-300">
                {{ __('Your progress is saved locally.') }}
                <a href="{{ route('register') }}" class="font-semibold underline hover:no-underline">{{ __('Sign up') }}</a>
                {{ __('to save across devices, track stats, and solve unlimited puzzles.') }}
            </p>
        </div>
    </div>

    {{-- Signup Prompt Modal (shown on solve completion) --}}
    <div
        x-data="{
            showSignup: false,
            solveTime: '',
            copyLabel: '{{ __('Copy Result') }}',
            _shareText() {
                return '🧩 {{ str_replace("'", "\\'", $title) }} — Zorbl\n⏱️ ' + this.solveTime + ' | {{ $width }}×{{ $height }}\n{{ route('puzzles.solve', $crosswordId) }}';
            },
            async shareResult() {
                try { await navigator.share({ text: this._shareText() }); } catch {}
            },
            async copyShareText() {
                try {
                    await navigator.clipboard.writeText(this._shareText());
                    this.copyLabel = '{{ __('Copied!') }}';
                    setTimeout(() => { this.copyLabel = '{{ __('Copy Result') }}'; }, 2000);
                } catch {}
            },
            twitterShareUrl() {
                return 'https://x.com/intent/post?text=' + encodeURIComponent(this._shareText());
            },
        }"
        x-on:show-guest-signup.window="showSignup = true; solveTime = $event.detail.time"
    >
        <template x-teleport="body">
            <div
                x-show="showSignup"
                x-cloak
                x-on:keydown.escape.window="showSignup = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                x-on:click.self="showSignup = false"
            >
                <div class="bg-elevated mx-4 w-full max-w-md rounded-2xl p-8 text-center shadow-xl" x-on:click.stop>
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-8 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-fg">{{ __('Congratulations!') }}</h3>
                    <p class="mt-2 text-sm text-fg-muted">
                        {{ __('Great solve! Create a free account to save your progress across devices, track your stats, and access unlimited puzzles.') }}
                    </p>

                    {{-- Share buttons --}}
                    <div class="mt-4 flex items-center justify-center gap-2">
                        <button
                            x-on:click="shareResult()"
                            x-show="typeof navigator.share === 'function'"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-100 px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-600"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 4.5a2.5 2.5 0 11.702 1.737L6.97 9.604a2.518 2.518 0 010 .799l6.733 3.366a2.5 2.5 0 11-.671 1.341l-6.733-3.366a2.5 2.5 0 110-3.482l6.733-3.366A2.52 2.52 0 0113 4.5z"/></svg>
                            {{ __('Share') }}
                        </button>
                        <button
                            x-on:click="copyShareText()"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-100 px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-600"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3.5A1.5 1.5 0 018.5 2h3.879a1.5 1.5 0 011.06.44l3.122 3.12A1.5 1.5 0 0117 6.622V12.5a1.5 1.5 0 01-1.5 1.5h-1v-3.379a3 3 0 00-.879-2.121L10.5 5.379A3 3 0 008.379 4.5H7v-1z"/><path d="M4.5 6A1.5 1.5 0 003 7.5v9A1.5 1.5 0 004.5 18h7a1.5 1.5 0 001.5-1.5v-5.879a1.5 1.5 0 00-.44-1.06L9.44 6.439A1.5 1.5 0 008.378 6H4.5z"/></svg>
                            <span x-text="copyLabel"></span>
                        </button>
                        <a
                            x-bind:href="twitterShareUrl()"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-100 px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-600"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </a>
                    </div>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                        <a href="{{ route('register') }}" class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-zinc-950 hover:bg-amber-400 transition">
                            {{ __('Create Free Account') }}
                        </a>
                        <a href="{{ route('login') }}" class="border-line-strong rounded-xl border px-6 py-2.5 text-sm font-semibold text-zinc-800 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-700 transition">
                            {{ __('Log In') }}
                        </a>
                    </div>
                    <button x-on:click="showSignup = false" class="mt-4 text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                        {{ __('Continue solving') }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>
