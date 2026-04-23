import Alpine from 'alpinejs';
import { crosswordSolver } from './crossword-solver.js';
import { createLocalStoragePersistence } from './embed-persistence.js';

/**
 * Decode an XOR+base64 obfuscated solution.
 */
function decodeSolution(encoded, crosswordId) {
    const key = 'zorbl_' + crosswordId;
    const bytes = atob(encoded);
    let result = '';
    for (let i = 0; i < bytes.length; i++) {
        result += String.fromCharCode(bytes.charCodeAt(i) ^ key.charCodeAt(i % key.length));
    }
    return JSON.parse(result);
}

/**
 * Build the solver HTML template.
 * Replicates the essential grid + clues from solver.blade.php using only Alpine directives.
 */
function buildTemplate(data) {
    return `
    <div x-data="crosswordSolver" class="zorbl-embed relative flex flex-col font-sans text-fg" style="max-width: 100%;">
        <!-- Toolbar -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <div class="flex flex-1 items-center gap-2 min-w-0">
                <h2 class="text-lg font-semibold truncate">${escapeHtml(data.title || 'Crossword')}</h2>
                ${data.author ? `<span class="text-sm text-zinc-500">by ${escapeHtml(data.author)}</span>` : ''}
            </div>
            <div class="flex items-center gap-1">
                <!-- Pencil mode -->
                <button
                    x-on:click="pencilMode = !pencilMode"
                    :class="pencilMode ? 'bg-amber-100 text-amber-700' : 'text-zinc-600 hover:text-zinc-800'"
                    class="rounded-lg p-1.5 transition-colors"
                    title="Pencil mode (P)"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
                        <path d="m15 5 4 4"/>
                    </svg>
                </button>
                <!-- Timer -->
                <div class="mr-1 flex items-center gap-1 rounded-lg bg-zinc-100 px-2 py-1 font-mono text-sm tabular-nums">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <span x-text="formattedTime()" :class="solved ? 'text-emerald-600' : 'text-zinc-800'"></span>
                </div>
                <!-- Check answers -->
                <button x-on:click="checkAnswers()" class="rounded-lg p-1.5 text-zinc-600 hover:text-zinc-800 transition-colors" title="Check answers">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5"/>
                    </svg>
                </button>
                <!-- Reveal letter -->
                <button x-on:click="revealLetter()" class="rounded-lg p-1.5 text-zinc-600 hover:text-zinc-800 transition-colors" title="Reveal letter">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
                <!-- Status -->
                <div class="flex items-center gap-1 pl-1 text-sm text-zinc-500">
                    <template x-if="pencilMode && !solved">
                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700">Pencil</span>
                    </template>
                    <template x-if="saving">
                        <span>Saving...</span>
                    </template>
                    <template x-if="showSaved">
                        <span class="text-emerald-500">&#10003; Saved</span>
                    </template>
                    <template x-if="solved">
                        <span class="font-semibold text-emerald-500">Solved!</span>
                    </template>
                </div>
            </div>
        </div>

        <!-- Main layout -->
        <div class="flex flex-1 gap-4 overflow-hidden max-lg:flex-col lg:max-h-[calc(100dvh-8rem)]">
            <!-- Across clues (desktop) -->
            <div class="hidden w-56 flex-col overflow-hidden lg:flex">
                <h3 class="mb-2 text-sm font-semibold shrink-0">Across</h3>
                <div class="flex-1 space-y-0.5 overflow-y-auto" x-ref="acrossPanel">
                    <template x-for="clue in computedCluesAcross" :key="'across-' + clue.number">
                        <div
                            x-on:click="selectClue('across', clue.number)"
                            :class="activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100' : 'hover:bg-zinc-100'"
                            class="cursor-pointer rounded px-2 py-1"
                        >
                            <div class="flex items-start gap-1.5">
                                <span class="mt-px text-xs font-bold text-zinc-600" x-text="clue.displayNumber"></span>
                                <div class="flex-1">
                                    <span class="text-sm text-zinc-800" x-text="clue.clue || '—'"></span>
                                    <span class="text-xs text-zinc-500" x-text="'(' + clue.length + ')'"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Grid -->
            <div class="flex min-w-0 flex-1 items-start justify-center overflow-hidden">
                <div
                    class="relative"
                    :style="'width: ' + Math.min(600, width * 40) + 'px;'"
                    x-on:keydown="handleKeydown($event)"
                    tabindex="0"
                    x-ref="gridContainer"
                    role="grid"
                >
                    <div
                        class="grid border border-zinc-800 [--bar-color:var(--color-zinc-800)]"
                        :style="'grid-template-columns: repeat(' + width + ', minmax(0, 1fr));'"
                    >
                        <template x-for="(row, rowIdx) in grid" :key="'row-' + rowIdx">
                            <template x-for="(cell, colIdx) in row" :key="'cell-' + rowIdx + '-' + colIdx">
                                <div
                                    x-on:click="selectCell(rowIdx, colIdx)"
                                    :class="[cellClasses(rowIdx, colIdx), isVoid(rowIdx, colIdx) ? '' : 'border border-zinc-400']"
                                    :style="cellBarStyles(rowIdx, colIdx)"
                                    class="relative box-border flex aspect-square items-center justify-center overflow-hidden select-none"
                                    role="gridcell"
                                >
                                    <!-- Clue number -->
                                    <template x-if="getDisplayNumber(rowIdx, colIdx) !== null">
                                        <span
                                            class="absolute top-0 left-0.5 text-zinc-800 leading-none"
                                            :style="'font-size: ' + Math.max(8, Math.min(11, 600 / width * 0.22)) + 'px'"
                                            x-text="getDisplayNumber(rowIdx, colIdx)"
                                        ></span>
                                    </template>
                                    <!-- Circle -->
                                    <template x-if="hasCircle(rowIdx, colIdx)">
                                        <svg class="pointer-events-none absolute inset-0.5 size-[calc(100%-4px)]" viewBox="0 0 100 100">
                                            <circle cx="50" cy="50" r="46" fill="none" stroke="currentColor" stroke-width="2" class="text-zinc-500" />
                                        </svg>
                                    </template>
                                    <!-- Letter -->
                                    <span
                                        class="font-semibold uppercase"
                                        :class="letterClass(rowIdx, colIdx)"
                                        :style="letterFontStyle(rowIdx, colIdx)"
                                        x-text="isBlock(rowIdx, colIdx) ? '' : (progress[rowIdx]?.[colIdx] || '')"
                                    ></span>
                                    <!-- Incorrect marker -->
                                    <template x-if="checked[rowIdx + ',' + colIdx] === 'wrong'">
                                        <span class="absolute top-0 right-0.5 text-red-500 leading-none" :style="'font-size: ' + Math.max(6, Math.min(9, 600 / width * 0.18)) + 'px'">&#10007;</span>
                                    </template>
                                    <!-- Revealed marker -->
                                    <template x-if="revealed[rowIdx + ',' + colIdx]">
                                        <span class="absolute bottom-0 right-0.5 text-blue-500 leading-none" :style="'font-size: ' + Math.max(6, Math.min(9, 600 / width * 0.18)) + 'px'">&#9670;</span>
                                    </template>
                                </div>
                            </template>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Down clues (desktop) -->
            <div class="hidden w-56 flex-col overflow-hidden lg:flex">
                <h3 class="mb-2 text-sm font-semibold shrink-0">Down</h3>
                <div class="flex-1 space-y-0.5 overflow-y-auto" x-ref="downPanel">
                    <template x-for="clue in computedCluesDown" :key="'down-' + clue.number">
                        <div
                            x-on:click="selectClue('down', clue.number)"
                            :class="activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100' : 'hover:bg-zinc-100'"
                            class="cursor-pointer rounded px-2 py-1"
                        >
                            <div class="flex items-start gap-1.5">
                                <span class="mt-px text-xs font-bold text-zinc-600" x-text="clue.displayNumber"></span>
                                <div class="flex-1">
                                    <span class="text-sm text-zinc-800" x-text="clue.clue || '—'"></span>
                                    <span class="text-xs text-zinc-500" x-text="'(' + clue.length + ')'"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Mobile clue tabs -->
            <div class="lg:hidden">
                <div class="flex border-b border-zinc-300">
                    <button
                        x-on:click="mobileClueTab = 'across'"
                        :class="mobileClueTab === 'across' ? 'border-zinc-800 text-zinc-900' : 'border-transparent text-zinc-600'"
                        class="border-b-2 px-4 py-2 text-sm font-medium"
                    >Across</button>
                    <button
                        x-on:click="mobileClueTab = 'down'"
                        :class="mobileClueTab === 'down' ? 'border-zinc-800 text-zinc-900' : 'border-transparent text-zinc-600'"
                        class="border-b-2 px-4 py-2 text-sm font-medium"
                    >Down</button>
                </div>
                <div class="max-h-48 space-y-0.5 overflow-y-auto py-2">
                    <template x-if="mobileClueTab === 'across'">
                        <div>
                            <template x-for="clue in computedCluesAcross" :key="'m-across-' + clue.number">
                                <div
                                    x-on:click="selectClue('across', clue.number)"
                                    :class="activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100' : ''"
                                    class="cursor-pointer rounded px-2 py-1"
                                >
                                    <div class="flex items-start gap-1.5">
                                        <span class="mt-px text-xs font-bold text-zinc-600" x-text="clue.displayNumber"></span>
                                        <div class="flex-1">
                                            <span class="text-sm text-zinc-800" x-text="clue.clue || '—'"></span>
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
                                    :class="activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100' : ''"
                                    class="cursor-pointer rounded px-2 py-1"
                                >
                                    <div class="flex items-start gap-1.5">
                                        <span class="mt-px text-xs font-bold text-zinc-600" x-text="clue.displayNumber"></span>
                                        <div class="flex-1">
                                            <span class="text-sm text-zinc-800" x-text="clue.clue || '—'"></span>
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
    </div>
    `;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Initialize all embed elements on the page.
 */
Alpine.data('crosswordSolver', crosswordSolver);

document.addEventListener('DOMContentLoaded', async () => {
    const elements = document.querySelectorAll('[data-zorbl-embed]');

    for (const el of elements) {
        const crosswordId = el.dataset.crosswordId;
        if (!crosswordId) {
            el.innerHTML = '<p style="color:#999;">Missing crossword ID.</p>';
            continue;
        }

        const apiUrl = (el.dataset.apiUrl || '/api/embed/') + crosswordId;

        try {
            const resp = await fetch(apiUrl);
            if (!resp.ok) {
                el.innerHTML = '<p style="color:#999;">Puzzle not available.</p>';
                continue;
            }

            const data = await resp.json();

            // Decode obfuscated solution
            if (data.solution_encoding === 'xor_b64') {
                data.solution = decodeSolution(data.solution, data.id);
            }

            // Load saved progress from localStorage
            const persistence = createLocalStoragePersistence(data.id);
            const saved = persistence.load();

            // Build empty progress grid if needed
            let progress = [];
            for (let r = 0; r < data.height; r++) {
                progress[r] = [];
                for (let c = 0; c < data.width; c++) {
                    progress[r][c] = '';
                }
            }

            let initialElapsed = 0;
            let initialSolved = false;
            let initialPencilCells = {};

            if (saved) {
                if (saved.progress) progress = saved.progress;
                if (saved.elapsed) initialElapsed = saved.elapsed;
                if (saved.isCompleted) initialSolved = true;
                if (saved.pencilCells) initialPencilCells = saved.pencilCells;
            }

            // Merge prefilled cells into progress
            if (data.prefilled) {
                for (let r = 0; r < data.height; r++) {
                    for (let c = 0; c < data.width; c++) {
                        const pf = data.prefilled[r]?.[c];
                        if (pf) {
                            progress[r][c] = pf;
                        }
                    }
                }
            }

            // Set the Alpine data attributes for initialization
            el.innerHTML = buildTemplate(data);

            // Configure the x-data on the root element
            const root = el.querySelector('[x-data="crosswordSolver"]');
            if (root) {
                root.setAttribute('x-data', `crosswordSolver(${JSON.stringify({
                    width: data.width,
                    height: data.height,
                    grid: data.grid,
                    solution: data.solution,
                    progress: progress,
                    styles: data.styles || {},
                    prefilled: data.prefilled,
                    cluesAcross: data.clues_across || [],
                    cluesDown: data.clues_down || [],
                    initialElapsed: initialElapsed,
                    initialSolved: initialSolved,
                    initialPencilCells: initialPencilCells,
                    persistence: '__PERSISTENCE__',
                }).replace('"__PERSISTENCE__"', `zorblPersistence_${data.id}`)})`);

                // Expose persistence on window so Alpine can reference it
                window[`zorblPersistence_${data.id}`] = persistence;
            }
        } catch (err) {
            el.innerHTML = '<p style="color:#999;">Failed to load puzzle.</p>';
        }
    }

    Alpine.start();
});
