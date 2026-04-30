import { describe, expect, it, vi, beforeEach } from 'vitest';
import { crosswordSolver } from '../../resources/js/crossword-solver.js';

describe('generateShareText', () => {
    let solver;

    beforeEach(() => {
        const grid = [
            [1, 2, 3],
            [4, 0, '#'],
            [5, 0, 0],
        ];
        const solution = [
            ['A', 'B', 'C'],
            ['D', 'E', '#'],
            ['F', 'G', 'H'],
        ];
        const progress = [
            ['A', 'B', 'C'],
            ['D', 'E', '#'],
            ['F', 'G', 'H'],
        ];

        solver = crosswordSolver({
            width: 3,
            height: 3,
            grid,
            solution,
            progress,
            styles: {},
            prefilled: null,
            cluesAcross: [{ number: 1, clue: 'test' }],
            cluesDown: [{ number: 1, clue: 'test' }],
            initialElapsed: 332,
            initialSolved: true,
            initialPencilCells: {},
            shareTitle: 'Test Puzzle',
            shareUrl: 'https://zorbl.test/puzzles/42',
        });
    });

    it('includes puzzle title', () => {
        const text = solver.generateShareText();
        expect(text).toContain('Test Puzzle');
    });

    it('includes formatted solve time', () => {
        const text = solver.generateShareText();
        expect(text).toContain('5:32');
    });

    it('includes grid pattern with blocks', () => {
        const text = solver.generateShareText();
        const lines = text.split('\n');
        const gridLines = lines.filter(l => l.match(/^[⬛⬜]+$/));
        expect(gridLines).toHaveLength(3);
        expect(gridLines[0]).toBe('⬜⬜⬜');
        expect(gridLines[1]).toBe('⬜⬜⬛');
        expect(gridLines[2]).toBe('⬜⬜⬜');
    });

    it('includes share URL', () => {
        const text = solver.generateShareText();
        expect(text).toContain('https://zorbl.test/puzzles/42');
    });

    it('falls back to window location when no shareUrl', () => {
        globalThis.window = { location: { href: 'https://fallback.test/page' } };
        solver.shareUrl = '';
        const text = solver.generateShareText();
        expect(text).toContain('https://fallback.test/page');
        delete globalThis.window;
    });
});
