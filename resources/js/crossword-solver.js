export function crosswordSolver({ width, height, grid, solution, progress, styles, prefilled, cluesAcross, cluesDown, initialElapsed, initialSolved, initialPencilCells, persistence }) {
    return {
        width,
        height,
        grid,
        solution,
        progress,
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
        _saveTimer: null,
        _savedTimer: null,
        _timerInterval: null,
        elapsedSeconds: initialElapsed || 0,
        rebusMode: false,
        pencilMode: false,
        pencilCells: initialPencilCells || {},
        achievementToasts: [],
        showCelebration: false,
        celebrationTime: '',
        persistence: persistence || null,

        init() {
            this.$watch('isDirty', (val) => {
                if (val) this.debouncedSave();
            });

            // Start the timer if puzzle isn't already solved
            if (!this.solved) {
                this._timerInterval = setInterval(() => {
                    this.elapsedSeconds++;
                }, 1000);
            }

            // Pause timer when window loses focus, resume on focus
            this._onVisibilityChange = () => {
                if (document.hidden && this._timerInterval) {
                    clearInterval(this._timerInterval);
                    this._timerInterval = null;
                } else if (!document.hidden && !this.solved && !this._timerInterval) {
                    this._timerInterval = setInterval(() => {
                        this.elapsedSeconds++;
                    }, 1000);
                }
            };
            document.addEventListener('visibilitychange', this._onVisibilityChange);
        },

        destroy() {
            if (this._timerInterval) clearInterval(this._timerInterval);
            if (this._onVisibilityChange) document.removeEventListener('visibilitychange', this._onVisibilityChange);
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

        // --- Custom numbers ---
        getCustomNumber(row, col) {
            return this.styles[row + ',' + col]?.number ?? null;
        },

        getDisplayNumber(row, col) {
            const custom = this.getCustomNumber(row, col);
            if (custom !== null) return custom;
            const cell = this.grid[row]?.[col];
            return typeof cell === 'number' && cell > 0 ? cell : null;
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

        // --- Word boundary helpers (bars + blocks) ---
        hasLeftBoundary(row, col) {
            if (col === 0) return true;
            if (this.isBlock(row, col - 1)) return true;
            return this.hasBar(row, col, 'left') || this.hasBar(row, col - 1, 'right');
        },

        hasRightBoundary(row, col) {
            if (col + 1 >= this.width) return true;
            if (this.isBlock(row, col + 1)) return true;
            return this.hasBar(row, col, 'right') || this.hasBar(row, col + 1, 'left');
        },

        hasTopBoundary(row, col) {
            if (row === 0) return true;
            if (this.isBlock(row - 1, col)) return true;
            return this.hasBar(row, col, 'top') || this.hasBar(row - 1, col, 'bottom');
        },

        hasBottomBoundary(row, col) {
            if (row + 1 >= this.height) return true;
            if (this.isBlock(row + 1, col)) return true;
            return this.hasBar(row, col, 'bottom') || this.hasBar(row + 1, col, 'top');
        },

        findSlot(dir, number) {
            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (this.grid[row][col] === number) {
                        if (dir === 'across') {
                            let len = 1;
                            while (!this.hasRightBoundary(row, col + len - 1)) len++;
                            if (len > 1) return { row, col, length: len };
                        } else {
                            let len = 1;
                            while (!this.hasBottomBoundary(row + len - 1, col)) len++;
                            if (len > 1) return { row, col, length: len };
                        }
                    }
                }
            }
            return null;
        },

        // --- Active clue tracking ---
        get activeClueNumber() {
            if (this.selectedRow < 0) return -1;
            return this.getClueNumberForCell(this.selectedRow, this.selectedCol, this.direction);
        },

        getClueNumberForCell(row, col, dir) {
            if (this.isBlock(row, col)) return -1;
            if (dir === 'across') {
                let c = col;
                while (c > 0 && !this.hasLeftBoundary(row, c)) c--;
                return typeof this.grid[row][c] === 'number' && this.grid[row][c] > 0 ? this.grid[row][c] : -1;
            } else {
                let r = row;
                while (r > 0 && !this.hasTopBoundary(r, col)) r--;
                return typeof this.grid[r][col] === 'number' && this.grid[r][col] > 0 ? this.grid[r][col] : -1;
            }
        },

        getWordCells(row, col, dir) {
            if (this.isBlock(row, col)) return [];
            const cells = [];
            if (dir === 'across') {
                let c = col;
                while (c > 0 && !this.hasLeftBoundary(row, c)) c--;
                while (c < this.width && !this.isBlock(row, c)) {
                    cells.push([row, c]);
                    if (this.hasRightBoundary(row, c)) break;
                    c++;
                }
            } else {
                let r = row;
                while (r > 0 && !this.hasTopBoundary(r, col)) r--;
                while (r < this.height && !this.isBlock(r, col)) {
                    cells.push([r, col]);
                    if (this.hasBottomBoundary(r, col)) break;
                    r++;
                }
            }
            return cells;
        },

        // --- Cell helpers ---
        isVoid(row, col) {
            return this.grid[row]?.[col] === null;
        },

        isBlock(row, col) {
            const cell = this.grid[row]?.[col];
            return cell === '#' || cell === null;
        },

        hasCircle(row, col) {
            return this.styles[row + ',' + col]?.shapebg === 'circle';
        },

        getCellColor(row, col) {
            return this.styles[row + ',' + col]?.color ?? null;
        },

        hasBar(row, col, edge) {
            return this.styles[row + ',' + col]?.bars?.includes(edge) || false;
        },

        cellBarStyles(row, col) {
            const key = row + ',' + col;
            const entry = this.styles[key];
            const parts = [];

            const bars = entry?.bars;
            if (bars && bars.length > 0) {
                const shadows = [];
                if (bars.includes('top'))    shadows.push('inset 0 2px 0 0 var(--bar-color)');
                if (bars.includes('bottom')) shadows.push('inset 0 -2px 0 0 var(--bar-color)');
                if (bars.includes('left'))   shadows.push('inset 2px 0 0 0 var(--bar-color)');
                if (bars.includes('right'))  shadows.push('inset -2px 0 0 0 var(--bar-color)');
                parts.push('box-shadow: ' + shadows.join(', '));
            }

            const color = entry?.color;
            if (color && !this.isBlock(row, col)) {
                const isSelected = row === this.selectedRow && col === this.selectedCol;
                const wordCells = this.selectedRow >= 0 ? this.getWordCells(this.selectedRow, this.selectedCol, this.direction) : [];
                const isInWord = wordCells.some(([r, c]) => r === row && c === col);
                if (!isSelected && !isInWord) {
                    parts.push('background-color: ' + color);
                }
            }

            return parts.join('; ');
        },

        cellClasses(row, col) {
            if (this.isVoid(row, col)) return 'invisible';
            if (this.isBlock(row, col)) return 'bg-zinc-800 dark:bg-zinc-300';

            const isSelected = row === this.selectedRow && col === this.selectedCol;
            const wordCells = this.selectedRow >= 0 ? this.getWordCells(this.selectedRow, this.selectedCol, this.direction) : [];
            const isInWord = wordCells.some(([r, c]) => r === row && c === col);

            const prefilled = this.isPrefilled(row, col);

            if (isSelected) return prefilled ? 'bg-blue-200 dark:bg-blue-800 cursor-pointer' : 'bg-blue-300 dark:bg-blue-700 cursor-pointer';
            if (isInWord) return prefilled ? 'bg-blue-50 dark:bg-blue-900/30 cursor-pointer' : 'bg-blue-100 dark:bg-blue-900/50 cursor-pointer';
            if (this.getCellColor(row, col)) return (prefilled ? 'cursor-pointer' : 'cursor-pointer');
            if (prefilled) return 'bg-zinc-100 dark:bg-zinc-700 cursor-pointer';
            return 'bg-zinc-50 dark:bg-zinc-800 cursor-pointer';
        },

        isRebus(row, col) {
            const val = this.progress[row]?.[col] || '';
            return val.length > 1;
        },

        letterFontStyle(row, col) {
            const val = this.progress[row]?.[col] || '';
            const baseFontSize = Math.max(12, Math.min(24, 600 / this.width * 0.55));
            if (val.length <= 1) {
                return 'font-size: ' + baseFontSize + 'px';
            }
            const scaled = Math.max(6, baseFontSize / Math.max(val.length * 0.55, 1));
            return 'font-size: ' + scaled + 'px; letter-spacing: -0.5px';
        },

        isPrefilled(row, col) {
            if (!this.prefilled) return false;
            return !!this.prefilled[row]?.[col];
        },

        isPencil(row, col) {
            return !!this.pencilCells[row + ',' + col];
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
            const key = row + ',' + col;
            if (this.revealed[key]) return 'text-blue-600 dark:text-blue-400';
            if (this.checked[key] === 'wrong') return 'text-red-600 dark:text-red-400';
            if (this.checked[key] === 'correct') return 'text-emerald-600 dark:text-emerald-400';
            if (this.pencilCells[key]) return 'text-zinc-500 dark:text-zinc-500';
            return 'text-zinc-900 dark:text-zinc-100';
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
            const siblings = [...parent.children].filter(el => el.nodeType === 1);
            const idx = siblings.indexOf(currentEl);
            if (idx < 0) return;

            let nextIdx = reverse
                ? (idx <= 0 ? siblings.length - 1 : idx - 1)
                : (idx >= siblings.length - 1 ? 0 : idx + 1);

            const nextClue = siblings[nextIdx];
            const input = nextClue.querySelector('input');
            if (input) {
                input.focus();
            } else {
                nextClue.focus();
            }
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

            // Toggle rebus mode with Insert key
            if (key === 'Insert') {
                e.preventDefault();
                if (!this.isBlock(this.selectedRow, this.selectedCol) && !this.isPrefilled(this.selectedRow, this.selectedCol)) {
                    this.rebusMode = !this.rebusMode;
                    if (this.rebusMode) {
                        this.progress[this.selectedRow][this.selectedCol] = '';
                        delete this.checked[this.selectedRow + ',' + this.selectedCol];
                        this.isDirty = true;
                    }
                }
                return;
            }

            // In rebus mode, accumulate letters
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
                    delete this.checked[this.selectedRow + ',' + this.selectedCol];
                    this.isDirty = true;
                    return;
                }
                if (/^[a-zA-Z0-9]$/.test(key)) {
                    e.preventDefault();
                    const cellKey = this.selectedRow + ',' + this.selectedCol;
                    const current = this.progress[this.selectedRow][this.selectedCol] || '';
                    this.progress[this.selectedRow][this.selectedCol] = current + key.toUpperCase();
                    delete this.checked[cellKey];
                    if (this.pencilMode) {
                        this.pencilCells[cellKey] = true;
                    } else {
                        delete this.pencilCells[cellKey];
                    }
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
            if (key === 'Enter') { e.preventDefault(); this.direction = this.direction === 'across' ? 'down' : 'across'; this.scrollActiveClueIntoView(); return; }

            if (key === 'Backspace') { e.preventDefault(); this.handleBackspace(); return; }
            if (key === 'Delete') {
                e.preventDefault();
                if (!this.isBlock(this.selectedRow, this.selectedCol) && !this.isPrefilled(this.selectedRow, this.selectedCol)) {
                    this.progress[this.selectedRow][this.selectedCol] = '';
                    delete this.checked[this.selectedRow + ',' + this.selectedCol];
                    this.isDirty = true;
                }
                return;
            }

            if (/^[a-zA-Z]$/.test(key)) {
                e.preventDefault();
                if (!this.isBlock(this.selectedRow, this.selectedCol) && !this.isPrefilled(this.selectedRow, this.selectedCol)) {
                    const cellKey = this.selectedRow + ',' + this.selectedCol;
                    this.progress[this.selectedRow][this.selectedCol] = key.toUpperCase();
                    delete this.checked[cellKey];
                    if (this.pencilMode) {
                        this.pencilCells[cellKey] = true;
                    } else {
                        delete this.pencilCells[cellKey];
                    }
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
                delete this.checked[row + ',' + col];
                this.isDirty = true;
            } else {
                if (this.direction === 'across') {
                    if (!this.hasLeftBoundary(row, col)) {
                        if (!this.isPrefilled(row, col - 1)) {
                            this.selectedCol = col - 1;
                            this.progress[row][col - 1] = '';
                            delete this.checked[row + ',' + (col - 1)];
                            this.isDirty = true;
                        } else {
                            this.selectedCol = col - 1;
                        }
                    }
                } else {
                    if (!this.hasTopBoundary(row, col)) {
                        if (!this.isPrefilled(row - 1, col)) {
                            this.selectedRow = row - 1;
                            this.progress[row - 1][col] = '';
                            delete this.checked[(row - 1) + ',' + col];
                            this.isDirty = true;
                        } else {
                            this.selectedRow = row - 1;
                        }
                    }
                }
            }
        },

        jumpToNextClue(reverse) {
            const clues = this.direction === 'across' ? this.cluesAcross : this.cluesDown;
            if (clues.length === 0) return;
            const currentNum = this.activeClueNumber;
            const idx = clues.findIndex(c => c.number === currentNum);
            let nextIdx = reverse ? (idx <= 0 ? clues.length - 1 : idx - 1) : (idx >= clues.length - 1 ? 0 : idx + 1);
            this.selectClue(this.direction, clues[nextIdx].number);
        },

        // --- Checking & revealing ---
        checkAnswers() {
            this.checked = {};
            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (this.isBlock(row, col) || this.isPrefilled(row, col) || !this.progress[row][col]) continue;
                    const key = row + ',' + col;
                    this.checked[key] = this.progress[row][col] === this.solution[row][col] ? 'correct' : 'wrong';
                }
            }
        },

        revealLetter() {
            if (this.selectedRow < 0 || this.isBlock(this.selectedRow, this.selectedCol)) return;
            const row = this.selectedRow, col = this.selectedCol;
            const answer = this.solution[row]?.[col];
            if (answer && answer !== '#') {
                const key = row + ',' + col;
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
                        const key = row + ',' + col;
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
            // Stop the timer
            if (this._timerInterval) {
                clearInterval(this._timerInterval);
                this._timerInterval = null;
            }
            // Save immediately with completed flag and final time
            this.saving = true;
            this.showSaved = false;
            const progressCopy = JSON.parse(JSON.stringify(this.progress));
            const pencilCopy = JSON.parse(JSON.stringify(this.pencilCells));
            if (this.persistence) {
                await this.persistence.save(progressCopy, true, this.elapsedSeconds, pencilCopy);
                this.onSaved();
            } else {
                await this.$wire.saveProgress(progressCopy, true, this.elapsedSeconds, pencilCopy);
            }
            this.isDirty = false;
            this.saving = false;
            setTimeout(() => {
                this.celebrationTime = this.formattedTime();
                this.showCelebration = true;
            }, 500);
        },

        // --- Persistence ---
        debouncedSave() {
            clearTimeout(this._saveTimer);
            this._saveTimer = setTimeout(() => this.saveNow(), 3000);
        },

        async saveNow() {
            if (!this.isDirty) return;
            this.saving = true;
            this.showSaved = false;
            const progressCopy = JSON.parse(JSON.stringify(this.progress));
            const pencilCopy = JSON.parse(JSON.stringify(this.pencilCells));
            if (this.persistence) {
                await this.persistence.save(progressCopy, this.solved, this.elapsedSeconds, pencilCopy);
                this.onSaved();
            } else {
                await this.$wire.saveProgress(progressCopy, this.solved, this.elapsedSeconds, pencilCopy);
            }
            this.isDirty = false;
            this.saving = false;
        },

        onSaved() {
            this.saving = false;
            this.showSaved = true;
            clearTimeout(this._savedTimer);
            this._savedTimer = setTimeout(() => { this.showSaved = false; }, 2000);
        },

        showAchievements(achievements) {
            if (!achievements || achievements.length === 0) return;
            achievements.forEach((a, i) => {
                setTimeout(() => {
                    const toast = { ...a, id: Date.now() + i };
                    this.achievementToasts.push(toast);
                    setTimeout(() => {
                        this.achievementToasts = this.achievementToasts.filter(t => t.id !== toast.id);
                    }, 5000);
                }, i * 800);
            });
        },
    };
}
