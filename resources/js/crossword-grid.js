export function crosswordGrid({ width, height, grid, solution, styles, cluesAcross, cluesDown }) {
    return {
        width,
        height,
        grid,
        solution,
        styles: styles || {},
        cluesAcross: cluesAcross || [],
        cluesDown: cluesDown || [],
        selectedRow: -1,
        selectedCol: -1,
        direction: 'across',
        mode: 'edit',
        symmetry: true,
        isDirty: false,
        saving: false,
        showSaved: false,
        mobileClueTab: 'across',
        clueSuggestions: [],
        clueSuggestionsLoading: false,
        clueSuggestionsWord: '',
        _saveTimer: null,
        _savedTimer: null,
        _suggestTimer: null,

        init() {
            this.$watch('isDirty', (val) => {
                if (val) this.debouncedSave();
            });

            this.$watch('activeClueNumber', () => {
                this.debouncedFetchSuggestions();
            });

            this.$watch('direction', () => {
                this.debouncedFetchSuggestions();
            });

            window.addEventListener('beforeunload', (e) => {
                if (this.isDirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },

        // --- Grid numbering (mirrors PHP GridNumberer) ---
        numberGrid() {
            const numbered = this.grid.map(row => row.map(cell => cell === '#' ? '#' : (cell === null ? null : 0)));
            const acrossSlots = [];
            const downSlots = [];
            let clueNum = 0;

            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (this.isBlock(row, col)) continue;

                    const startsAcross = (col === 0 || this.isBlock(row, col - 1)) &&
                        (col + 1 < this.width && !this.isBlock(row, col + 1));
                    const startsDown = (row === 0 || this.isBlock(row - 1, col)) &&
                        (row + 1 < this.height && !this.isBlock(row + 1, col));

                    if (startsAcross || startsDown) {
                        clueNum++;
                        numbered[row][col] = clueNum;

                        if (startsAcross) {
                            let len = 0;
                            while (col + len < this.width && !this.isBlock(row, col + len)) len++;
                            acrossSlots.push({ number: clueNum, row, col, length: len });
                        }
                        if (startsDown) {
                            let len = 0;
                            while (row + len < this.height && !this.isBlock(row + len, col)) len++;
                            downSlots.push({ number: clueNum, row, col, length: len });
                        }
                    }
                }
            }

            this.grid = numbered;
            this.remapClues(acrossSlots, downSlots);
        },

        remapClues(acrossSlots, downSlots) {
            const oldAcross = new Map(this.cluesAcross.map(c => [c.number, c.clue]));
            const oldDown = new Map(this.cluesDown.map(c => [c.number, c.clue]));

            this.cluesAcross = acrossSlots.map(s => ({
                number: s.number,
                clue: oldAcross.get(s.number) || '',
            }));
            this.cluesDown = downSlots.map(s => ({
                number: s.number,
                clue: oldDown.get(s.number) || '',
            }));
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
                while (c < this.width && !this.isBlock(row, c)) {
                    cells.push([row, c]);
                    c++;
                }
            } else {
                let r = row;
                while (r > 0 && !this.isBlock(r - 1, col)) r--;
                while (r < this.height && !this.isBlock(r, col)) {
                    cells.push([r, col]);
                    r++;
                }
            }
            return cells;
        },

        // --- Cell state helpers ---
        isVoid(row, col) {
            return this.grid[row]?.[col] === null;
        },

        isBlock(row, col) {
            const cell = this.grid[row]?.[col];
            return cell === '#' || cell === null;
        },

        hasCircle(row, col) {
            const key = row + ',' + col;
            return this.styles[key]?.shapebg === 'circle';
        },

        cellClasses(row, col) {
            if (this.isVoid(row, col)) {
                return 'invisible';
            }
            if (this.isBlock(row, col)) {
                return 'bg-zinc-800 dark:bg-zinc-300 cursor-pointer';
            }

            const isSelected = row === this.selectedRow && col === this.selectedCol;
            const wordCells = this.selectedRow >= 0 ? this.getWordCells(this.selectedRow, this.selectedCol, this.direction) : [];
            const isInWord = wordCells.some(([r, c]) => r === row && c === col);

            if (isSelected) {
                return 'bg-blue-300 dark:bg-blue-700 cursor-pointer';
            }
            if (isInWord) {
                return 'bg-blue-100 dark:bg-blue-900/50 cursor-pointer';
            }
            return 'bg-white dark:bg-zinc-800 cursor-pointer';
        },

        // --- Selection ---
        selectCell(row, col) {
            if (this.isVoid(row, col)) return;
            if (this.isBlock(row, col)) {
                if (this.mode === 'edit') {
                    this.toggleBlock(row, col);
                }
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

        selectClue(dir, number, event) {
            this.direction = dir;
            const clues = dir === 'across' ? this.cluesAcross : this.cluesDown;
            const clue = clues.find(c => c.number === number);
            if (!clue) return;

            const clickedInput = event?.target?.tagName === 'INPUT' || event?.target?.closest?.('.clue-content');

            // Find the cell with this clue number
            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (this.grid[row][col] === number) {
                        this.selectedRow = row;
                        this.selectedCol = col;
                        if (!clickedInput) {
                            this.$refs.gridContainer?.focus();
                        }
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
                if (el && panel) {
                    el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            });
        },

        // --- Keyboard handling ---
        handleKeydown(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

            const key = e.key;

            if (key === 'Escape') {
                this.selectedRow = -1;
                this.selectedCol = -1;
                return;
            }

            if (this.selectedRow < 0) return;

            if (key === 'ArrowUp' || key === 'ArrowDown' || key === 'ArrowLeft' || key === 'ArrowRight') {
                e.preventDefault();
                this.moveArrow(key);
                return;
            }

            if (key === 'Tab') {
                e.preventDefault();
                this.jumpToNextClue(e.shiftKey);
                return;
            }

            if (key === 'Enter') {
                e.preventDefault();
                this.direction = this.direction === 'across' ? 'down' : 'across';
                this.scrollActiveClueIntoView();
                return;
            }

            if (key === ' ' && this.mode === 'edit') {
                e.preventDefault();
                this.toggleBlock(this.selectedRow, this.selectedCol);
                return;
            }

            if (key === 'Backspace') {
                e.preventDefault();
                this.handleBackspace();
                return;
            }

            if (key === 'Delete') {
                e.preventDefault();
                if (!this.isBlock(this.selectedRow, this.selectedCol)) {
                    this.solution[this.selectedRow][this.selectedCol] = '';
                    this.markDirty();
                }
                return;
            }

            if (/^[a-zA-Z]$/.test(key)) {
                e.preventDefault();
                if (!this.isBlock(this.selectedRow, this.selectedCol)) {
                    this.solution[this.selectedRow][this.selectedCol] = key.toUpperCase();
                    this.advanceCursor();
                    this.markDirty();
                }
                return;
            }
        },

        moveArrow(key) {
            let row = this.selectedRow;
            let col = this.selectedCol;

            const delta = {
                ArrowUp: [-1, 0],
                ArrowDown: [1, 0],
                ArrowLeft: [0, -1],
                ArrowRight: [0, 1],
            }[key];

            do {
                row += delta[0];
                col += delta[1];
            } while (
                row >= 0 && row < this.height &&
                col >= 0 && col < this.width &&
                this.isBlock(row, col)
            );

            if (row >= 0 && row < this.height && col >= 0 && col < this.width && !this.isVoid(row, col)) {
                this.selectedRow = row;
                this.selectedCol = col;

                // Update direction to match arrow
                if (key === 'ArrowLeft' || key === 'ArrowRight') this.direction = 'across';
                if (key === 'ArrowUp' || key === 'ArrowDown') this.direction = 'down';

                this.scrollActiveClueIntoView();
            }
        },

        advanceCursor() {
            let row = this.selectedRow;
            let col = this.selectedCol;

            if (this.direction === 'across') {
                col++;
                while (col < this.width && this.isBlock(row, col)) col++;
                if (col < this.width) {
                    this.selectedCol = col;
                }
            } else {
                row++;
                while (row < this.height && this.isBlock(row, col)) row++;
                if (row < this.height) {
                    this.selectedRow = row;
                }
            }
        },

        handleBackspace() {
            const row = this.selectedRow;
            const col = this.selectedCol;

            if (!this.isBlock(row, col) && this.solution[row][col]) {
                this.solution[row][col] = '';
                this.markDirty();
            } else {
                // Move backward
                if (this.direction === 'across') {
                    let c = col - 1;
                    while (c >= 0 && this.isBlock(row, c)) c--;
                    if (c >= 0) {
                        this.selectedCol = c;
                        this.solution[row][c] = '';
                        this.markDirty();
                    }
                } else {
                    let r = row - 1;
                    while (r >= 0 && this.isBlock(r, col)) r--;
                    if (r >= 0) {
                        this.selectedRow = r;
                        this.solution[r][col] = '';
                        this.markDirty();
                    }
                }
            }
        },

        jumpToNextClue(reverse) {
            const clues = this.direction === 'across' ? this.cluesAcross : this.cluesDown;
            if (clues.length === 0) return;

            const currentNum = this.activeClueNumber;
            const idx = clues.findIndex(c => c.number === currentNum);

            let nextIdx;
            if (reverse) {
                nextIdx = idx <= 0 ? clues.length - 1 : idx - 1;
            } else {
                nextIdx = idx >= clues.length - 1 ? 0 : idx + 1;
            }

            this.selectClue(this.direction, clues[nextIdx].number);
        },

        // --- Block toggling ---
        toggleBlock(row, col) {
            if (this.isBlock(row, col)) {
                this.grid[row][col] = 0;
                this.solution[row][col] = '';
                if (this.symmetry) {
                    const symRow = this.height - 1 - row;
                    const symCol = this.width - 1 - col;
                    this.grid[symRow][symCol] = 0;
                    this.solution[symRow][symCol] = '';
                }
            } else {
                this.grid[row][col] = '#';
                this.solution[row][col] = '#';
                // Remove style for blocked cell
                delete this.styles[row + ',' + col];
                if (this.symmetry) {
                    const symRow = this.height - 1 - row;
                    const symCol = this.width - 1 - col;
                    this.grid[symRow][symCol] = '#';
                    this.solution[symRow][symCol] = '#';
                    delete this.styles[symRow + ',' + symCol];
                }
                // Deselect if we blocked the selected cell
                if (this.selectedRow === row && this.selectedCol === col) {
                    this.selectedRow = -1;
                    this.selectedCol = -1;
                }
            }

            this.numberGrid();
            this.markDirty();
        },

        // --- Annotations ---
        toggleCircle() {
            if (this.selectedRow < 0 || this.isBlock(this.selectedRow, this.selectedCol)) return;

            const key = this.selectedRow + ',' + this.selectedCol;
            if (this.styles[key]?.shapebg === 'circle') {
                delete this.styles[key];
            } else {
                this.styles[key] = { shapebg: 'circle' };
            }
            this.markDirty();
        },

        // --- Clear ---
        clearLetters() {
            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (!this.isBlock(row, col)) {
                        this.solution[row][col] = '';
                    }
                }
            }
            this.markDirty();
        },

        clearAll() {
            this.grid = Array.from({ length: this.height }, () => Array(this.width).fill(0));
            this.solution = Array.from({ length: this.height }, () => Array(this.width).fill(''));
            this.styles = {};
            this.selectedRow = -1;
            this.selectedCol = -1;
            this.numberGrid();
            this.markDirty();
        },

        // --- Persistence ---
        markDirty() {
            this.isDirty = true;
        },

        debouncedSave() {
            clearTimeout(this._saveTimer);
            this._saveTimer = setTimeout(() => {
                this.saveNow();
            }, 3000);
        },

        async saveNow() {
            if (!this.isDirty) return;

            this.saving = true;
            this.showSaved = false;

            await this.$wire.save(
                JSON.parse(JSON.stringify(this.grid)),
                JSON.parse(JSON.stringify(this.solution)),
                Object.keys(this.styles).length > 0 ? JSON.parse(JSON.stringify(this.styles)) : null,
                JSON.parse(JSON.stringify(this.cluesAcross)),
                JSON.parse(JSON.stringify(this.cluesDown)),
            );

            this.isDirty = false;
            this.saving = false;
        },

        onSaved() {
            this.saving = false;
            this.showSaved = true;
            clearTimeout(this._savedTimer);
            this._savedTimer = setTimeout(() => {
                this.showSaved = false;
            }, 2000);
        },

        onGridResized() {
            // Re-sync from Livewire after resize
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

        async saveAndSolve() {
            if (this.isDirty) {
                await this.$wire.save(
                    JSON.parse(JSON.stringify(this.grid)),
                    JSON.parse(JSON.stringify(this.solution)),
                    Object.keys(this.styles).length > 0 ? JSON.parse(JSON.stringify(this.styles)) : null,
                    JSON.parse(JSON.stringify(this.cluesAcross)),
                    JSON.parse(JSON.stringify(this.cluesDown)),
                );
            }
            window.location.href = this.$el.querySelector('a[href*="/solve"]').href;
        },

        // --- Clue suggestions ---
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

        debouncedFetchSuggestions() {
            clearTimeout(this._suggestTimer);
            this._suggestTimer = setTimeout(() => this.fetchClueSuggestions(), 200);
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
                const results = await this.$wire.lookupClues(word);
                this.clueSuggestions = results;
                this.clueSuggestionsWord = word;
            } catch (e) {
                this.clueSuggestions = [];
            }
            this.clueSuggestionsLoading = false;
        },

        useClue(clue, text) {
            clue.clue = text;
            this.markDirty();
        },
    };
}
