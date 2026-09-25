import { describe, expect, it } from 'vitest';
import { collapsibleGrid } from '../../resources/js/collapsible-grid.js';

function makeGrid(cardCount, columns) {
    const cards = Array.from({ length: cardCount }, () => ({ hidden: false }));
    const g = collapsibleGrid();
    g.$refs = { grid: { children: cards } };
    g.columns = columns;
    return { g, cards };
}

describe('collapsible grid', () => {
    it('hides everything past the first row until expanded', () => {
        const { g, cards } = makeGrid(7, 3);
        g.apply();

        expect(cards.map((c) => c.hidden)).toEqual([false, false, false, true, true, true, true]);
        expect(g.total).toBe(7);
        expect(g.shown).toBe(3);
        expect(g.hasMore).toBe(true);
    });

    it('shows every card once expanded', () => {
        const { g, cards } = makeGrid(7, 3);
        g.expanded = true;
        g.apply();

        expect(cards.every((c) => !c.hidden)).toBe(true);
        expect(g.shown).toBe(7);
    });

    it('offers no toggle when the cards already fit in one row', () => {
        const { g } = makeGrid(2, 3);
        g.apply();

        expect(g.hasMore).toBe(false);
        expect(g.shown).toBe(2);
    });
});
