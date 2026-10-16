{{-- Shared puzzle settings form: the Details / Grid / Theme tabs and their
     panels. Rendered once per surface: the desktop sidebar
     (editor-settings-pane) and the mobile modal (editor-settings-modal), the
     same way the suggestions list is shared by its pane and sheet. Every
     field binds to the editor component, so both copies stay in sync and
     the surface only supplies the header and the Save row.

     $settingsSurface prefixes the tab and panel ids so the two copies never
     collide. The root is a container, so the layout and colour grids pick
     their column count from the width they are given rather than from the
     viewport.

     The active tab is client-side state: every panel is rendered and
     toggled with x-show, so Livewire bindings and validation errors survive
     switching. A tab whose fields failed validation gets a dot so the error
     is not hidden. When the surface gives the form a fixed height the tab
     bar stays put and the panels scroll underneath it. --}}
<div x-data="{ settingsTab: 'details' }" class="@container flex min-h-0 flex-1 flex-col" data-test="settings-form">
    @php
        $settingsTabs = [
            'details' => __('Details'),
            'grid' => __('Grid'),
            'theme' => __('Theme'),
        ];
        $settingsTabErrors = [
            'details' => $errors->hasAny(['title', 'author', 'copyright', 'notes', 'allowEmbed', 'tagIds']),
            'grid' => $errors->hasAny(['minAnswerLength', 'layout', 'cellColor', 'blockColor', 'circleColor', 'letterColor', 'lineColor', 'resizeWidth', 'resizeHeight']),
            'theme' => $errors->hasAny(['secretTheme', 'metaAnswerPrompt', 'metaAnswers', 'metaAnswers.*', 'metaAnswerReveal']),
        ];
    @endphp
    <div class="border-line flex shrink-0 border-b" role="tablist" aria-label="{{ __('Settings sections') }}" data-test="settings-tabs">
        @foreach ($settingsTabs as $tabKey => $tabLabel)
            <button
                type="button"
                role="tab"
                id="{{ $settingsSurface }}-settings-tab-{{ $tabKey }}"
                aria-controls="{{ $settingsSurface }}-settings-panel-{{ $tabKey }}"
                data-test="settings-tab-{{ $tabKey }}"
                x-on:click="settingsTab = '{{ $tabKey }}'"
                :aria-selected="settingsTab === '{{ $tabKey }}'"
                :tabindex="settingsTab === '{{ $tabKey }}' ? 0 : -1"
                :class="settingsTab === '{{ $tabKey }}' ? 'border-zinc-800 dark:border-zinc-200' : 'border-transparent text-zinc-600 dark:text-zinc-400'"
                class="text-fg -mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium"
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


    {{-- Panels --}}
    <div class="min-h-0 flex-1 overflow-y-auto pt-5 pb-6">
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
                <flux:label>{{ __('Allow others to embed this puzzle') }}</flux:label>
                <flux:description>{{ __('When off, solvers can no longer copy the embed code. You can still embed your own puzzle from this page.') }}</flux:description>
                <flux:error name="allowEmbed"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Tags') }}</flux:label>
                <flux:description>{{ __('Categorize your puzzle so solvers can find it more easily.') }}</flux:description>

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
                <flux:label>{{ __('Minimum Answer Length') }}</flux:label>
                <flux:input type="number" wire:model="minAnswerLength" min="1" max="15"/>
                <flux:description>{{ __('Shortest allowed word length in the grid.') }}</flux:description>
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
                <flux:label>{{ __('Rotational symmetry') }}</flux:label>
                <flux:description>{{ __('Placing or removing a block does the same to its mirror cell across the centre of the grid.') }}</flux:description>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Layout') }}</flux:label>
                <flux:description>{{ __('How the grid and clues are arranged on screen.') }}</flux:description>
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
                <flux:label>{{ __('Default Colors') }}</flux:label>
                <flux:description>{{ __('Default colors for the whole grid. Colors set on individual cells via right-click take precedence.') }}</flux:description>
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
                <flux:heading size="sm">{{ __('Grid Size') }}</flux:heading>
                <flux:text size="sm"
                           class="mt-1">{{ __('Existing content will be preserved where dimensions overlap. Clues will be reset.') }}</flux:text>

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
            <flux:field>
                <flux:label>{{ __('Secret Theme') }}</flux:label>
                <flux:textarea wire:model="secretTheme"
                               placeholder="{{ __('A theme hint only used to guide AI Autofill (e.g. 80s movies, things that fly). Not shown to solvers.') }}"
                               rows="2"/>
                <flux:description>{{ __('Only used by AI Autofill to pick the best fill. Never shown to solvers.') }}</flux:description>
                <flux:error name="secretTheme"/>
            </flux:field>

            <flux:separator/>

            {{-- Meta Answer Section --}}
            <div class="space-y-4">
                <div>
                    <flux:heading size="sm">{{ __('Meta Answer') }}</flux:heading>
                    <flux:text size="sm" class="mt-1">{{ __('Add a meta answer prompt that solvers see after completing the puzzle. Great for themed puzzles with a hidden message.') }}</flux:text>
                </div>

                <flux:field>
                    <flux:label>{{ __('Prompt') }}</flux:label>
                    <flux:input wire:model="metaAnswerPrompt"
                                placeholder="{{ __('e.g. What is the hidden theme? or Unscramble the circled letters') }}"
                                maxlength="500" />
                    <flux:error name="metaAnswerPrompt"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Accepted Answers') }}</flux:label>
                    <flux:description>{{ __('Add one or more correct answers. Matching is case-insensitive.') }}</flux:description>

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

                <flux:field>
                    <flux:checkbox wire:model="metaAnswerReveal" label="{{ __('Show solvers whether their answer is correct') }}" />
                    <flux:description>{{ __('When enabled, solvers get instant feedback after submitting their answer.') }}</flux:description>
                </flux:field>
            </div>
        </div>
    </div>
</div>
