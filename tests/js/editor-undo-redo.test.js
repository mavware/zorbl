import { describe, expect, it, beforeEach, vi } from 'vitest';
import { crosswordGrid } from '../../resources/js/crossword-grid.js';

// 3x3 grid with one block at (0,2). History is re-baselined after the
// initial numbering so the first user action is the first undo step.
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
            ['', '', '#'],
            ['', '', ''],
            ['', '', ''],
        ],
        styles: {},
        cluesAcross: [],
        cluesDown: [],
        minAnswerLength: 2,
        prefilled: null,
        gridLocked: false,
        puzzleType: 'classic',
        ...overrides,
    });
    inst.$watch = () => {};
    inst.$refs = {};
    inst.$nextTick = () => {};
    inst.$wire = { savePrefilled: vi.fn(() => Promise.resolve()) };
    inst.debouncedRefreshWordSuggestions = () => {};
    inst.refreshSuggestionsPane = () => {};
    inst.symmetry = false;
    inst.numberGrid();
    inst._resetHistory();
    return inst;
}

const keydown = (g, key, extra = {}) => g.handleKeydown({
    key, target: { tagName: 'DIV' }, preventDefault() {}, ctrlKey: false, metaKey: false, shiftKey: false, ...extra,
});

const shortcut = (g, key, extra = {}) => {
    const e = { key, target: { tagName: 'DIV' }, preventDefault: vi.fn(), ctrlKey: false, metaKey: false, shiftKey: false, ...extra };
    g.handleShortcutKeydown(e);
    return e;
};

describe('editor undo/redo', () => {
    let g;
    beforeEach(() => { g = makeGrid(); });

    it('starts with nothing to undo or redo', () => {
        expect(g.canUndo).toBe(false);
        expect(g.canRedo).toBe(false);
    });

    it('undoes a typed letter and redoes it without moving the cursor', () => {
        g.selectedRow = 0; g.selectedCol = 0;
        keydown(g, 'A');
        expect(g.solution[0][0]).toBe('A');
        expect(g.selectedCol).toBe(1);

        g.undo();
        expect(g.solution[0][0]).toBe('');
        expect(g.selectedCol).toBe(1);
        expect(g.canRedo).toBe(true);

        g.redo();
        expect(g.solution[0][0]).toBe('A');
        expect(g.canRedo).toBe(false);
    });

    it('records one step per letter', () => {
        g.selectedRow = 0; g.selectedCol = 0;
        keydown(g, 'A');
        keydown(g, 'B');
        g.undo();
        expect(g.solution[0]).toEqual(['A', '', '#']);
        g.undo();
        expect(g.solution[0]).toEqual(['', '', '#']);
        expect(g.canUndo).toBe(false);
    });

    it('restores the grid and clue numbering after a block toggle', () => {
        const gridBefore = JSON.parse(JSON.stringify(g.grid));
        const acrossBefore = JSON.parse(JSON.stringify(g.cluesAcross));
        const downBefore = JSON.parse(JSON.stringify(g.cluesDown));

        g.toggleBlock(1, 1);
        expect(g.grid[1][1]).toBe('#');
        expect(g.cluesAcross).not.toEqual(acrossBefore);

        g.undo();
        expect(g.grid).toEqual(gridBefore);
        expect(g.cluesAcross).toEqual(acrossBefore);
        expect(g.cluesDown).toEqual(downBefore);
    });

    it('treats a multi-cell context toggle as a single step', () => {
        g.multiSelectedCells = { '1,0': true, '1,1': true, '2,0': true };
        g.contextMenu = { show: true, row: 1, col: 0, x: 0, y: 0 };
        g.contextToggleBlock();
        expect(g.grid[1][0]).toBe('#');
        expect(g.grid[1][1]).toBe('#');
        expect(g.grid[2][0]).toBe('#');

        g.undo();
        expect(g.grid[1][0]).toBe(3);
        expect(g.grid[1][1]).toBe(0);
        expect(g.grid[2][0]).toBe(5);
        expect(g.canUndo).toBe(false);
    });

    it('clears redo when a new edit follows an undo', () => {
        g.selectedRow = 0; g.selectedCol = 0;
        keydown(g, 'A');
        g.undo();
        expect(g.canRedo).toBe(true);
        g.selectedCol = 0;
        keydown(g, 'B');
        expect(g.canRedo).toBe(false);
        expect(g.solution[0][0]).toBe('B');
    });

    it('restores styles after clear all', () => {
        g.setCellColor(1, 1, '#ff0000');
        g.markDirty();
        const stylesBefore = JSON.parse(JSON.stringify(g.styles));

        g.clearAll();
        expect(g.styles).toEqual({});
        expect(g.grid[0][2]).not.toBe('#');

        g.undo();
        expect(g.styles).toEqual(stylesBefore);
        expect(g.grid[0][2]).toBe('#');
    });

    it('restores prefilled cells and re-saves them when undoing a rebus', () => {
        g.rebusCells = [[1, 0]];
        g.rebusInputValue = 'QU';
        g.showRebusInput = true;
        g.applyRebus();
        expect(g.solution[1][0]).toBe('QU');
        expect(g.prefilled[1][0]).toBe('QU');
        expect(g.$wire.savePrefilled).toHaveBeenCalledTimes(1);

        g.undo();
        expect(g.solution[1][0]).toBe('');
        expect(g.prefilled).toBeNull();
        expect(g.$wire.savePrefilled).toHaveBeenCalledTimes(1);

        g.redo();
        expect(g.prefilled[1][0]).toBe('QU');
        expect(g.$wire.savePrefilled).toHaveBeenCalledTimes(2);
    });

    it('marks the grid dirty after a restore so autosave runs', () => {
        g.selectedRow = 0; g.selectedCol = 0;
        keydown(g, 'A');
        g.isDirty = false;
        g.undo();
        expect(g.isDirty).toBe(true);
    });

    it('does not record a step when nothing changed', () => {
        g.markDirty(); // clue input blur without an edit
        expect(g.canUndo).toBe(false);

        g.selectedRow = 1; g.selectedCol = 1;
        keydown(g, 'Delete'); // already empty
        expect(g.canUndo).toBe(false);
    });

    it('records a clue text edit as one step', () => {
        const clue = g.cluesAcross[0];
        clue.clue = 'Feline';
        g.markDirty();
        g.undo();
        expect(g.cluesAcross[0].clue).toBe('');
        g.redo();
        expect(g.cluesAcross[0].clue).toBe('Feline');
    });

    it('forgets history when the grid is resized', () => {
        g.selectedRow = 0; g.selectedCol = 0;
        keydown(g, 'A');
        g.$wire = { ...g.$wire, width: 3, height: 3, grid: g.grid, solution: g.solution };
        g.onGridResized();
        expect(g.canUndo).toBe(false);
        expect(g.canRedo).toBe(false);
    });

    it('forgets history when a freestyle grid is locked or unlocked', () => {
        g.selectedRow = 0; g.selectedCol = 0;
        keydown(g, 'A');
        g.$wire = { ...g.$wire, grid: g.grid, solution: g.solution, cluesAcross: [], cluesDown: [] };
        g.onFreestyleLocked(true);
        expect(g.gridLocked).toBe(true);
        expect(g.canUndo).toBe(false);
    });
});

describe('editor undo/redo keyboard shortcuts', () => {
    let g;
    beforeEach(() => {
        g = makeGrid();
        g.selectedRow = 0; g.selectedCol = 0;
        keydown(g, 'A');
    });

    it('Cmd/Ctrl+Z undoes', () => {
        const e = shortcut(g, 'z', { metaKey: true });
        expect(g.solution[0][0]).toBe('');
        expect(e.preventDefault).toHaveBeenCalled();

        g.redo();
        shortcut(g, 'Z', { ctrlKey: true });
        expect(g.solution[0][0]).toBe('');
    });

    it('Cmd/Ctrl+Shift+Z and Ctrl+Y redo', () => {
        g.undo();
        shortcut(g, 'z', { metaKey: true, shiftKey: true });
        expect(g.solution[0][0]).toBe('A');

        g.undo();
        shortcut(g, 'y', { ctrlKey: true });
        expect(g.solution[0][0]).toBe('A');
    });

    it('ignores the shortcut inside editable fields', () => {
        const e = shortcut(g, 'z', { metaKey: true, target: { tagName: 'INPUT' } });
        expect(g.solution[0][0]).toBe('A');
        expect(e.preventDefault).not.toHaveBeenCalled();

        shortcut(g, 'z', { metaKey: true, target: { tagName: 'DIV', isContentEditable: true } });
        expect(g.solution[0][0]).toBe('A');
    });

    it('ignores a bare z key', () => {
        const e = shortcut(g, 'z');
        expect(g.solution[0][0]).toBe('A');
        expect(e.preventDefault).not.toHaveBeenCalled();
    });
});
