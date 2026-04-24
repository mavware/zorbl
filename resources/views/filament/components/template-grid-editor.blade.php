@php
    $statePath = $getStatePath();
    $lastDotPosition = strrpos($statePath, '.');
    $containerPath = $lastDotPosition === false ? '' : substr($statePath, 0, $lastDotPosition).'.';
    $widthPath = $containerPath.'width';
    $heightPath = $containerPath.'height';
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            grid: $wire.$entangle(@js($statePath)),
            width: $wire.$entangle(@js($widthPath)),
            height: $wire.$entangle(@js($heightPath)),
            symmetry: true,

            init() {
                this.$watch('width', (val) => this.resize(Number(val), Number(this.height)));
                this.$watch('height', (val) => this.resize(Number(this.width), Number(val)));

                if (!Array.isArray(this.grid) || this.grid.length !== Number(this.height)) {
                    this.grid = this.openGrid(Number(this.width), Number(this.height));
                }
            },

            toggleBlock(row, col) {
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
            },

            clearAll() {
                this.grid = this.openGrid(Number(this.width), Number(this.height));
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
            },

            openGrid(w, h) {
                return Array.from({ length: h }, () => Array.from({ length: w }, () => 0));
            },

            cellSizePx() {
                const w = Number(this.width) || 15;
                return Math.max(12, Math.min(32, Math.floor(600 / w)));
            },
        }"
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
            <span style="color: rgb(113 113 122);">
                <span x-text="width"></span> &times; <span x-text="height"></span>
            </span>
        </div>

        <div
            data-grid-container
            :style="`display: inline-grid; gap: 0; border: 1px solid rgb(63 63 70); border-radius: 0.25rem; grid-template-columns: repeat(${Number(width)}, ${cellSizePx()}px); width: ${Number(width) * cellSizePx() + 2}px;`"
        >
            <template x-for="(row, r) in grid" :key="`row-${r}`">
                <template x-for="(cell, c) in row" :key="`cell-${r}-${c}`">
                    <button
                        type="button"
                        x-on:click="toggleBlock(r, c)"
                        :data-row="r"
                        :data-col="c"
                        :data-block="cell === '#' ? 'true' : 'false'"
                        :style="`display: block; width: ${cellSizePx()}px; height: ${cellSizePx()}px; border: 1px solid rgb(212 212 216); padding: 0; cursor: pointer; background: ${cell === '#' ? 'rgb(39 39 42)' : 'rgb(255 255 255)'}; transition: background-color 0.15s;`"
                    ></button>
                </template>
            </template>
        </div>

        <p style="font-size: 0.75rem; color: rgb(113 113 122);">
            {{ __('Click any cell to toggle a block. Symmetry mirrors toggles through the grid center.') }}
        </p>
    </div>
</x-dynamic-component>
