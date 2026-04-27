<?php

use Zorbl\CrosswordIO\Exceptions\IpuzImportException;
use Zorbl\CrosswordIO\Exceptions\JpzImportException;
use Zorbl\CrosswordIO\Exceptions\PdfImportException;
use Zorbl\CrosswordIO\Exceptions\PuzImportException;
use App\Enums\PuzzleType;
use App\Models\Crossword;
use App\Services\GridTemplateProvider;
use Zorbl\CrosswordIO\GridNumberer;
use Zorbl\CrosswordIO\ImportDetector;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('My Puzzles')] class extends Component {
    use WithFileUploads;

    public bool $showNewModal = false;
    public bool $showImportModal = false;
    public string $puzzleType = 'standard';
    public int $newWidth = 15;
    public int $newHeight = 15;
    public ?int $selectedTemplate = null;
    public $importFile;
    public string $importError = '';

    #[Computed]
    public function crosswords()
    {
        return Auth::user()->crosswords()->latest()->get();
    }

    #[Computed]
    public function selectedPuzzleType(): PuzzleType
    {
        return PuzzleType::tryFrom($this->puzzleType) ?? PuzzleType::Standard;
    }

    #[Computed]
    public function templates(): array
    {
        if ($this->selectedPuzzleType === PuzzleType::Diamond) {
            return [];
        }

        return app(GridTemplateProvider::class)->getTemplates($this->newWidth ?? 0, $this->newHeight ?? 0);
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
        unset($this->templates, $this->selectedPuzzleType);
    }

    public function updatedNewWidth(): void
    {
        $type = $this->selectedPuzzleType;

        if ($type->requiresSquare()) {
            $this->newHeight = $this->newWidth;
        }

        $this->selectedTemplate = null;
        unset($this->templates, $this->selectedPuzzleType);
    }

    public function updatedNewHeight(): void
    {
        $type = $this->selectedPuzzleType;

        if ($type->requiresSquare()) {
            $this->newWidth = $this->newHeight;
        }

        $this->selectedTemplate = null;
        unset($this->templates, $this->selectedPuzzleType);
    }

    public function createPuzzle(): void
    {
        $type = $this->selectedPuzzleType;

        $rules = [
            'newWidth' => ['required', 'integer', 'min:3', 'max:30'],
            'newHeight' => ['required', 'integer', 'min:3', 'max:30'],
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
            $this->addError('newWidth', $user->isPro()
                ? __('You have reached your puzzle limit.')
                : __('Free accounts can create up to :count puzzles. Upgrade to Pro for unlimited.', ['count' => $limits->maxPuzzles()]));

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
            'title' => 'Untitled Puzzle',
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

    public function deletePuzzle(int $id): void
    {
        $crossword = Crossword::findOrFail($id);
        $this->authorize('delete', $crossword);
        $crossword->delete();
    }
}
?>

<div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('My Puzzles') }}</flux:heading>

            <div class="flex gap-2">
                <flux:button variant="ghost" size="sm" icon="chart-bar" :href="route('crosswords.analytics')" wire:navigate>
                    {{ __('Analytics') }}
                </flux:button>
                <flux:button variant="primary" icon="plus" wire:click="$set('showNewModal', true)">
                    {{ __('New Puzzle') }}
                </flux:button>
                <flux:button icon="arrow-up-tray" wire:click="$set('showImportModal', true)">
                    {{ __('Import Puzzle') }}
                </flux:button>
            </div>
        </div>

        @if($this->crosswords->isEmpty())
            <div class="border-line-strong flex flex-col items-center justify-center rounded-xl border border-dashed py-16">
                <flux:icon name="puzzle-piece" class="mb-4 size-12 text-zinc-500" />
                <flux:heading size="lg" class="mb-2">{{ __('No puzzles yet') }}</flux:heading>
                <flux:text class="mb-6">{{ __('Create a new crossword or import an existing puzzle file.') }}</flux:text>
                <div class="flex gap-2">
                    <flux:button variant="primary" icon="plus" wire:click="$set('showNewModal', true)">
                        {{ __('New Puzzle') }}
                    </flux:button>
                    <flux:button icon="arrow-up-tray" wire:click="$set('showImportModal', true)">
                        {{ __('Import Puzzle') }}
                    </flux:button>
                </div>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($this->crosswords as $crossword)
                    <div
                        wire:key="crossword-{{ $crossword->id }}"
                        class="border-line group relative rounded-xl border p-4 transition-colors hover:border-zinc-400 dark:hover:border-zinc-500"
                    >
                        <a href="{{ route('crosswords.editor', $crossword) }}" wire:navigate class="block">
                            <div class="mb-3 flex justify-center">
                                <x-grid-thumbnail :grid="$crossword->grid" :width="$crossword->width" :height="$crossword->height" />
                            </div>

                            <flux:heading size="sm" class="truncate">
                                {{ $crossword->title ?: __('Untitled Puzzle') }}
                            </flux:heading>

                            <x-puzzle-details :crossword="$crossword" />

                            <x-puzzle-completeness-bar :crossword="$crossword" />
                        </a>

                        <div class="absolute top-2 right-2 opacity-0 transition-opacity group-hover:opacity-100">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    <flux:menu.item icon="trash" variant="danger" wire:click="deletePuzzle({{ $crossword->id }})" wire:confirm="{{ __('Are you sure you want to delete this puzzle?') }}">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

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
                                'flex flex-col items-center gap-2 rounded-lg border-2 p-3 text-center transition-colors',
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
                        max="30"
                        :step="$this->selectedPuzzleType->requiresOdd() ? 2 : 1"
                    />
                    <flux:error name="newWidth" />
                </flux:field>

                @if (! $this->selectedPuzzleType->requiresSquare())
                    <flux:field>
                        <flux:label>{{ __('Height') }}</flux:label>
                        <flux:input type="number" wire:model.live.debounce.300ms="newHeight" min="3" max="30" />
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

            {{-- Grid Template (Standard and Freestyle only) --}}
            @if ($this->selectedPuzzleType !== PuzzleType::Diamond)
                <div class="relative h-48" wire:key="template-section-{{ $puzzleType }}-{{ $newWidth }}x{{ $newHeight }}">
                    <div wire:loading.delay wire:target="newWidth, newHeight, puzzleType" class="bg-surface absolute inset-0 z-10 flex items-center justify-center rounded-lg /60 /60">
                        <flux:icon.loading class="size-5 text-zinc-500" />
                    </div>
                    @if(count($this->templates) > 0)
                        <flux:label class="mb-2">{{ __('Grid Template') }} <span class="text-zinc-500 text-xs font-normal">{{ __('(optional)') }}</span></flux:label>
                        <div class="flex min-h-[6.5rem] gap-3 overflow-x-auto pb-2">
                            {{-- Blank grid option --}}
                            <button
                                type="button"
                                wire:click="$set('selectedTemplate', null)"
                                class="border-line flex shrink-0 flex-col items-center gap-1.5 rounded-lg border-2 p-2 transition-colors {{ $selectedTemplate === null ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : ' hover:border-zinc-400 dark:hover:border-zinc-500' }}"
                            >
                                <x-grid-thumbnail :grid="Crossword::emptyGrid($newWidth, $newHeight)" :width="$newWidth" :height="$newHeight" :cell-size="6" :max-width="80" />
                                <span class="whitespace-nowrap text-xs text-zinc-700 dark:text-zinc-400">{{ __('Blank') }}</span>
                            </button>

                            @foreach($this->templates as $index => $template)
                                <button
                                    type="button"
                                    wire:click="$set('selectedTemplate', {{ $index }})"
                                    class="border-line flex shrink-0 flex-col items-center gap-1.5 rounded-lg border-2 p-2 transition-colors {{ $selectedTemplate === $index ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : ' hover:border-zinc-400 dark:hover:border-zinc-500' }}"
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

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showNewModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="createPuzzle">{{ __('Create') }}</flux:button>
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
