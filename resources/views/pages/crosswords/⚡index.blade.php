<?php

use CrosswordBuilder\CrosswordIO\Exceptions\IpuzImportException;
use CrosswordBuilder\CrosswordIO\Exceptions\JpzImportException;
use CrosswordBuilder\CrosswordIO\Exceptions\PdfImportException;
use CrosswordBuilder\CrosswordIO\Exceptions\PuzImportException;
use App\Enums\PuzzleType;
use App\Models\Crossword;
use App\Livewire\Concerns\ExportsCrossword;
use App\Services\GridTemplateProvider;
use CrosswordBuilder\CrosswordIO\GridNumberer;
use CrosswordBuilder\CrosswordIO\ImportDetector;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Build')] class extends Component {
    use ExportsCrossword;
    use WithFileUploads;

    public bool $showNewModal = false;
    public bool $showImportModal = false;
    public string $puzzleType = 'standard';
    public int $newWidth = 15;
    public int $newHeight = 15;
    public ?int $selectedTemplate = null;
    public $importFile;
    public string $importError = '';
    public string $newPuzzleLimitMessage = '';

    /** The puzzle a PDF export was requested for from its card menu. */
    public ?int $pdfExportPuzzleId = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $sortBy = 'newest';

    #[Computed]
    public function crosswords()
    {
        $query = Auth::user()->crosswords();

        if ($this->search !== '') {
            $term = $this->search;
            $query->where(function ($q) use ($term) {
                $q->whereLike('title', "%{$term}%")
                    ->orWhereLike('author', "%{$term}%");
            });
        }

        if ($this->status === 'published') {
            $query->where('is_published', true);
        } elseif ($this->status === 'draft') {
            $query->where('is_published', false);
        }

        match ($this->sortBy) {
            'oldest' => $query->oldest(),
            'alpha' => $query->orderBy('title'),
            'largest' => $query->orderByRaw('width * height DESC'),
            'smallest' => $query->orderByRaw('width * height ASC'),
            default => $query->latest(),
        };

        return $query->get();
    }

    #[Computed]
    public function publishedCount(): int
    {
        return Auth::user()->crosswords()->where('is_published', true)->count();
    }

    #[Computed]
    public function draftCount(): int
    {
        return Auth::user()->crosswords()->where('is_published', false)->count();
    }

    /**
     * Brand-new account with no activity yet — render a friendlier first-run
     * hero so they don't bounce off a wall of zero-state cards.
     */
    #[Computed]
    public function isNewUser(): bool
    {
        return $this->publishedCount === 0
            && $this->draftCount === 0
            && ! Auth::user()->puzzleAttempts()->exists();
    }

    #[Computed]
    public function selectedPuzzleType(): PuzzleType
    {
        return PuzzleType::tryFrom($this->puzzleType) ?? PuzzleType::Standard;
    }

    #[Computed]
    public function templates(): array
    {
        if ($this->selectedPuzzleType !== PuzzleType::Standard) {
            return [];
        }

        return app(GridTemplateProvider::class)->getTemplates($this->newWidth ?? 0, $this->newHeight ?? 0);
    }

    #[Computed]
    public function freestylePreview(): ?Crossword
    {
        if ($this->selectedPuzzleType !== PuzzleType::Freestyle) {
            return null;
        }

        return Crossword::query()
            ->where('is_published', true)
            ->where('puzzle_type', PuzzleType::Freestyle)
            ->where('width', $this->newWidth)
            ->where('height', $this->newHeight)
            ->safeFor(Auth::user())
            ->inRandomOrder()
            ->first();
    }

    public function updatedShowNewModal(): void
    {
        $this->newPuzzleLimitMessage = '';
    }

    public function updatedPuzzleType(): void
    {
        $type = $this->selectedPuzzleType;

        if ($type === PuzzleType::Diamond) {
            if ($this->newWidth % 2 === 0) {
                $this->newWidth = $this->newWidth + 1;
            }
            $this->newHeight = $this->newWidth;
        }

        $this->selectedTemplate = null;
        unset($this->templates, $this->freestylePreview, $this->selectedPuzzleType);
    }

    public function updatedNewWidth(): void
    {
        $type = $this->selectedPuzzleType;

        if ($type->requiresSquare()) {
            $this->newHeight = $this->newWidth;
        }

        $this->selectedTemplate = null;
        unset($this->templates, $this->freestylePreview, $this->selectedPuzzleType);
    }

    public function updatedNewHeight(): void
    {
        $type = $this->selectedPuzzleType;

        if ($type->requiresSquare()) {
            $this->newWidth = $this->newHeight;
        }

        $this->selectedTemplate = null;
        unset($this->templates, $this->freestylePreview, $this->selectedPuzzleType);
    }

    public function createPuzzle(): void
    {
        $this->newPuzzleLimitMessage = '';

        $type = $this->selectedPuzzleType;

        $rules = [
            'newWidth' => ['required', 'integer', 'min:3', 'max:40'],
            'newHeight' => ['required', 'integer', 'min:3', 'max:40'],
        ];

        $this->validate($rules);

        if ($type->requiresSquare() && $this->newWidth !== $this->newHeight) {
            $this->addError('newHeight', __(':type puzzles must be square.', ['type' => $type->label()]));

            return;
        }

        if ($type->requiresOdd() && $this->newWidth % 2 === 0) {
            $this->addError('newWidth', __(':type puzzles require an odd grid size.', ['type' => $type->label()]));

            return;
        }

        $user = Auth::user();
        $limits = $user->planLimits();

        if ($user->crosswords()->count() >= $limits->maxPuzzles()) {
            $this->newPuzzleLimitMessage = $limits->isAnonymous()
                ? __('Create a free account to build more puzzles.')
                : __('You have reached your puzzle limit.');

            return;
        }

        if ($this->selectedTemplate !== null && isset($this->templates[$this->selectedTemplate])) {
            $grid = $this->templates[$this->selectedTemplate]['grid'];
            $styles = $this->templates[$this->selectedTemplate]['styles'] ?? null;
        } else {
            $grid = $type->generateGrid($this->newWidth, $this->newHeight);
            $styles = null;
        }

        $result = app(GridNumberer::class)->number($grid, $this->newWidth, $this->newHeight, $styles ?? []);

        $solution = Crossword::emptySolution($this->newWidth, $this->newHeight);
        foreach ($result['grid'] as $r => $row) {
            foreach ($row as $c => $cell) {
                if ($cell === null) {
                    $solution[$r][$c] = null;
                } elseif ($cell === '#') {
                    $solution[$r][$c] = '#';
                }
            }
        }

        $crossword = Auth::user()->crosswords()->create([
            'title' => null,
            'author' => Auth::user()->name,
            'copyright' => copyright(Auth::user()->copyright_name ?? Auth::user()->name ?? ''),
            'width' => $this->newWidth,
            'height' => $this->newHeight,
            'puzzle_type' => $type,
            'grid' => $result['grid'],
            'solution' => $solution,
            'styles' => $styles,
            'clues_across' => array_map(fn ($s) => ['number' => $s['number'], 'clue' => ''], $result['across']),
            'clues_down' => array_map(fn ($s) => ['number' => $s['number'], 'clue' => ''], $result['down']),
            'metadata' => ['puzzle_type' => $type->value],
        ]);

        $this->redirect(route('crosswords.editor', $crossword), navigate: true);
    }

    public function importPuzzle(): void
    {
        $this->validate([
            'importFile' => ['required', 'file', 'max:2048'],
        ]);

        $this->importError = '';

        try {
            $contents = file_get_contents($this->importFile->getRealPath());
            $extension = $this->importFile->getClientOriginalExtension();
            $data = app(ImportDetector::class)->import($contents, $extension);

            $crossword = Auth::user()->crosswords()->create($data);

            $this->redirect(route('crosswords.editor', $crossword), navigate: true);
        } catch (IpuzImportException|PuzImportException|JpzImportException|PdfImportException $e) {
            $this->importError = $e->getMessage();
        }
    }

    public function duplicatePuzzle(int $id): void
    {
        $crossword = Crossword::findOrFail($id);
        $this->authorize('view', $crossword);

        $user = Auth::user();
        $limits = $user->planLimits();

        if ($user->crosswords()->count() >= $limits->maxPuzzles()) {
            Flux::toast(
                text: $limits->isAnonymous()
                    ? __('Create a free account to build more puzzles.')
                    : __('You have reached your puzzle limit.'),
                variant: 'danger',
            );

            return;
        }

        $duplicate = $user->crosswords()->create([
            'title' => __('Copy of :title', ['title' => $crossword->displayTitle()]),
            'author' => $user->name,
            'copyright' => copyright($user->copyright_name ?? $user->name ?? ''),
            'notes' => $crossword->notes,
            'secret_theme' => $crossword->secret_theme,
            'layout' => $crossword->layout,
            'puzzle_type' => $crossword->puzzle_type,
            'freestyle_locked' => false,
            'width' => $crossword->width,
            'height' => $crossword->height,
            'kind' => $crossword->kind,
            'grid' => $crossword->grid,
            'solution' => $crossword->solution,
            'prefilled' => $crossword->prefilled,
            'clues_across' => $crossword->clues_across,
            'clues_down' => $crossword->clues_down,
            'styles' => $crossword->styles,
            'metadata' => $crossword->metadata,
            'is_published' => false,
        ]);

        $this->redirect(route('crosswords.editor', $duplicate), navigate: true);
    }

    public function deletePuzzle(int $id): void
    {
        $crossword = Crossword::findOrFail($id);
        $this->authorize('delete', $crossword);
        $crossword->delete();
    }

    /**
     * Open the PDF export settings for one puzzle, chosen from its card menu.
     */
    public function choosePdfExportFor(int $id): void
    {
        $this->pdfExportPuzzleId = $id;
        $this->attemptExport('pdf');
    }

    #[Computed]
    public function pdfExportCrossword(): ?Crossword
    {
        return $this->pdfExportPuzzleId === null ? null : Crossword::find($this->pdfExportPuzzleId);
    }

    protected function getExportableCrossword(): Crossword
    {
        $crossword = Crossword::findOrFail($this->pdfExportPuzzleId);
        $this->authorize('view', $crossword);

        return $crossword;
    }

    protected function getPdfIncludeSolution(): bool
    {
        return true;
    }

    protected function getExportPlanGates(): array
    {
        return [
            'puz' => null,
            'jpz' => null,
            'pdf' => 'canExportPdf',
        ];
    }

    protected function onExportUpgradeRequired(string $format): void
    {
        $this->pdfExportPuzzleId = null;

        Flux::toast(
            text: __('Create a free account to export PDFs.'),
            variant: 'warning',
        );
    }
}
?>

<div class="space-y-6" data-full-bleed>
        <x-page-header :kicker="__('Your workshop')" :title="__('Build puzzles')" :bleed="false">
            <x-header-button icon="plus" wire:click="$set('showNewModal', true)">
                {{ __('New Puzzle') }}
            </x-header-button>
            <x-header-button variant="secondary" icon="arrow-up-tray" wire:click="$set('showImportModal', true)">
                {{ __('Import Puzzle') }}
            </x-header-button>

            @if($this->isNewUser)
                {{-- First-run welcome — inside the header, above its rule, so the stats band below stays attached. --}}
                <x-slot:body>
                <div class="relative overflow-hidden rounded-xl border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 p-6 dark:border-amber-800/50 dark:from-amber-950/30 dark:to-orange-950/20" data-test="dashboard-welcome-hero">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="max-w-xl">
                            <flux:heading size="lg" class="!text-amber-700 dark:!text-amber-300">
                                {{ __('Welcome to :app, :name!', ['app' => config('app.name'), 'name' => auth()->user()->name]) }}
                            </flux:heading>
                            <flux:text class="mt-2">
                                {{ __('You\'re all set up. Two good ways to get started:') }}
                            </flux:text>
                            <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                                <li class="flex items-start gap-2">
                                    <flux:icon name="play" class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                    <span>{{ __('Try a solve — pick any puzzle from the community to see how the editor and solver feel.') }}</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <flux:icon name="pencil-square" class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                    <span>{{ __('Build your first puzzle — the editor handles symmetry, numbering, and exports for you.') }}</span>
                                </li>
                            </ul>
                        </div>
                        <div class="flex flex-shrink-0 flex-col gap-2 sm:items-end">
                            <flux:button variant="primary" icon="plus" wire:click="$set('showNewModal', true)">
                                {{ __('Build a puzzle') }}
                            </flux:button>
                            <flux:button variant="ghost" icon="play" :href="route('crosswords.solving')" wire:navigate>
                                {{ __('Browse puzzles to solve') }}
                            </flux:button>
                            <a href="{{ route('help.index') }}" wire:navigate class="mt-1 text-xs text-zinc-600 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200">
                                {{ __('Read the Help Center →') }}
                            </a>
                        </div>
                    </div>
                </div>

                </x-slot:body>
            @endif

            <x-slot:footer>
                {{-- Builder Stats — flush under the header rule --}}
                <livewire:constructor-stats key="constructor-stats" />
            </x-slot:footer>
        </x-page-header>

        {{-- First-run welcome — only visible to brand-new accounts with zero activity. --}}

        {{-- Search & Filters --}}
        <div class="flex flex-col gap-3 px-6 py-5 sm:flex-row sm:items-center lg:px-8">
            <label class="relative flex-1">
                <span class="sr-only">{{ __('Search puzzles...') }}</span>
                <flux:icon name="magnifying-glass" class="text-ink-faint pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <input
                    type="search"
                    placeholder="{{ __('Search puzzles...') }}"
                    wire:model.live.debounce.300ms="search"
                    class="field-classical w-full pr-3 pl-9"
                />
            </label>
            <div class="flex items-center gap-3">
                <div class="border-border-strong divide-hairline inline-flex h-10 divide-x overflow-hidden rounded-sm border" role="radiogroup" aria-label="{{ __('Status') }}">
                    @foreach (['' => __('All'), 'published' => __('Published'), 'draft' => __('Drafts')] as $value => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="status" value="{{ $value }}" wire:model.live="status" class="peer sr-only" />
                            <span class="font-classical text-ink-muted hover:text-ink peer-checked:bg-amber-400/10 peer-checked:text-amber-400 peer-focus-visible:outline-2 peer-focus-visible:-outline-offset-2 peer-focus-visible:outline-amber-400 flex h-full items-center px-3.5 text-[15px] font-medium transition-colors">
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <label class="relative">
                    <span class="sr-only">{{ __('Sort') }}</span>
                    <select wire:model.live="sortBy" class="field-classical font-classical appearance-none pr-9 pl-3.5 text-[15px] font-medium">
                        <option value="newest">{{ __('Sort') }}: {{ __('Newest') }}</option>
                        <option value="oldest">{{ __('Sort') }}: {{ __('Oldest') }}</option>
                        <option value="alpha">{{ __('Sort') }}: {{ __('A–Z') }}</option>
                        <option value="largest">{{ __('Sort') }}: {{ __('Largest') }}</option>
                        <option value="smallest">{{ __('Sort') }}: {{ __('Smallest') }}</option>
                    </select>
                    <flux:icon name="chevron-down" class="text-ink-faint pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                </label>
            </div>
        </div>

        @if($this->crosswords->isEmpty())
            <div class="border-border-strong mx-6 flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-16 text-center lg:mx-8">
                @if($search !== '' || $status !== '')
                    <h3 class="font-classical text-ink text-[26px] leading-tight font-medium">{{ __('No matching puzzles') }}</h3>
                    <p class="text-ink-muted mt-2 text-sm">{{ __('Try adjusting your search or filters.') }}</p>
                @else
                    <h3 class="font-classical text-ink text-[26px] leading-tight font-medium">{{ __('No puzzles yet') }}</h3>
                    <p class="text-ink-muted mt-2 mb-6 text-sm">{{ __('Create a new crossword or import an existing puzzle file.') }}</p>
                    <div class="flex flex-wrap justify-center gap-3">
                        <button type="button" class="btn-classical btn-amber-outline" wire:click="$set('showNewModal', true)">
                            <flux:icon name="plus" class="size-4" />
                            {{ __('New Puzzle') }}
                        </button>
                        <button type="button" class="btn-classical btn-classical-muted" wire:click="$set('showImportModal', true)">
                            <flux:icon name="arrow-up-tray" class="size-4" />
                            {{ __('Import Puzzle') }}
                        </button>
                    </div>
                @endif
            </div>
        @else
            {{-- Results collapse to their first row until expanded. Alpine reads the
                 grid's resolved column count so the row is exact at any width, and
                 re-applies after Livewire re-renders the cards. --}}
            <div
                x-data="{
                    expanded: false,
                    columns: 0,
                    total: 0,
                    get hasMore() { return this.total > this.columns },
                    get shown() { return this.expanded ? this.total : Math.min(this.columns, this.total) },
                    measure() {
                        this.columns = getComputedStyle(this.$refs.grid).gridTemplateColumns.split(' ').length;
                        this.apply();
                    },
                    apply() {
                        const cards = Array.from(this.$refs.grid.children);
                        this.total = cards.length;
                        cards.forEach((card, index) => {
                            const hide = ! this.expanded && index >= this.columns;
                            if (card.hidden !== hide) { card.hidden = hide; }
                        });
                    },
                }"
                x-init="
                    measure();
                    new ResizeObserver(() => measure()).observe($refs.grid);
                    new MutationObserver(() => apply()).observe($refs.grid, { childList: true, attributes: true, attributeFilter: ['hidden'] });
                "
                x-effect="expanded; apply()"
                data-test="puzzle-results"
            >
                <div x-ref="grid" class="grid gap-[22px] px-6 [grid-template-columns:repeat(auto-fill,minmax(268px,1fr))] lg:px-8">
                @foreach($this->crosswords as $crossword)
                    <article
                        wire:key="crossword-{{ $crossword->id }}"
                        class="border-border hover:border-border-strong flex flex-col gap-3.5 rounded-sm border p-4.5 transition-colors"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-classical text-ink truncate text-[21px] leading-[1.15] font-semibold">
                                    <a href="{{ route('crosswords.editor', $crossword) }}" wire:navigate class="hover:text-amber-300 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        {{ $crossword->displayTitle() }}
                                    </a>
                                </h3>
                                <x-puzzle-details :crossword="$crossword" />
                            </div>
                            @if($crossword->is_published)
                                <span class="badge-classical border-amber-400 text-amber-400">{{ __('Published') }}</span>
                            @else
                                <span class="badge-classical border-ink-faint text-ink-faint">{{ __('Draft') }}</span>
                            @endif
                        </div>

                        <a href="{{ route('crosswords.editor', $crossword) }}" wire:navigate class="block focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                            <x-grid-thumbnail
                                :grid="$crossword->grid"
                                :width="$crossword->width"
                                :height="$crossword->height"
                                :fluid="true"
                                frame-class="border-hairline bg-hairline rounded-sm border"
                                open-class="bg-panel"
                                block-class="bg-zinc-300"
                            />
                        </a>

                        <x-puzzle-completeness-bar :crossword="$crossword" />

                        <div class="flex gap-[10px] pt-1">
                            <a href="{{ route('crosswords.editor', $crossword) }}" wire:navigate class="btn-classical btn-classical-compact btn-amber-outline flex-1">
                                {{ __('Open editor') }}
                            </a>
                            <flux:dropdown position="bottom" align="end">
                                <button type="button" class="btn-classical btn-classical-compact btn-classical-muted" aria-label="{{ __('More actions') }}">
                                    <flux:icon name="ellipsis-vertical" class="size-4" />
                                </button>
                                <flux:menu>
                                    <flux:menu.item icon="document-duplicate" wire:click="duplicatePuzzle({{ $crossword->id }})">
                                        {{ __('Duplicate') }}
                                    </flux:menu.item>
                                    <flux:menu.item icon="document-arrow-down" wire:click="choosePdfExportFor({{ $crossword->id }})">
                                        {{ __('Export PDF') }}
                                    </flux:menu.item>
                                    <flux:menu.item icon="trash" variant="danger" wire:click="deletePuzzle({{ $crossword->id }})" wire:confirm="{{ __('Are you sure you want to delete this puzzle?') }}">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </article>
                @endforeach
                </div>

                <div x-show="hasMore" x-cloak class="mt-5 flex flex-wrap items-center justify-between gap-3 px-6 lg:px-8">
                    <span class="meta-classical tnum" x-text="expanded ? '{{ __('Showing all :total puzzles') }}'.replace(':total', total) : '{{ __('Showing :shown of :total puzzles') }}'.replace(':shown', shown).replace(':total', total)"></span>
                    <button
                        type="button"
                        class="btn-classical btn-classical-compact btn-classical-muted"
                        @click="expanded = ! expanded"
                        :aria-expanded="expanded"
                        x-text="expanded ? '{{ __('Show fewer') }}' : '{{ __('Show all puzzles') }}'"
                        data-test="toggle-all-puzzles-button"
                    ></button>
                </div>
            </div>
        @endif

        <hr class="border-hairline" />

        {{-- Constructor Analytics --}}
        <div class="px-6 lg:px-8">
            <livewire:constructor-analytics key="constructor-analytics" />
        </div>

        {{-- New Puzzle Modal --}}
    <flux:modal wire:model="showNewModal" class="w-full max-w-lg">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('New Puzzle') }}</flux:heading>

            {{-- Puzzle Type Selector --}}
            <div>
                <flux:label class="mb-2">{{ __('Puzzle Type') }}</flux:label>
                <div class="grid grid-cols-3 gap-3">
                    @foreach (PuzzleType::cases() as $type)
                        <button
                            type="button"
                            wire:click="$set('puzzleType', @js($type->value))"
                            @class([
                                'flex flex-col items-center gap-2 rounded-lg border p-3 text-center transition-colors',
                                'border-blue-500 bg-blue-50 ring-1 ring-blue-500 dark:border-blue-400 dark:bg-blue-950/40 dark:ring-blue-400' => $puzzleType === $type->value,
                                'border-zinc-200 hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-500 dark:hover:bg-zinc-800' => $puzzleType !== $type->value,
                            ])
                        >
                            <flux:icon :name="$type->icon()" @class([
                                'size-6',
                                'text-blue-600 dark:text-blue-400' => $puzzleType === $type->value,
                                'text-zinc-400' => $puzzleType !== $type->value,
                            ]) />
                            <span @class([
                                'text-sm font-medium',
                                'text-blue-700 dark:text-blue-300' => $puzzleType === $type->value,
                                'text-zinc-700 dark:text-zinc-300' => $puzzleType !== $type->value,
                            ])>{{ __($type->label()) }}</span>
                            <span class="text-[11px] leading-tight text-zinc-400">{{ __($type->description()) }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Grid Dimensions --}}
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ $this->selectedPuzzleType->requiresSquare() ? __('Size') : __('Width') }}</flux:label>
                    <flux:input
                        type="number"
                        wire:model.live.debounce.300ms="newWidth"
                        min="3"
                        max="40"
                        :step="$this->selectedPuzzleType->requiresOdd() ? 2 : 1"
                    />
                    <flux:error name="newWidth" />
                </flux:field>

                @if (! $this->selectedPuzzleType->requiresSquare())
                    <flux:field>
                        <flux:label>{{ __('Height') }}</flux:label>
                        <flux:input type="number" wire:model.live.debounce.300ms="newHeight" min="3" max="40" />
                        <flux:error name="newHeight" />
                    </flux:field>
                @endif
            </div>

            {{-- Diamond Preview --}}
            @if ($this->selectedPuzzleType === PuzzleType::Diamond)
                <div class="flex flex-col items-center gap-2">
                    <flux:label>{{ __('Preview') }}</flux:label>
                    <x-grid-thumbnail :grid="PuzzleType::Diamond->generateGrid($newWidth, $newHeight)" :width="$newWidth" :height="$newHeight" :cell-size="6" :max-width="120" />
                </div>
            @endif

            {{-- Freestyle Preview (no templates; show a random published freestyle puzzle of this size) --}}
            @if ($this->selectedPuzzleType === PuzzleType::Freestyle)
                <div class="flex flex-col items-center gap-2" wire:key="freestyle-preview-{{ $newWidth }}x{{ $newHeight }}">
                    <flux:label>{{ __('Preview') }}</flux:label>
                    @if ($this->freestylePreview)
                        <x-grid-thumbnail :grid="$this->freestylePreview->grid" :styles="$this->freestylePreview->styles" :width="$newWidth" :height="$newHeight" :cell-size="6" :max-width="120" />
                    @else
                        <x-grid-thumbnail :grid="Crossword::emptyGrid($newWidth, $newHeight)" :width="$newWidth" :height="$newHeight" :cell-size="6" :max-width="120" />
                    @endif
                </div>
            @endif

            {{-- Grid Template (Standard only) --}}
            @if ($this->selectedPuzzleType === PuzzleType::Standard)
                <div class="relative h-48" wire:key="template-section-{{ $puzzleType }}-{{ $newWidth }}x{{ $newHeight }}">
                    <div wire:loading.delay wire:target="newWidth, newHeight, puzzleType" class="bg-surface absolute inset-0 z-10 flex items-center justify-center rounded-lg /60 /60">
                        <flux:icon.loading class="size-5 text-zinc-500" />
                    </div>
                    @if(count($this->templates) > 0)
                        <flux:label class="mb-2">{{ __('Grid Template') }} <span class="text-zinc-500 text-xs font-normal"> {{ __('(optional)') }}</span></flux:label>
                        <div class="flex min-h-[6.5rem] gap-3 overflow-x-auto pb-2">
                            {{-- Blank grid option --}}
                            <button
                                type="button"
                                wire:click="$set('selectedTemplate', null)"
                                class="border-line flex shrink-0 flex-col items-center gap-1.5 rounded-lg border p-2 transition-colors {{ $selectedTemplate === null ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : ' hover:border-zinc-400 dark:hover:border-zinc-500' }}"
                            >
                                <x-grid-thumbnail :grid="Crossword::emptyGrid($newWidth, $newHeight)" :width="$newWidth" :height="$newHeight" :cell-size="6" :max-width="80" />
                                <span class="whitespace-nowrap text-xs text-zinc-700 dark:text-zinc-400">{{ __('Blank') }}</span>
                            </button>

                            @foreach($this->templates as $index => $template)
                                <button
                                    type="button"
                                    wire:click="$set('selectedTemplate', {{ $index }})"
                                    class="border-line flex shrink-0 flex-col items-center gap-1.5 rounded-lg border p-2 transition-colors {{ $selectedTemplate === $index ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : ' hover:border-zinc-400 dark:hover:border-zinc-500' }}"
                                >
                                    <x-grid-thumbnail :grid="$template['grid']" :styles="$template['styles'] ?? null" :width="$newWidth" :height="$newHeight" :cell-size="6" :max-width="80" />
                                    <span class="whitespace-nowrap text-xs text-zinc-700 dark:text-zinc-400">{{ $template['name'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @else
                        <div class="flex h-full items-center justify-center">
                            <flux:text size="sm" class="text-zinc-500">{{ __('Templates are available for square grids (3×3 to 27×27).') }}</flux:text>
                        </div>
                    @endif
                </div>
            @endif

            @if ($newPuzzleLimitMessage !== '')
                <div class="w-full rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-2.5 text-sm text-amber-900 dark:text-amber-200">
                    {{ $newPuzzleLimitMessage }}
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showNewModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="createPuzzle">{{ __('Create') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- PDF Export Settings Modal --}}
    <flux:modal wire:model="showPdfExportModal">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('PDF Export Settings') }}</flux:heading>
            <flux:text>{{ __('Configure the page orientation, add an image, and optional narrative text for your PDF export.') }}</flux:text>

            <flux:radio.group wire:model="pdfOrientation" label="{{ __('Orientation') }}">
                <flux:radio value="portrait" label="{{ __('Portrait') }}" description="{{ __('Standard vertical layout (8.5 × 11 in)') }}" />
                <flux:radio value="landscape" label="{{ __('Landscape') }}" description="{{ __('Horizontal layout (11 × 8.5 in) — better for wide puzzles') }}" />
            </flux:radio.group>

            <div>
                <flux:input type="file" wire:model="pdfImage" label="{{ __('Header Image') }}" accept="image/png,image/jpeg,image/gif,image/webp" />
                <flux:text class="mt-1">{{ __('Optional image displayed above the puzzle grid (max 2 MB).') }}</flux:text>
                @if ($this->pdfExportCrossword?->pdf_image && !$pdfRemoveImage)
                    <div class="mt-2 flex items-center gap-2">
                        <flux:text class="text-sm text-green-600 dark:text-green-400">{{ __('Current image saved.') }}</flux:text>
                        <flux:button size="xs" variant="danger" wire:click="$set('pdfRemoveImage', true)">{{ __('Remove') }}</flux:button>
                    </div>
                @elseif ($pdfRemoveImage)
                    <div class="mt-2">
                        <flux:text class="text-sm text-amber-600 dark:text-amber-400">{{ __('Image will be removed on export.') }}</flux:text>
                    </div>
                @endif
            </div>

            <flux:textarea wire:model="pdfNarrative" label="{{ __('Narrative Text') }}" placeholder="{{ __('Add introductory text, theme explanation, or instructions that will appear above the puzzle grid...') }}" rows="4" />

            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelPdfExport">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="confirmPdfExport">{{ __('Export PDF') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Import Modal --}}
    <flux:modal wire:model="showImportModal">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Import Puzzle') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Select file') }}</flux:label>
                <flux:text size="sm" class="mb-2">{{ __('Supported formats: ipuz, json, puz, jpz, pdf') }}</flux:text>
                <flux:input type="file" wire:model="importFile" accept=".ipuz,.json,.puz,.jpz,.pdf" />
                <flux:error name="importFile" />
            </flux:field>

            @if($importError)
                <flux:callout variant="danger">
                    <flux:text>{{ $importError }}</flux:text>
                </flux:callout>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showImportModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="importPuzzle" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="importPuzzle">{{ __('Import') }}</span>
                    <span wire:loading wire:target="importPuzzle">{{ __('Importing...') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
