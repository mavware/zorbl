import { describe, expect, it, beforeEach } from 'vitest';
import { crosswordSolver } from '../../resources/js/crossword-solver.js';

// 3x3 grid, all playable. Solution is "CAT/DOG/EEL".
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
        initialElapsed: 0,
        initialSolved: false,
        initialPencilCells: {},
        persistence: null,
        puzzleTitle: 'Test',
        shareTitle: 'Test',
        shareUrl: '',
        ...overrides,
    });
    // Initialize the bits init() touches that need DOM access.
    s.$watch = () => {};
    s.$refs = {};
    // No-op $nextTick to skip the DOM-dependent scroll helper in node tests.
    s.$nextTick = () => {};
    return s;
}

describe('typeCharacter', () => {
    let s;
    beforeEach(() => { s = makeSolver(); });

    it('writes the uppercased letter into the selected cell', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('c');
        expect(s.progress[0][0]).toBe('C');
    });

    it('advances the cursor in the active direction', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.direction = 'across';
        s.typeCharacter('C');
        expect(s.selectedRow).toBe(0);
        expect(s.selectedCol).toBe(1);
    });

    it('ignores non-letter input', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('1');
        s.typeCharacter('!');
        expect(s.progress[0][0]).toBe('');
    });

    it('selects the first playable cell when nothing is selected yet', () => {
        s.selectedRow = -1; s.selectedCol = -1;
        s.typeCharacter('Z');
        expect(s.progress[0][0]).toBe('Z');
        expect(s.selectedRow).toBe(0);
    });

    it('marks the puzzle dirty so autosave will fire', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('C');
        expect(s.isDirty).toBe(true);
    });

    it('jumps to the next across word when typing the last letter at the right edge', () => {
        s.selectedRow = 0; s.selectedCol = 2;
        s.direction = 'across';
        s.typeCharacter('T');
        // 1-across just finished; focus should land on the first empty cell of 4-across.
        expect(s.selectedRow).toBe(1);
        expect(s.selectedCol).toBe(0);
    });

    it('jumps to the next down word when typing the last letter at the bottom edge', () => {
        s.selectedRow = 2; s.selectedCol = 0;
        s.direction = 'down';
        s.typeCharacter('E');
        // 1-down just finished; focus should land on the first empty cell of 2-down.
        expect(s.selectedRow).toBe(0);
        expect(s.selectedCol).toBe(1);
    });

    it('skips fully filled subsequent words when looking for the next empty cell', () => {
        // Fill 4-across entirely so advance from end of 1-across skips it and lands on 5-across.
        s.progress[1] = ['D', 'O', 'G'];
        s.selectedRow = 0; s.selectedCol = 2;
        s.direction = 'across';
        s.typeCharacter('T');
        expect(s.selectedRow).toBe(2);
        expect(s.selectedCol).toBe(0);
    });
});

describe('advanceCursor across a black square', () => {
    it('jumps to the next across word when the cell to the right is a block', () => {
        // 3x3 grid with a block at (0,2): 1-across is two cells, then a block.
        const grid = [
            [1, 2, '#'],
            [3, 0, 0],
            [4, 0, 0],
        ];
        const solution = [
            ['C', 'A', '#'],
            ['D', 'O', 'G'],
            ['E', 'E', 'L'],
        ];
        const progress = [['', '', ''], ['', '', ''], ['', '', '']];
        const s = (function () {
            const { crosswordSolver } = require('../../resources/js/crossword-solver.js');
            const inst = crosswordSolver({
                width: 3, height: 3, grid, solution, progress,
                styles: {}, prefilled: null,
                cluesAcross: [
                    { number: 1, clue: 'A', cells: [[0,0],[0,1]] },
                    { number: 3, clue: 'B', cells: [[1,0],[1,1],[1,2]] },
                    { number: 4, clue: 'C', cells: [[2,0],[2,1],[2,2]] },
                ],
                cluesDown: [
                    { number: 1, clue: 'D1', cells: [[0,0],[1,0],[2,0]] },
                    { number: 2, clue: 'D2', cells: [[0,1],[1,1],[2,1]] },
                ],
                initialElapsed: 0, initialSolved: false, initialPencilCells: {},
                persistence: null, puzzleTitle: 'T', shareTitle: 'T', shareUrl: '',
            });
            inst.$watch = () => {};
            inst.$refs = {};
            inst.$nextTick = () => {};
            return inst;
        })();

        s.selectedRow = 0; s.selectedCol = 1;
        s.direction = 'across';
        s.typeCharacter('A');
        // 1-across ends here (block to the right); focus should land on 3-across's first empty cell.
        expect(s.selectedRow).toBe(1);
        expect(s.selectedCol).toBe(0);
    });
});

describe('toggleDirection', () => {
    it('switches across <-> down', () => {
        const s = makeSolver();
        s.selectedRow = 0; s.selectedCol = 0;
        s.direction = 'across';
        s.toggleDirection();
        expect(s.direction).toBe('down');
        s.toggleDirection();
        expect(s.direction).toBe('across');
    });

    it('is a no-op when no cell is selected', () => {
        const s = makeSolver();
        s.selectedRow = -1; s.selectedCol = -1;
        s.direction = 'across';
        s.toggleDirection();
        expect(s.direction).toBe('across');
    });
});

describe('pressBackspace', () => {
    it('clears the selected cell when it has content', () => {
        const s = makeSolver();
        s.selectedRow = 0; s.selectedCol = 1;
        s.progress[0][1] = 'A';
        s.pressBackspace();
        expect(s.progress[0][1]).toBe('');
        expect(s.isDirty).toBe(true);
    });

    it('is a no-op when no cell is selected', () => {
        const s = makeSolver();
        s.selectedRow = -1; s.selectedCol = -1;
        s.pressBackspace();
        expect(s.isDirty).toBe(false);
    });
});

describe('swipe detection', () => {
    it('horizontal right swipe navigates to the previous clue', () => {
        const s = makeSolver();
        s.selectedRow = 0; s.selectedCol = 0;
        s.direction = 'across';
        // Start on 4-across (index 1 in clues), expect to land on 1-across after right swipe.
        s.selectClue('across', 4);
        s.onSwipeStart({ changedTouches: [{ clientX: 100, clientY: 200 }] });
        s.onSwipeEnd({ changedTouches: [{ clientX: 200, clientY: 210 }] });
        expect(s.activeClueNumber).toBe(1);
    });

    it('horizontal left swipe navigates to the next clue', () => {
        const s = makeSolver();
        s.selectClue('across', 1);
        s.onSwipeStart({ changedTouches: [{ clientX: 200, clientY: 200 }] });
        s.onSwipeEnd({ changedTouches: [{ clientX: 80, clientY: 195 }] });
        expect(s.activeClueNumber).toBe(4);
    });

    it('ignores vertical swipes', () => {
        const s = makeSolver();
        s.selectClue('across', 1);
        s.onSwipeStart({ changedTouches: [{ clientX: 100, clientY: 100 }] });
        s.onSwipeEnd({ changedTouches: [{ clientX: 105, clientY: 250 }] });
        expect(s.activeClueNumber).toBe(1);
    });

    it('ignores short swipes', () => {
        const s = makeSolver();
        s.selectClue('across', 1);
        s.onSwipeStart({ changedTouches: [{ clientX: 100, clientY: 100 }] });
        s.onSwipeEnd({ changedTouches: [{ clientX: 130, clientY: 105 }] });
        expect(s.activeClueNumber).toBe(1);
    });
});
