/**
 * localStorage-based persistence for embedded crossword solvers.
 * Stores progress, completion state, elapsed time, and pencil cells.
 */
export function createLocalStoragePersistence(crosswordId) {
    const key = `crosswordbuilder_embed_${crosswordId}`;

    return {
        /**
         * Save solver state to localStorage.
         */
        save(progress, isCompleted, elapsed, pencilCells, revealedCells) {
            try {
                localStorage.setItem(key, JSON.stringify({
                    progress,
                    isCompleted,
                    elapsed,
                    pencilCells,
                    revealedCells,
                    savedAt: Date.now(),
                }));
            } catch {
                // localStorage may be unavailable (private browsing, full quota)
            }
            return Promise.resolve();
        },

        /**
         * Load saved solver state from localStorage.
         * Returns null if no saved state exists.
         */
        load() {
            try {
                const raw = localStorage.getItem(key);
                return raw ? JSON.parse(raw) : null;
            } catch {
                return null;
            }
        },
    };
}
