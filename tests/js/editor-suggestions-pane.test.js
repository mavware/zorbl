import { describe, expect, it, beforeEach, afterEach, vi } from 'vitest';
import { crosswordGrid } from '../../resources/js/crossword-grid.js';
import { cellKey } from '../../resources/js/grid/helpers.js';

// 3x3 grid with one block at (0,2).
//   Across: 1 → (0,0)-(0,1)   3 → (1,0)-(1,2)   5 → (2,0)-(2,2)
//   Down:   1 → (0,0)-(2,0)   2 → (0,1)-(2,1)   4 → (1,2)-(2,2)
function makeGrid(overrides = {}) {
    const inst = crosswordGrid({
        width: 3,
        height: 3,
        grid: [
            [1, 2, '#'],
            [3, 0, 4],
            [5, 0, 0],
        ],
        solution: [
            ['A', '', '#'],
            ['S', '', 'L'],
            ['', '', ''],
        ],
        styles: {},
        cluesAcross: [
            { number: 1, clue: '' },
            { number: 3, clue: '' },
            { number: 5, clue: '' },
        ],
        cluesDown: [
            { number: 1, clue: '' },
            { number: 2, clue: '' },
            { number: 4, clue: '' },
        ],
        minAnswerLength: 2,
        prefilled: null,
        gridLocked: false,
        puzzleType: 'classic',
        ...overrides,
    });
    inst.$watch = () => {};
    inst.$refs = { gridContainer: { focus: vi.fn() } };
    inst.$nextTick = () => {};
    inst.$wire = {
        suggestWords: vi.fn(() => Promise.resolve([])),
        lookupClues: vi.fn(() => Promise.resolve([])),
    };
    return inst;
}

const paneKey = (g, key) => g.handleSuggestionsKeydown({
    key, preventDefault() {}, stopPropagation() {},
});

const gridKey = (g, key) => g.handleKeydown({
    key, target: { tagName: 'DIV' }, preventDefault() {}, ctrlKey: false, metaKey: false, shiftKey: false,
});

describe('suggestions pane slot pattern', () => {
    it('is null when no cell is selected', () => {
        const g = makeGrid();
        expect(g.suggestionsSlot).toBeNull();
    });

    it('derives direction, number and a ? pattern for the selected across slot', () => {
        const g = makeGrid();
        g.selectedRow = 1; g.selectedCol = 1; g.direction = 'across';
        expect(g.suggestionsSlot).toEqual({
            direction: 'across', number: 3, pattern: 'S?L', length: 3, filled: false,
        });
    });

    it('follows the direction toggle to the crossing down slot', () => {
        const g = makeGrid();
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'down';
        expect(g.suggestionsSlot).toMatchObject({ direction: 'down', number: 1, pattern: 'AS?' });
    });

    it('uppercases letters and flags a fully filled slot', () => {
        const g = makeGrid();
        g.solution[1] = ['s', 'a', 'l'];
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        expect(g.suggestionsSlot).toMatchObject({ pattern: 'SAL', filled: true });
    });

    it('updates as letters are typed in the grid', () => {
        const g = makeGrid();
        g.selectedRow = 1; g.selectedCol = 1; g.direction = 'across';
        gridKey(g, 'o');
        expect(g.suggestionsSlot.pattern).toBe('SOL');
        // The cursor advanced onto the L, so Backspace clears that cell.
        gridKey(g, 'Backspace');
        expect(g.suggestionsSlot.pattern).toBe('SO?');
    });
});

describe('suggestions pane keyboard', () => {
    let g;

    beforeEach(() => {
        g = makeGrid();
        g.suggestionsPaneMounted = true;
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        g.wordSuggestions = [
            { word: 'SAL', score: 80 },
            { word: 'SOL', score: 50 },
            { word: 'SEL', score: 20 },
        ];
    });

    it('↓ highlights the next candidate and previews it without touching the solution', () => {
        paneKey(g, 'ArrowDown');
        expect(g.suggestionsIndex).toBe(0);
        expect(g.previewLetters).toEqual({ [cellKey(1, 1)]: 'A' });
        expect(g.displayLetter(1, 1)).toBe('A');
        expect(g.displayLetter(1, 0)).toBe('S');
        expect(g.solution[1]).toEqual(['S', '', 'L']);
        expect(g.isDirty).toBe(false);

        paneKey(g, 'ArrowDown');
        expect(g.suggestionsIndex).toBe(1);
        expect(g.displayLetter(1, 1)).toBe('O');
        expect(g.isPreviewCell(1, 1)).toBe(true);
        expect(g.isPreviewCell(1, 0)).toBe(false);
    });

    it('↑ moves back and wraps from the top to the last candidate', () => {
        paneKey(g, 'ArrowUp');
        expect(g.suggestionsIndex).toBe(2);
        expect(g.displayLetter(1, 1)).toBe('E');
        paneKey(g, 'ArrowUp');
        expect(g.suggestionsIndex).toBe(1);
    });

    it('Enter places the highlighted word and clears the preview', () => {
        paneKey(g, 'ArrowDown');
        paneKey(g, 'ArrowDown');
        paneKey(g, 'Enter');
        expect(g.solution[1]).toEqual(['S', 'O', 'L']);
        expect(g.isDirty).toBe(true);
        expect(g.previewLetters).toEqual({});
        expect(g.suggestionsIndex).toBe(-1);
        expect(g.suggestionsSlot).toMatchObject({ pattern: 'SOL', filled: true });
    });

    it('Enter with nothing highlighted places nothing', () => {
        paneKey(g, 'Enter');
        expect(g.solution[1]).toEqual(['S', '', 'L']);
        expect(g.isDirty).toBe(false);
    });

    it('Esc drops the preview and returns focus to the grid without placing', () => {
        paneKey(g, 'ArrowDown');
        paneKey(g, 'Escape');
        expect(g.previewLetters).toEqual({});
        expect(g.suggestionsIndex).toBe(-1);
        expect(g.solution[1]).toEqual(['S', '', 'L']);
        expect(g.$refs.gridContainer.focus).toHaveBeenCalled();
    });

    it('leaving the pane clears a lingering preview', () => {
        paneKey(g, 'ArrowDown');
        g.onSuggestionsPaneBlur({ relatedTarget: null });
        expect(g.previewLetters).toEqual({});
    });

    it('Enter on the clue library tab writes the clue into the active entry', () => {
        g.suggestionsTab = 'clues';
        g.clueSuggestions = [{ clue: 'Kitchen staple', author: 'Ada', puzzle: 'Monday' }];
        paneKey(g, 'ArrowDown');
        expect(g.previewLetters).toEqual({});
        paneKey(g, 'Enter');
        expect(g.cluesAcross[1].clue).toBe('Kitchen staple');
        expect(g.isDirty).toBe(true);
        // Desktop keeps the library list; only the mobile popover closes on use.
        expect(g.clueSuggestions).toHaveLength(1);
    });
});

describe('suggestions pane lookups', () => {
    beforeEach(() => { vi.useFakeTimers(); });
    afterEach(() => { vi.useRealTimers(); });

    it('debounces letter edits into a single request', async () => {
        const g = makeGrid();
        g.suggestionsPaneMounted = true;
        g.selectedRow = 2; g.selectedCol = 0; g.direction = 'across';

        g.refreshSuggestionsPane();
        expect(g.$wire.suggestWords).toHaveBeenCalledTimes(1);
        expect(g.$wire.suggestWords).toHaveBeenLastCalledWith('___', 3);

        gridKey(g, 'a');
        gridKey(g, 'b');
        expect(g.$wire.suggestWords).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(300);
        expect(g.$wire.suggestWords).toHaveBeenCalledTimes(2);
        expect(g.$wire.suggestWords).toHaveBeenLastCalledWith('AB_', 3);
    });

    it('does not refetch the same pattern twice', () => {
        const g = makeGrid();
        g.suggestionsPaneMounted = true;
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        g.refreshSuggestionsPane();
        g.refreshSuggestionsPane();
        expect(g.$wire.suggestWords).toHaveBeenCalledTimes(1);
    });

    it('stays quiet while collapsed or before the pane is mounted', () => {
        const g = makeGrid();
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        g.refreshSuggestionsPane();
        g.suggestionsPaneMounted = true;
        g.suggestionsPaneCollapsed = true;
        g.refreshSuggestionsPane();
        expect(g.$wire.suggestWords).not.toHaveBeenCalled();
    });

    it('switching to the clue library looks up the filled answer', async () => {
        const g = makeGrid();
        g.suggestionsPaneMounted = true;
        g.solution[1] = ['S', 'A', 'L'];
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        g.$wire.lookupClues = vi.fn(() => Promise.resolve([{ clue: 'x', author: 'y', puzzle: 'z' }]));
        g.setSuggestionsTab('clues');
        expect(g.$wire.lookupClues).toHaveBeenCalledWith('SAL');
        await vi.runAllTimersAsync();
        expect(g.clueSuggestions).toHaveLength(1);
        expect(g.clueSuggestionsLoading).toBe(false);
    });

    it('drops a stale word lookup that resolves after the slot moved on', async () => {
        const g = makeGrid();
        g.suggestionsPaneMounted = true;
        let resolveFirst;
        g.$wire.suggestWords = vi.fn()
            .mockImplementationOnce(() => new Promise((r) => { resolveFirst = r; }))
            .mockImplementation(() => Promise.resolve([{ word: 'NEW', score: 90 }]));

        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        g.refreshSuggestionsPane();
        g.selectedRow = 2; g.selectedCol = 0;
        g.onActiveSlotChanged();
        await vi.runAllTimersAsync();
        resolveFirst([{ word: 'OLD', score: 10 }]);
        await vi.runAllTimersAsync();

        expect(g.wordSuggestions).toEqual([{ word: 'NEW', score: 90 }]);
    });
});

describe('suggestion score colour', () => {
    it('maps score bands to emerald, amber and muted', () => {
        const g = makeGrid();
        expect(g.suggestionScoreClass(70)).toContain('emerald');
        expect(g.suggestionScoreClass(45)).toContain('amber');
        expect(g.suggestionScoreClass(44.9)).toBe('text-fg-subtle');
    });
});

describe('suggestions pane focus', () => {
    it('clicking inside the pane keeps the grid selection', () => {
        const g = makeGrid();
        const target = {};
        g.$refs.suggestionsPane = { contains: (el) => el === target, offsetParent: {} };
        g.selectedRow = 1; g.selectedCol = 0;
        g.handleClickOutside({ target });
        expect(g.selectedRow).toBe(1);
        g.handleClickOutside({ target: {} });
        expect(g.selectedRow).toBe(-1);
    });
});
