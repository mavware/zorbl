// Pure helpers shared by the crossword editor and solver Alpine components.
// All functions take grid/styles as arguments — they never read from `this`.

export function cellKey(row, col) {
    return row + ',' + col;
}

export function isVoid(grid, row, col) {
    return grid[row]?.[col] === null;
}

export function isBlock(grid, row, col) {
    const cell = grid[row]?.[col];
    return cell === '#' || cell === null;
}

export function hasBar(styles, row, col, edge) {
    return styles[cellKey(row, col)]?.bars?.includes(edge) || false;
}

export function hasCircle(styles, row, col) {
    return styles[cellKey(row, col)]?.shapebg === 'circle';
}

export function getCellColor(styles, row, col) {
    return styles[cellKey(row, col)]?.color ?? null;
}

export function getCustomNumber(styles, row, col) {
    return styles[cellKey(row, col)]?.number ?? null;
}

export function getDisplayNumber(grid, styles, row, col) {
    const custom = getCustomNumber(styles, row, col);
    if (custom !== null) return custom;
    const cell = grid[row]?.[col];
    return typeof cell === 'number' && cell > 0 ? cell : null;
}

// --- Word boundary helpers (bars + blocks + edges) ---

export function hasLeftBoundary(grid, styles, row, col) {
    if (col === 0) return true;
    if (isBlock(grid, row, col - 1)) return true;
    return hasBar(styles, row, col, 'left') || hasBar(styles, row, col - 1, 'right');
}

export function hasRightBoundary(grid, styles, width, row, col) {
    if (col + 1 >= width) return true;
    if (isBlock(grid, row, col + 1)) return true;
    return hasBar(styles, row, col, 'right') || hasBar(styles, row, col + 1, 'left');
}

export function hasTopBoundary(grid, styles, row, col) {
    if (row === 0) return true;
    if (isBlock(grid, row - 1, col)) return true;
    return hasBar(styles, row, col, 'top') || hasBar(styles, row - 1, col, 'bottom');
}

export function hasBottomBoundary(grid, styles, height, row, col) {
    if (row + 1 >= height) return true;
    if (isBlock(grid, row + 1, col)) return true;
    return hasBar(styles, row, col, 'bottom') || hasBar(styles, row + 1, col, 'top');
}

// --- Slot / word lookup ---

// Returns { row, col, length } for the slot whose start cell holds `number`,
// or null if no such slot exists in the given direction.
export function findSlot(grid, styles, width, height, dir, number) {
    for (let row = 0; row < height; row++) {
        for (let col = 0; col < width; col++) {
            if (grid[row][col] !== number) continue;
            if (dir === 'across') {
                let len = 1;
                while (!hasRightBoundary(grid, styles, width, row, col + len - 1)) len++;
                if (len > 1) return { row, col, length: len };
            } else {
                let len = 1;
                while (!hasBottomBoundary(grid, styles, height, row + len - 1, col)) len++;
                if (len > 1) return { row, col, length: len };
            }
        }
    }
    return null;
}

// Returns the clue number for the cell at (row, col) in the given direction,
// or -1 for blocks / cells that aren't part of any slot.
export function getClueNumberForCell(grid, styles, row, col, dir) {
    if (isBlock(grid, row, col)) return -1;
    if (dir === 'across') {
        let c = col;
        while (c > 0 && !hasLeftBoundary(grid, styles, row, c)) c--;
        const cell = grid[row][c];
        return typeof cell === 'number' && cell > 0 ? cell : -1;
    }
    let r = row;
    while (r > 0 && !hasTopBoundary(grid, styles, r, col)) r--;
    const cell = grid[r][col];
    return typeof cell === 'number' && cell > 0 ? cell : -1;
}

// Returns an array of [row, col] pairs for every cell in the word that
// contains (row, col) in the given direction.
export function getWordCells(grid, styles, width, height, row, col, dir) {
    if (isBlock(grid, row, col)) return [];
    const cells = [];

    if (dir === 'across') {
        let c = col;
        while (c > 0 && !hasLeftBoundary(grid, styles, row, c)) c--;
        while (c < width && !isBlock(grid, row, c)) {
            cells.push([row, c]);
            if (hasRightBoundary(grid, styles, width, row, c)) break;
            c++;
        }
    } else {
        let r = row;
        while (r > 0 && !hasTopBoundary(grid, styles, r, col)) r--;
        while (r < height && !isBlock(grid, r, col)) {
            cells.push([r, col]);
            if (hasBottomBoundary(grid, styles, height, r, col)) break;
            r++;
        }
    }
    return cells;
}

// Returns a Set<string> of "row,col" keys for every cell in the active word.
// Used by cellClasses / cellBarStyles to answer "is this cell highlighted?"
// in O(1) instead of O(width+height) per cell.
export function computeActiveWordCells(grid, styles, width, height, selectedRow, selectedCol, direction) {
    const set = new Set();
    if (selectedRow < 0 || selectedCol < 0) return set;
    const cells = getWordCells(grid, styles, width, height, selectedRow, selectedCol, direction);
    for (const [r, c] of cells) {
        set.add(cellKey(r, c));
    }
    return set;
}

// Mutates `styles` in place. After deleting whichever sub-key the caller
// already removed, drop the entry entirely if it has nothing meaningful left.
// `{ bars: [] }` is treated as empty too — that's how the editor has always
// signalled "no bars on this cell" before the cleanup.
export function cleanupStyleEntry(styles, key) {
    const entry = styles[key];
    if (!entry) return;
    const keys = Object.keys(entry);
    if (keys.length === 0) {
        delete styles[key];
        return;
    }
    if (keys.length === 1 && entry.bars?.length === 0) {
        delete styles[key];
    }
}
