import { describe, expect, it, beforeEach } from 'vitest';
import { createHistory } from '../../resources/js/grid/history.js';

describe('createHistory', () => {
    let h;
    beforeEach(() => {
        h = createHistory({ maxSize: 3 });
        h.reset('a');
    });

    it('starts with nothing to undo or redo', () => {
        expect(h.canUndo).toBe(false);
        expect(h.canRedo).toBe(false);
    });

    it('records a new state and can undo back to the baseline', () => {
        h.record('b');
        expect(h.canUndo).toBe(true);
        expect(h.undo()).toBe('a');
        expect(h.canUndo).toBe(false);
        expect(h.canRedo).toBe(true);
    });

    it('redo returns the state that was undone', () => {
        h.record('b');
        h.undo();
        expect(h.redo()).toBe('b');
        expect(h.canRedo).toBe(false);
        expect(h.canUndo).toBe(true);
    });

    it('returns null when there is nothing to undo or redo', () => {
        expect(h.undo()).toBeNull();
        expect(h.redo()).toBeNull();
    });

    it('ignores a record of the unchanged state', () => {
        h.record('a');
        expect(h.canUndo).toBe(false);
    });

    it('clears the redo stack when a new state is recorded', () => {
        h.record('b');
        h.undo();
        h.record('c');
        expect(h.canRedo).toBe(false);
        expect(h.undo()).toBe('a');
    });

    it('drops the oldest entries past maxSize', () => {
        h.record('b');
        h.record('c');
        h.record('d');
        h.record('e');
        expect(h.undoStack).toEqual(['b', 'c', 'd']);
    });

    it('suppresses recording inside a group so the caller records one step', () => {
        h.group(() => {
            h.record('b');
            h.record('c');
        });
        expect(h.canUndo).toBe(false);
        h.record('c');
        expect(h.undoStack).toEqual(['a']);
        expect(h.undo()).toBe('a');
    });

    it('re-enables recording even when the grouped callback throws', () => {
        expect(() => h.group(() => { throw new Error('boom'); })).toThrow('boom');
        h.record('b');
        expect(h.canUndo).toBe(true);
    });

    it('reset forgets both stacks and adopts the new baseline', () => {
        h.record('b');
        h.undo();
        h.reset('z');
        expect(h.canUndo).toBe(false);
        expect(h.canRedo).toBe(false);
        h.record('y');
        expect(h.undo()).toBe('z');
    });
});
