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
} from './grid/helpers.js';
import { cloneForWire, createAutosave } from './grid/persistence.js';

export function crosswordSolver({
    width, height, grid, solution, progress, styles, prefilled,
    cluesAcross, cluesDown, initialElapsed, initialSolved, initialPencilCells, persistence,
    puzzleTitle,
}) {
    return {
        width,
        height,
        grid,
        solution,
        progress,
        puzzleTitle: puzzleTitle || '',
        styles: (styles && !Array.isArray(styles)) ? styles : {},
        prefilled: prefilled || null,
        cluesAcross: cluesAcross || [],
        cluesDown: cluesDown || [],
        selectedRow: -1,
        selectedCol: -1,
        direction: 'across',
        checked: {},
        revealed: {},
        solved: initialSolved || false,
        isDirty: false,
        saving: false,
        showSaved: false,
        mobileClueTab: 'across',
        _timerInterval: null,
        _autosave: null,
        _onVisibilityChange: null,
        _celebrationTimer: null,
        _achievementTimers: [],
        elapsedSeconds: initialElapsed || 0,
        rebusMode: false,
        pencilMode: false,
        pencilCells: initialPencilCells || {},
        achievementToasts: [],
        showCelebration: false,
        celebrationTime: '',
        persistence: persistence || null,

        init() {
            this._autosave = createAutosave({
                save: () => this._performSave(false),
                isDirty: () => this.isDirty,
                setDirty: (v) => { this.isDirty = v; },
                setSaving: (v) => { this.saving = v; },
                setShowSaved: (v) => { this.showSaved = v; },
            });

            this.$watch('isDirty', (val) => {
                if (val) this._autosave.scheduleSave();
            });

            if (!this.solved) this._startTimer();

            this._onVisibilityChange = () => {
                if (document.hidden) {
                    this._stopTimer();
                } else if (!this.solved) {
                    this._startTimer();
                }
            };
            document.addEventListener('visibilitychange', this._onVisibilityChange);
        },

        destroy() {
            this._stopTimer();
            if (this._onVisibilityChange) {
                document.removeEventListener('visibilitychange', this._onVisibilityChange);
                this._onVisibilityChange = null;
            }
            this._autosave?.destroy();
            clearTimeout(this._celebrationTimer);
            for (const t of this._achievementTimers) clearTimeout(t);
            this._achievementTimers = [];
        },

        _startTimer() {
            if (this._timerInterval) return;
            this._timerInterval = setInterval(() => { this.elapsedSeconds++; }, 1000);
        },

        _stopTimer() {
            if (this._timerInterval) {
                clearInterval(this._timerInterval);
                this._timerInterval = null;
            }
        },

        formattedTime() {
            const s = this.elapsedSeconds;
            const hours = Math.floor(s / 3600);
            const minutes = Math.floor((s % 3600) / 60);
            const secs = s % 60;
            if (hours > 0) {
                return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
            }
            return `${minutes}:${String(secs).padStart(2, '0')}`;
        },

        // --- Cell / slot helpers (delegate to grid/helpers) ---
        isVoid(row, col)     { return isVoid(this.grid, row, col); },
        isBlock(row, col)    { return isBlock(this.grid, row, col); },
        hasCircle(row, col)  { return hasCircle(this.styles, row, col); },
        hasBar(row, col, e)  { return hasBar(this.styles, row, col, e); },
        getCellColor(r, c)   { return getCellColor(this.styles, r, c); },
        getCustomNumber(r, c){ return getCustomNumber(this.styles, r, c); },
        getDisplayNumber(r, c){ return getDisplayNumber(this.grid, this.styles, r, c); },
        hasLeftBoundary(r, c)  { return hasLeftBoundary(this.grid, this.styles, r, c); },
        hasRightBoundary(r, c) { return hasRightBoundary(this.grid, this.styles, this.width, r, c); },
        hasTopBoundary(r, c)   { return hasTopBoundary(this.grid, this.styles, r, c); },
        hasBottomBoundary(r, c){ return hasBottomBoundary(this.grid, this.styles, this.height, r, c); },
        findSlot(dir, n) {
            return findSlot(this.grid, this.styles, this.width, this.height, dir, n);
        },
        getClueNumberForCell(r, c, d) {
            return getClueNumberForCell(this.grid, this.styles, r, c, d);
        },
        getWordCells(r, c, d) {
            return getWordCells(this.grid, this.styles, this.width, this.height, r, c, d);
        },

        // --- Computed clue lists with lengths ---
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

        get activeClueNumber() {
            if (this.selectedRow < 0) return -1;
            return this.getClueNumberForCell(this.selectedRow, this.selectedCol, this.direction);
        },

        // Cached active-word membership. Alpine evaluates getters lazily and
        // dedupes within a render pass, so this collapses ~width*height linear
        // scans down to one.
        get activeWordCellSet() {
            return computeActiveWordCells(
                this.grid, this.styles, this.width, this.height,
                this.selectedRow, this.selectedCol, this.direction,
            );
        },

        cellBarStyles(row, col) {
            const key = cellKey(row, col);
            const entry = this.styles[key];
            if (!entry) return '';
            const parts = [];

            const bars = entry.bars;
            if (bars && bars.length > 0) {
                const shadows = [];
                if (bars.includes('top'))    shadows.push('inset 0 2px 0 0 var(--bar-color)');
                if (bars.includes('bottom')) shadows.push('inset 0 -2px 0 0 var(--bar-color)');
                if (bars.includes('left'))   shadows.push('inset 2px 0 0 0 var(--bar-color)');
                if (bars.includes('right'))  shadows.push('inset -2px 0 0 0 var(--bar-color)');
                parts.push('box-shadow: ' + shadows.join(', '));
            }

            const color = entry.color;
            if (color && !this.isBlock(row, col)) {
                const isSelected = row === this.selectedRow && col === this.selectedCol;
                const isInWord = this.activeWordCellSet.has(key);
                if (!isSelected && !isInWord) parts.push('background-color: ' + color);
            }

            return parts.join('; ');
        },

        cellClasses(row, col) {
            if (this.isVoid(row, col)) return 'invisible';
            if (this.isBlock(row, col)) return 'bg-zinc-800 dark:bg-zinc-300';

            const isSelected = row === this.selectedRow && col === this.selectedCol;
            const isInWord = this.activeWordCellSet.has(cellKey(row, col));
            const prefilled = this.isPrefilled(row, col);

            if (isSelected) return prefilled ? 'bg-blue-200 dark:bg-blue-800 cursor-pointer' : 'bg-blue-300 dark:bg-blue-700 cursor-pointer';
            if (isInWord) return prefilled ? 'bg-blue-50 dark:bg-blue-900/30 cursor-pointer' : 'bg-blue-100 dark:bg-blue-900/50 cursor-pointer';
            if (this.getCellColor(row, col)) return 'cursor-pointer';
            if (prefilled) return 'bg-muted cursor-pointer';
            return 'bg-zinc-50 dark:bg-zinc-800 cursor-pointer';
        },

        isRebus(row, col) {
            return (this.progress[row]?.[col] || '').length > 1;
        },

        letterFontStyle(row, col) {
            const val = this.progress[row]?.[col] || '';
            const baseFontSize = clamp(12, 24, 600 / this.width * 0.55);
            if (val.length <= 1) return 'font-size: ' + baseFontSize + 'px';
            const scaled = Math.max(6, baseFontSize / Math.max(val.length * 0.55, 1));
            return 'font-size: ' + scaled + 'px; letter-spacing: -0.5px';
        },

        isPrefilled(row, col) {
            if (!this.prefilled) return false;
            return !!this.prefilled[row]?.[col];
        },

        isPencil(row, col) {
            return !!this.pencilCells[cellKey(row, col)];
        },

        activeClueAnnouncement() {
            if (this.selectedRow < 0) return '';
            const num = this.activeClueNumber;
            if (num < 0) return '';
            const clues = this.direction === 'across' ? this.cluesAcross : this.cluesDown;
            const clue = clues.find(c => c.number === num);
            if (!clue) return '';
            return `${num} ${this.direction}: ${clue.clue || 'no clue'}`;
        },

        letterClass(row, col) {
            const key = cellKey(row, col);
            if (this.revealed[key]) return 'text-blue-600 dark:text-blue-400';
            if (this.checked[key] === 'wrong') return 'text-red-600 dark:text-red-400';
            if (this.checked[key] === 'correct') return 'text-emerald-600 dark:text-emerald-400';
            if (this.pencilCells[key]) return 'text-fg-subtle';
            return 'text-fg';
        },

        // --- Selection ---
        selectCell(row, col) {
            if (this.isVoid(row, col)) return;
            if (this.isBlock(row, col)) return;

            if (this.selectedRow === row && this.selectedCol === col) {
                this.direction = this.direction === 'across' ? 'down' : 'across';
            } else {
                this.selectedRow = row;
                this.selectedCol = col;
            }

            this.scrollActiveClueIntoView();
            this.$refs.gridContainer?.focus();
        },

        selectClue(dir, number) {
            this.direction = dir;
            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (this.grid[row][col] === number) {
                        this.selectedRow = row;
                        this.selectedCol = col;
                        this.$refs.gridContainer?.focus();
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

        // --- Keyboard ---
        handleKeydown(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            const key = e.key;

            if (key === 'Escape') {
                if (this.rebusMode) { this.rebusMode = false; return; }
                this.selectedRow = -1; this.selectedCol = -1; return;
            }
            if (this.selectedRow < 0) return;

            if (key === 'Insert') {
                e.preventDefault();
                if (!this.isBlock(this.selectedRow, this.selectedCol)
                    && !this.isPrefilled(this.selectedRow, this.selectedCol)) {
                    this.rebusMode = !this.rebusMode;
                    if (this.rebusMode) {
                        this.progress[this.selectedRow][this.selectedCol] = '';
                        delete this.checked[cellKey(this.selectedRow, this.selectedCol)];
                        this.isDirty = true;
                    }
                }
                return;
            }

            if (this.rebusMode) {
                if (key === 'Enter' || key === 'Tab') {
                    e.preventDefault();
                    this.rebusMode = false;
                    this.advanceCursor();
                    this.checkIfSolved();
                    return;
                }
                if (key === 'Backspace') {
                    e.preventDefault();
                    const val = this.progress[this.selectedRow][this.selectedCol] || '';
                    this.progress[this.selectedRow][this.selectedCol] = val.slice(0, -1);
                    delete this.checked[cellKey(this.selectedRow, this.selectedCol)];
                    this.isDirty = true;
                    return;
                }
                if (/^[a-zA-Z0-9]$/.test(key)) {
                    e.preventDefault();
                    const k = cellKey(this.selectedRow, this.selectedCol);
                    const current = this.progress[this.selectedRow][this.selectedCol] || '';
                    this.progress[this.selectedRow][this.selectedCol] = current + key.toUpperCase();
                    delete this.checked[k];
                    if (this.pencilMode) this.pencilCells[k] = true;
                    else delete this.pencilCells[k];
                    this.isDirty = true;
                    return;
                }
                return;
            }

            if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(key)) {
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

            if (key === 'Backspace') { e.preventDefault(); this.handleBackspace(); return; }
            if (key === 'Delete') {
                e.preventDefault();
                if (!this.isBlock(this.selectedRow, this.selectedCol)
                    && !this.isPrefilled(this.selectedRow, this.selectedCol)) {
                    this.progress[this.selectedRow][this.selectedCol] = '';
                    delete this.checked[cellKey(this.selectedRow, this.selectedCol)];
                    this.isDirty = true;
                }
                return;
            }

            if (/^[a-zA-Z]$/.test(key)) {
                e.preventDefault();
                if (!this.isBlock(this.selectedRow, this.selectedCol)
                    && !this.isPrefilled(this.selectedRow, this.selectedCol)) {
                    const k = cellKey(this.selectedRow, this.selectedCol);
                    this.progress[this.selectedRow][this.selectedCol] = key.toUpperCase();
                    delete this.checked[k];
                    if (this.pencilMode) this.pencilCells[k] = true;
                    else delete this.pencilCells[k];
                    this.advanceCursor();
                    this.isDirty = true;
                    this.checkIfSolved();
                }
            }
        },

        moveArrow(key) {
            let row = this.selectedRow, col = this.selectedCol;
            const delta = { ArrowUp: [-1, 0], ArrowDown: [1, 0], ArrowLeft: [0, -1], ArrowRight: [0, 1] }[key];
            do { row += delta[0]; col += delta[1]; }
            while (row >= 0 && row < this.height && col >= 0 && col < this.width && this.isBlock(row, col));
            if (row >= 0 && row < this.height && col >= 0 && col < this.width && !this.isVoid(row, col)) {
                this.selectedRow = row; this.selectedCol = col;
                if (key === 'ArrowLeft' || key === 'ArrowRight') this.direction = 'across';
                if (key === 'ArrowUp' || key === 'ArrowDown') this.direction = 'down';
                this.scrollActiveClueIntoView();
            }
        },

        advanceCursor() {
            const row = this.selectedRow, col = this.selectedCol;
            if (this.direction === 'across') {
                if (!this.hasRightBoundary(row, col)) this.selectedCol = col + 1;
            } else {
                if (!this.hasBottomBoundary(row, col)) this.selectedRow = row + 1;
            }
        },

        handleBackspace() {
            const row = this.selectedRow, col = this.selectedCol;
            if (!this.isBlock(row, col) && !this.isPrefilled(row, col) && this.progress[row][col]) {
                this.progress[row][col] = '';
                delete this.checked[cellKey(row, col)];
                this.isDirty = true;
                return;
            }
            if (this.direction === 'across') {
                if (!this.hasLeftBoundary(row, col)) {
                    this.selectedCol = col - 1;
                    if (!this.isPrefilled(row, col - 1)) {
                        this.progress[row][col - 1] = '';
                        delete this.checked[cellKey(row, col - 1)];
                        this.isDirty = true;
                    }
                }
            } else {
                if (!this.hasTopBoundary(row, col)) {
                    this.selectedRow = row - 1;
                    if (!this.isPrefilled(row - 1, col)) {
                        this.progress[row - 1][col] = '';
                        delete this.checked[cellKey(row - 1, col)];
                        this.isDirty = true;
                    }
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

        // --- Checking & revealing ---
        checkAnswers() {
            this.checked = {};
            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (this.isBlock(row, col) || this.isPrefilled(row, col) || !this.progress[row][col]) continue;
                    const key = cellKey(row, col);
                    this.checked[key] = this.progress[row][col] === this.solution[row][col] ? 'correct' : 'wrong';
                }
            }
        },

        revealLetter() {
            if (this.selectedRow < 0 || this.isBlock(this.selectedRow, this.selectedCol)) return;
            const row = this.selectedRow, col = this.selectedCol;
            const answer = this.solution[row]?.[col];
            if (answer && answer !== '#') {
                const key = cellKey(row, col);
                this.progress[row][col] = answer;
                this.revealed[key] = true;
                delete this.checked[key];
                delete this.pencilCells[key];
                this.isDirty = true;
                this.advanceCursor();
                this.checkIfSolved();
            }
        },

        clearProgress() {
            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (!this.isBlock(row, col) && !this.isPrefilled(row, col)) this.progress[row][col] = '';
                }
            }
            this.checked = {};
            this.revealed = {};
            this.pencilCells = {};
            this.solved = false;
            this.isDirty = true;
        },

        clearErrors() {
            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (this.isBlock(row, col) || this.isPrefilled(row, col)) continue;
                    if (this.progress[row][col] && this.progress[row][col] !== this.solution[row][col]) {
                        const key = cellKey(row, col);
                        this.progress[row][col] = '';
                        delete this.checked[key];
                        delete this.pencilCells[key];
                    }
                }
            }
            this.isDirty = true;
        },

        async checkIfSolved() {
            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (this.isBlock(row, col)) continue;
                    if ((this.progress[row][col] || '') !== (this.solution[row][col] || '')) return;
                }
            }
            this.solved = true;
            this._stopTimer();
            await this._performSave(true);
            this._celebrationTimer = setTimeout(() => {
                this.celebrationTime = this.formattedTime();
                this.showCelebration = true;
            }, 500);
        },

        // --- Persistence ---
        async _performSave(asCompletion) {
            this.saving = true;
            this.showSaved = false;
            const progressCopy = cloneForWire(this.progress);
            const pencilCopy = cloneForWire(this.pencilCells);
            const solved = asCompletion || this.solved;
            try {
                if (this.persistence) {
                    await this.persistence.save(progressCopy, solved, this.elapsedSeconds, pencilCopy);
                    this._autosave?.acknowledge();
                } else {
                    await this.$wire.saveProgress(progressCopy, solved, this.elapsedSeconds, pencilCopy);
                }
            } finally {
                this.isDirty = false;
                this.saving = false;
            }
        },

        // Templates call onSaved() from a Livewire-dispatched event.
        onSaved() { this._autosave?.acknowledge(); },

        _buildShareText(puzzleUrl) {
            return `🧩 ${this.puzzleTitle || 'a crossword'} — Zorbl\n⏱️ ${this.celebrationTime} | ${this.width}×${this.height}\n${puzzleUrl}`;
        },

        canNativeShare() {
            return typeof navigator.share === 'function';
        },

        async shareResult(puzzleUrl) {
            const text = this._buildShareText(puzzleUrl);
            try {
                await navigator.share({ text });
            } catch (e) {
                if (e.name !== 'AbortError') {
                    await navigator.clipboard.writeText(text);
                }
            }
        },

        async copyShareText(puzzleUrl, buttonEl) {
            const text = this._buildShareText(puzzleUrl);
            try {
                await navigator.clipboard.writeText(text);
                const label = buttonEl.querySelector('[x-ref="copyLabel"]');
                if (label) {
                    const original = label.textContent;
                    label.textContent = 'Copied!';
                    setTimeout(() => { label.textContent = original; }, 2000);
                }
            } catch {}
        },

        twitterShareUrl(puzzleUrl) {
            const text = this._buildShareText(puzzleUrl);
            return 'https://x.com/intent/post?text=' + encodeURIComponent(text);
        },

        showAchievements(achievements) {
            if (!achievements || achievements.length === 0) return;
            achievements.forEach((a, i) => {
                const stagger = setTimeout(() => {
                    const toast = { ...a, id: Date.now() + i };
                    this.achievementToasts.push(toast);
                    const dismiss = setTimeout(() => {
                        this.achievementToasts = this.achievementToasts.filter(t => t.id !== toast.id);
                        this._achievementTimers = this._achievementTimers.filter(t => t !== dismiss);
                    }, 5000);
                    this._achievementTimers.push(dismiss);
                    this._achievementTimers = this._achievementTimers.filter(t => t !== stagger);
                }, i * 800);
                this._achievementTimers.push(stagger);
            });
        },
    };
}

function clamp(min, max, value) {
    return Math.max(min, Math.min(max, value));
}
