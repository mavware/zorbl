import { describe, expect, it } from 'vitest';
import { crosswordGrid } from '../../resources/js/crossword-grid.js';

// 3x3 grid with one block at (0,2). 8 playable cells.
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
            ['A', 'B', '#'],
            ['C', '', 'D'],
            ['E', '', ''],
        ],
        styles: {},
        cluesAcross: [
            { number: 1, clue: 'First' },
            { number: 3, clue: '' },
            { number: 5, clue: 'Third' },
        ],
        cluesDown: [
            { number: 1, clue: 'Down 1' },
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
    inst.$refs = {};
    inst.$nextTick = () => {};
    return inst;
}

describe('cell-fill progress getters', () => {
    it('counts only playable cells (skips blocks and voids)', () => {
        const g = makeGrid();
        expect(g.cellsTotal).toBe(8);
    });

    it('counts cells whose solution holds a non-empty letter', () => {
        const g = makeGrid();
        // A, B, C, D, E filled; (1,1), (2,1), (2,2) empty.
        expect(g.cellsFilled).toBe(5);
    });

    it('treats voids the same as blocks', () => {
        const g = makeGrid({
            grid: [
                [1, null, '#'],
                [3, 0, 4],
                [5, 0, 0],
            ],
            solution: [
                ['A', null, '#'],
                ['B', 'C', 'D'],
                ['E', 'F', 'G'],
            ],
        });
        // 7 playable cells, all filled.
        expect(g.cellsTotal).toBe(7);
        expect(g.cellsFilled).toBe(7);
    });

    it('reports zero filled when all letters are blank', () => {
        const g = makeGrid({
            solution: [
                ['', '', '#'],
                ['', '', ''],
                ['', '', ''],
            ],
        });
        expect(g.cellsFilled).toBe(0);
    });
});

describe('fill-color classes', () => {
    it('returns red below one-third filled', () => {
        const g = makeGrid({
            solution: [
                ['A', '', '#'],
                ['', '', ''],
                ['', '', ''],
            ],
        });
        // 1/8 = 0.125, < 1/3
        expect(g.cellsFillColorClass).toBe('text-red-300');
    });

    it('returns yellow between one-third and two-thirds', () => {
        const g = makeGrid({
            solution: [
                ['A', 'B', '#'],
                ['C', 'D', ''],
                ['', '', ''],
            ],
        });
        // 4/8 = 0.5, in [1/3, 2/3)
        expect(g.cellsFillColorClass).toBe('text-yellow-300');
    });

    it('returns green at two-thirds or above', () => {
        const g = makeGrid({
            solution: [
                ['A', 'B', '#'],
                ['C', 'D', 'E'],
                ['F', '', ''],
            ],
        });
        // 6/8 = 0.75, >= 2/3
        expect(g.cellsFillColorClass).toBe('text-green-300');
    });

    it('returns zinc when no playable cells exist', () => {
        const g = makeGrid({
            width: 2,
            height: 2,
            grid: [['#', '#'], ['#', '#']],
            solution: [['#', '#'], ['#', '#']],
            cluesAcross: [],
            cluesDown: [],
        });
        expect(g.cellsFillColorClass).toBe('text-zinc-500');
        expect(g.cluesFillColorClass).toBe('text-zinc-500');
    });
});

describe('completion flags', () => {
    it('isCellsComplete is true only when every playable cell has a letter', () => {
        const g = makeGrid({
            solution: [
                ['A', 'B', '#'],
                ['C', 'D', 'E'],
                ['F', 'G', 'H'],
            ],
        });
        expect(g.isCellsComplete).toBe(true);
    });

    it('isCellsComplete is false when any playable cell is blank', () => {
        const g = makeGrid({
            solution: [
                ['A', 'B', '#'],
                ['C', 'D', 'E'],
                ['F', 'G', ''],
            ],
        });
        expect(g.isCellsComplete).toBe(false);
    });

    it('isCluesComplete is true only when every clue text is non-empty', () => {
        const g = makeGrid({
            cluesAcross: [{ number: 1, clue: 'X' }],
            cluesDown: [{ number: 1, clue: 'Y' }],
        });
        expect(g.isCluesComplete).toBe(true);
    });

    it('isCluesComplete is false when any clue text is empty', () => {
        const g = makeGrid({
            cluesAcross: [{ number: 1, clue: 'X' }, { number: 3, clue: '' }],
            cluesDown: [{ number: 1, clue: 'Y' }],
        });
        expect(g.isCluesComplete).toBe(false);
    });
});

describe('clue-fill progress getters', () => {
    it('counts total clues across both directions', () => {
        const g = makeGrid();
        // 3 across + 3 down
        expect(g.cluesTotal).toBe(6);
    });

    it('counts only clues with non-empty text', () => {
        const g = makeGrid();
        // Across non-empty: 1, 5. Down non-empty: 1. Empty strings excluded.
        expect(g.cluesFilled).toBe(3);
    });

    it('treats whitespace-only clue text as empty', () => {
        const g = makeGrid({
            cluesAcross: [
                { number: 1, clue: 'Real' },
                { number: 3, clue: '   ' },
            ],
            cluesDown: [],
        });
        expect(g.cluesTotal).toBe(2);
        expect(g.cluesFilled).toBe(1);
    });
});
