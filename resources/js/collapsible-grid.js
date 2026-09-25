// Collapses a CSS grid of cards to its first row until expanded. The
// resolved column count is read from the grid itself, so the row is exact
// at any width, and it re-applies after Livewire re-renders the cards.
//
// Usage: x-data="collapsibleGrid" on a wrapper, x-ref="grid" on the grid.
export function collapsibleGrid() {
    return {
        expanded: false,
        columns: 0,
        total: 0,

        get hasMore() {
            return this.total > this.columns;
        },

        get shown() {
            return this.expanded ? this.total : Math.min(this.columns, this.total);
        },

        init() {
            this.measure();
            new ResizeObserver(() => this.measure()).observe(this.$refs.grid);
            new MutationObserver(() => this.apply()).observe(this.$refs.grid, {
                childList: true,
                attributes: true,
                attributeFilter: ['hidden'],
            });
            this.$watch('expanded', () => this.apply());
        },

        measure() {
            this.columns = getComputedStyle(this.$refs.grid).gridTemplateColumns.split(' ').length;
            this.apply();
        },

        apply() {
            const cards = Array.from(this.$refs.grid.children);
            this.total = cards.length;
            cards.forEach((card, index) => {
                const hide = !this.expanded && index >= this.columns;
                if (card.hidden !== hide) {
                    card.hidden = hide;
                }
            });
        },
    };
}
