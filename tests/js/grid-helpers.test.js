import { describe, expect, it } from 'vitest';
import {
    cellKey,
    isVoid,
    isBlock,
    hasBar,
    hasCircle,
    getCellColor,
    getCustomNumber,
    getDisplayNumber,
    hasLeftBoundary,
    hasRightBoundary,
    hasTopBoundary,
    hasBottomBoundary,
    findSlot,
    getClueNumberForCell,
    getWordCells,
    computeActiveWordCells,
    cleanupStyleEntry,
} from '../../resources/js/grid/helpers.js';

// 3×3 fully-open grid, numbered as the JS numberer would produce:
//   [1, 2, 3]    rows 0–2 are all playable; (0,0) starts both 1A and 1D,
//   [4, 0, 0]    (0,1) starts 2D, (0,2) starts 3D, (1,0) starts 4A, (2,0) 5A.
//   [5, 0, 0]
const STD_GRID = [
    [1, 2, 3],
    [4, 0, 0],
    [5, 0, 0],
];

// A grid with a block at (1,0). Used to exercise blocks specifically.
//   [1, 2, 3]
//   ['#', 4, 5]
//   [6, 0, 0]
const BLOCK_GRID = [
    [1, 2, 3],
    ['#', 4, 5],
    [6, 0, 0],
];

describe('cellKey', () => {
    it('joins row,col with a comma', () => {
        expect(cellKey(2, 5)).toBe('2,5');
        expect(cellKey(0, 0)).toBe('0,0');
    });
});

describe('isVoid / isBlock', () => {
    it('null is both void and block', () => {
        const grid = [[null]];
        expect(isVoid(grid, 0, 0)).toBe(true);
        expect(isBlock(grid, 0, 0)).toBe(true);
    });

    it("'#' is a block but not a void", () => {
        const grid = [['#']];
        expect(isVoid(grid, 0, 0)).toBe(false);
        expect(isBlock(grid, 0, 0)).toBe(true);
    });

    it('numbered + zero cells are neither void nor block', () => {
        expect(isBlock(STD_GRID, 0, 0)).toBe(false);
        expect(isVoid(STD_GRID, 0, 0)).toBe(false);
        expect(isBlock(STD_GRID, 2, 1)).toBe(false);
    });

    it('out-of-bounds reads as undefined, so neither void nor block', () => {
        // Boundary helpers handle the puzzle edge explicitly with col === 0
        // and col + 1 >= width checks; isBlock itself is not the sentinel.
        expect(isBlock(STD_GRID, -1, 0)).toBe(false);
        expect(isBlock(STD_GRID, 0, 99)).toBe(false);
    });
});

describe('hasBar / hasCircle / getCellColor / getCustomNumber', () => {
    const styles = {
        '0,0': { bars: ['right'], shapebg: 'circle' },
        '1,1': { color: '#FECACA', number: 7 },
    };

    it('detects a bar by edge', () => {
        expect(hasBar(styles, 0, 0, 'right')).toBe(true);
        expect(hasBar(styles, 0, 0, 'left')).toBe(false);
        expect(hasBar(styles, 5, 5, 'top')).toBe(false);
    });

    it('detects circle and color', () => {
        expect(hasCircle(styles, 0, 0)).toBe(true);
        expect(hasCircle(styles, 1, 1)).toBe(false);
        expect(getCellColor(styles, 1, 1)).toBe('#FECACA');
        expect(getCellColor(styles, 0, 0)).toBeNull();
    });

    it('returns custom number or null', () => {
        expect(getCustomNumber(styles, 1, 1)).toBe(7);
        expect(getCustomNumber(styles, 0, 0)).toBeNull();
    });
});

describe('getDisplayNumber', () => {
    it('prefers the custom number over the auto-generated one', () => {
        const styles = { '0,0': { number: 42 } };
        expect(getDisplayNumber(STD_GRID, styles, 0, 0)).toBe(42);
    });

    it('falls back to the grid number', () => {
        expect(getDisplayNumber(STD_GRID, {}, 0, 0)).toBe(1);
        expect(getDisplayNumber(STD_GRID, {}, 0, 1)).toBe(2);
    });

    it('returns null for non-slot cells', () => {
        expect(getDisplayNumber(STD_GRID, {}, 2, 1)).toBeNull(); // value is 0
        expect(getDisplayNumber(BLOCK_GRID, {}, 1, 0)).toBeNull(); // block
    });
});

describe('boundary helpers', () => {
    it('honours puzzle edges', () => {
        expect(hasLeftBoundary(STD_GRID, {}, 0, 0)).toBe(true);
        expect(hasRightBoundary(STD_GRID, {}, 3, 0, 2)).toBe(true);
        expect(hasTopBoundary(STD_GRID, {}, 0, 0)).toBe(true);
        expect(hasBottomBoundary(STD_GRID, {}, 3, 2, 0)).toBe(true);
    });

    it('honours adjacent blocks', () => {
        // (1,0) is '#' so (1,1) has a left boundary
        expect(hasLeftBoundary(BLOCK_GRID, {}, 1, 1)).toBe(true);
        // (1,0) is '#' so (2,0) — which is itself open — sees that as a top boundary
        expect(hasTopBoundary(BLOCK_GRID, {}, 2, 0)).toBe(true);
    });

    it('honours bars from either side of the seam', () => {
        const styles = { '0,0': { bars: ['right'] } };
        expect(hasRightBoundary(STD_GRID, styles, 3, 0, 0)).toBe(true);
        expect(hasLeftBoundary(STD_GRID, styles, 0, 1)).toBe(true);
    });

    it('returns false when nothing blocks the seam', () => {
        expect(hasRightBoundary(STD_GRID, {}, 3, 0, 0)).toBe(false);
        expect(hasLeftBoundary(STD_GRID, {}, 0, 1)).toBe(false);
    });
});

describe('findSlot', () => {
    it('finds an across slot at the start cell', () => {
        const slot = findSlot(STD_GRID, {}, 3, 3, 'across', 1);
        expect(slot).toEqual({ row: 0, col: 0, length: 3 });
    });

    it('finds a down slot at the start cell', () => {
        const slot = findSlot(STD_GRID, {}, 3, 3, 'down', 1);
        expect(slot).toEqual({ row: 0, col: 0, length: 3 });
    });

    it('returns null when walking from the cell yields a length-1 word', () => {
        // #5 in STD_GRID is at (2,0). Walking down stops at (2,0) (bottom row),
        // length 1, so findSlot('down', 5) reports no slot.
        expect(findSlot(STD_GRID, {}, 3, 3, 'down', 5)).toBeNull();
    });

    it('returns null for a number that does not exist', () => {
        expect(findSlot(STD_GRID, {}, 3, 3, 'across', 999)).toBeNull();
    });
});

describe('getClueNumberForCell', () => {
    it('returns the clue number at the start cell', () => {
        expect(getClueNumberForCell(STD_GRID, {}, 0, 0, 'across')).toBe(1);
    });

    it('walks back to the across start cell', () => {
        expect(getClueNumberForCell(STD_GRID, {}, 0, 2, 'across')).toBe(1);
    });

    it('walks back to the down start cell', () => {
        expect(getClueNumberForCell(STD_GRID, {}, 2, 1, 'down')).toBe(2);
    });

    it('returns -1 for blocks', () => {
        expect(getClueNumberForCell(BLOCK_GRID, {}, 1, 0, 'across')).toBe(-1);
    });

    it('returns -1 for void cells', () => {
        const grid = [[null]];
        expect(getClueNumberForCell(grid, {}, 0, 0, 'across')).toBe(-1);
    });
});

describe('getWordCells', () => {
    it('collects every cell of an across word', () => {
        expect(getWordCells(STD_GRID, {}, 3, 3, 0, 1, 'across')).toEqual([
            [0, 0], [0, 1], [0, 2],
        ]);
    });

    it('collects every cell of a down word', () => {
        expect(getWordCells(STD_GRID, {}, 3, 3, 0, 1, 'down')).toEqual([
            [0, 1], [1, 1], [2, 1],
        ]);
    });

    it('stops at a bar', () => {
        const styles = { '0,1': { bars: ['right'] } };
        expect(getWordCells(STD_GRID, styles, 3, 3, 0, 0, 'across')).toEqual([
            [0, 0], [0, 1],
        ]);
    });

    it('returns [] for a block', () => {
        expect(getWordCells(BLOCK_GRID, {}, 3, 3, 1, 0, 'across')).toEqual([]);
    });
});

describe('computeActiveWordCells', () => {
    it('returns a Set keyed by row,col for the active word', () => {
        const set = computeActiveWordCells(STD_GRID, {}, 3, 3, 0, 1, 'across');
        expect(set.has('0,0')).toBe(true);
        expect(set.has('0,1')).toBe(true);
        expect(set.has('0,2')).toBe(true);
        expect(set.has('1,1')).toBe(false);
        expect(set.size).toBe(3);
    });

    it('returns an empty Set when nothing is selected', () => {
        const set = computeActiveWordCells(STD_GRID, {}, 3, 3, -1, -1, 'across');
        expect(set.size).toBe(0);
    });
});

describe('cleanupStyleEntry', () => {
    it('drops an entry that has gone empty', () => {
        const styles = { '0,0': {} };
        cleanupStyleEntry(styles, '0,0');
        expect(styles).toEqual({});
    });

    it('drops an entry whose only remaining key is bars: []', () => {
        const styles = { '0,0': { bars: [] } };
        cleanupStyleEntry(styles, '0,0');
        expect(styles).toEqual({});
    });

    it('keeps an entry with leftover keys', () => {
        const styles = { '0,0': { color: '#fff' } };
        cleanupStyleEntry(styles, '0,0');
        expect(styles).toEqual({ '0,0': { color: '#fff' } });
    });

    it('is a no-op for a missing key', () => {
        const styles = {};
        cleanupStyleEntry(styles, '5,5');
        expect(styles).toEqual({});
    });
});
