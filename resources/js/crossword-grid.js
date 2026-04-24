import {
    cellKey,
    isVoid,
    isBlock,
    hasCircle,
    hasBar,
    getCellColor,
    getCustomNumber,
    getDisplayNumber,
    hasLeftBoundary,
    hasRightBoundary,
    hasTopBoundary,
    hasBottomBoundary,
    findSlot,
    getClueNumberForCell,
    getWordCells,
    computeActiveWordCells,
    cleanupStyleEntry,
} from './grid/helpers.js';
import { numberGrid as runNumberGrid } from './grid/numbering.js';
import { cloneForWire, createAutosave } from './grid/persistence.js';

const HIGHLIGHT_AUTO_CLEAR_MS = 8000;
const WORD_SUGGEST_DEBOUNCE_MS = 300;
const LONG_PRESS_MS = 500;

export function crosswordGrid({
    width, height, grid, solution, styles, cluesAcross, cluesDown,
    minAnswerLength, prefilled, gridLocked,
}) {
    return {
        // --- State -----------------------------------------------------------
        width,
        height,
        grid,
        solution,
        styles: (styles && !Array.isArray(styles)) ? styles : {},
        cluesAcross: cluesAcross || [],
        cluesDown: cluesDown || [],
        minAnswerLength: minAnswerLength || 3,
        prefilled: prefilled || null,
        gridLocked: !!gridLocked,
        selectedRow: -1,
        selectedCol: -1,
        direction: 'across',
        mode: 'edit',
        symmetry: true,
        isDirty: false,
        saving: false,
        showSaved: false,
        clueSuggestions: [],
        clueSuggestionsLoading: false,
        clueSuggestionsWord: '',
        showSuggestions: false,
        showWordSuggestions: false,
        wordSuggestions: [],
        wordSuggestionsLoading: false,
        wordSuggestionsPattern: '',
        showRebusInput: false,
        rebusInputValue: '',
        rebusCells: [],
        showCustomNumberInput: false,
        customNumberInputValue: '',
        customNumberCells: [],
        incompleteHighlights: [],
        rebusMode: false,
        multiSelectedCells: {},
        contextMenu: { show: false, row: -1, col: -1, x: 0, y: 0 },
        fillInProgress: false,
        fillMode: null,

        // Internal: timers and listeners. Kept on `this` so destroy() can clean them up.
        _autosave: null,
        _longPressTimer: null,
        _wordSuggestTimer: null,
        _highlightTimer: null,
        _onDocClick: null,
        _onDocMousedown: null,
        _onBeforeUnload: null,
        _onLivewireNavigating: null,

        // --- Lifecycle -------------------------------------------------------
        init() {
            this._autosave = createAutosave({
                save: () => this._performSave(),
                isDirty: () => this.isDirty,
                setDirty: (v) => { this.isDirty = v; },
                setSaving: (v) => { this.saving = v; },
                setShowSaved: (v) => { this.showSaved = v; },
            });

            this._onDocClick = () => this.closeContextMenu();
            this._onDocMousedown = (e) => this.handleClickOutside(e);
            this._onBeforeUnload = (e) => {
                if (this.isDirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            };
            this._onLivewireNavigating = () => {
                if (this.isDirty && !this._autosave?.isInFlight()) {
                    this.$wire.save(
                        cloneForWire(this.grid),
                        cloneForWire(this.solution),
                        Object.keys(this.styles).length > 0 ? cloneForWire(this.styles) : null,
                        cloneForWire(this.cluesAcross),
                        cloneForWire(this.cluesDown),
                    );
                    this.isDirty = false;
                }
            };

            document.addEventListener('click', this._onDocClick);
            document.addEventListener('mousedown', this._onDocMousedown);
            window.addEventListener('beforeunload', this._onBeforeUnload);
            document.addEventListener('livewire:navigating', this._onLivewireNavigating);

            this.$watch('isDirty', (val) => { if (val) this._autosave.scheduleSave(); });
            this.$watch('activeClueNumber', () => this.closeSuggestions());
            this.$watch('direction', () => this.closeSuggestions());
        },

        destroy() {
            if (this._onDocClick) document.removeEventListener('click', this._onDocClick);
            if (this._onDocMousedown) document.removeEventListener('mousedown', this._onDocMousedown);
            if (this._onBeforeUnload) window.removeEventListener('beforeunload', this._onBeforeUnload);
            if (this._onLivewireNavigating) document.removeEventListener('livewire:navigating', this._onLivewireNavigating);
            this._onDocClick = this._onDocMousedown = this._onBeforeUnload = this._onLivewireNavigating = null;

            this._autosave?.destroy();
            clearTimeout(this._longPressTimer);
            clearTimeout(this._wordSuggestTimer);
            clearTimeout(this._highlightTimer);
        },

        // --- Cell / slot helpers (delegate to grid/helpers) -----------------
        isVoid(row, col)        { return isVoid(this.grid, row, col); },
        isBlock(row, col)       { return isBlock(this.grid, row, col); },
        hasCircle(row, col)     { return hasCircle(this.styles, row, col); },
        hasBar(row, col, edge)  { return hasBar(this.styles, row, col, edge); },
        getCellColor(row, col)  { return getCellColor(this.styles, row, col); },
        getCustomNumber(row, col){ return getCustomNumber(this.styles, row, col); },
        getDisplayNumber(row, col){ return getDisplayNumber(this.grid, this.styles, row, col); },
        hasLeftBoundary(row, col)  { return hasLeftBoundary(this.grid, this.styles, row, col); },
        hasRightBoundary(row, col) { return hasRightBoundary(this.grid, this.styles, this.width, row, col); },
        hasTopBoundary(row, col)   { return hasTopBoundary(this.grid, this.styles, row, col); },
        hasBottomBoundary(row, col){ return hasBottomBoundary(this.grid, this.styles, this.height, row, col); },
        findSlot(dir, n) {
            return findSlot(this.grid, this.styles, this.width, this.height, dir, n);
        },
        getClueNumberForCell(r, c, d) {
            return getClueNumberForCell(this.grid, this.styles, r, c, d);
        },
        getWordCells(r, c, d) {
            return getWordCells(this.grid, this.styles, this.width, this.height, r, c, d);
        },

        // --- Grid numbering --------------------------------------------------
        numberGrid() {
            const result = runNumberGrid({
                grid: this.grid,
                width: this.width,
                height: this.height,
                styles: this.styles,
                minLength: this.minAnswerLength,
                cluesAcross: this.cluesAcross,
                cluesDown: this.cluesDown,
            });
            this.cluesAcross = result.cluesAcross;
            this.cluesDown = result.cluesDown;
        },

        // --- Custom numbers --------------------------------------------------
        setCustomNumber(row, col, number) {
            const key = cellKey(row, col);
            if (number !== null && number !== '') {
                this.styles[key] = { ...this.styles[key], number: parseInt(number, 10) };
                return;
            }
            const entry = { ...this.styles[key] };
            delete entry.number;
            this.styles[key] = entry;
            cleanupStyleEntry(this.styles, key);
        },

        // --- Computed clue lists --------------------------------------------
        get computedCluesAcross() {
            return this.cluesAcross.map(clue => {
                const slot = this.findSlot('across', clue.number);
                clue.length = slot ? slot.length : 0;
                clue.displayNumber = slot ? (this.getCustomNumber(slot.row, slot.col) ?? clue.number) : clue.number;
                return clue;
            });
        },

        get computedCluesDown() {
            return this.cluesDown.map(clue => {
                const slot = this.findSlot('down', clue.number);
                clue.length = slot ? slot.length : 0;
                clue.displayNumber = slot ? (this.getCustomNumber(slot.row, slot.col) ?? clue.number) : clue.number;
                return clue;
            });
        },

        // --- Active clue tracking --------------------------------------------
        get activeClueNumber() {
            if (this.selectedRow < 0) return -1;
            return this.getClueNumberForCell(this.selectedRow, this.selectedCol, this.direction);
        },

        // Cached membership for the active word. Alpine evaluates getters lazily
        // and dedupes within a render pass, so this collapses ~width*height
        // linear scans down to one.
        get activeWordCellSet() {
            return computeActiveWordCells(
                this.grid, this.styles, this.width, this.height,
                this.selectedRow, this.selectedCol, this.direction,
            );
        },

        // --- Cell appearance --------------------------------------------------
        cellClasses(row, col) {
            if (this.isVoid(row, col)) {
                return this.mode === 'edit'
                    ? 'bg-transparent hover:bg-zinc-200/40 dark:hover:bg-zinc-700/30 cursor-pointer'
                    : 'invisible';
            }
            if (this.isBlock(row, col)) {
                return 'bg-zinc-800 hover:bg-zinc-700 dark:bg-zinc-300 dark:hover:bg-zinc-200 cursor-pointer';
            }

            const isSelected = row === this.selectedRow && col === this.selectedCol;
            const isMulti = this.isMultiSelected(row, col);
            const isInWord = this.activeWordCellSet.has(cellKey(row, col));

            const emptyHighlight = this.isCellIncomplete() && !this.solution[row]?.[col]?.trim()
                ? ' ring-2 ring-inset ring-amber-400 dark:ring-amber-500' : '';

            if (isMulti) {
                return 'bg-emerald-200 hover:bg-emerald-300 dark:bg-emerald-700 dark:hover:bg-emerald-600 cursor-pointer' + emptyHighlight;
            }
            if (isSelected) {
                return 'bg-blue-300 hover:bg-blue-400 dark:bg-blue-700 dark:hover:bg-blue-600 cursor-pointer' + emptyHighlight;
            }
            if (isInWord) {
                return 'bg-blue-100 hover:bg-blue-200 dark:bg-blue-900/50 dark:hover:bg-blue-900/70 cursor-pointer' + emptyHighlight;
            }
            if (this.getCellColor(row, col)) {
                // Background-color is set inline in cellBarStyles; CSS hover
                // can't override it, so darken via brightness instead.
                return 'cursor-pointer transition-[filter] hover:brightness-95 dark:hover:brightness-110' + emptyHighlight;
            }
            return 'bg-zinc-50 hover:bg-zinc-100 dark:bg-zinc-800 dark:hover:bg-zinc-700/70 cursor-pointer' + emptyHighlight;
        },

        // Dotted seams between adjacent void cells, edit-mode only. Regular
        // cell seams + the thick puzzle outline are drawn via box-shadow in
        // cellBarStyles so the outline stays unbroken at intersections.
        cellBorderClasses(row, col) {
            if (this.mode !== 'edit') return '';
            if (!this.isVoid(row, col)) return '';
            const classes = ['border-dashed', 'border-zinc-200/50', 'dark:border-zinc-700/50'];
            if (row > 0                && this.isVoid(row - 1, col)) classes.push('border-t');
            if (row < this.height - 1  && this.isVoid(row + 1, col)) classes.push('border-b');
            if (col > 0                && this.isVoid(row, col - 1)) classes.push('border-l');
            if (col < this.width - 1   && this.isVoid(row, col + 1)) classes.push('border-r');
            return classes.join(' ');
        },

        cellBarStyles(row, col) {
            if (this.isVoid(row, col)) return '';

            const key = cellKey(row, col);
            const entry = this.styles[key];
            const parts = [];

            // box-shadow renders the FIRST listed shadow on top, so the order is:
            // user bars → thick puzzle outline → thin internal seams. The thick
            // outline draws over any seam that would otherwise break it at the
            // corner where a seam meets the puzzle edge.
            const shadows = [];

            const bars = entry?.bars;
            if (bars && bars.length > 0) {
                if (bars.includes('top'))    shadows.push('inset 0 2px 0 0 var(--bar-color)');
                if (bars.includes('bottom')) shadows.push('inset 0 -2px 0 0 var(--bar-color)');
                if (bars.includes('left'))   shadows.push('inset 2px 0 0 0 var(--bar-color)');
                if (bars.includes('right'))  shadows.push('inset -2px 0 0 0 var(--bar-color)');
            }

            const topMissing    = row === 0              || this.isVoid(row - 1, col);
            const bottomMissing = row === this.height - 1 || this.isVoid(row + 1, col);
            const leftMissing   = col === 0              || this.isVoid(row, col - 1);
            const rightMissing  = col === this.width - 1  || this.isVoid(row, col + 1);
            if (topMissing)    shadows.push('inset 0 2px 0 0 var(--bar-color)');
            if (bottomMissing) shadows.push('inset 0 -2px 0 0 var(--bar-color)');
            if (leftMissing)   shadows.push('inset 2px 0 0 0 var(--bar-color)');
            if (rightMissing)  shadows.push('inset -2px 0 0 0 var(--bar-color)');
            if (!topMissing)    shadows.push('inset 0 1px 0 0 var(--color-line-strong)');
            if (!bottomMissing) shadows.push('inset 0 -1px 0 0 var(--color-line-strong)');
            if (!leftMissing)   shadows.push('inset 1px 0 0 0 var(--color-line-strong)');
            if (!rightMissing)  shadows.push('inset -1px 0 0 0 var(--color-line-strong)');

            if (shadows.length > 0) parts.push('box-shadow: ' + shadows.join(', '));

            const color = entry?.color;
            if (color && !this.isBlock(row, col)) {
                const isSelected = row === this.selectedRow && col === this.selectedCol;
                const isMulti = this.isMultiSelected(row, col);
                const isInWord = this.activeWordCellSet.has(key);
                if (!isSelected && !isMulti && !isInWord) parts.push('background-color: ' + color);
            }

            return parts.join('; ');
        },

        isRebus(row, col) {
            return (this.solution[row]?.[col] || '').length > 1;
        },

        letterFontStyle(row, col) {
            const val = this.solution[row]?.[col] || '';
            const baseFontSize = clamp(12, 24, 600 / this.width * 0.55);
            if (val.length <= 1) return 'font-size: ' + baseFontSize + 'px';
            const scaled = Math.max(6, baseFontSize / Math.max(val.length * 0.55, 1));
            return 'font-size: ' + scaled + 'px; letter-spacing: -0.5px';
        },

        // --- Multi-selection -------------------------------------------------
        isMultiSelected(row, col) {
            return !!this.multiSelectedCells[cellKey(row, col)];
        },

        clearMultiSelection() {
            this.multiSelectedCells = {};
        },

        getMultiSelectedCoords() {
            return Object.keys(this.multiSelectedCells).map(key => {
                const [r, c] = key.split(',').map(Number);
                return [r, c];
            });
        },

        handleClickOutside(event) {
            if (this.selectedRow < 0 && this.selectedCol < 0
                && Object.keys(this.multiSelectedCells).length === 0) {
                return;
            }

            // An open overlay (context menu, rebus prompt, custom-number prompt)
            // is a sibling of the grid container, so clicks in it otherwise
            // look "outside" and would clear the very selection the overlay is
            // acting on. Suppress the deselect while any overlay is open.
            if (this.contextMenu.show || this.showRebusInput || this.showCustomNumberInput) {
                return;
            }

            const target = event.target;
            const grid = this.$refs.gridContainer;
            const across = this.$refs.acrossPanel;
            const down = this.$refs.downPanel;

            if (grid?.contains(target) || across?.contains(target) || down?.contains(target)) {
                return;
            }

            this.selectedRow = -1;
            this.selectedCol = -1;
            this.clearMultiSelection();

            if (grid && document.activeElement && grid.contains(document.activeElement)) {
                document.activeElement.blur();
            }
        },

        // --- Selection -------------------------------------------------------
        selectCell(row, col, event) {
            if (this.isVoid(row, col)) {
                if (this.mode === 'edit' && !this.gridLocked && !(event && (event.ctrlKey || event.metaKey))) {
                    this.toggleVoid(row, col);
                }
                return;
            }

            // Ctrl/Cmd+click → multi-select
            if (event && (event.ctrlKey || event.metaKey)) {
                if (this.isBlock(row, col)) return;
                const key = cellKey(row, col);
                if (this.multiSelectedCells[key]) delete this.multiSelectedCells[key];
                else this.multiSelectedCells[key] = true;
                // Also include the primary selected cell when starting fresh.
                if (Object.keys(this.multiSelectedCells).length === 1 && this.selectedRow >= 0) {
                    const primaryKey = cellKey(this.selectedRow, this.selectedCol);
                    if (!this.isBlock(this.selectedRow, this.selectedCol)) {
                        this.multiSelectedCells[primaryKey] = true;
                    }
                }
                this.$refs.gridContainer?.focus();
                return;
            }

            this.clearMultiSelection();

            if (this.isBlock(row, col)) {
                // Block cells aren't selectable; double-click toggles them.
                return;
            }

            if (this.selectedRow === row && this.selectedCol === col) {
                this.direction = this.direction === 'across' ? 'down' : 'across';
            } else {
                this.selectedRow = row;
                this.selectedCol = col;
            }

            this.scrollActiveClueIntoView();
            this.$refs.gridContainer?.focus();
        },

        // Double-click toggles a cell between black and white. Not for void
        // cells (those use single-click) and not while the grid is locked.
        toggleBlockOnDblClick(row, col) {
            if (this.mode !== 'edit') return;
            if (this.gridLocked) return;
            if (this.isVoid(row, col)) return;
            this.toggleBlock(row, col);
        },

        selectClue(dir, number, event) {
            this.direction = dir;
            const clues = dir === 'across' ? this.cluesAcross : this.cluesDown;
            const clue = clues.find(c => c.number === number);
            if (!clue) return;

            const clickedInput = event?.target?.tagName === 'INPUT'
                || event?.target?.closest?.('.clue-content');

            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (this.grid[row][col] === number) {
                        this.selectedRow = row;
                        this.selectedCol = col;
                        if (!clickedInput) this.$refs.gridContainer?.focus();
                        return;
                    }
                }
            }
        },

        focusNextClue(currentEl, dir, reverse) {
            const parent = currentEl.parentElement;
            if (!parent) return;
            const siblings = [...parent.children].filter(el => el.nodeType === 1);
            const idx = siblings.indexOf(currentEl);
            if (idx < 0) return;

            const nextIdx = reverse
                ? (idx <= 0 ? siblings.length - 1 : idx - 1)
                : (idx >= siblings.length - 1 ? 0 : idx + 1);

            const nextClue = siblings[nextIdx];
            const input = nextClue?.querySelector('input');
            if (input) input.focus();
            else nextClue?.focus();
        },

        scrollActiveClueIntoView() {
            this.$nextTick(() => {
                const num = this.activeClueNumber;
                if (num < 0) return;
                const panel = this.direction === 'across' ? this.$refs.acrossPanel : this.$refs.downPanel;
                const el = document.getElementById('clue-' + this.direction + '-' + num);
                if (el && panel) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            });
        },

        // --- Keyboard handling ------------------------------------------------
        handleKeydown(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

            const key = e.key;

            if (key === 'Escape') {
                if (this.contextMenu.show) { this.closeContextMenu(); return; }
                if (Object.keys(this.multiSelectedCells).length > 0) { this.clearMultiSelection(); return; }
                this.selectedRow = -1;
                this.selectedCol = -1;
                return;
            }

            if (this.selectedRow < 0) return;

            if (key === 'Insert') {
                e.preventDefault();
                if (!this.isBlock(this.selectedRow, this.selectedCol) && !this.gridLocked) {
                    this.rebusMode = !this.rebusMode;
                    if (this.rebusMode) {
                        this.solution[this.selectedRow][this.selectedCol] = '';
                        this.markDirty();
                    }
                }
                return;
            }

            if (this.rebusMode) {
                if (key === 'Escape') { e.preventDefault(); this.rebusMode = false; return; }
                if (key === 'Enter' || key === 'Tab') {
                    e.preventDefault();
                    this.rebusMode = false;
                    this.advanceCursor();
                    return;
                }
                if (key === 'Backspace') {
                    e.preventDefault();
                    const val = this.solution[this.selectedRow][this.selectedCol] || '';
                    this.solution[this.selectedRow][this.selectedCol] = val.slice(0, -1);
                    this.markDirty();
                    return;
                }
                if (/^[a-zA-Z0-9]$/.test(key)) {
                    e.preventDefault();
                    const current = this.solution[this.selectedRow][this.selectedCol] || '';
                    this.solution[this.selectedRow][this.selectedCol] = current + key.toUpperCase();
                    this.markDirty();
                    return;
                }
                return;
            }

            if (key === 'ArrowUp' || key === 'ArrowDown' || key === 'ArrowLeft' || key === 'ArrowRight') {
                e.preventDefault();
                this.moveArrow(key);
                return;
            }

            if (key === 'Tab') { e.preventDefault(); this.jumpToNextClue(e.shiftKey); return; }

            if (key === 'Enter') {
                e.preventDefault();
                this.direction = this.direction === 'across' ? 'down' : 'across';
                this.scrollActiveClueIntoView();
                return;
            }

            if (key === ' ' && this.mode === 'edit' && !this.gridLocked) {
                e.preventDefault();
                this.toggleBlock(this.selectedRow, this.selectedCol);
                return;
            }

            if (this.gridLocked) return; // navigation/direction toggle is fine; mutations are not

            if (key === 'Backspace') {
                e.preventDefault();
                this.handleBackspace();
                this.debouncedRefreshWordSuggestions();
                return;
            }

            if (key === 'Delete') {
                e.preventDefault();
                if (!this.isBlock(this.selectedRow, this.selectedCol)) {
                    this.solution[this.selectedRow][this.selectedCol] = '';
                    this.markDirty();
                    this.debouncedRefreshWordSuggestions();
                }
                return;
            }

            if (/^[a-zA-Z]$/.test(key)) {
                e.preventDefault();
                if (!this.isBlock(this.selectedRow, this.selectedCol)) {
                    this.solution[this.selectedRow][this.selectedCol] = key.toUpperCase();
                    this.advanceCursor();
                    this.markDirty();
                    this.debouncedRefreshWordSuggestions();
                }
            }
        },

        moveArrow(key) {
            let row = this.selectedRow;
            let col = this.selectedCol;

            const delta = {
                ArrowUp: [-1, 0], ArrowDown: [1, 0], ArrowLeft: [0, -1], ArrowRight: [0, 1],
            }[key];

            do {
                row += delta[0];
                col += delta[1];
            } while (
                row >= 0 && row < this.height
                && col >= 0 && col < this.width
                && this.isBlock(row, col)
            );

            if (row >= 0 && row < this.height && col >= 0 && col < this.width && !this.isVoid(row, col)) {
                this.selectedRow = row;
                this.selectedCol = col;
                if (key === 'ArrowLeft' || key === 'ArrowRight') this.direction = 'across';
                if (key === 'ArrowUp' || key === 'ArrowDown') this.direction = 'down';
                this.scrollActiveClueIntoView();
            }
        },

        advanceCursor() {
            const row = this.selectedRow;
            const col = this.selectedCol;
            if (this.direction === 'across') {
                if (!this.hasRightBoundary(row, col)) this.selectedCol = col + 1;
            } else {
                if (!this.hasBottomBoundary(row, col)) this.selectedRow = row + 1;
            }
        },

        handleBackspace() {
            const row = this.selectedRow;
            const col = this.selectedCol;

            if (!this.isBlock(row, col) && this.solution[row][col]) {
                this.solution[row][col] = '';
                this.markDirty();
                return;
            }
            if (this.direction === 'across') {
                if (!this.hasLeftBoundary(row, col)) {
                    this.selectedCol = col - 1;
                    this.solution[row][col - 1] = '';
                    this.markDirty();
                }
            } else {
                if (!this.hasTopBoundary(row, col)) {
                    this.selectedRow = row - 1;
                    this.solution[row - 1][col] = '';
                    this.markDirty();
                }
            }
        },

        jumpToNextClue(reverse) {
            const clues = this.direction === 'across' ? this.cluesAcross : this.cluesDown;
            if (clues.length === 0) return;
            const currentNum = this.activeClueNumber;
            const idx = clues.findIndex(c => c.number === currentNum);
            const nextIdx = reverse
                ? (idx <= 0 ? clues.length - 1 : idx - 1)
                : (idx >= clues.length - 1 ? 0 : idx + 1);
            this.selectClue(this.direction, clues[nextIdx].number);
        },

        // --- Block / void toggling -------------------------------------------
        toggleBlock(row, col) {
            const becomingBlock = !this.isBlock(row, col);
            this._writeCell(row, col, becomingBlock ? '#' : 0, becomingBlock ? '#' : '');
            if (this.symmetry) {
                const symRow = this.height - 1 - row;
                const symCol = this.width - 1 - col;
                this._writeCell(symRow, symCol, becomingBlock ? '#' : 0, becomingBlock ? '#' : '');
            }
            if (becomingBlock && this.selectedRow === row && this.selectedCol === col) {
                this.selectedRow = -1;
                this.selectedCol = -1;
            }
            this.numberGrid();
            this.markDirty();
        },

        toggleVoid(row, col) {
            const becomingVoid = !this.isVoid(row, col);
            this._writeCell(row, col, becomingVoid ? null : 0, becomingVoid ? null : '');
            if (this.symmetry) {
                const symRow = this.height - 1 - row;
                const symCol = this.width - 1 - col;
                this._writeCell(symRow, symCol, becomingVoid ? null : 0, becomingVoid ? null : '');
            }
            if (becomingVoid && this.selectedRow === row && this.selectedCol === col) {
                this.selectedRow = -1;
                this.selectedCol = -1;
            }
            this.numberGrid();
            this.markDirty();
        },

        _writeCell(row, col, gridValue, solutionValue) {
            this.grid[row][col] = gridValue;
            this.solution[row][col] = solutionValue;
            // A block / void cell can't have annotations.
            if (gridValue === '#' || gridValue === null) {
                delete this.styles[cellKey(row, col)];
            }
        },

        // --- Annotations -----------------------------------------------------
        toggleCircle() {
            if (this.selectedRow < 0 || this.isBlock(this.selectedRow, this.selectedCol)) return;
            this.setCircle(this.selectedRow, this.selectedCol, !this.hasCircle(this.selectedRow, this.selectedCol));
            this.markDirty();
        },

        setCircle(row, col, addCircle) {
            if (this.isBlock(row, col)) return;
            const key = cellKey(row, col);
            if (addCircle) {
                this.styles[key] = { ...this.styles[key], shapebg: 'circle' };
                return;
            }
            const entry = { ...this.styles[key] };
            delete entry.shapebg;
            this.styles[key] = entry;
            cleanupStyleEntry(this.styles, key);
        },

        setCellColor(row, col, color) {
            if (this.isBlock(row, col)) return;
            const key = cellKey(row, col);
            if (color) {
                this.styles[key] = { ...this.styles[key], color };
                return;
            }
            const entry = { ...this.styles[key] };
            delete entry.color;
            this.styles[key] = entry;
            cleanupStyleEntry(this.styles, key);
        },

        // --- Bars ------------------------------------------------------------
        toggleBar(row, col, edge) {
            this._mutateBars(row, col, (bars) => {
                const idx = bars.indexOf(edge);
                if (idx >= 0) bars.splice(idx, 1);
                else bars.push(edge);
            });
            this.markDirty();
        },

        setBar(row, col, edge, addBar) {
            if (this.isBlock(row, col)) return;
            this._mutateBars(row, col, (bars) => {
                const idx = bars.indexOf(edge);
                if (addBar && idx < 0) bars.push(edge);
                else if (!addBar && idx >= 0) bars.splice(idx, 1);
            });
        },

        _mutateBars(row, col, mutate) {
            const key = cellKey(row, col);
            const entry = this.styles[key] ? { ...this.styles[key] } : {};
            const bars = entry.bars ? [...entry.bars] : [];
            mutate(bars);
            if (bars.length > 0) entry.bars = bars;
            else delete entry.bars;
            this.styles[key] = entry;
            cleanupStyleEntry(this.styles, key);
        },

        // --- Context menu -----------------------------------------------------
        openContextMenu(row, col, event) {
            if (this.gridLocked) return;
            this.contextMenu.x = event.clientX;
            this.contextMenu.y = event.clientY;
            this.contextMenu.row = row;
            this.contextMenu.col = col;
            this.contextMenu.show = true;

            if (Object.keys(this.multiSelectedCells).length > 0 && !this.isMultiSelected(row, col)) {
                this.clearMultiSelection();
            }

            this.$nextTick(() => this.repositionContextMenu(event.clientX, event.clientY));
        },

        // Keep the menu fully on-screen, flipping above/left of the click
        // when there isn't enough room below/right.
        repositionContextMenu(clickX, clickY) {
            const menu = this.$refs.contextMenu;
            if (!menu) return;
            const margin = 8;
            const { width, height } = menu.getBoundingClientRect();
            const vw = window.innerWidth;
            const vh = window.innerHeight;

            let x = clickX;
            if (x + width > vw - margin) x = clickX - width;
            if (x < margin) x = margin;
            if (x + width > vw - margin) x = vw - width - margin;

            let y = clickY;
            if (y + height > vh - margin) y = clickY - height;
            if (y < margin) y = margin;
            if (y + height > vh - margin) y = vh - height - margin;

            this.contextMenu.x = x;
            this.contextMenu.y = y;
        },

        closeContextMenu() {
            this.contextMenu.show = false;
        },

        contextToggleBlock() {
            const cells = this.getMultiSelectedCoords();
            if (cells.length > 0) {
                const makeBlock = !this.isBlock(this.contextMenu.row, this.contextMenu.col);
                for (const [r, c] of cells) {
                    if (makeBlock !== this.isBlock(r, c)) this.toggleBlock(r, c);
                }
                this.clearMultiSelection();
            } else {
                this.toggleBlock(this.contextMenu.row, this.contextMenu.col);
            }
            this.closeContextMenu();
        },

        contextToggleVoid() {
            const cells = this.getMultiSelectedCoords();
            if (cells.length > 0) {
                const makeVoid = !this.isVoid(this.contextMenu.row, this.contextMenu.col);
                for (const [r, c] of cells) {
                    if (makeVoid !== this.isVoid(r, c)) this.toggleVoid(r, c);
                }
                this.clearMultiSelection();
            } else {
                this.toggleVoid(this.contextMenu.row, this.contextMenu.col);
            }
            this.closeContextMenu();
        },

        contextToggleCircle() {
            const addCircle = !this.hasCircle(this.contextMenu.row, this.contextMenu.col);
            const cells = this.getMultiSelectedCoords();
            const targets = cells.length > 0 ? cells : [[this.contextMenu.row, this.contextMenu.col]];
            for (const [r, c] of targets) this.setCircle(r, c, addCircle);
            if (cells.length > 0) this.clearMultiSelection();
            this.markDirty();
            this.closeContextMenu();
        },

        contextSetColor(color) {
            const cells = this.getMultiSelectedCoords();
            const targets = cells.length > 0 ? cells : [[this.contextMenu.row, this.contextMenu.col]];
            for (const [r, c] of targets) this.setCellColor(r, c, color);
            if (cells.length > 0) this.clearMultiSelection();
            this.markDirty();
            this.closeContextMenu();
        },

        contextClearColor() {
            this.contextSetColor(null);
        },

        contextToggleBar(edge) {
            const addBar = !this.hasBar(this.contextMenu.row, this.contextMenu.col, edge);
            const cells = this.getMultiSelectedCoords();
            const targets = cells.length > 0 ? cells : [[this.contextMenu.row, this.contextMenu.col]];
            for (const [r, c] of targets) this.setBar(r, c, edge, addBar);
            if (cells.length > 0) this.clearMultiSelection();
            this.markDirty();
        },

        // --- Pre-fill / Rebus ------------------------------------------------
        contextEditRebus() {
            const row = this.contextMenu.row;
            const col = this.contextMenu.col;
            if (this.isBlock(row, col)) return;

            const cells = this.getMultiSelectedCoords();
            this.rebusCells = cells.length > 0
                ? cells.filter(([r, c]) => !this.isBlock(r, c))
                : [[row, col]];

            this.rebusInputValue = this.solution[row]?.[col] || '';
            this.showRebusInput = true;
            this.closeContextMenu();

            this.$nextTick(() => {
                this.$refs.rebusInput?.focus();
                this.$refs.rebusInput?.select();
            });
        },

        applyRebus() {
            const value = this.rebusInputValue.toUpperCase().trim();
            this.showRebusInput = false;

            if (!value) {
                this.$refs.gridContainer?.focus();
                return;
            }

            this.ensurePrefillGrid();

            for (const [row, col] of this.rebusCells) {
                if (this.isBlock(row, col)) continue;
                this.solution[row][col] = value;
                this.prefilled[row][col] = value;
            }

            this.clearMultiSelection();
            this.markDirty();
            this.savePrefilled();
            this.$refs.gridContainer?.focus();
        },

        cancelRebus() {
            this.showRebusInput = false;
            this.rebusInputValue = '';
            this.rebusCells = [];
            this.$refs.gridContainer?.focus();
        },

        clearRebus() {
            this.ensurePrefillGrid();
            let prefillDirty = false;

            for (const [row, col] of this.rebusCells) {
                this.solution[row][col] = '';
                if (this.prefilled[row]?.[col]) {
                    this.prefilled[row][col] = '';
                    prefillDirty = true;
                }
            }

            if (prefillDirty) this.savePrefilled();

            this.clearMultiSelection();
            this.showRebusInput = false;
            this.markDirty();
            this.$refs.gridContainer?.focus();
        },

        // --- Custom number overlay -------------------------------------------
        contextSetCustomNumber() {
            const row = this.contextMenu.row;
            const col = this.contextMenu.col;
            if (this.isBlock(row, col)) return;

            const cells = this.getMultiSelectedCoords();
            this.customNumberCells = cells.length > 0
                ? cells.filter(([r, c]) => !this.isBlock(r, c))
                : [[row, col]];

            const existing = this.getCustomNumber(row, col);
            this.customNumberInputValue = existing !== null ? String(existing) : '';
            this.showCustomNumberInput = true;
            this.closeContextMenu();

            this.$nextTick(() => {
                this.$refs.customNumberInput?.focus();
                this.$refs.customNumberInput?.select();
            });
        },

        applyCustomNumber() {
            const value = this.customNumberInputValue.trim();
            this.showCustomNumberInput = false;

            if (value === '') {
                this.$refs.gridContainer?.focus();
                return;
            }

            for (const [row, col] of this.customNumberCells) {
                if (this.isBlock(row, col)) continue;
                this.setCustomNumber(row, col, value);
            }

            this.clearMultiSelection();
            this.markDirty();
            this.$refs.gridContainer?.focus();
        },

        cancelCustomNumber() {
            this.showCustomNumberInput = false;
            this.customNumberInputValue = '';
            this.customNumberCells = [];
            this.$refs.gridContainer?.focus();
        },

        removeCustomNumber() {
            for (const [row, col] of this.customNumberCells) {
                this.setCustomNumber(row, col, null);
            }
            this.clearMultiSelection();
            this.showCustomNumberInput = false;
            this.markDirty();
            this.$refs.gridContainer?.focus();
        },

        ensurePrefillGrid() {
            if (this.prefilled) return;
            this.prefilled = Array.from({ length: this.height },
                () => Array(this.width).fill(''));
        },

        // --- Long press (mobile) ---------------------------------------------
        startLongPress(row, col, event) {
            const touch = event.touches[0];
            this._longPressTimer = setTimeout(() => {
                this.openContextMenu(row, col, { clientX: touch.clientX, clientY: touch.clientY });
            }, LONG_PRESS_MS);
        },

        cancelLongPress() {
            clearTimeout(this._longPressTimer);
        },

        // --- Clear -----------------------------------------------------------
        clearLetters() {
            if (this.gridLocked) return;
            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (!this.isBlock(row, col)) this.solution[row][col] = '';
                }
            }
            this.markDirty();
        },

        clearAll() {
            if (this.gridLocked) return;
            this.grid = Array.from({ length: this.height }, () => Array(this.width).fill(0));
            this.solution = Array.from({ length: this.height }, () => Array(this.width).fill(''));
            this.styles = {};
            this.selectedRow = -1;
            this.selectedCol = -1;
            this.numberGrid();
            this.markDirty();
        },

        // --- Persistence -----------------------------------------------------
        markDirty() {
            this.isDirty = true;
        },

        // Manual save (kept for compatibility with templates that call it).
        async saveNow() {
            if (this._autosave) await this._autosave.flush();
        },

        async _performSave() {
            await this.$wire.save(
                cloneForWire(this.grid),
                cloneForWire(this.solution),
                Object.keys(this.styles).length > 0 ? cloneForWire(this.styles) : null,
                cloneForWire(this.cluesAcross),
                cloneForWire(this.cluesDown),
            );
        },

        onSaved() {
            this._autosave?.acknowledge();
        },

        isPrefilled(row, col) {
            if (!this.prefilled) return false;
            return !!this.prefilled[row]?.[col];
        },

        async savePrefilled() {
            if (!this.prefilled) return;
            this.saving = true;
            this.showSaved = false;
            await this.$wire.savePrefilled(cloneForWire(this.prefilled));
            this.saving = false;
        },

        onSettingsUpdated() {
            const newMin = this.$wire.minAnswerLength;
            if (newMin !== this.minAnswerLength) {
                this.minAnswerLength = newMin;
                this.numberGrid();
                this.markDirty();
            }
        },

        // --- Highlights ------------------------------------------------------
        highlightIncomplete(checks) {
            this.incompleteHighlights = checks;
            clearTimeout(this._highlightTimer);
            this._highlightTimer = setTimeout(() => {
                this.incompleteHighlights = [];
                this._highlightTimer = null;
            }, HIGHLIGHT_AUTO_CLEAR_MS);
        },

        isClueIncomplete(dir) {
            return this.incompleteHighlights.includes(dir === 'across' ? 'clues_across' : 'clues_down');
        },

        isCellIncomplete() {
            return this.incompleteHighlights.includes('fill');
        },

        // --- Clue quality indicators -----------------------------------------
        clueQuality(clue, dir) {
            const text = (clue.clue || '').trim();
            if (!text) return [];

            const issues = [];
            const answer = this.getAnswerForSlot(dir, clue.number);

            if (text.length < 4) {
                issues.push({ type: 'short', message: 'Clue may be too short' });
            }

            if (answer && answer.length > 1) {
                if (text.toLowerCase().includes(answer.toLowerCase())) {
                    issues.push({ type: 'answer', message: 'Clue contains the answer' });
                }
            }

            const allClues = [...this.cluesAcross, ...this.cluesDown];
            const dupes = allClues.filter(c => {
                if (c.number === clue.number
                    && ((dir === 'across' && this.cluesAcross.includes(c))
                        || (dir === 'down' && this.cluesDown.includes(c)))) return false;
                return (c.clue || '').trim().toLowerCase() === text.toLowerCase();
            });
            if (dupes.length > 0) {
                issues.push({ type: 'duplicate', message: 'Duplicate clue text' });
            }

            return issues;
        },

        clueQualityIcon(clue, dir) {
            const issues = this.clueQuality(clue, dir);
            if (issues.length === 0) return '';
            if (issues.some(i => i.type === 'answer')) return 'error';
            return 'warning';
        },

        clueQualityTooltip(clue, dir) {
            return this.clueQuality(clue, dir).map(i => i.message).join(', ');
        },

        onGridResized() {
            this.width = this.$wire.width;
            this.height = this.$wire.height;
            this.grid = this.$wire.grid;
            this.solution = this.$wire.solution;
            this.styles = {};
            this.cluesAcross = [];
            this.cluesDown = [];
            this.selectedRow = -1;
            this.selectedCol = -1;
            this.isDirty = false;
        },

        // --- Clue suggestions ------------------------------------------------
        getAnswerForSlot(dir, number) {
            const slot = this.findSlot(dir, number);
            if (!slot) return null;

            let word = '';
            for (let i = 0; i < slot.length; i++) {
                const r = dir === 'across' ? slot.row : slot.row + i;
                const c = dir === 'across' ? slot.col + i : slot.col;
                const letter = this.solution[r]?.[c] || '';
                if (!letter || letter === '#') return null;
                word += letter;
            }
            return word.toUpperCase();
        },

        toggleSuggestions() {
            if (this.showSuggestions) {
                this.closeSuggestions();
            } else {
                this.showSuggestions = true;
                this.clueSuggestionsWord = '';
                this.fetchClueSuggestions();
            }
        },

        closeSuggestions() {
            this.showSuggestions = false;
            this.clueSuggestions = [];
            this.clueSuggestionsWord = '';
            this.clueSuggestionsLoading = false;
        },

        async fetchClueSuggestions() {
            const num = this.activeClueNumber;
            if (num < 0) {
                this.clueSuggestions = [];
                this.clueSuggestionsWord = '';
                return;
            }

            const word = this.getAnswerForSlot(this.direction, num);
            if (!word || word.length < 2) {
                this.clueSuggestions = [];
                this.clueSuggestionsWord = '';
                return;
            }

            if (word === this.clueSuggestionsWord) return;

            this.clueSuggestionsLoading = true;
            try {
                this.clueSuggestions = await this.$wire.lookupClues(word);
                this.clueSuggestionsWord = word;
            } catch (e) {
                this.clueSuggestions = [];
            }
            this.clueSuggestionsLoading = false;
        },

        useClue(clue, text) {
            clue.clue = text;
            this.closeSuggestions();
            this.markDirty();
        },

        // --- Word suggestions (autofill) -------------------------------------
        getPatternForSlot(dir, number) {
            const slot = this.findSlot(dir, number);
            if (!slot) return null;

            let pattern = '';
            for (let i = 0; i < slot.length; i++) {
                const r = dir === 'across' ? slot.row : slot.row + i;
                const c = dir === 'across' ? slot.col + i : slot.col;
                const letter = this.solution[r]?.[c] || '';
                pattern += (letter && letter !== '#') ? letter.toUpperCase() : '_';
            }
            return pattern;
        },

        toggleWordSuggestions() {
            if (this.showWordSuggestions) {
                this.closeWordSuggestions();
            } else {
                this.showWordSuggestions = true;
                this.closeSuggestions();
                this.wordSuggestionsPattern = '';
                this.fetchWordSuggestions();
            }
            this.$refs.gridContainer?.focus();
        },

        closeWordSuggestions() {
            this.showWordSuggestions = false;
            this.wordSuggestions = [];
            this.wordSuggestionsPattern = '';
            this.wordSuggestionsLoading = false;
        },

        debouncedRefreshWordSuggestions() {
            if (!this.showWordSuggestions) return;
            clearTimeout(this._wordSuggestTimer);
            this._wordSuggestTimer = setTimeout(() => {
                this.wordSuggestionsPattern = '';
                this.fetchWordSuggestions();
            }, WORD_SUGGEST_DEBOUNCE_MS);
        },

        async fetchWordSuggestions() {
            const num = this.activeClueNumber;
            if (num < 0) { this.wordSuggestions = []; return; }

            const pattern = this.getPatternForSlot(this.direction, num);
            if (!pattern || pattern.length < 2 || !pattern.includes('_')) {
                this.wordSuggestions = [];
                return;
            }

            if (pattern === this.wordSuggestionsPattern) return;

            this.wordSuggestionsLoading = true;
            try {
                this.wordSuggestions = await this.$wire.suggestWords(pattern, pattern.length);
                this.wordSuggestionsPattern = pattern;
            } catch (e) {
                this.wordSuggestions = [];
            }
            this.wordSuggestionsLoading = false;
        },

        applyWordSuggestion(word) {
            const num = this.activeClueNumber;
            const dir = this.direction;
            const slot = this.findSlot(dir, num);
            if (!slot) return;

            for (let i = 0; i < slot.length && i < word.length; i++) {
                const r = dir === 'across' ? slot.row : slot.row + i;
                const c = dir === 'across' ? slot.col + i : slot.col;
                this.solution[r][c] = word[i];
            }

            this.closeWordSuggestions();
            this.markDirty();
        },

        // --- Grid autofill ---------------------------------------------------
        get hasUnfilledSlots() {
            for (const clue of this.computedCluesAcross) {
                const pattern = this.getPatternForSlot('across', clue.number);
                if (pattern && pattern.includes('_')) return true;
            }
            for (const clue of this.computedCluesDown) {
                const pattern = this.getPatternForSlot('down', clue.number);
                if (pattern && pattern.includes('_')) return true;
            }
            return false;
        },

        async quickFill() {
            await this._runFill('heuristic', 'Fill failed', () => this.$wire.heuristicFill(
                cloneForWire(this.solution),
                cloneForWire(this.grid),
                this._stylesPayload(),
            ));
        },

        async aiFill() {
            await this._runFill('ai', 'AI fill failed', () => this.$wire.aiFill(
                cloneForWire(this.solution),
                cloneForWire(this.grid),
                this._stylesPayload(),
            ), {
                upgradeFeature: 'ai_fill',
                onResult: (result) => {
                    if (result.needs_theme) this.$wire.set('showSettingsModal', true);
                },
            });
        },

        _stylesPayload() {
            return Object.keys(this.styles).length > 0 ? cloneForWire(this.styles) : null;
        },

        async aiGenerateClues() {
            this.fillInProgress = true;
            this.fillMode = 'ai';
            try {
                const result = await this.$wire.aiGenerateClues(cloneForWire(this.solution));
                if (result.upgrade) {
                    this.$wire.set('upgradeFeature', 'ai_clues');
                    this.$wire.set('showUpgradeModal', true);
                    return;
                }
                if (result.success && result.clues) this.applyClues(result.clues);
                this.$dispatch('notify', {
                    message: result.message,
                    type: result.success ? 'success' : 'warning',
                });
            } catch (e) {
                this.$dispatch('notify', {
                    message: 'Clue generation failed: ' + (e.message || 'Unknown error'),
                    type: 'error',
                });
            } finally {
                this.fillInProgress = false;
                this.fillMode = null;
            }
        },

        async _runFill(mode, errorPrefix, action, opts = {}) {
            this.fillInProgress = true;
            this.fillMode = mode;
            try {
                const result = await action();
                if (result.upgrade) {
                    if (opts.upgradeFeature) {
                        this.$wire.set('upgradeFeature', opts.upgradeFeature);
                        this.$wire.set('showUpgradeModal', true);
                    }
                    return;
                }
                if (result.fills && result.fills.length) this.applyFills(result.fills);
                this.$dispatch('notify', {
                    message: result.message,
                    type: result.success ? 'success' : 'warning',
                });
                opts.onResult?.(result);
            } catch (e) {
                this.$dispatch('notify', {
                    message: errorPrefix + ': ' + (e.message || 'Unknown error'),
                    type: 'error',
                });
            } finally {
                this.fillInProgress = false;
                this.fillMode = null;
            }
        },

        applyFills(fills) {
            for (const fill of fills) {
                const slot = this.findSlot(fill.direction, fill.number);
                if (!slot) continue;
                for (let i = 0; i < slot.length && i < fill.word.length; i++) {
                    const r = fill.direction === 'across' ? slot.row : slot.row + i;
                    const c = fill.direction === 'across' ? slot.col + i : slot.col;
                    this.solution[r][c] = fill.word[i].toUpperCase();
                }
            }
            this.markDirty();
        },

        applyClues(clues) {
            const apply = (list, source) => {
                if (!source) return;
                for (const [num, text] of Object.entries(source)) {
                    const clue = list.find(c => c.number === parseInt(num));
                    if (clue) clue.clue = text;
                }
            };
            apply(this.cluesAcross, clues.across);
            apply(this.cluesDown, clues.down);
            this.markDirty();
        },
    };
}

function clamp(min, max, value) {
    return Math.max(min, Math.min(max, value));
}
