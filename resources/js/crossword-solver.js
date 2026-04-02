export function crosswordSolver({ width, height, grid, solution, progress, styles, cluesAcross, cluesDown }) {
    return {
        width,
        height,
        grid,
        solution,
        progress,
        styles: styles || {},
        cluesAcross: cluesAcross || [],
        cluesDown: cluesDown || [],
        selectedRow: -1,
        selectedCol: -1,
        direction: 'across',
        checked: {},
        revealed: {},
        solved: false,
        isDirty: false,
        saving: false,
        showSaved: false,
        mobileClueTab: 'across',
        _saveTimer: null,
        _savedTimer: null,

        init() {
            this.$watch('isDirty', (val) => {
                if (val) this.debouncedSave();
            });
        },

        // --- Computed clue lists with lengths ---
        get computedCluesAcross() {
            return this.cluesAcross.map(clue => {
                const slot = this.findSlot('across', clue.number);
                clue.length = slot ? slot.length : 0;
                return clue;
            });
        },

        get computedCluesDown() {
            return this.cluesDown.map(clue => {
                const slot = this.findSlot('down', clue.number);
                clue.length = slot ? slot.length : 0;
                return clue;
            });
        },

        findSlot(dir, number) {
            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (this.grid[row][col] === number) {
                        if (dir === 'across') {
                            let len = 0;
                            while (col + len < this.width && !this.isBlock(row, col + len)) len++;
                            if (len > 1) return { row, col, length: len };
                        } else {
                            let len = 0;
                            while (row + len < this.height && !this.isBlock(row + len, col)) len++;
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
                while (c > 0 && !this.isBlock(row, c - 1)) c--;
                return typeof this.grid[row][c] === 'number' && this.grid[row][c] > 0 ? this.grid[row][c] : -1;
            } else {
                let r = row;
                while (r > 0 && !this.isBlock(r - 1, col)) r--;
                return typeof this.grid[r][col] === 'number' && this.grid[r][col] > 0 ? this.grid[r][col] : -1;
            }
        },

        getWordCells(row, col, dir) {
            if (this.isBlock(row, col)) return [];
            const cells = [];
            if (dir === 'across') {
                let c = col;
                while (c > 0 && !this.isBlock(row, c - 1)) c--;
                while (c < this.width && !this.isBlock(row, c)) { cells.push([row, c]); c++; }
            } else {
                let r = row;
                while (r > 0 && !this.isBlock(r - 1, col)) r--;
                while (r < this.height && !this.isBlock(r, col)) { cells.push([r, col]); r++; }
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

        cellClasses(row, col) {
            if (this.isVoid(row, col)) return 'invisible';
            if (this.isBlock(row, col)) return 'bg-zinc-800 dark:bg-zinc-300';

            const isSelected = row === this.selectedRow && col === this.selectedCol;
            const wordCells = this.selectedRow >= 0 ? this.getWordCells(this.selectedRow, this.selectedCol, this.direction) : [];
            const isInWord = wordCells.some(([r, c]) => r === row && c === col);

            if (isSelected) return 'bg-blue-300 dark:bg-blue-700 cursor-pointer';
            if (isInWord) return 'bg-blue-100 dark:bg-blue-900/50 cursor-pointer';
            return 'bg-white dark:bg-zinc-800 cursor-pointer';
        },

        letterClass(row, col) {
            const key = row + ',' + col;
            if (this.revealed[key]) return 'text-blue-600 dark:text-blue-400';
            if (this.checked[key] === 'wrong') return 'text-red-600 dark:text-red-400';
            if (this.checked[key] === 'correct') return 'text-emerald-600 dark:text-emerald-400';
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

            if (key === 'Escape') { this.selectedRow = -1; this.selectedCol = -1; return; }
            if (this.selectedRow < 0) return;

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
                if (!this.isBlock(this.selectedRow, this.selectedCol)) {
                    this.progress[this.selectedRow][this.selectedCol] = '';
                    delete this.checked[this.selectedRow + ',' + this.selectedCol];
                    this.isDirty = true;
                }
                return;
            }

            if (/^[a-zA-Z]$/.test(key)) {
                e.preventDefault();
                if (!this.isBlock(this.selectedRow, this.selectedCol)) {
                    this.progress[this.selectedRow][this.selectedCol] = key.toUpperCase();
                    delete this.checked[this.selectedRow + ',' + this.selectedCol];
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
            let row = this.selectedRow, col = this.selectedCol;
            if (this.direction === 'across') {
                col++;
                while (col < this.width && this.isBlock(row, col)) col++;
                if (col < this.width) this.selectedCol = col;
            } else {
                row++;
                while (row < this.height && this.isBlock(row, col)) row++;
                if (row < this.height) this.selectedRow = row;
            }
        },

        handleBackspace() {
            const row = this.selectedRow, col = this.selectedCol;
            if (!this.isBlock(row, col) && this.progress[row][col]) {
                this.progress[row][col] = '';
                delete this.checked[row + ',' + col];
                this.isDirty = true;
            } else {
                if (this.direction === 'across') {
                    let c = col - 1;
                    while (c >= 0 && this.isBlock(row, c)) c--;
                    if (c >= 0) { this.selectedCol = c; this.progress[row][c] = ''; delete this.checked[row + ',' + c]; this.isDirty = true; }
                } else {
                    let r = row - 1;
                    while (r >= 0 && this.isBlock(r, col)) r--;
                    if (r >= 0) { this.selectedRow = r; this.progress[r][col] = ''; delete this.checked[r + ',' + col]; this.isDirty = true; }
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
                    if (this.isBlock(row, col) || !this.progress[row][col]) continue;
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
                this.progress[row][col] = answer;
                this.revealed[row + ',' + col] = true;
                delete this.checked[row + ',' + col];
                this.isDirty = true;
                this.advanceCursor();
                this.checkIfSolved();
            }
        },

        clearProgress() {
            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (!this.isBlock(row, col)) this.progress[row][col] = '';
                }
            }
            this.checked = {};
            this.revealed = {};
            this.solved = false;
            this.isDirty = true;
        },

        clearErrors() {
            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (this.isBlock(row, col)) continue;
                    if (this.progress[row][col] && this.progress[row][col] !== this.solution[row][col]) {
                        this.progress[row][col] = '';
                        delete this.checked[row + ',' + col];
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
            // Save immediately with completed flag
            this.saving = true;
            this.showSaved = false;
            await this.$wire.saveProgress(JSON.parse(JSON.stringify(this.progress)), true);
            this.isDirty = false;
            this.saving = false;
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
            await this.$wire.saveProgress(JSON.parse(JSON.stringify(this.progress)), this.solved);
            this.isDirty = false;
            this.saving = false;
        },

        onSaved() {
            this.saving = false;
            this.showSaved = true;
            clearTimeout(this._savedTimer);
            this._savedTimer = setTimeout(() => { this.showSaved = false; }, 2000);
        },
    };
}
