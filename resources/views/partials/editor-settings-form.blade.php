{{-- Shared puzzle settings form: the Details / Grid / Theme tabs and their
     panels. Rendered once per surface: the desktop sidebar
     (editor-settings-pane) and the mobile modal (editor-settings-modal), the
     same way the suggestions list is shared by its pane and sheet. Every
     field binds to the editor component, so both copies stay in sync and
     the surface only supplies the header and the Save row.

     The sidebar gets a fourth tab, Suggestions (editor-suggestions-pane),
     which on desktop replaces the pane that used to sit beside the grid.
     The modal does not: below `lg` the bottom sheet serves suggestions.

     $settingsSurface prefixes the tab and panel ids so the two copies never
     collide. The root is a container, so the layout and colour grids pick
     their column count from the width they are given rather than from the
     viewport.

     The active tab is client-side state: every panel is rendered and
     toggled with x-show, so Livewire bindings and validation errors survive
     switching. A tab whose fields failed validation gets a dot so the error
     is not hidden. When the surface gives the form a fixed height the tab
     bar stays put and the panels scroll underneath it. --}}
{{-- On the sidebar, the grid asks for the Suggestions tab whenever a cell is
     selected or a clue is focused (revealSuggestions in crossword-grid.js). --}}
<div
    x-data="{ settingsTab: 'details' }"
    @if ($settingsSurface === 'pane') x-on:editor-reveal-suggestions.window="settingsTab = 'suggestions'" @endif
    class="@container flex min-h-0 flex-1 flex-col"
    data-test="settings-form"
>
    @php
        $settingsTabs = [
            'details' => __('Details'),
            'grid' => __('Grid'),
            'theme' => __('Theme'),
        ];
        if ($settingsSurface === 'pane') {
            $settingsTabs['suggestions'] = __('Ideas');
        }
        $settingsTabErrors = [
            'details' => $errors->hasAny(['title', 'author', 'copyright', 'notes', 'allowEmbed', 'tagIds']),
            'grid' => $errors->hasAny(['minAnswerLength', 'layout', 'cellColor', 'blockColor', 'circleColor', 'letterColor', 'lineColor', 'resizeWidth', 'resizeHeight']),
            'theme' => $errors->hasAny(['secretTheme', 'metaAnswerPrompt', 'metaAnswers', 'metaAnswers.*', 'metaAnswerReveal']),
            'suggestions' => false,
        ];
    @endphp
    {{-- In the desktop sidebar the tabs are a full-bleed segmented row: each
         takes an equal share of the width, hairlines separate them and the
         active one is filled, like the toolbar's Edit / Solve toggle, so they
         read as buttons. In the wider flyout they are underline tabs packed
         to the start. Picking Suggestions also starts its lookups, which only
         run while that panel is visible. --}}
    @php
        $isPane = $settingsSurface === 'pane';
        $tabBarClasses = $isPane
            ? 'border-line -mx-4 flex shrink-0 divide-x divide-line border-b'
            : 'border-line flex shrink-0 border-b';
        $tabClasses = $isPane
            ? 'flex min-w-0 flex-1 items-center justify-center gap-1 px-1 py-2.5 text-xs font-semibold transition-colors'
            : 'text-fg -mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium';
        $tabActiveClasses = $isPane
            ? 'bg-zinc-800 text-white dark:bg-zinc-200 dark:text-zinc-900'
            : 'border-zinc-800 dark:border-zinc-200';
        $tabInactiveClasses = $isPane
            ? 'text-fg-muted hover:bg-zinc-100 hover:text-fg dark:hover:bg-zinc-700/50'
            : 'border-transparent text-zinc-600 dark:text-zinc-400';
    @endphp
    <div class="{{ $tabBarClasses }}" role="tablist" aria-label="{{ __('Settings sections') }}" data-test="settings-tabs">
        @foreach ($settingsTabs as $tabKey => $tabLabel)
            <button
                type="button"
                role="tab"
                id="{{ $settingsSurface }}-settings-tab-{{ $tabKey }}"
                aria-controls="{{ $settingsSurface }}-settings-panel-{{ $tabKey }}"
                data-test="settings-tab-{{ $tabKey }}"
                x-on:click="settingsTab = '{{ $tabKey }}'{{ $tabKey === 'suggestions' ? '; showSuggestionsPane()' : '' }}"
                :aria-selected="settingsTab === '{{ $tabKey }}'"
                :tabindex="settingsTab === '{{ $tabKey }}' ? 0 : -1"
                :class="settingsTab === '{{ $tabKey }}' ? '{{ $tabActiveClasses }}' : '{{ $tabInactiveClasses }}'"
                class="{{ $tabClasses }}"
            >
                {{ $tabLabel }}
                @if ($settingsTabErrors[$tabKey])
                    <span class="size-1.5 rounded-full bg-red-500" data-test="settings-tab-error-{{ $tabKey }}">
                        <span class="sr-only">{{ __('Has errors') }}</span>
                    </span>
                @endif
            </button>
        @endforeach
    </div>


    {{-- Panels. A flex column so the Suggestions panel can fill the height
         and scroll its own list; the other panels scroll the container.
         A scrolling box also clips sideways, which would cut the focus ring
         off full-width inputs, so it bleeds 1.5 units past the content on
         each side and pads the same amount back. --}}
    <div class="-mx-1.5 flex min-h-0 flex-1 flex-col overflow-y-auto px-1.5 pt-5 pb-6" data-test="settings-panels">
        {{-- Details: what solvers see on the puzzle page --}}
        <div
            id="{{ $settingsSurface }}-settings-panel-details"
            role="tabpanel"
            aria-labelledby="{{ $settingsSurface }}-settings-tab-details"
            x-show="settingsTab === 'details'"
            class="space-y-6"
            data-test="settings-panel-details"
        >
            <flux:field>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:input wire:model="title" placeholder="{{ __('Puzzle title') }}"/>
                <flux:error name="title"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Constructor') }}</flux:label>
                <flux:input wire:model="author" placeholder="{{ __('Constructor name') }}"/>
                <flux:error name="author"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Copyright') }}</flux:label>
                <flux:input wire:model="copyright" placeholder="{{ copyright(__('Your Name')) }}"/>
                <flux:error name="copyright"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Notes') }}</flux:label>
                <flux:textarea wire:model="notes" placeholder="{{ __('Notes for solvers (shown before solving)') }}"
                               rows="3"/>
                <flux:error name="notes"/>
            </flux:field>

            <flux:field variant="inline">
                <flux:switch wire:model.live="allowEmbed"/>
                <flux:label>
                    <span>{{ __('Embedding Allowed') }} <x-info-tooltip>{{ __('When off, solvers can no longer copy the embed code. You can still embed your own puzzle from this page.') }}</x-info-tooltip></span>
                </flux:label>
                <flux:error name="allowEmbed"/>
            </flux:field>

            <flux:field>
                <flux:label>
                    <span>{{ __('Tags') }} <x-info-tooltip>{{ __('Categorize your puzzle so solvers can find it more easily.') }}</x-info-tooltip></span>
                </flux:label>

                @if(count($this->selectedTags))
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach($this->selectedTags as $tag)
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                {{ $tag['name'] }}
                                <button type="button" wire:click="removeTag({{ $tag['id'] }})" class="ml-0.5 inline-flex items-center rounded-full p-0.5 text-blue-600 hover:bg-blue-200 hover:text-blue-800 dark:text-blue-400 dark:hover:bg-blue-800 dark:hover:text-blue-200">
                                    <svg class="size-3" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                                </button>
                            </span>
                        @endforeach
                    </div>
                @endif

                <div class="relative mt-2" x-data="{ open: false }" x-on:click.outside="open = false">
                    <flux:input
                        wire:model.live.debounce.300ms="tagSearch"
                        placeholder="{{ __('Search or create tags...') }}"
                        size="sm"
                        x-on:focus="open = true"
                        x-on:input="open = true"
                    />
                    <div
                        x-show="open"
                        x-cloak
                        class="bg-elevated border-line absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border shadow-lg"
                    >
                        @php
                            $available = collect($this->availableTags)->reject(fn ($t) => in_array($t['id'], $this->tagIds));
                            $suggestions = $this->suggestedStandardTags;
                        @endphp

                        @foreach($available as $tag)
                            <button
                                type="button"
                                wire:click="addTag({{ $tag['id'] }})"
                                x-on:click="open = false"
                                class="flex w-full items-center px-3 py-2 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            >
                                {{ $tag['name'] }}
                            </button>
                        @endforeach

                        @if(count($suggestions))
                            <div class="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-fg-subtle">
                                {{ __('Suggested') }}
                            </div>
                            @foreach($suggestions as $name)
                                <button
                                    type="button"
                                    wire:click="addStandardTag(@js($name))"
                                    x-on:click="open = false"
                                    class="flex w-full items-center px-3 py-2 text-left text-sm text-zinc-800 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-700"
                                >
                                    {{ $name }}
                                </button>
                            @endforeach
                        @endif

                        @if($available->isEmpty() && count($suggestions) === 0)
                            @if($this->tagSearch !== '')
                                <button
                                    type="button"
                                    wire:click="createTag"
                                    x-on:click="open = false"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-blue-600 hover:bg-zinc-100 dark:text-blue-400 dark:hover:bg-zinc-700"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                                    {{ __('Create ":name"', ['name' => $this->tagSearch]) }}
                                </button>
                            @else
                                <div class="px-3 py-2 text-sm text-zinc-500">{{ __('No tags found') }}</div>
                            @endif
                        @endif
                    </div>
                </div>
            </flux:field>
        </div>

        {{-- Grid: how the grid behaves and looks --}}
        <div
            id="{{ $settingsSurface }}-settings-panel-grid"
            role="tabpanel"
            aria-labelledby="{{ $settingsSurface }}-settings-tab-grid"
            x-show="settingsTab === 'grid'"
            class="space-y-6"
            data-test="settings-panel-grid"
        >
            <flux:field>
                <flux:label>
                    <span>{{ __('Minimum Answer Length') }} <x-info-tooltip>{{ __('Shortest allowed word length in the grid.') }}</x-info-tooltip></span>
                </flux:label>
                <flux:input type="number" wire:model="minAnswerLength" min="1" max="15"/>
                <flux:error name="minAnswerLength"/>
            </flux:field>

            {{-- Symmetry is client-side editor state, not puzzle metadata, so
                 it binds straight to the Alpine flag rather than a Livewire
                 property. The switch element exposes `checked` and fires
                 `change` like a checkbox. --}}
            <flux:field variant="inline" x-show="puzzleType.allowSymmetryToggle" data-test="symmetry-toggle">
                <flux:switch
                    x-effect="$el.checked = symmetry"
                    x-on:change="symmetry = $event.target.checked"
                />
                <flux:label>
                    <span>{{ __('Rotational symmetry') }} <x-info-tooltip>{{ __('Placing or removing a block does the same to its mirror cell across the centre of the grid.') }}</x-info-tooltip></span>
                </flux:label>
            </flux:field>

            <flux:field>
                <flux:label>
                    <span>{{ __('Layout') }} <x-info-tooltip>{{ __('How the grid and clues are arranged on screen.') }}</x-info-tooltip></span>
                </flux:label>
                @php $selectedLayout = $this->layout ?? \App\Enums\CrosswordLayout::auto($this->width); @endphp
                <div class="mt-2 grid grid-cols-3 gap-2 @sm:grid-cols-4 @lg:grid-cols-5">
                    @foreach (\App\Enums\CrosswordLayout::ordered() as $case)
                        <button
                            type="button"
                            wire:click="$set('layout', {{ $case->value }})"
                            @class([
                                'group flex flex-col items-center gap-1.5 rounded-lg border p-2 text-left transition-colors',
                                'border-blue-500 bg-blue-50 ring-1 ring-blue-500 dark:border-blue-400 dark:bg-blue-950/40 dark:ring-blue-400' => $selectedLayout === $case,
                                'border-zinc-300 hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-500 dark:hover:bg-zinc-800' => $selectedLayout !== $case,
                            ])
                            aria-pressed="{{ $selectedLayout === $case ? 'true' : 'false' }}"
                            title="{{ $case->label() }}"
                        >
                            <div class="w-full overflow-hidden rounded">
                                @include('partials.layout-icon', ['case' => $case])
                            </div>
                            <span class="line-clamp-2 text-center text-[10px] leading-tight text-zinc-700 dark:text-zinc-400">{{ $case->label() }}</span>
                        </button>
                    @endforeach
                </div>
                <flux:error name="layout"/>
            </flux:field>

            <flux:field>
                <flux:label>
                    <span>{{ __('Default Colors') }} <x-info-tooltip>{{ __('Default colors for the whole grid. Colors set on individual cells via right-click take precedence.') }}</x-info-tooltip></span>
                </flux:label>
                <div class="mt-2 grid grid-cols-1 gap-2 @sm:grid-cols-2">
                    @foreach ([
                        'cellColor' => __('Cells'),
                        'blockColor' => __('Blocks'),
                        'circleColor' => __('Circles'),
                        'letterColor' => __('Letters'),
                        'lineColor' => __('Lines'),
                    ] as $colorProp => $colorLabel)
                        @php $colorValue = $this->{$colorProp}; @endphp
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-line px-3 py-2">
                            <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $colorLabel }}</span>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs text-fg-muted">{{ $colorValue ?? __('Default') }}</span>
                                <input
                                    type="color"
                                    wire:model.live="{{ $colorProp }}"
                                    value="{{ $colorValue ?? '#000000' }}"
                                    class="size-7 cursor-pointer rounded border border-line bg-transparent p-0.5"
                                    aria-label="{{ $colorLabel }}"
                                />
                                @if ($colorValue !== null)
                                    <button
                                        type="button"
                                        wire:click="$set('{{ $colorProp }}', null)"
                                        class="flex size-6 items-center justify-center rounded text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                                        title="{{ __('Reset to default') }}"
                                    >&times;</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <flux:error name="cellColor"/>
                <flux:error name="blockColor"/>
                <flux:error name="circleColor"/>
                <flux:error name="letterColor"/>
                <flux:error name="lineColor"/>
            </flux:field>

            <flux:separator/>

            <div>
                <flux:heading size="sm">
                    {{ __('Grid Size') }} <x-info-tooltip>{{ __('Existing content will be preserved where dimensions overlap. Clues will be reset.') }}</x-info-tooltip>
                </flux:heading>

                <div class="mt-3 grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Width') }}</flux:label>
                        <flux:input type="number" wire:model="resizeWidth" min="3" max="40"/>
                        <flux:error name="resizeWidth"/>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Height') }}</flux:label>
                        <flux:input type="number" wire:model="resizeHeight" min="3" max="40"/>
                        <flux:error name="resizeHeight"/>
                    </flux:field>
                </div>

                <div class="mt-3 flex justify-end">
                    <flux:button variant="danger" size="sm"
                                 wire:click="resizeGrid">{{ __('Resize Grid') }}</flux:button>
                </div>
            </div>
        </div>

        {{-- Theme: AI hints and the post-solve meta answer --}}
        <div
            id="{{ $settingsSurface }}-settings-panel-theme"
            role="tabpanel"
            aria-labelledby="{{ $settingsSurface }}-settings-tab-theme"
            x-show="settingsTab === 'theme'"
            class="space-y-6"
            data-test="settings-panel-theme"
        >
            {{-- Meta Answer Section --}}
            <div class="space-y-4">
                <flux:heading size="sm">
                    {{ __('Meta Answer') }} <x-info-tooltip>{{ __('Add a meta answer prompt that solvers see after completing the puzzle. Great for themed puzzles with a hidden message.') }}</x-info-tooltip>
                </flux:heading>

                <flux:field>
                    <flux:label>{{ __('Prompt') }}</flux:label>
                    <flux:input wire:model="metaAnswerPrompt"
                                placeholder="{{ __('e.g. What is the hidden theme? or Unscramble the circled letters') }}"
                                maxlength="500" />
                    <flux:error name="metaAnswerPrompt"/>
                </flux:field>

                <flux:field>
                    <flux:label>
                        <span>{{ __('Accepted Answers') }} <x-info-tooltip>{{ __('Add one or more correct answers. Matching is case-insensitive.') }}</x-info-tooltip></span>
                    </flux:label>

                    @if(count($metaAnswers) > 0)
                        <div class="mt-2 space-y-2">
                            @foreach($metaAnswers as $index => $answer)
                                <div class="flex items-center gap-2">
                                    <flux:badge>{{ $answer }}</flux:badge>
                                    <flux:button size="xs" variant="ghost" wire:click="removeMetaAnswer({{ $index }})">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                                    </flux:button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(count($metaAnswers) < 10)
                        <div class="mt-2 flex items-center gap-2">
                            <flux:input wire:model="newMetaAnswer"
                                        wire:keydown.enter.prevent="addMetaAnswer"
                                        placeholder="{{ __('Type an answer and press Enter') }}"
                                        size="sm" />
                            <flux:button size="sm" wire:click="addMetaAnswer">{{ __('Add') }}</flux:button>
                        </div>
                    @endif

                    <flux:error name="metaAnswers"/>
                </flux:field>

                <flux:field variant="inline">
                    <flux:checkbox wire:model="metaAnswerReveal" />
                    <flux:label>
                        <span>{{ __('Show solvers whether their answer is correct') }} <x-info-tooltip>{{ __('When enabled, solvers get instant feedback after submitting their answer.') }}</x-info-tooltip></span>
                    </flux:label>
                </flux:field>
            </div>

            <flux:separator/>

            <flux:field>
                <flux:label>
                    <span>{{ __('AI Theme Hint') }} <x-info-tooltip>{{ __('Only used by AI Autofill to pick the best fill (e.g. 80s movies, things that fly). Never shown to solvers.') }}</x-info-tooltip></span>
                </flux:label>
                <flux:textarea wire:model="secretTheme"
                               placeholder="{{ __('Theme hint used to guide AI Autofill.') }}"
                               rows="2"/>
                <flux:error name="secretTheme"/>
            </flux:field>

        </div>

        {{-- Suggestions: desktop sidebar only --}}
        @if ($settingsSurface === 'pane')
            @include('partials.editor-suggestions-pane')

            {{-- Export and Save sit right under whichever settings panel is
                 showing, so they are always in reach without scrolling past
                 the whole form. The Ideas tab has nothing to save or export,
                 so the row hides there. The flyout has its own sticky Save /
                 Cancel footer. --}}
            <div
                x-show="settingsTab !== 'suggestions'"
                class="border-line mt-6 flex shrink-0 items-center justify-between gap-2 border-t pt-4"
                data-test="settings-pane-actions"
            >
                {{-- Export --}}
                <flux:dropdown position="top" align="start">
                    <flux:button variant="ghost" size="sm" icon="arrow-down-tray">
                        {{ __('Export') }}
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item wire:click="attemptExport('ipuz')">{{ __('.ipuz') }}</flux:menu.item>
                        <flux:menu.item wire:click="attemptExport('puz')">{{ __('.puz (Across Lite)') }}</flux:menu.item>
                        <flux:menu.item wire:click="attemptExport('jpz')">{{ __('.jpz (Crossword Compiler)') }}</flux:menu.item>
                        <flux:menu.item wire:click="attemptExport('pdf')">{{ __('.pdf (Print-Ready)') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                <flux:button variant="primary" size="sm" wire:click="saveMetadata">{{ __('Save') }}</flux:button>
            </div>
        @endif
    </div>
</div>
