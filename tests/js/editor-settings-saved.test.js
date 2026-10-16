import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { SETTING_FLASH_MS, crosswordGrid } from '../../resources/js/crossword-grid.js';

function makeGrid() {
    const inst = crosswordGrid({
        width: 3,
        height: 3,
        grid: [
            [0, 0, 0],
            [0, 0, 0],
            [0, 0, 0],
        ],
        solution: [
            ['', '', ''],
            ['', '', ''],
            ['', '', ''],
        ],
        styles: {},
        cluesAcross: [],
        cluesDown: [],
        minAnswerLength: 3,
        prefilled: null,
        gridLocked: false,
        puzzleType: 'classic',
    });
    inst.$watch = () => {};
    inst.$refs = {};
    inst.$nextTick = () => {};
    return inst;
}

describe('settings saved flash', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('shows a check for the saved field only, then clears it', () => {
        const grid = makeGrid();
        expect(grid.isSettingFlashing('title')).toBe(false);

        grid.flashSettingSaved('title');
        expect(grid.isSettingFlashing('title')).toBe(true);
        expect(grid.isSettingFlashing('author')).toBe(false);

        vi.advanceTimersByTime(SETTING_FLASH_MS - 1);
        expect(grid.isSettingFlashing('title')).toBe(true);

        vi.advanceTimersByTime(1);
        expect(grid.isSettingFlashing('title')).toBe(false);
    });

    it('restarts the clock when the same field saves again', () => {
        const grid = makeGrid();
        grid.flashSettingSaved('notes');
        vi.advanceTimersByTime(SETTING_FLASH_MS - 100);

        grid.flashSettingSaved('notes');
        vi.advanceTimersByTime(200);
        expect(grid.isSettingFlashing('notes')).toBe(true);

        vi.advanceTimersByTime(SETTING_FLASH_MS);
        expect(grid.isSettingFlashing('notes')).toBe(false);
    });
});
