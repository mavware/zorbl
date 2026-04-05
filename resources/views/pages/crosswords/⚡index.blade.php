<?php

use Zorbl\CrosswordIO\Exceptions\IpuzImportException;
use Zorbl\CrosswordIO\Exceptions\JpzImportException;
use Zorbl\CrosswordIO\Exceptions\PuzImportException;
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
    public function templates(): array
    {
        return app(GridTemplateProvider::class)->getTemplates($this->newWidth, $this->newHeight);
    }

    public function updatedNewWidth(): void
    {
        $this->selectedTemplate = null;
        unset($this->templates);
    }

    public function updatedNewHeight(): void
    {
        $this->selectedTemplate = null;
        unset($this->templates);
    }

    public function createPuzzle(): void
    {
        $this->validate([
            'newWidth' => ['required', 'integer', 'min:3', 'max:30'],
            'newHeight' => ['required', 'integer', 'min:3', 'max:30'],
        ]);

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
        } else {
            $grid = Crossword::emptyGrid($this->newWidth, $this->newHeight);
        }

        $result = app(GridNumberer::class)->number($grid, $this->newWidth, $this->newHeight);

        $crossword = Auth::user()->crosswords()->create([
            'title' => 'Untitled Puzzle',
            'author' => Auth::user()->name,
            'copyright' => copyright(Auth::user()->copyright_name ?? Auth::user()->name ?? ''),
            'width' => $this->newWidth,
            'height' => $this->newHeight,
            'grid' => $result['grid'],
            'solution' => Crossword::emptySolution($this->newWidth, $this->newHeight),
            'clues_across' => array_map(fn ($s) => ['number' => $s['number'], 'clue' => ''], $result['across']),
            'clues_down' => array_map(fn ($s) => ['number' => $s['number'], 'clue' => ''], $result['down']),
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
        } catch (IpuzImportException|PuzImportException|JpzImportException $e) {
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
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-600">
                <flux:icon name="puzzle-piece" class="mb-4 size-12 text-zinc-400" />
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
                        class="group relative rounded-xl border border-zinc-200 p-4 transition-colors hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500"
                    >
                        @php($completeness = $crossword->completeness())
                        <a href="{{ route('crosswords.editor', $crossword) }}" wire:navigate class="block">
                            {{-- Mini grid thumbnail --}}
                            <div class="mb-3 flex justify-center">
                                <div
                                    class="inline-grid gap-px rounded border border-zinc-200 bg-zinc-200 p-px dark:border-zinc-600 dark:bg-zinc-600"
                                    style="grid-template-columns: repeat({{ $crossword->width }}, minmax(0, 1fr)); width: {{ min($crossword->width * 8, 120) }}px;"
                                >
                                    @for($row = 0; $row < $crossword->height; $row++)
                                        @for($col = 0; $col < $crossword->width; $col++)
                                            <div class="{{ $crossword->grid[$row][$col] === null ? 'invisible' : (($crossword->grid[$row][$col] ?? 0) === '#' ? 'bg-zinc-800 dark:bg-zinc-300' : 'bg-white dark:bg-zinc-800') }}" style="aspect-ratio: 1;"></div>
                                        @endfor
                                    @endfor
                                </div>
                            </div>

                            <flux:heading size="sm" class="truncate">{{ $crossword->title ?: __('Untitled Puzzle') }}</flux:heading>
                            <flux:text size="sm" class="mt-1">
                                {{ $crossword->width }}&times;{{ $crossword->height }}
                                &middot;
                                {{ $crossword->updated_at->diffForHumans() }}
                            </flux:text>

                            {{-- Completeness bar --}}
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <div
                                        class="h-full rounded-full transition-all {{ $completeness['percentage'] === 100 ? 'bg-emerald-500' : ($completeness['percentage'] >= 60 ? 'bg-amber-500' : 'bg-zinc-400') }}"
                                        style="width: {{ $completeness['percentage'] }}%"
                                    ></div>
                                </div>
                                <span class="text-xs tabular-nums text-zinc-400">{{ $completeness['percentage'] }}%</span>
                            </div>
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

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Width') }}</flux:label>
                    <flux:input type="number" wire:model.live="newWidth" min="3" max="30" />
                    <flux:error name="newWidth" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Height') }}</flux:label>
                    <flux:input type="number" wire:model.live="newHeight" min="3" max="30" />
                    <flux:error name="newHeight" />
                </flux:field>
            </div>

            @if(count($this->templates) > 0)
            <div>
                <flux:label class="mb-2">{{ __('Grid Template') }} <span class="text-zinc-400 text-xs font-normal">{{ __('(optional)') }}</span></flux:label>
                <div class="flex min-h-[6.5rem] gap-3 overflow-x-auto pb-2">
                    {{-- Blank grid option --}}
                    <button
                        type="button"
                        wire:click="$set('selectedTemplate', null)"
                        class="flex shrink-0 flex-col items-center gap-1.5 rounded-lg border-2 p-2 transition-colors {{ $selectedTemplate === null ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-zinc-200 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500' }}"
                    >
                        <div
                            class="inline-grid gap-px rounded border border-zinc-200 bg-zinc-200 p-px dark:border-zinc-600 dark:bg-zinc-600"
                            style="grid-template-columns: repeat({{ $newWidth }}, minmax(0, 1fr)); width: {{ min($newWidth * 6, 80) }}px;"
                        >
                            @for($r = 0; $r < $newHeight; $r++)
                                @for($c = 0; $c < $newWidth; $c++)
                                    <div class="bg-white dark:bg-zinc-800" style="aspect-ratio: 1;"></div>
                                @endfor
                            @endfor
                        </div>
                        <span class="whitespace-nowrap text-xs text-zinc-600 dark:text-zinc-400">{{ __('Blank') }}</span>
                    </button>

                    @foreach($this->templates as $index => $template)
                            <button
                                type="button"
                                wire:click="$set('selectedTemplate', {{ $index }})"
                                class="flex shrink-0 flex-col items-center gap-1.5 rounded-lg border-2 p-2 transition-colors {{ $selectedTemplate === $index ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-zinc-200 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500' }}"
                            >
                                <div
                                    class="inline-grid gap-px rounded border border-zinc-200 bg-zinc-200 p-px dark:border-zinc-600 dark:bg-zinc-600"
                                    style="grid-template-columns: repeat({{ $newWidth }}, minmax(0, 1fr)); width: {{ min($newWidth * 6, 80) }}px;"
                                >
                                    @foreach($template['grid'] as $row)
                                        @foreach($row as $cell)
                                            <div class="{{ $cell === '#' ? 'bg-zinc-800 dark:bg-zinc-300' : 'bg-white dark:bg-zinc-800' }}" style="aspect-ratio: 1;"></div>
                                        @endforeach
                                    @endforeach
                                </div>
                                <span class="whitespace-nowrap text-xs text-zinc-600 dark:text-zinc-400">{{ $template['name'] }}</span>
                            </button>
                    @endforeach
                </div>
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
                <flux:text size="sm" class="mb-2">{{ __('Supported formats: ipuz, json, puz, jpz') }}</flux:text>
                <flux:input type="file" wire:model="importFile" accept=".ipuz,.json,.puz,.jpz" />
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
