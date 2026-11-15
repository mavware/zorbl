import { afterEach, describe, expect, it, beforeEach, vi } from 'vitest';
import { crosswordGrid, MOBILE_INPUT_SENTINEL } from '../../resources/js/crossword-grid.js';
import { HASH_SUBSTITUTE } from '../../resources/js/grid/helpers.js';

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
    inst.$wire = { savePrefilled: () => Promise.resolve() };
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

    it('stores a typed # as the fullwidth substitute and keeps the cell open', () => {
        keydown('#');
        expect(g.solution[1][0]).toBe(HASH_SUBSTITUTE);
        expect(g.grid[1][0]).toBe(3);
        expect(g.isBlock(1, 0)).toBe(false);
        expect(g.selectedCol).toBe(1);
    });

    it('never types the shortcuts key', () => {
        keydown('?');
        expect(g.solution[1][0]).toBe('');
        expect(g.selectedCol).toBe(0);
    });

    it('substitutes # inside a pre-fill value', () => {
        g.rebusCells = [[1, 0]];
        g.rebusInputValue = ' #1 ';
        g.applyRebus();
        expect(g.solution[1][0]).toBe(HASH_SUBSTITUTE + '1');
        expect(g.prefilled[1][0]).toBe(HASH_SUBSTITUTE + '1');
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

describe('editor touch keyboard input', () => {
    let g;
    let mobileInput;
    let gridContainer;

    const type = (data, value = MOBILE_INPUT_SENTINEL + data, inputType = 'insertText') => {
        mobileInput.value = value;
        g.handleMobileInput({ target: mobileInput, data, inputType });
    };
    const backspace = () => {
        mobileInput.value = '';
        g.handleMobileInput({ target: mobileInput, data: null, inputType: 'deleteContentBackward' });
    };
    const stubPointer = (isCoarse) => vi.stubGlobal('matchMedia', () => ({ matches: isCoarse }));

    beforeEach(() => {
        g = makeGrid();
        g.mode = 'edit';
        mobileInput = { tagName: 'INPUT', value: '', focus: vi.fn() };
        gridContainer = { focus: vi.fn() };
        g.$refs = { mobileInput, gridContainer };
        g.revealSuggestions = () => {};
        g.selectedRow = 1; g.selectedCol = 0; g.direction = 'across';
    });

    afterEach(() => vi.unstubAllGlobals());

    it('focuses the text input when a cell is tapped on a touch device', () => {
        stubPointer(true);
        g.selectCell(2, 1);
        expect(mobileInput.focus).toHaveBeenCalled();
        expect(gridContainer.focus).not.toHaveBeenCalled();
    });

    it('focuses the grid container on a mouse or trackpad device', () => {
        stubPointer(false);
        g.selectCell(2, 1);
        expect(gridContainer.focus).toHaveBeenCalled();
        expect(mobileInput.focus).not.toHaveBeenCalled();
    });

    it('does not raise the keyboard when no cell is selected', () => {
        stubPointer(true);
        g.selectedRow = -1; g.selectedCol = -1;
        g.focusGrid();
        expect(mobileInput.focus).not.toHaveBeenCalled();
    });

    it('writes typed characters into the grid and resets the field', () => {
        type('a');
        type('b');
        expect(g.solution[1]).toEqual(['A', 'B', '']);
        expect(g.selectedCol).toBe(2);
        expect(mobileInput.value).toBe(MOBILE_INPUT_SENTINEL);
    });

    it('takes only the newest character of a growing composition', () => {
        type('a', MOBILE_INPUT_SENTINEL + 'a', 'insertCompositionText');
        type('ab', MOBILE_INPUT_SENTINEL + 'ab', 'insertCompositionText');
        expect(g.solution[1]).toEqual(['A', 'B', '']);
    });

    it('treats deleting the sentinel as a backspace', () => {
        type('a');
        backspace();
        expect(g.solution[1][0]).toBe('');
        expect(g.selectedCol).toBe(0);
        expect(mobileInput.value).toBe(MOBILE_INPUT_SENTINEL);
    });

    it('handles hardware key presses that reach the text input', () => {
        g.handleKeydown({
            key: 'x', target: mobileInput, preventDefault() {}, ctrlKey: false, metaKey: false, shiftKey: false,
        });
        expect(g.solution[1][0]).toBe('X');
    });

    it('still ignores key presses inside other text fields', () => {
        g.handleKeydown({
            key: 'x', target: { tagName: 'TEXTAREA' }, preventDefault() {}, ctrlKey: false, metaKey: false, shiftKey: false,
        });
        expect(g.solution[1][0]).toBe('');
    });
});
