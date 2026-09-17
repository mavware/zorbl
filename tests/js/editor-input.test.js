import { describe, expect, it, beforeEach } from 'vitest';
import { crosswordGrid } from '../../resources/js/crossword-grid.js';

// 3x3 grid with one block at (0,2).
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
    inst.debouncedRefreshWordSuggestions = () => {};
    return inst;
}

describe('editor handleKeydown character entry', () => {
    let g;
    const keydown = (key, extra = {}) => g.handleKeydown({
        key, target: { tagName: 'DIV' }, preventDefault() {}, ctrlKey: false, metaKey: false, shiftKey: false, ...extra,
    });

    beforeEach(() => {
        g = makeGrid();
        g.mode = 'edit';
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
    });

    it('writes letters, digits, and symbols and advances the cursor', () => {
        keydown('x');
        keydown('9');
        keydown('$');
        expect(g.solution[1]).toEqual(['X', '9', '$']);
        expect(g.isDirty).toBe(true);
    });

    it('keeps space as the block toggle instead of typing it', () => {
        keydown(' ');
        expect(g.solution[1][0]).toBe('#');
        expect(g.grid[1][0]).toBe('#');
    });

    it('never types the block marker or the shortcuts key', () => {
        keydown('#');
        keydown('?');
        expect(g.solution[1][0]).toBe('');
        expect(g.selectedCol).toBe(0);
    });

    it('ignores characters typed with a modifier held', () => {
        keydown('1', { metaKey: true });
        keydown('=', { ctrlKey: true });
        expect(g.solution[1][0]).toBe('');
    });

    it('appends digits and symbols in rebus mode', () => {
        g.rebusMode = true;
        keydown('1');
        keydown('0');
        keydown('%');
        expect(g.solution[1][0]).toBe('10%');
        expect(g.selectedCol).toBe(0);
    });

    it('does not mutate a locked grid', () => {
        g.gridLocked = true;
        keydown('5');
        expect(g.solution[1][0]).toBe('');
    });
});
