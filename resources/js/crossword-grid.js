export function crosswordGrid({ width, height, grid, solution, styles, cluesAcross, cluesDown, minAnswerLength, prefilled }) {
    return {
        width,
        height,
        grid,
        solution,
        styles: styles || {},
        cluesAcross: cluesAcross || [],
        cluesDown: cluesDown || [],
        minAnswerLength: minAnswerLength || 3,
        prefilled: prefilled || null,
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
        incompleteHighlights: [],
        rebusMode: false,
        multiSelectedCells: {},
        contextMenu: { show: false, row: -1, col: -1, x: 0, y: 0 },
        _saveTimer: null,
        _saving: false,
        _savedTimer: null,
        _longPressTimer: null,

        init() {
            document.addEventListener('click', () => this.closeContextMenu());
            document.addEventListener('mousedown', (e) => this.handleClickOutside(e));
            this.$watch('isDirty', (val) => {
                if (val) this.debouncedSave();
            });

            this.$watch('activeClueNumber', () => {
                this.closeSuggestions();
            });

            this.$watch('direction', () => {
                this.closeSuggestions();
            });

            window.addEventListener('beforeunload', (e) => {
                if (this.isDirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            document.addEventListener('livewire:navigating', () => {
                if (this.isDirty && !this._saving) {
                    clearTimeout(this._saveTimer);
                    this.$wire.save(
                        JSON.parse(JSON.stringify(this.grid)),
                        JSON.parse(JSON.stringify(this.solution)),
                        Object.keys(this.styles).length > 0 ? JSON.parse(JSON.stringify(this.styles)) : null,
                        JSON.parse(JSON.stringify(this.cluesAcross)),
                        JSON.parse(JSON.stringify(this.cluesDown)),
                    );
                    this.isDirty = false;
                }
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

        // --- Grid numbering (mirrors PHP GridNumberer) ---
        numberGrid() {
            const minLen = this.minAnswerLength || 2;
            const acrossSlots = [];
            const downSlots = [];
            let clueNum = 0;

            // Reset all non-block, non-void cells to 0 in place
            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    const cell = this.grid[row][col];
                    if (cell !== '#' && cell !== null) {
                        this.grid[row][col] = 0;
                    }
                }
            }

            for (let row = 0; row < this.height; row++) {
                for (let col = 0; col < this.width; col++) {
                    if (this.isBlock(row, col)) continue;

                    const startsAcross = this.hasLeftBoundary(row, col) && !this.hasRightBoundary(row, col);
                    const startsDown = this.hasTopBoundary(row, col) && !this.hasBottomBoundary(row, col);

                    let acrossLen = 0;
                    let downLen = 0;

                    if (startsAcross) {
                        acrossLen = 1;
                        while (!this.hasRightBoundary(row, col + acrossLen - 1)) acrossLen++;
                    }
                    if (startsDown) {
                        downLen = 1;
                        while (!this.hasBottomBoundary(row + downLen - 1, col)) downLen++;
                    }

                    const hasAcross = startsAcross && acrossLen >= minLen;
                    const hasDown = startsDown && downLen >= minLen;

                    if (hasAcross || hasDown) {
                        clueNum++;
                        this.grid[row][col] = clueNum;

                        if (hasAcross) {
                            acrossSlots.push({ number: clueNum, row, col, length: acrossLen });
                        }
                        if (hasDown) {
                            downSlots.push({ number: clueNum, row, col, length: downLen });
                        }
                    }
                }
            }

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

        // --- Custom numbers ---
        getCustomNumber(row, col) {
            return this.styles[row + ',' + col]?.number ?? null;
        },

        setCustomNumber(row, col, number) {
            const key = row + ',' + col;
            if (number !== null && number !== '') {
                this.styles[key] = { ...this.styles[key], number: parseInt(number, 10) };
            } else {
                const entry = { ...this.styles[key] };
                delete entry.number;
                if (Object.keys(entry).length === 0 || (Object.keys(entry).length === 1 && entry.bars?.length === 0)) {
                    delete this.styles[key];
                } else {
                    this.styles[key] = entry;
                }
            }
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
            const isMulti = this.isMultiSelected(row, col);
            const wordCells = this.selectedRow >= 0 ? this.getWordCells(this.selectedRow, this.selectedCol, this.direction) : [];
            const isInWord = wordCells.some(([r, c]) => r === row && c === col);

            const emptyHighlight = this.isCellIncomplete() && !this.solution[row]?.[col]?.trim()
                ? ' ring-2 ring-inset ring-amber-400 dark:ring-amber-500' : '';

            if (isMulti) {
                return 'bg-emerald-200 dark:bg-emerald-700 cursor-pointer' + emptyHighlight;
            }
            if (isSelected) {
                return 'bg-blue-300 dark:bg-blue-700 cursor-pointer' + emptyHighlight;
            }
            if (isInWord) {
                return 'bg-blue-100 dark:bg-blue-900/50 cursor-pointer' + emptyHighlight;
            }
            return 'bg-white dark:bg-zinc-800 cursor-pointer' + emptyHighlight;
        },

        isRebus(row, col) {
            const val = this.solution[row]?.[col] || '';
            return val.length > 1;
        },

        letterFontStyle(row, col) {
            const val = this.solution[row]?.[col] || '';
            const baseFontSize = Math.max(12, Math.min(24, 600 / this.width * 0.55));
            if (val.length <= 1) {
                return 'font-size: ' + baseFontSize + 'px';
            }
            // Scale down for multi-letter cells
            const scaled = Math.max(6, baseFontSize / Math.max(val.length * 0.55, 1));
            return 'font-size: ' + scaled + 'px; letter-spacing: -0.5px';
        },

        // --- Multi-selection ---
        isMultiSelected(row, col) {
            return !!this.multiSelectedCells[row + ',' + col];
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
            if (this.selectedRow < 0 && this.selectedCol < 0 && Object.keys(this.multiSelectedCells).length === 0) {
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

        // --- Selection ---
        selectCell(row, col, event) {
            if (this.isVoid(row, col)) return;

            // Ctrl/Cmd+click for multi-select
            if (event && (event.ctrlKey || event.metaKey)) {
                if (this.isBlock(row, col)) return;
                const key = row + ',' + col;
                if (this.multiSelectedCells[key]) {
                    delete this.multiSelectedCells[key];
                } else {
                    this.multiSelectedCells[key] = true;
                }
                // Also include the primary selected cell in multi-selection if starting fresh
                if (Object.keys(this.multiSelectedCells).length === 1 && this.selectedRow >= 0) {
                    const primaryKey = this.selectedRow + ',' + this.selectedCol;
                    if (!this.isBlock(this.selectedRow, this.selectedCol)) {
                        this.multiSelectedCells[primaryKey] = true;
                    }
                }
                this.$refs.gridContainer?.focus();
                return;
            }

            // Normal click clears multi-selection
            this.clearMultiSelection();

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
                if (this.contextMenu.show) {
                    this.closeContextMenu();
                    return;
                }
                if (Object.keys(this.multiSelectedCells).length > 0) {
                    this.clearMultiSelection();
                    return;
                }
                this.selectedRow = -1;
                this.selectedCol = -1;
                return;
            }

            if (this.selectedRow < 0) return;

            // Toggle rebus mode with Insert key
            if (key === 'Insert') {
                e.preventDefault();
                if (!this.isBlock(this.selectedRow, this.selectedCol)) {
                    this.rebusMode = !this.rebusMode;
                    if (this.rebusMode) {
                        this.solution[this.selectedRow][this.selectedCol] = '';
                        this.markDirty();
                    }
                }
                return;
            }

            // In rebus mode, accumulate letters and handle special keys
            if (this.rebusMode) {
                if (key === 'Escape') {
                    e.preventDefault();
                    this.rebusMode = false;
                    return;
                }
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
            const row = this.selectedRow;
            const col = this.selectedCol;

            if (this.direction === 'across') {
                if (!this.hasRightBoundary(row, col)) {
                    this.selectedCol = col + 1;
                }
            } else {
                if (!this.hasBottomBoundary(row, col)) {
                    this.selectedRow = row + 1;
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
                // Move backward within the current word
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
                const entry = { ...this.styles[key] };
                delete entry.shapebg;
                if (Object.keys(entry).length === 0 || (Object.keys(entry).length === 1 && entry.bars?.length === 0)) {
                    delete this.styles[key];
                } else {
                    this.styles[key] = entry;
                }
            } else {
                this.styles[key] = { ...this.styles[key], shapebg: 'circle' };
            }
            this.markDirty();
        },

        // --- Context menu ---
        openContextMenu(row, col, event) {
            if (this.isVoid(row, col)) return;
            const menuWidth = 200;
            const menuHeight = 280;
            this.contextMenu.x = Math.min(event.clientX, window.innerWidth - menuWidth);
            this.contextMenu.y = Math.min(event.clientY, window.innerHeight - menuHeight);
            this.contextMenu.row = row;
            this.contextMenu.col = col;
            this.contextMenu.show = true;

            // If right-clicking outside the multi-selection, clear it
            if (Object.keys(this.multiSelectedCells).length > 0 && !this.isMultiSelected(row, col)) {
                this.clearMultiSelection();
            }
        },

        closeContextMenu() {
            this.contextMenu.show = false;
        },

        contextToggleBlock() {
            const cells = this.getMultiSelectedCoords();
            if (cells.length > 0) {
                const makeBlock = !this.isBlock(this.contextMenu.row, this.contextMenu.col);
                for (const [r, c] of cells) {
                    const currentlyBlock = this.isBlock(r, c);
                    if (makeBlock && !currentlyBlock) {
                        this.toggleBlock(r, c);
                    } else if (!makeBlock && currentlyBlock) {
                        this.toggleBlock(r, c);
                    }
                }
                this.clearMultiSelection();
            } else {
                this.toggleBlock(this.contextMenu.row, this.contextMenu.col);
            }
            this.closeContextMenu();
        },

        setCircle(row, col, addCircle) {
            if (this.isBlock(row, col)) return;
            const key = row + ',' + col;
            if (addCircle) {
                this.styles[key] = { ...this.styles[key], shapebg: 'circle' };
            } else {
                const entry = { ...this.styles[key] };
                delete entry.shapebg;
                if (Object.keys(entry).length === 0 || (Object.keys(entry).length === 1 && entry.bars?.length === 0)) {
                    delete this.styles[key];
                } else {
                    this.styles[key] = entry;
                }
            }
        },

        contextToggleCircle() {
            const addCircle = !this.hasCircle(this.contextMenu.row, this.contextMenu.col);
            const cells = this.getMultiSelectedCoords();
            if (cells.length > 0) {
                for (const [r, c] of cells) {
                    this.setCircle(r, c, addCircle);
                }
                this.clearMultiSelection();
            } else {
                this.setCircle(this.contextMenu.row, this.contextMenu.col, addCircle);
            }
            this.markDirty();
            this.closeContextMenu();
        },

        // --- Pre-fill / Rebus ---
        showRebusInput: false,
        rebusInputValue: '',
        rebusCells: [],

        contextEditRebus() {
            const row = this.contextMenu.row;
            const col = this.contextMenu.col;
            if (this.isBlock(row, col)) return;

            const cells = this.getMultiSelectedCoords();
            if (cells.length > 0) {
                this.rebusCells = cells.filter(([r, c]) => !this.isBlock(r, c));
            } else {
                this.rebusCells = [[row, col]];
            }

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

            if (prefillDirty) {
                this.savePrefilled();
            }

            this.clearMultiSelection();
            this.showRebusInput = false;
            this.markDirty();
            this.$refs.gridContainer?.focus();
        },

        // --- Custom number overlay ---
        showCustomNumberInput: false,
        customNumberInputValue: '',
        customNumberCells: [],

        contextSetCustomNumber() {
            const row = this.contextMenu.row;
            const col = this.contextMenu.col;
            if (this.isBlock(row, col)) return;

            const cells = this.getMultiSelectedCoords();
            if (cells.length > 0) {
                this.customNumberCells = cells.filter(([r, c]) => !this.isBlock(r, c));
            } else {
                this.customNumberCells = [[row, col]];
            }

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
            if (!this.prefilled) {
                this.prefilled = [];
                for (let r = 0; r < this.height; r++) {
                    this.prefilled.push([]);
                    for (let c = 0; c < this.width; c++) {
                        this.prefilled[r].push('');
                    }
                }
            }
        },

        // --- Bars ---
        toggleBar(row, col, edge) {
            const key = row + ',' + col;
            const entry = this.styles[key] ? { ...this.styles[key] } : {};
            const bars = entry.bars ? [...entry.bars] : [];

            const idx = bars.indexOf(edge);
            if (idx >= 0) {
                bars.splice(idx, 1);
            } else {
                bars.push(edge);
            }

            if (bars.length > 0) {
                entry.bars = bars;
            } else {
                delete entry.bars;
            }

            if (Object.keys(entry).length === 0) {
                delete this.styles[key];
            } else {
                this.styles[key] = entry;
            }
            this.markDirty();
        },

        setBar(row, col, edge, addBar) {
            if (this.isBlock(row, col)) return;
            const key = row + ',' + col;
            const entry = this.styles[key] ? { ...this.styles[key] } : {};
            const bars = entry.bars ? [...entry.bars] : [];
            const idx = bars.indexOf(edge);

            if (addBar && idx < 0) {
                bars.push(edge);
            } else if (!addBar && idx >= 0) {
                bars.splice(idx, 1);
            } else {
                return;
            }

            if (bars.length > 0) {
                entry.bars = bars;
            } else {
                delete entry.bars;
            }

            if (Object.keys(entry).length === 0) {
                delete this.styles[key];
            } else {
                this.styles[key] = entry;
            }
        },

        contextToggleBar(edge) {
            const addBar = !this.hasBar(this.contextMenu.row, this.contextMenu.col, edge);
            const cells = this.getMultiSelectedCoords();
            if (cells.length > 0) {
                for (const [r, c] of cells) {
                    this.setBar(r, c, edge, addBar);
                }
                this.clearMultiSelection();
            } else {
                this.setBar(this.contextMenu.row, this.contextMenu.col, edge, addBar);
            }
            this.markDirty();
        },

        hasBar(row, col, edge) {
            return this.styles[row + ',' + col]?.bars?.includes(edge) || false;
        },

        cellBarStyles(row, col) {
            const key = row + ',' + col;
            const bars = this.styles[key]?.bars;
            if (!bars || bars.length === 0) return '';

            const shadows = [];
            if (bars.includes('top'))    shadows.push('inset 0 2px 0 0 var(--bar-color)');
            if (bars.includes('bottom')) shadows.push('inset 0 -2px 0 0 var(--bar-color)');
            if (bars.includes('left'))   shadows.push('inset 2px 0 0 0 var(--bar-color)');
            if (bars.includes('right'))  shadows.push('inset -2px 0 0 0 var(--bar-color)');
            return 'box-shadow: ' + shadows.join(', ');
        },

        // --- Long press (mobile) ---
        startLongPress(row, col, event) {
            const touch = event.touches[0];
            this._longPressTimer = setTimeout(() => {
                this.openContextMenu(row, col, { clientX: touch.clientX, clientY: touch.clientY });
            }, 500);
        },

        cancelLongPress() {
            clearTimeout(this._longPressTimer);
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
            if (!this.isDirty || this._saving) return;

            this._saving = true;
            this.saving = true;
            this.showSaved = false;
            this.isDirty = false;

            try {
                await this.$wire.save(
                    JSON.parse(JSON.stringify(this.grid)),
                    JSON.parse(JSON.stringify(this.solution)),
                    Object.keys(this.styles).length > 0 ? JSON.parse(JSON.stringify(this.styles)) : null,
                    JSON.parse(JSON.stringify(this.cluesAcross)),
                    JSON.parse(JSON.stringify(this.cluesDown)),
                );
            } catch (e) {
                this.isDirty = true;
            } finally {
                this._saving = false;
                this.saving = false;

                if (this.isDirty) {
                    this.debouncedSave();
                }
            }
        },

        onSaved() {
            this.saving = false;
            this.showSaved = true;
            clearTimeout(this._savedTimer);
            this._savedTimer = setTimeout(() => {
                this.showSaved = false;
            }, 2000);
        },

        isPrefilled(row, col) {
            if (!this.prefilled) return false;
            return !!this.prefilled[row]?.[col];
        },

        async savePrefilled() {
            if (!this.prefilled) return;
            this.saving = true;
            this.showSaved = false;
            await this.$wire.savePrefilled(JSON.parse(JSON.stringify(this.prefilled)));
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

        highlightIncomplete(checks) {
            this.incompleteHighlights = checks;

            // Auto-clear highlights after 8 seconds
            setTimeout(() => {
                this.incompleteHighlights = [];
            }, 8000);
        },

        isClueIncomplete(dir) {
            return this.incompleteHighlights.includes(dir === 'across' ? 'clues_across' : 'clues_down');
        },

        isCellIncomplete() {
            return this.incompleteHighlights.includes('fill');
        },

        // --- Clue quality indicators ---
        clueQuality(clue, dir) {
            const text = (clue.clue || '').trim();
            if (!text) return [];

            const issues = [];
            const answer = this.getAnswerForSlot(dir, clue.number);

            // Too short: clue text should be meaningfully longer than the answer
            if (text.length < 4) {
                issues.push({ type: 'short', message: 'Clue may be too short' });
            }

            // Clue contains the answer
            if (answer && answer.length > 1) {
                const answerLower = answer.toLowerCase();
                const textLower = text.toLowerCase();
                if (textLower.includes(answerLower)) {
                    issues.push({ type: 'answer', message: 'Clue contains the answer' });
                }
            }

            // Duplicate clue text (same text used for another clue)
            const allClues = [...this.cluesAcross, ...this.cluesDown];
            const dupes = allClues.filter(c => {
                if (c.number === clue.number && ((dir === 'across' && this.cluesAcross.includes(c)) || (dir === 'down' && this.cluesDown.includes(c)))) return false;
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
            const issues = this.clueQuality(clue, dir);
            return issues.map(i => i.message).join(', ');
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

        showSuggestions: false,

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

        // --- Word suggestions (autofill) ---
        showWordSuggestions: false,
        wordSuggestions: [],
        wordSuggestionsLoading: false,
        wordSuggestionsPattern: '',

        getPatternForSlot(dir, number) {
            const slot = this.findSlot(dir, number);
            if (!slot) return null;

            let pattern = '';
            for (let i = 0; i < slot.length; i++) {
                const r = dir === 'across' ? slot.row : slot.row + i;
                const c = dir === 'across' ? slot.col + i : slot.col;
                const letter = this.solution[r]?.[c] || '';
                pattern += (letter && letter !== '#' && letter !== '') ? letter.toUpperCase() : '_';
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
            }, 300);
        },

        async fetchWordSuggestions() {
            const num = this.activeClueNumber;
            if (num < 0) {
                this.wordSuggestions = [];
                return;
            }

            const pattern = this.getPatternForSlot(this.direction, num);
            if (!pattern || pattern.length < 2) {
                this.wordSuggestions = [];
                return;
            }

            if (!pattern.includes('_')) {
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

        // --- Grid autofill ---
        fillInProgress: false,
        fillMode: null,

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
            this.fillInProgress = true;
            this.fillMode = 'heuristic';
            try {
                const result = await this.$wire.heuristicFill(
                    JSON.parse(JSON.stringify(this.solution))
                );
                if (result.fills && result.fills.length) {
                    this.applyFills(result.fills);
                }
                this.$dispatch('notify', {
                    message: result.message,
                    type: result.success ? 'success' : 'warning',
                });
            } catch (e) {
                this.$dispatch('notify', {
                    message: 'Fill failed: ' + (e.message || 'Unknown error'),
                    type: 'error',
                });
            }
            this.fillInProgress = false;
            this.fillMode = null;
        },

        async aiFill() {
            this.fillInProgress = true;
            this.fillMode = 'ai';
            try {
                const result = await this.$wire.aiFill(
                    JSON.parse(JSON.stringify(this.solution))
                );
                if (result.upgrade) {
                    this.$wire.set('upgradeFeature', 'ai_fill');
                    this.$wire.set('showUpgradeModal', true);
                    this.fillInProgress = false;
                    this.fillMode = null;
                    return;
                }
                if (result.fills && result.fills.length) {
                    this.applyFills(result.fills);
                }
                this.$dispatch('notify', {
                    message: result.message,
                    type: result.success ? 'success' : 'warning',
                });
                if (result.needs_theme) {
                    this.$wire.set('showSettingsModal', true);
                }
            } catch (e) {
                this.$dispatch('notify', {
                    message: 'AI fill failed: ' + (e.message || 'Unknown error'),
                    type: 'error',
                });
            }
            this.fillInProgress = false;
            this.fillMode = null;
        },

        async aiGenerateClues() {
            this.fillInProgress = true;
            this.fillMode = 'ai';
            try {
                const result = await this.$wire.aiGenerateClues(
                    JSON.parse(JSON.stringify(this.solution))
                );
                if (result.upgrade) {
                    this.$wire.set('upgradeFeature', 'ai_clues');
                    this.$wire.set('showUpgradeModal', true);
                    this.fillInProgress = false;
                    this.fillMode = null;
                    return;
                }
                if (result.success && result.clues) {
                    this.applyClues(result.clues);
                }
                this.$dispatch('notify', {
                    message: result.message,
                    type: result.success ? 'success' : 'warning',
                });
            } catch (e) {
                this.$dispatch('notify', {
                    message: 'Clue generation failed: ' + (e.message || 'Unknown error'),
                    type: 'error',
                });
            }
            this.fillInProgress = false;
            this.fillMode = null;
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
            if (clues.across) {
                for (const [num, text] of Object.entries(clues.across)) {
                    const key = parseInt(num);
                    const clue = this.cluesAcross.find(c => c.number === key);
                    if (clue) {
                        clue.clue = text;
                    }
                }
            }
            if (clues.down) {
                for (const [num, text] of Object.entries(clues.down)) {
                    const key = parseInt(num);
                    const clue = this.cluesDown.find(c => c.number === key);
                    if (clue) {
                        clue.clue = text;
                    }
                }
            }
            this.markDirty();
        },
    };
}
