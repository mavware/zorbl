// Mirrors the PHP GridNumberer in packages/crossword-io/src/GridNumberer.php.
// Numbers each cell of `grid` in place (writes 1, 2, 3... at slot starts and 0
// elsewhere) and returns the resulting clue lists, preserving existing clue
// text where the slot number still exists.

import {
    isBlock,
    hasLeftBoundary,
    hasRightBoundary,
    hasTopBoundary,
    hasBottomBoundary,
} from './helpers.js';

/**
 * @param {Object} args
 * @param {Array<Array<*>>} args.grid                 Numbered grid (mutated in place).
 * @param {number} args.width
 * @param {number} args.height
 * @param {Object} args.styles                        Style map keyed by "row,col".
 * @param {number} args.minLength                     Minimum slot length to qualify (default 2).
 * @param {Array<{number:number, clue:string}>} args.cluesAcross  Existing across clues, keyed by old number.
 * @param {Array<{number:number, clue:string}>} args.cluesDown    Existing down clues.
 * @returns {{
 *   acrossSlots: Array<{number:number, row:number, col:number, length:number}>,
 *   downSlots:   Array<{number:number, row:number, col:number, length:number}>,
 *   cluesAcross: Array<{number:number, clue:string}>,
 *   cluesDown:   Array<{number:number, clue:string}>,
 * }}
 */
export function numberGrid({ grid, width, height, styles, minLength, cluesAcross, cluesDown }) {
    const minLen = minLength || 2;
    const acrossSlots = [];
    const downSlots = [];
    let clueNum = 0;

    // Reset all non-block, non-void cells to 0 in place.
    for (let row = 0; row < height; row++) {
        for (let col = 0; col < width; col++) {
            const cell = grid[row][col];
            if (cell !== '#' && cell !== null) {
                grid[row][col] = 0;
            }
        }
    }

    for (let row = 0; row < height; row++) {
        for (let col = 0; col < width; col++) {
            if (isBlock(grid, row, col)) continue;

            const startsAcross = hasLeftBoundary(grid, styles, row, col)
                && !hasRightBoundary(grid, styles, width, row, col);
            const startsDown = hasTopBoundary(grid, styles, row, col)
                && !hasBottomBoundary(grid, styles, height, row, col);

            let acrossLen = 0;
            let downLen = 0;

            if (startsAcross) {
                acrossLen = 1;
                while (!hasRightBoundary(grid, styles, width, row, col + acrossLen - 1)) acrossLen++;
            }
            if (startsDown) {
                downLen = 1;
                while (!hasBottomBoundary(grid, styles, height, row + downLen - 1, col)) downLen++;
            }

            const hasAcross = startsAcross && acrossLen >= minLen;
            const hasDown = startsDown && downLen >= minLen;

            if (hasAcross || hasDown) {
                clueNum++;
                grid[row][col] = clueNum;
                if (hasAcross) acrossSlots.push({ number: clueNum, row, col, length: acrossLen });
                if (hasDown)   downSlots.push({ number: clueNum, row, col, length: downLen });
            }
        }
    }

    const oldAcross = new Map((cluesAcross || []).map(c => [c.number, c.clue]));
    const oldDown = new Map((cluesDown || []).map(c => [c.number, c.clue]));

    return {
        acrossSlots,
        downSlots,
        cluesAcross: acrossSlots.map(s => ({ number: s.number, clue: oldAcross.get(s.number) || '' })),
        cluesDown:   downSlots.map(s => ({ number: s.number, clue: oldDown.get(s.number) || '' })),
    };
}
