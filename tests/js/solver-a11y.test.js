import { describe, expect, it, beforeEach } from 'vitest';
import { crosswordSolver } from '../../resources/js/crossword-solver.js';

function makeSolver(overrides = {}) {
    const width = 3, height = 3;
    const grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];
    const solution = [
        ['C', 'A', 'T'],
        ['D', 'O', 'G'],
        ['E', 'E', 'L'],
    ];
    const progress = [
        ['', '', ''],
        ['', '', ''],
        ['', '', ''],
    ];
    const s = crosswordSolver({
        width, height, grid, solution, progress,
        styles: {},
        prefilled: null,
        cluesAcross: [
            { number: 1, clue: 'Feline', cells: [[0,0],[0,1],[0,2]] },
            { number: 4, clue: 'Canine', cells: [[1,0],[1,1],[1,2]] },
            { number: 5, clue: 'Slippery fish', cells: [[2,0],[2,1],[2,2]] },
        ],
        cluesDown: [
            { number: 1, clue: 'Down 1', cells: [[0,0],[1,0],[2,0]] },
            { number: 2, clue: 'Down 2', cells: [[0,1],[1,1],[2,1]] },
            { number: 3, clue: 'Down 3', cells: [[0,2],[1,2],[2,2]] },
        ],
        initialElapsed: 0, initialSolved: false, initialPencilCells: {},
        persistence: null,
        puzzleTitle: 'Test', shareTitle: 'Test', shareUrl: '',
        ...overrides,
    });
    s.$watch = () => {};
    s.$refs = {};
    s.$nextTick = () => {};
    return s;
}

describe('cellAriaLabel', () => {
    it('describes position, clue start, and emptiness for an unfilled numbered cell', () => {
        const s = makeSolver();
        const label = s.cellAriaLabel(0, 0);
        expect(label).toContain('Row 1');
        expect(label).toContain('column 1');
        expect(label).toContain('clue 1 start');
        expect(label).toContain('empty');
    });

    it('describes contents for a filled cell', () => {
        const s = makeSolver();
        s.progress[0][0] = 'C';
        expect(s.cellAriaLabel(0, 0)).toContain('contains C');
    });

    it('describes pencilled state', () => {
        const s = makeSolver();
        s.progress[0][0] = 'X';
        s.pencilCells['0,0'] = true;
        expect(s.cellAriaLabel(0, 0)).toContain('pencilled');
    });

    it('says Black square for a block cell', () => {
        const s = makeSolver({ grid: [
            [1, '#', 2],
            [3, 0, 0],
            [4, 0, 0],
        ]});
        expect(s.cellAriaLabel(0, 1)).toContain('Black square');
    });

    it('says Void cell for a null cell', () => {
        const s = makeSolver({ grid: [
            [1, null, 2],
            [3, 0, 0],
            [4, 0, 0],
        ]});
        expect(s.cellAriaLabel(0, 1)).toContain('Void');
    });
});

describe('activeClueAnnouncement', () => {
    it('includes direction, clue number, clue text, position, and content', () => {
        const s = makeSolver();
        s.selectClue('across', 1);
        const announcement = s.activeClueAnnouncement();
        expect(announcement).toContain('Across 1');
        expect(announcement).toContain('Feline');
        expect(announcement).toContain('row 1');
        expect(announcement).toContain('column 1');
        expect(announcement).toContain('empty');
    });

    it('announces solve completion when puzzle is solved', () => {
        const s = makeSolver();
        s.solved = true;
        expect(s.activeClueAnnouncement()).toBe('Puzzle solved.');
    });

    it('updates when direction toggles', () => {
        const s = makeSolver();
        s.selectClue('across', 1);
        expect(s.activeClueAnnouncement()).toContain('Across 1');
        s.toggleDirection();
        expect(s.activeClueAnnouncement()).toContain('Down 1');
    });
});
