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
        initialElapsed: 0,
        initialSolved: false,
        initialPencilCells: {},
        persistence: null,
        puzzleTitle: 'Test',
        shareTitle: 'Test',
        shareUrl: '',
        ...overrides,
    });
    s.$watch = () => {};
    s.$refs = {};
    s.$nextTick = () => {};
    return s;
}

describe('undo/redo state tracking', () => {
    let s;
    beforeEach(() => { s = makeSolver(); });

    it('starts with empty undo and redo stacks', () => {
        expect(s.canUndo).toBe(false);
        expect(s.canRedo).toBe(false);
    });

    it('pushes state to undo stack when typing a character', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('C');
        expect(s.canUndo).toBe(true);
        expect(s.canRedo).toBe(false);
    });

    it('clears redo stack when a new action is taken', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('C');
        s.undo();
        expect(s.canRedo).toBe(true);
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('X');
        expect(s.canRedo).toBe(false);
    });
});

describe('undo', () => {
    let s;
    beforeEach(() => { s = makeSolver(); });

    it('restores the previous cell value after typing', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('C');
        expect(s.progress[0][0]).toBe('C');
        s.undo();
        expect(s.progress[0][0]).toBe('');
    });

    it('restores state after multiple typed characters', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.direction = 'across';
        s.typeCharacter('C');
        s.typeCharacter('A');
        s.typeCharacter('T');
        expect(s.progress[0]).toEqual(['C', 'A', 'T']);
        s.undo();
        expect(s.progress[0][2]).toBe('');
        expect(s.progress[0][0]).toBe('C');
        expect(s.progress[0][1]).toBe('A');
        s.undo();
        expect(s.progress[0][1]).toBe('');
        s.undo();
        expect(s.progress[0][0]).toBe('');
    });

    it('restores state after backspace', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.progress[0][0] = 'C';
        s.handleBackspace();
        expect(s.progress[0][0]).toBe('');
        s.undo();
        expect(s.progress[0][0]).toBe('C');
    });

    it('restores state after clearProgress', () => {
        s.progress[0] = ['C', 'A', 'T'];
        s.progress[1] = ['D', 'O', 'G'];
        s.clearProgress();
        expect(s.progress[0]).toEqual(['', '', '']);
        s.undo();
        expect(s.progress[0]).toEqual(['C', 'A', 'T']);
        expect(s.progress[1]).toEqual(['D', 'O', 'G']);
    });

    it('restores state after clearErrors', () => {
        s.progress[0] = ['C', 'X', 'T'];
        s.clearErrors();
        expect(s.progress[0][1]).toBe('');
        s.undo();
        expect(s.progress[0][1]).toBe('X');
    });

    it('restores state after revealLetter', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.progress[0][0] = 'X';
        s.revealLetter();
        expect(s.progress[0][0]).toBe('C');
        s.undo();
        expect(s.progress[0][0]).toBe('X');
    });

    it('restores pencil cell state', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.pencilMode = true;
        s.typeCharacter('C');
        expect(s.pencilCells['0,0']).toBe(true);
        s.undo();
        expect(s.pencilCells['0,0']).toBeUndefined();
    });

    it('is a no-op when the stack is empty', () => {
        s.progress[0][0] = 'C';
        s.undo();
        expect(s.progress[0][0]).toBe('C');
    });

    it('is a no-op when puzzle is solved', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('C');
        s.solved = true;
        s.undo();
        expect(s.progress[0][0]).toBe('C');
    });

    it('marks dirty after undo so autosave picks up the reverted state', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('C');
        s.isDirty = false;
        s.undo();
        expect(s.isDirty).toBe(true);
    });
});

describe('redo', () => {
    let s;
    beforeEach(() => { s = makeSolver(); });

    it('restores the undone state', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('C');
        s.undo();
        expect(s.progress[0][0]).toBe('');
        s.redo();
        expect(s.progress[0][0]).toBe('C');
    });

    it('supports multiple redo steps', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.direction = 'across';
        s.typeCharacter('C');
        s.typeCharacter('A');
        s.undo();
        s.undo();
        expect(s.progress[0][0]).toBe('');
        expect(s.progress[0][1]).toBe('');
        s.redo();
        expect(s.progress[0][0]).toBe('C');
        s.redo();
        expect(s.progress[0][1]).toBe('A');
    });

    it('is a no-op when nothing to redo', () => {
        s.progress[0][0] = 'C';
        s.redo();
        expect(s.progress[0][0]).toBe('C');
    });

    it('is a no-op when puzzle is solved', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('C');
        s.undo();
        s.solved = true;
        s.redo();
        expect(s.progress[0][0]).toBe('');
    });
});

describe('undo stack limit', () => {
    it('caps the undo stack at _maxUndoSize', () => {
        const s = makeSolver();
        s._maxUndoSize = 5;
        s.selectedRow = 0; s.selectedCol = 0;
        for (let i = 0; i < 10; i++) {
            s.progress[0][0] = '';
            s.typeCharacter('A');
        }
        expect(s._undoStack.length).toBe(5);
    });
});

describe('keyboard shortcuts', () => {
    let s;
    beforeEach(() => { s = makeSolver(); });

    it('Ctrl+Z triggers undo', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('C');
        let prevented = false;
        s.handleKeydown({
            key: 'z', ctrlKey: true, metaKey: false, shiftKey: false,
            target: { tagName: 'DIV' },
            preventDefault: () => { prevented = true; },
        });
        expect(s.progress[0][0]).toBe('');
        expect(prevented).toBe(true);
    });

    it('Cmd+Z triggers undo', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('C');
        s.handleKeydown({
            key: 'z', ctrlKey: false, metaKey: true, shiftKey: false,
            target: { tagName: 'DIV' },
            preventDefault: () => {},
        });
        expect(s.progress[0][0]).toBe('');
    });

    it('Ctrl+Shift+Z triggers redo', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('C');
        s.undo();
        let prevented = false;
        s.handleKeydown({
            key: 'z', ctrlKey: true, metaKey: false, shiftKey: true,
            target: { tagName: 'DIV' },
            preventDefault: () => { prevented = true; },
        });
        expect(s.progress[0][0]).toBe('C');
        expect(prevented).toBe(true);
    });

    it('Ctrl+Y triggers redo', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('C');
        s.undo();
        s.handleKeydown({
            key: 'y', ctrlKey: true, metaKey: false, shiftKey: false,
            target: { tagName: 'DIV' },
            preventDefault: () => {},
        });
        expect(s.progress[0][0]).toBe('C');
    });

    it('does not fire undo/redo when focused on an input', () => {
        s.selectedRow = 0; s.selectedCol = 0;
        s.typeCharacter('C');
        s.handleKeydown({
            key: 'z', ctrlKey: true, metaKey: false, shiftKey: false,
            target: { tagName: 'INPUT' },
            preventDefault: () => {},
        });
        expect(s.progress[0][0]).toBe('C');
    });
});

describe('delete key undo', () => {
    it('restores the cell value after delete', () => {
        const s = makeSolver();
        s.selectedRow = 0; s.selectedCol = 0;
        s.progress[0][0] = 'C';
        s.handleKeydown({
            key: 'Delete', ctrlKey: false, metaKey: false, shiftKey: false,
            target: { tagName: 'DIV' },
            preventDefault: () => {},
        });
        expect(s.progress[0][0]).toBe('');
        s.undo();
        expect(s.progress[0][0]).toBe('C');
    });
});
