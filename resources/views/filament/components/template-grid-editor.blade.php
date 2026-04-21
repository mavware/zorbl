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
        class="space-y-3"
    >
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <button
                type="button"
                x-on:click="symmetry = !symmetry"
                :class="symmetry
                    ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
                    : 'border-zinc-300 text-zinc-500 dark:border-zinc-600 dark:text-zinc-400'"
                class="rounded border px-2 py-1 transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800"
            >
                <span x-text="symmetry ? '{{ __('Symmetry on') }}' : '{{ __('Symmetry off') }}'"></span>
            </button>
            <button
                type="button"
                x-on:click="clearAll()"
                class="rounded border border-zinc-300 px-2 py-1 text-zinc-600 transition-colors hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-800"
            >
                {{ __('Clear all') }}
            </button>
            <span class="text-zinc-500 dark:text-zinc-400">
                <span x-text="width"></span> &times; <span x-text="height"></span>
            </span>
        </div>

        <div
            class="inline-grid gap-0 rounded border border-zinc-700 p-0 dark:border-zinc-300"
            :style="`grid-template-columns: repeat(${Number(width)}, ${cellSizePx()}px);`"
        >
            <template x-for="(row, r) in grid" :key="`row-${r}`">
                <template x-for="(cell, c) in row" :key="`cell-${r}-${c}`">
                    <button
                        type="button"
                        x-on:click="toggleBlock(r, c)"
                        :data-row="r"
                        :data-col="c"
                        :data-block="cell === '#' ? 'true' : 'false'"
                        :class="cell === '#'
                            ? 'bg-zinc-800 dark:bg-zinc-200'
                            : 'bg-white hover:bg-zinc-100 dark:bg-zinc-900 dark:hover:bg-zinc-700'"
                        class="aspect-square border border-zinc-300 transition-colors dark:border-zinc-600"
                    ></button>
                </template>
            </template>
        </div>

        <p class="text-xs text-zinc-500 dark:text-zinc-400">
            {{ __('Click any cell to toggle a block. Symmetry mirrors toggles through the grid center.') }}
        </p>
    </div>
</x-dynamic-component>
