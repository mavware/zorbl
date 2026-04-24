        {{-- Grid --}}
        <div class="flex min-w-0 flex-1 items-start justify-center overflow-hidden">
            <div
                class="relative max-h-full max-w-full"
                :style="'width: ' + Math.min(600, width * 40) + 'px;'"
                x-on:keydown="handleKeydown($event)"
                tabindex="0"
                x-ref="gridContainer"
                role="grid"
                :aria-label="'Crossword grid, ' + width + ' columns by ' + height + ' rows'"
            >
                <div
                    class="grid [--bar-color:var(--color-zinc-800)] dark:[--bar-color:var(--color-zinc-300)]"
                    :style="'grid-template-columns: repeat(' + width + ', minmax(0, 1fr));'"
                >
                    <template x-for="(row, rowIdx) in grid" :key="'row-' + rowIdx">
                        <template x-for="(cell, colIdx) in row" :key="'cell-' + rowIdx + '-' + colIdx">
                            <div
                                x-on:click="selectCell(rowIdx, colIdx, $event)"
                                x-on:dblclick.prevent="toggleBlockOnDblClick(rowIdx, colIdx)"
                                x-on:contextmenu.prevent="openContextMenu(rowIdx, colIdx, $event)"
                                x-on:touchstart.passive="startLongPress(rowIdx, colIdx, $event)"
                                x-on:touchend="cancelLongPress()"
                                x-on:touchmove="cancelLongPress()"
                                :class="[cellClasses(rowIdx, colIdx), cellBorderClasses(rowIdx, colIdx)]"
                                :style="cellBarStyles(rowIdx, colIdx)"
                                class="relative box-border flex aspect-square items-center justify-center overflow-hidden select-none"
                                role="gridcell"
                            >
                                {{-- Clue number --}}
                                <template x-if="getDisplayNumber(rowIdx, colIdx) !== null">
                                        <span
                                            :class="getCustomNumber(rowIdx, colIdx) !== null ? 'absolute top-0 left-0.5 text-blue-600 dark:text-blue-400 leading-none' : 'absolute top-0 left-0.5 text-zinc-800 dark:text-zinc-400 leading-none'"
                                            :style="'font-size: ' + Math.max(8, Math.min(11, 600 / width * 0.22)) + 'px'"
                                            x-text="getDisplayNumber(rowIdx, colIdx)"
                                        ></span>
                                </template>

                                {{-- Circle annotation --}}
                                <template x-if="hasCircle(rowIdx, colIdx)">
                                    <svg class="pointer-events-none absolute inset-0.5 size-[calc(100%-4px)]"
                                         viewBox="0 0 100 100">
                                        <circle cx="50" cy="50" r="46" fill="none" stroke="currentColor"
                                                stroke-width="2" class="text-fg-subtle"/>
                                    </svg>
                                </template>

                                {{-- Rebus indicator --}}
                                <template x-if="rebusMode && rowIdx === selectedRow && colIdx === selectedCol">
                                    <span class="absolute top-0 right-0.5 text-xs leading-none text-blue-500">R</span>
                                </template>

                                {{-- Prefilled indicator --}}
                                <template x-if="isPrefilled(rowIdx, colIdx)">
                                    <div class="absolute inset-0 bg-violet-200/40 dark:bg-violet-800/30"></div>
                                </template>

                                {{-- Letter --}}
                                <span
                                    class="font-semibold uppercase"
                                    :class="isPrefilled(rowIdx, colIdx) ? 'text-violet-700 dark:text-violet-300' : 'text-fg'"
                                    :style="letterFontStyle(rowIdx, colIdx)"
                                    x-text="isBlock(rowIdx, colIdx) ? '' : (solution[rowIdx]?.[colIdx] || '')"
                                ></span>
                            </div>
                        </template>
                    </template>
                </div>
            </div>

            {{-- Context menu --}}
            <div
                x-ref="contextMenu"
                x-show="contextMenu.show"
                x-on:click.stop
                :style="'position: fixed; left: ' + contextMenu.x + 'px; top: ' + contextMenu.y + 'px; z-index: 50;'"
                class="bg-elevated border-line min-w-44 rounded-lg border py-1 shadow-lg"
                x-transition
                x-cloak
            >
                {{-- Multi-selection indicator --}}
                <template x-if="Object.keys(multiSelectedCells).length > 1">
                    <div class="px-3 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                         x-text="Object.keys(multiSelectedCells).length + ' {{ __('cells selected') }}'"></div>
                </template>

                <button
                    x-show="!isVoid(contextMenu.row, contextMenu.col)"
                    x-on:click="contextToggleBlock()"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-zinc-800 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                >
                    <span
                        x-text="isBlock(contextMenu.row, contextMenu.col) ? '{{ __('Make white') }}' : '{{ __('Make black') }}'"></span>
                </button>

                <button
                    x-on:click="contextToggleVoid()"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-zinc-800 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                >
                    <span
                        x-text="isVoid(contextMenu.row, contextMenu.col) ? '{{ __('Restore cell') }}' : '{{ __('Remove cell') }}'"></span>
                </button>

                <button
                    x-show="!isBlock(contextMenu.row, contextMenu.col)"
                    x-on:click="contextToggleCircle()"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-zinc-800 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                >
                    <span
                        x-text="hasCircle(contextMenu.row, contextMenu.col) ? '{{ __('Remove circle') }}' : '{{ __('Add circle') }}'"></span>
                </button>

                <div x-show="!isBlock(contextMenu.row, contextMenu.col)">
                    <div class="my-1 border-t border-line"></div>
                    <div class="px-3 py-1 text-xs font-medium text-zinc-500">{{ __('Cell color') }}</div>
                    <div class="flex flex-wrap gap-1 px-3 py-1.5">
                        @php
                            $colors = [
                                '#FECACA', '#FED7AA', '#FEF08A', '#BBF7D0',
                                '#BAE6FD', '#C7D2FE', '#E9D5FF', '#FBCFE8',
                            ];
                        @endphp
                        @foreach ($colors as $color)
                            <button
                                x-on:click.stop="contextSetColor('{{ $color }}')"
                                class="border-line-strong size-5 rounded border transition hover:scale-110"
                                style="background-color: {{ $color }}"
                                title="{{ $color }}"
                            ></button>
                        @endforeach
                        <button
                            x-on:click.stop="contextClearColor()"
                            x-show="getCellColor(contextMenu.row, contextMenu.col)"
                            class="bg-elevated border-line-strong flex size-5 items-center justify-center rounded border text-xs text-zinc-500 transition hover:scale-110"
                            title="{{ __('Remove color') }}"
                        >&times;</button>
                    </div>
                </div>

                <button
                    x-show="!isBlock(contextMenu.row, contextMenu.col)"
                    x-on:click="contextEditRebus()"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-zinc-800 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                >
                    <span
                        x-text="isPrefilled(contextMenu.row, contextMenu.col) ? '{{ __('Edit pre-filled value...') }}' : '{{ __('Pre-fill cell...') }}'"></span>
                </button>

                <button
                    x-show="!isBlock(contextMenu.row, contextMenu.col)"
                    x-on:click="contextSetCustomNumber()"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-zinc-800 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                >
                    <span
                        x-text="getCustomNumber(contextMenu.row, contextMenu.col) !== null ? '{{ __('Edit custom number...') }}' : '{{ __('Set custom number...') }}'"></span>
                </button>

                <div x-show="!isBlock(contextMenu.row, contextMenu.col)">
                    <div class="my-1 border-t border-line"></div>
                    <div class="px-3 py-1 text-xs font-medium text-zinc-500">{{ __('Bars') }}</div>
                    <template x-for="edge in ['top', 'right', 'bottom', 'left']" :key="'bar-' + edge">
                        <button
                            x-on:click.stop="contextToggleBar(edge)"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-zinc-800 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                        >
                            <svg x-show="hasBar(contextMenu.row, contextMenu.col, edge)"
                                 xmlns="http://www.w3.org/2000/svg" class="size-4 text-zinc-600" viewBox="0 0 20 20"
                                 fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                      clip-rule="evenodd"/>
                            </svg>
                            <span x-show="!hasBar(contextMenu.row, contextMenu.col, edge)" class="size-4"></span>
                            <span x-text="edge.charAt(0).toUpperCase() + edge.slice(1)"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Rebus input overlay --}}
            <div
                x-show="showRebusInput"
                x-cloak
                x-transition
                class="absolute inset-x-0 top-0 z-40 flex items-start justify-center pt-4"
            >
                <div
                    class="bg-elevated border-line w-64 rounded-lg border p-3 shadow-lg"
                    x-on:keydown.escape.stop="cancelRebus()"
                    x-on:keydown.enter.stop="applyRebus()"
                    x-on:click.stop
                >
                    <div class="mb-2 flex items-center gap-2 text-sm font-medium text-zinc-800 dark:text-zinc-300">
                        <span x-text="rebusCells.length > 1 ? '{{ __('Pre-fill') }} ' + rebusCells.length + ' {{ __('cells') }}' : '{{ __('Pre-fill cell') }}'"></span>
                    </div>
                    <p class="mb-3 text-xs text-fg-muted">
                        <span x-text="rebusCells.length > 1 ? '{{ __('Enter a value to apply to all selected cells. This value will be given to solvers as a pre-filled clue.') }}' : '{{ __('Enter a letter, multiple characters (rebus), or a symbol/emoji. This value will be given to solvers as a pre-filled clue.') }}'"></span>
                    </p>
                    <input
                        type="text"
                        x-ref="rebusInput"
                        x-model="rebusInputValue"
                        class="border-line-strong text-fg mb-3 w-full rounded-md border bg-white px-2 py-1.5 text-sm placeholder-zinc-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:bg-zinc-700 dark:placeholder-zinc-500"
                        placeholder="{{ __('e.g. A, THE, ★, 🌟') }}"
                    />
                    <div class="flex items-center justify-between">
                        <button
                            type="button"
                            x-on:click="clearRebus()"
                            class="text-xs text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300"
                        >{{ __('Clear cell') }}</button>
                        <div class="flex gap-2">
                            <flux:button size="xs" x-on:click="cancelRebus()">{{ __('Cancel') }}</flux:button>
                            <flux:button size="xs" variant="primary" x-on:click="applyRebus()">{{ __('Apply') }}</flux:button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Custom number input overlay --}}
            <div
                x-show="showCustomNumberInput"
                x-cloak
                x-transition
                class="absolute inset-x-0 top-0 z-40 flex items-start justify-center pt-4"
            >
                <div
                    class="bg-elevated border-line w-56 rounded-lg border p-3 shadow-lg"
                    x-on:keydown.escape.stop="cancelCustomNumber()"
                    x-on:keydown.enter.stop="applyCustomNumber()"
                    x-on:click.stop
                >
                    <div class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-300">
                        {{ __('Custom number') }}
                    </div>
                    <p class="mb-3 text-xs text-fg-muted">
                        {{ __('Enter a number to display on this cell instead of the auto-generated clue number.') }}
                    </p>
                    <input
                        type="number"
                        x-ref="customNumberInput"
                        x-model="customNumberInputValue"
                        class="border-line-strong w-full rounded border px-2 py-1 text-center text-sm dark:bg-zinc-700 dark:text-zinc-200"
                        min="0"
                    >
                    <div class="mt-3 flex items-center justify-between">
                        <button
                            x-show="customNumberCells.length > 0 && getCustomNumber(customNumberCells[0][0], customNumberCells[0][1]) !== null"
                            type="button"
                            x-on:click="removeCustomNumber()"
                            class="text-xs text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300"
                        >{{ __('Remove') }}</button>
                        <span x-show="customNumberCells.length === 0 || getCustomNumber(customNumberCells[0][0], customNumberCells[0][1]) === null"></span>
                        <div class="flex gap-2">
                            <flux:button size="xs" x-on:click="cancelCustomNumber()">{{ __('Cancel') }}</flux:button>
                            <flux:button size="xs" variant="primary" x-on:click="applyCustomNumber()">{{ __('Apply') }}</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
