import { describe, expect, it, beforeEach, afterEach, vi } from 'vitest';
import { crosswordGrid } from '../../resources/js/crossword-grid.js';

// Same 3x3 fixture as the pane tests: one block at (0,2).
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

// A sheet element as the browser reports it: position: fixed, so
// offsetParent is null, and hidden (display: none) means no client rects.
const sheetEl = (visible) => ({
    offsetParent: null,
    getClientRects: () => (visible ? [{ width: 390, height: 48 }] : []),
});

describe('suggestions sheet visibility', () => {
    it('is inactive while closed, even with a slot selected', () => {
        const g = makeGrid();
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        expect(g.isSuggestionsSheetActive()).toBe(false);
        expect(g.isSuggestionsSurfaceActive()).toBe(false);
    });

    it('is active once opened, reading visibility from client rects rather than offsetParent', () => {
        const g = makeGrid();
        g.$refs.suggestionsSheet = sheetEl(true);
        g.suggestionsSheetOpen = true;
        expect(g.isSuggestionsSheetActive()).toBe(true);
    });

    it('is inactive when the layout hides it at lg and above', () => {
        const g = makeGrid();
        g.$refs.suggestionsSheet = sheetEl(false);
        g.suggestionsSheetOpen = true;
        expect(g.isSuggestionsSheetActive()).toBe(false);
    });

    it('counts as an active surface alongside the desktop pane', () => {
        const g = makeGrid();
        g.suggestionsSheetOpen = true;
        expect(g.isSuggestionsSurfaceActive()).toBe(true);

        g.suggestionsSheetOpen = false;
        g.suggestionsPaneMounted = true;
        expect(g.isSuggestionsSurfaceActive()).toBe(true);
    });
});

describe('suggestions sheet lookups', () => {
    beforeEach(() => { vi.useFakeTimers(); });
    afterEach(() => { vi.useRealTimers(); });

    it('stays quiet while collapsed to the peek strip', () => {
        const g = makeGrid();
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        g.refreshSuggestionsPane();
        g.debouncedRefreshWordSuggestions();
        vi.advanceTimersByTime(1000);
        expect(g.$wire.suggestWords).not.toHaveBeenCalled();
        expect(g.$wire.lookupClues).not.toHaveBeenCalled();
    });

    it('opening it fetches words for the selected slot', () => {
        const g = makeGrid();
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        g.openSuggestionsSheet();
        expect(g.suggestionsSheetOpen).toBe(true);
        expect(g.$wire.suggestWords).toHaveBeenCalledTimes(1);
        expect(g.$wire.suggestWords).toHaveBeenLastCalledWith('S_L', 3);
    });

    it('opening on the clue library tab looks up the filled answer instead', () => {
        const g = makeGrid();
        g.solution[1] = ['S', 'A', 'L'];
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        g.openSuggestionsSheet('clues');
        expect(g.suggestionsTab).toBe('clues');
        expect(g.$wire.lookupClues).toHaveBeenCalledWith('SAL');
        expect(g.$wire.suggestWords).not.toHaveBeenCalled();
    });

    it('opening without a tab keeps the tab the user last chose', () => {
        const g = makeGrid();
        g.suggestionsTab = 'clues';
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        g.openSuggestionsSheet();
        expect(g.suggestionsTab).toBe('clues');
    });

    it('follows the selection to a new slot while open', () => {
        const g = makeGrid();
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        g.openSuggestionsSheet();
        g.selectedRow = 2; g.selectedCol = 0;
        g.onActiveSlotChanged();
        expect(g.suggestionsSheetOpen).toBe(true);
        expect(g.$wire.suggestWords).toHaveBeenCalledTimes(2);
        expect(g.$wire.suggestWords).toHaveBeenLastCalledWith('___', 3);
    });

    it('debounces letter edits into a single request while open', async () => {
        const g = makeGrid();
        g.selectedRow = 2; g.selectedCol = 0; g.direction = 'across';
        g.openSuggestionsSheet();
        expect(g.$wire.suggestWords).toHaveBeenCalledTimes(1);

        const type = (key) => g.handleKeydown({
            key, target: { tagName: 'DIV' }, preventDefault() {}, ctrlKey: false, metaKey: false, shiftKey: false,
        });
        type('a');
        type('b');
        expect(g.$wire.suggestWords).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(300);
        expect(g.$wire.suggestWords).toHaveBeenCalledTimes(2);
        expect(g.$wire.suggestWords).toHaveBeenLastCalledWith('AB_', 3);
    });
});

describe('suggestions sheet choosing', () => {
    it('placing a word fills the slot and collapses the sheet to the peek strip', () => {
        const g = makeGrid();
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        g.openSuggestionsSheet();
        g.applyWordSuggestion('SOL');
        expect(g.solution[1]).toEqual(['S', 'O', 'L']);
        expect(g.suggestionsSheetOpen).toBe(false);
        expect(g.isDirty).toBe(true);
    });

    it('using a clue writes it into the active entry and collapses the sheet', () => {
        const g = makeGrid();
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        g.openSuggestionsSheet('clues');
        g.useClue(g.activeClue, 'Sun, in Seville');
        expect(g.cluesAcross[1].clue).toBe('Sun, in Seville');
        expect(g.suggestionsSheetOpen).toBe(false);
    });

    it('leaves the desktop pane open when a word is placed there', () => {
        const g = makeGrid();
        g.suggestionsPaneMounted = true;
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        g.applyWordSuggestion('SOL');
        expect(g.suggestionsPaneCollapsed).toBe(false);
        expect(g.suggestionsSheetOpen).toBe(false);
    });

    it('toggle opens and closes, dropping any preview on close', () => {
        const g = makeGrid();
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
        g.toggleSuggestionsSheet();
        expect(g.suggestionsSheetOpen).toBe(true);
        g.previewWord('SOL');
        expect(Object.keys(g.previewLetters)).toHaveLength(1);
        g.toggleSuggestionsSheet();
        expect(g.suggestionsSheetOpen).toBe(false);
        expect(g.previewLetters).toEqual({});
    });
});

describe('suggestions sheet focus', () => {
    // The editor clears the selection on any document mousedown outside the
    // grid and its panels. The sheet is fixed to the bottom of the screen and
    // lives outside all of them, so it has to be exempt too, otherwise tapping
    // its peek strip deselects the cell and the sheet vanishes.
    it('tapping the sheet keeps the grid selection', () => {
        const g = makeGrid();
        const inSheet = {};
        g.$refs.gridContainer = { focus: vi.fn(), contains: () => false };
        g.$refs.suggestionsSheet = { ...sheetEl(true), contains: (el) => el === inSheet };
        g.selectedRow = 1; g.selectedCol = 0;

        g.handleClickOutside({ target: inSheet });
        expect(g.selectedRow).toBe(1);
        expect(g.suggestionsSlot).not.toBeNull();

        // A genuine outside tap still deselects (the handler blurs via document).
        vi.stubGlobal('document', { activeElement: null });
        try {
            g.handleClickOutside({ target: {} });
        } finally {
            vi.unstubAllGlobals();
        }
        expect(g.selectedRow).toBe(-1);
    });
});
