@php
    $statePath = $getStatePath();
    $lastDotPosition = strrpos($statePath, '.');
    $containerPath = $lastDotPosition === false ? '' : substr($statePath, 0, $lastDotPosition).'.';
    $widthPath = $containerPath.'width';
    $heightPath = $containerPath.'height';
    $stylesPath = $containerPath.'styles';
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            gridPath: @js($statePath),
            stylesPath: @js($stylesPath),
            grid: [],
            styles: {},
            width: $wire.$entangle(@js($widthPath)),
            height: $wire.$entangle(@js($heightPath)),
            symmetry: true,
            contextMenu: { show: false, row: 0, col: 0, x: 0, y: 0 },

            init() {
                // Read initial grid/styles from wire once. We don't entangle these because
                // entangle's clone-on-sync cycle races with x-for and lags renders by one click.
                const initialGrid = $wire.get(this.gridPath);
                const initialStyles = $wire.get(this.stylesPath);

                this.grid = (Array.isArray(initialGrid) && initialGrid.length === Number(this.height))
                    ? JSON.parse(JSON.stringify(initialGrid))
                    : this.openGrid(Number(this.width), Number(this.height));

                this.styles = (initialStyles && !Array.isArray(initialStyles))
                    ? JSON.parse(JSON.stringify(initialStyles))
                    : {};

                this.pushGrid();
                this.pushStyles();

                this.$watch('width', (val) => this.resize(Number(val), Number(this.height)));
                this.$watch('height', (val) => this.resize(Number(this.width), Number(val)));
            },

            pushGrid() {
                $wire.set(this.gridPath, this.grid, false);
            },

            pushStyles() {
                $wire.set(this.stylesPath, this.styles, false);
            },

            toggleBlock(row, col) {
                this.closeContextMenu();
                const w = Number(this.width);
                const h = Number(this.height);
                const isBlock = this.grid[row][col] === '#';
                const next = isBlock ? 0 : '#';
                const copy = this.grid.map((r) => r.slice());
                copy[row][col] = next;

                if (this.symmetry) {
                    const sr = h - 1 - row;
                    const sc = w - 1 - col;
                    if (sr !== row || sc !== col) {
                        copy[sr][sc] = next;
                    }
                }

                this.grid = copy;
                this.pushGrid();

                // Strip bars from any cell that just became a block; bars on
                // a block are nonsense and would survive otherwise.
                if (next === '#') {
                    const stylesCopy = this._cloneStyles();
                    this._clearCellBars(stylesCopy, row, col);
                    if (this.symmetry) {
                        const sr = h - 1 - row;
                        const sc = w - 1 - col;
                        if (sr !== row || sc !== col) this._clearCellBars(stylesCopy, sr, sc);
                    }
                    this.styles = stylesCopy;
                    this.pushStyles();
                }
            },

            clearAll() {
                this.grid = this.openGrid(Number(this.width), Number(this.height));
                this.styles = {};
                this.pushGrid();
                this.pushStyles();
            },

            clearBars() {
                const next = this._cloneStyles();
                for (const key of Object.keys(next)) {
                    if (next[key]?.bars) {
                        delete next[key].bars;
                        if (Object.keys(next[key]).length === 0) delete next[key];
                    }
                }
                this.styles = next;
                this.pushStyles();
            },

            resize(w, h) {
                if (!Number.isFinite(w) || !Number.isFinite(h) || w < 3 || h < 3) return;

                const current = Array.isArray(this.grid) ? this.grid : [];
                const resized = [];

                for (let r = 0; r < h; r++) {
                    const row = [];
                    for (let c = 0; c < w; c++) {
                        row.push(current[r]?.[c] ?? 0);
                    }
                    resized.push(row);
                }

                this.grid = resized;

                // Drop any styles that fall outside the new dimensions.
                const stylesCopy = this._cloneStyles();
                for (const key of Object.keys(stylesCopy)) {
                    const [rr, cc] = key.split(',').map(Number);
                    if (rr >= h || cc >= w) delete stylesCopy[key];
                }
                this.styles = stylesCopy;

                this.pushGrid();
                this.pushStyles();
            },

            openGrid(w, h) {
                return Array.from({ length: h }, () => Array.from({ length: w }, () => 0));
            },

            get flatCells() {
                const cells = [];
                const rows = Array.isArray(this.grid) ? this.grid : [];
                for (let r = 0; r < rows.length; r++) {
                    const row = Array.isArray(rows[r]) ? rows[r] : [];
                    for (let c = 0; c < row.length; c++) {
                        cells.push({
                            r,
                            c,
                            block: row[c] === '#',
                            key: `${r}-${c}`,
                        });
                    }
                }
                return cells;
            },

            cellSizePx() {
                const w = Number(this.width) || 15;
                return Math.max(12, Math.min(32, Math.floor(600 / w)));
            },

            isBlock(row, col) {
                return this.grid?.[row]?.[col] === '#';
            },

            hasBar(row, col, edge) {
                const key = `${row},${col}`;
                const bars = (this.styles || {})[key]?.bars;
                return Array.isArray(bars) && bars.includes(edge);
            },

            cellBarBoxShadow(row, col) {
                const bars = (this.styles || {})[`${row},${col}`]?.bars;
                if (!Array.isArray(bars) || bars.length === 0) return '';
                const shadows = [];
                if (bars.includes('top'))    shadows.push('inset 0 2px 0 0 rgb(20 184 166)');
                if (bars.includes('bottom')) shadows.push('inset 0 -2px 0 0 rgb(20 184 166)');
                if (bars.includes('left'))   shadows.push('inset 2px 0 0 0 rgb(20 184 166)');
                if (bars.includes('right'))  shadows.push('inset -2px 0 0 0 rgb(20 184 166)');
                return `box-shadow: ${shadows.join(', ')};`;
            },

            openContextMenu(row, col, event) {
                if (this.isBlock(row, col)) return;
                this.contextMenu = { show: true, row, col, x: event.clientX, y: event.clientY };
            },

            closeContextMenu() {
                this.contextMenu.show = false;
            },

            toggleBar(edge) {
                const row = this.contextMenu.row;
                const col = this.contextMenu.col;
                const target = !this.hasBar(row, col, edge);
                const next = this._cloneStyles();
                this._setBar(next, row, col, edge, target);

                if (this.symmetry) {
                    const mr = Number(this.height) - 1 - row;
                    const mc = Number(this.width) - 1 - col;
                    const mirrorEdge = { top: 'bottom', bottom: 'top', left: 'right', right: 'left' }[edge];
                    if (mr !== row || mc !== col || mirrorEdge !== edge) {
                        this._setBar(next, mr, mc, mirrorEdge, target);
                    }
                }

                this.styles = next;
                this.pushStyles();
            },

            _cloneStyles() {
                return JSON.parse(JSON.stringify(this.styles || {}));
            },

            _setBar(styles, row, col, edge, on) {
                const key = `${row},${col}`;
                const entry = styles[key] || {};
                const bars = Array.isArray(entry.bars) ? entry.bars.filter((e) => e !== edge) : [];
                if (on) bars.push(edge);

                if (bars.length > 0) {
                    styles[key] = { ...entry, bars };
                } else {
                    const { bars: _, ...rest } = entry;
                    if (Object.keys(rest).length === 0) delete styles[key];
                    else styles[key] = rest;
                }
            },

            _clearCellBars(styles, row, col) {
                const key = `${row},${col}`;
                if (!styles[key]) return;
                const { bars: _, ...rest } = styles[key];
                if (Object.keys(rest).length === 0) delete styles[key];
                else styles[key] = rest;
            },
        }"
        x-on:click.window="closeContextMenu()"
        wire:ignore.self
        data-testid="template-grid-editor"
        style="display: flex; flex-direction: column; gap: 0.75rem;"
    >
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
            <button
                type="button"
                x-on:click="symmetry = !symmetry"
                :style="`border: 1px solid ${symmetry ? 'rgb(16 185 129)' : 'rgb(161 161 170)'}; color: ${symmetry ? 'rgb(5 150 105)' : 'rgb(113 113 122)'}; background: transparent; padding: 0.25rem 0.5rem; border-radius: 0.25rem; cursor: pointer;`"
            >
                <span x-text="symmetry ? '{{ __('Symmetry on') }}' : '{{ __('Symmetry off') }}'"></span>
            </button>
            <button
                type="button"
                x-on:click="clearAll()"
                style="border: 1px solid rgb(161 161 170); color: rgb(82 82 91); background: transparent; padding: 0.25rem 0.5rem; border-radius: 0.25rem; cursor: pointer;"
            >
                {{ __('Clear all') }}
            </button>
            <button
                type="button"
                x-on:click="clearBars()"
                style="border: 1px solid rgb(161 161 170); color: rgb(82 82 91); background: transparent; padding: 0.25rem 0.5rem; border-radius: 0.25rem; cursor: pointer;"
            >
                {{ __('Clear bars') }}
            </button>
            <span style="color: rgb(113 113 122);">
                <span x-text="width"></span> &times; <span x-text="height"></span>
            </span>
        </div>

        <div
            data-grid-container
            :style="`display: inline-grid; gap: 0; border: 1px solid rgb(63 63 70); border-radius: 0.25rem; grid-template-columns: repeat(${Number(width)}, ${cellSizePx()}px); width: ${Number(width) * cellSizePx() + 2}px;`"
        >
            <template x-for="cellInfo in flatCells" :key="cellInfo.key">
                <button
                    type="button"
                    x-on:click="toggleBlock(cellInfo.r, cellInfo.c)"
                    x-on:contextmenu.prevent="openContextMenu(cellInfo.r, cellInfo.c, $event)"
                    :data-row="cellInfo.r"
                    :data-col="cellInfo.c"
                    :data-block="cellInfo.block ? 'true' : 'false'"
                    :style="`display: block; width: ${cellSizePx()}px; height: ${cellSizePx()}px; border: 1px solid rgb(212 212 216); padding: 0; cursor: pointer; background: ${cellInfo.block ? 'rgb(39 39 42)' : 'rgb(255 255 255)'}; transition: background-color 0.15s; ${cellBarBoxShadow(cellInfo.r, cellInfo.c)}`"
                ></button>
            </template>
        </div>

        <p style="font-size: 0.75rem; color: rgb(113 113 122);">
            {{ __('Click any cell to toggle a block. Right-click an open cell to add bars on its edges. Symmetry mirrors both blocks and bars through the grid center.') }}
        </p>

        <div
            x-show="contextMenu.show"
            x-cloak
            x-on:click.stop
            x-on:contextmenu.prevent.stop
            :style="`position: fixed; left: ${contextMenu.x}px; top: ${contextMenu.y}px; z-index: 50; min-width: 180px; background: rgb(255 255 255); color: rgb(24 24 27); border: 1px solid rgb(212 212 216); border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); padding: 0.25rem 0;`"
        >
            <div style="padding: 0.25rem 0.75rem; font-size: 0.75rem; font-weight: 500; color: rgb(113 113 122);">
                {{ __('Bars') }}
            </div>
            <template x-for="edge in ['top', 'right', 'bottom', 'left']" :key="`bar-${edge}`">
                <button
                    type="button"
                    x-on:click.stop="toggleBar(edge)"
                    style="display: flex; width: 100%; align-items: center; gap: 0.5rem; padding: 0.375rem 0.75rem; font-size: 0.875rem; text-align: left; background: transparent; border: 0; cursor: pointer;"
                    onmouseover="this.style.background='rgb(244 244 245)'"
                    onmouseout="this.style.background='transparent'"
                >
                    <span :style="`display: inline-block; width: 0.875rem; height: 0.875rem; ${hasBar(contextMenu.row, contextMenu.col, edge) ? 'background: rgb(20 184 166); border-radius: 0.125rem;' : 'border: 1px solid rgb(161 161 170); border-radius: 0.125rem;'}`"></span>
                    <span x-text="edge.charAt(0).toUpperCase() + edge.slice(1)"></span>
                </button>
            </template>
        </div>
    </div>
</x-dynamic-component>
