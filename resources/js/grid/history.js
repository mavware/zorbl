// Undo/redo history for the editor Alpine component. The host serializes
// its own state (a JSON string) and hands it to `record()` after every
// change; the helper keeps the stacks and hands strings back on undo/redo.
//
// Storing strings rather than objects makes de-duplication a plain equality
// check, so a change that ends up as a no-op (a blur on an untouched clue
// input, Delete on an empty cell) never becomes an empty history step.
//
// It is a plain object so that, once it lives on an Alpine component, the
// reactive proxy tracks `undoStack.length` and `canUndo` / `canRedo` update
// the toolbar buttons.

const DEFAULT_MAX_SIZE = 200;

/**
 * @param {Object} [args]
 * @param {number} [args.maxSize]  Oldest undo entries are dropped past this.
 */
export function createHistory({ maxSize = DEFAULT_MAX_SIZE } = {}) {
    return {
        current: null,
        undoStack: [],
        redoStack: [],
        depth: 0,

        get canUndo() { return this.undoStack.length > 0; },
        get canRedo() { return this.redoStack.length > 0; },

        /** Forget everything and treat `state` as the baseline. */
        reset(state) {
            this.current = state;
            this.undoStack = [];
            this.redoStack = [];
        },

        /** Record `state` as the newest step. No-op inside a group or when unchanged. */
        record(state) {
            if (this.depth > 0 || state === this.current) return;

            if (this.current !== null) {
                this.undoStack.push(this.current);
                if (this.undoStack.length > maxSize) this.undoStack.shift();
            }
            this.redoStack = [];
            this.current = state;
        },

        /**
         * Run `fn` with recording suppressed so that a burst of changes
         * becomes a single step. The caller records once afterwards.
         */
        group(fn) {
            this.depth++;
            try {
                return fn();
            } finally {
                this.depth--;
            }
        },

        /** @returns {string|null} the state to restore, or null if nothing to undo */
        undo() {
            if (!this.canUndo) return null;
            this.redoStack.push(this.current);
            this.current = this.undoStack.pop();
            return this.current;
        },

        /** @returns {string|null} the state to restore, or null if nothing to redo */
        redo() {
            if (!this.canRedo) return null;
            this.undoStack.push(this.current);
            this.current = this.redoStack.pop();
            return this.current;
        },
    };
}
