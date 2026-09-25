import { describe, expect, it } from 'vitest';
import { crosswordGrid } from '../../resources/js/crossword-grid.js';
import { fitTextareaToContent, supportsFieldSizing } from '../../resources/js/grid/helpers.js';

function makeGrid() {
    const inst = crosswordGrid({
        width: 3,
        height: 1,
        grid: [[1, 0, 0]],
        solution: [['', '', '']],
        styles: {},
        cluesAcross: [{ number: 1, clue: 'A long clue' }],
        cluesDown: [],
        minAnswerLength: 2,
        prefilled: null,
        gridLocked: false,
        puzzleType: 'classic',
    });
    inst.$watch = () => {};
    inst.$refs = { gridContainer: { focus() { inst.$refs.gridContainer.focused = true; } } };
    inst.$nextTick = () => {};
    return inst;
}

const noFieldSizing = { supports: () => false };
const withFieldSizing = { supports: (prop, value) => prop === 'field-sizing' && value === 'content' };

describe('fitTextareaToContent', () => {
    it('grows the textarea to its scroll height when the browser lacks field-sizing', () => {
        const el = { style: { height: '' }, scrollHeight: 57 };
        fitTextareaToContent(el, noFieldSizing);
        expect(el.style.height).toBe('57px');
    });

    it('resets to auto first so a shortened clue can shrink back', () => {
        const heights = [];
        const el = { style: {}, scrollHeight: 20 };
        Object.defineProperty(el.style, 'height', {
            set(v) { heights.push(v); },
            get() { return heights.at(-1); },
        });
        fitTextareaToContent(el, noFieldSizing);
        expect(heights).toEqual(['auto', '20px']);
    });

    it('leaves the height alone when the element is not laid out yet', () => {
        const el = { style: { height: '' }, scrollHeight: 0 };
        fitTextareaToContent(el, noFieldSizing);
        expect(el.style.height).toBe('auto');
    });

    it('does nothing when the browser supports field-sizing natively', () => {
        const el = { style: { height: '' }, scrollHeight: 57 };
        fitTextareaToContent(el, withFieldSizing);
        expect(el.style.height).toBe('');
        expect(supportsFieldSizing(withFieldSizing)).toBe(true);
        expect(supportsFieldSizing(undefined)).toBe(false);
    });

    it('ignores a missing element', () => {
        expect(() => fitTextareaToContent(null, noFieldSizing)).not.toThrow();
    });
});

describe('clue panel textareas', () => {
    it('focusNextClue focuses the textarea inside the next clue row', () => {
        const g = makeGrid();
        let focused = null;
        const textarea = { focus() { focused = 'textarea'; } };
        const rowA = { nodeType: 1, querySelector: () => null, focus() { focused = 'rowA'; } };
        const rowB = {
            nodeType: 1,
            querySelector: (sel) => (sel.includes('textarea') ? textarea : null),
            focus() { focused = 'rowB'; },
        };
        const parent = { children: [rowA, rowB] };
        rowA.parentElement = parent;
        rowB.parentElement = parent;

        g.focusNextClue(rowA, 'across', false);
        expect(focused).toBe('textarea');
    });

    it('selectClue keeps focus in a clue textarea instead of moving it to the grid', () => {
        const g = makeGrid();
        g.selectClue('across', 1, { target: { tagName: 'TEXTAREA' } });
        expect(g.selectedRow).toBe(0);
        expect(g.selectedCol).toBe(0);
        expect(g.$refs.gridContainer.focused).toBeUndefined();

        g.selectClue('across', 1, { target: { tagName: 'DIV' } });
        expect(g.$refs.gridContainer.focused).toBe(true);
    });
});
