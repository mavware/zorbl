import { describe, expect, it, vi, beforeEach } from 'vitest';
import { isBlock } from '../../resources/js/grid/helpers.js';

function buildSolverContext(overrides = {}) {
    const grid = overrides.grid ?? [
        [1, 2, '#'],
        [3, 0, 0],
        ['#', 4, 0],
    ];
    const width = overrides.width ?? grid[0].length;
    const height = overrides.height ?? grid.length;
    const styles = overrides.styles ?? {};

    return {
        width,
        height,
        grid,
        styles,
        elapsedSeconds: overrides.elapsedSeconds ?? 323,

        isBlock(row, col) {
            return isBlock(this.grid, row, col);
        },

        formattedTime() {
            const s = this.elapsedSeconds;
            const hours = Math.floor(s / 3600);
            const minutes = Math.floor((s % 3600) / 60);
            const secs = s % 60;
            if (hours > 0) {
                return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
            }
            return `${minutes}:${String(secs).padStart(2, '0')}`;
        },

        generateShareText() {
            const rows = [];
            for (let r = 0; r < this.height; r++) {
                let row = '';
                for (let c = 0; c < this.width; c++) {
                    if (this.isBlock(r, c)) {
                        row += '⬛';
                    } else {
                        row += '⬜';
                    }
                }
                rows.push(row);
            }

            const maxRows = 12;
            let gridArt;
            if (this.height <= maxRows) {
                gridArt = rows.join('\n');
            } else {
                gridArt = rows.slice(0, maxRows - 1).join('\n') + '\n...';
            }

            const title = this._title ?? 'Crossword';
            const time = this.formattedTime();
            const url = this._url ?? 'https://zorbl.test/crosswords/1/solve';

            return `Zorbl - "${title}"\n⏱️ ${time} | ${this.width}×${this.height}\n\n${gridArt}\n\n${url}`;
        },

        _title: overrides.title ?? 'Test Puzzle',
        _url: overrides.url ?? 'https://zorbl.test/crosswords/1/solve',
    };
}

describe('generateShareText', () => {
    it('includes puzzle title', () => {
        const ctx = buildSolverContext({ title: 'Sunday Fun' });
        const text = ctx.generateShareText();
        expect(text).toContain('"Sunday Fun"');
    });

    it('includes solve time', () => {
        const ctx = buildSolverContext({ elapsedSeconds: 125 });
        const text = ctx.generateShareText();
        expect(text).toContain('2:05');
    });

    it('includes grid dimensions', () => {
        const ctx = buildSolverContext();
        const text = ctx.generateShareText();
        expect(text).toContain('3×3');
    });

    it('renders blocks as black squares and cells as white squares', () => {
        const ctx = buildSolverContext({
            grid: [
                [1, '#'],
                ['#', 2],
            ],
        });
        const text = ctx.generateShareText();
        expect(text).toContain('⬜⬛');
        expect(text).toContain('⬛⬜');
    });

    it('includes the puzzle URL', () => {
        const ctx = buildSolverContext({ url: 'https://zorbl.test/crosswords/42/solve' });
        const text = ctx.generateShareText();
        expect(text).toContain('https://zorbl.test/crosswords/42/solve');
    });

    it('truncates grid art for tall puzzles', () => {
        const tallGrid = Array.from({ length: 15 }, (_, i) =>
            Array.from({ length: 3 }, (_, j) => (i === 0 && j === 0 ? 1 : 0))
        );
        const ctx = buildSolverContext({ grid: tallGrid });
        const text = ctx.generateShareText();
        expect(text).toContain('...');
        const lines = text.split('\n');
        const gridLines = lines.filter(l => l.match(/[⬛⬜]/));
        expect(gridLines.length).toBe(11);
    });

    it('does not truncate grid for 12-row puzzles', () => {
        const grid12 = Array.from({ length: 12 }, (_, i) =>
            Array.from({ length: 3 }, (_, j) => (i === 0 && j === 0 ? 1 : 0))
        );
        const ctx = buildSolverContext({ grid: grid12 });
        const text = ctx.generateShareText();
        expect(text).not.toContain('...');
    });

    it('formats hours correctly', () => {
        const ctx = buildSolverContext({ elapsedSeconds: 3661 });
        const text = ctx.generateShareText();
        expect(text).toContain('1:01:01');
    });
});
