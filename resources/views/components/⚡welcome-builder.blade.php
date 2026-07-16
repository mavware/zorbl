<?php

use App\Enums\PuzzleType;
use App\Models\Crossword;
use App\Services\AnonymousUserManager;
use App\Services\GridTemplateProvider;
use CrosswordBuilder\CrosswordIO\GridNumberer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $puzzleType = 'standard';

    public int $newWidth = 15;

    public int $newHeight = 15;

    public ?int $selectedTemplate = null;

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

    /**
     * Whether the current user has already reached their puzzle cap. Anonymous
     * visitors who haven't created their guest account yet are never at the
     * limit — they can still build their first puzzle.
     */
    #[Computed]
    public function atPuzzleLimit(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->crosswords()->count() >= $user->planLimits()->maxPuzzles();
    }

    /**
     * The reason the user can't build another puzzle, or null when they can.
     */
    #[Computed]
    public function limitMessage(): ?string
    {
        if (! $this->atPuzzleLimit) {
            return null;
        }

        $limits = Auth::user()->planLimits();

        if ($limits->isAnonymous()) {
            return __('Create a free account to build more puzzles.');
        }

        return $limits->isPro()
            ? __('You have reached your puzzle limit.')
            : __('Free accounts can create up to :count puzzles. Upgrade to Pro for unlimited.', ['count' => $limits->maxPuzzles()]);
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

    public function createPuzzle()
    {
        if (! Auth::check()) {
            $anon = app(AnonymousUserManager::class)->getOrCreateForRequest(request());
            Auth::login($anon);
        }

        $type = $this->selectedPuzzleType;

        $this->validate([
            'newWidth' => ['required', 'integer', 'min:3', 'max:40'],
            'newHeight' => ['required', 'integer', 'min:3', 'max:40'],
        ]);

        if ($type->requiresSquare() && $this->newWidth !== $this->newHeight) {
            $this->addError('newHeight', __(':type puzzles must be square.', ['type' => $type->label()]));

            return null;
        }

        if ($type->requiresOdd() && $this->newWidth % 2 === 0) {
            $this->addError('newWidth', __(':type puzzles require an odd grid size.', ['type' => $type->label()]));

            return null;
        }

        // The limit computeds may have been cached during render before the
        // guest account existed; recompute against the now-authenticated user.
        unset($this->atPuzzleLimit, $this->limitMessage);

        if ($this->atPuzzleLimit) {
            $this->addError('newWidth', $this->limitMessage);

            return null;
        }

        $user = Auth::user();

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

        $crossword = $user->crosswords()->create([
            'title' => null,
            'author' => $user->name,
            'copyright' => copyright($user->copyright_name ?? $user->name ?? ''),
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

        return $this->redirect(route('crosswords.editor', $crossword), navigate: true);
    }
}
?>

<div class="space-y-6 text-left">
    {{-- Puzzle Type Selector --}}
    <div>
        <flux:label class="mb-2 text-zinc-200">{{ __('Puzzle Type') }}</flux:label>
        <div class="grid grid-cols-3 gap-3">
            @foreach (PuzzleType::cases() as $type)
                <button
                    type="button"
                    wire:click="$set('puzzleType', @js($type->value))"
                    @class([
                        'flex flex-col items-center gap-2 rounded-lg border-2 p-3 text-center transition-colors',
                        'border-amber-500 bg-amber-500/10 ring-1 ring-amber-500' => $puzzleType === $type->value,
                        'border-zinc-700 hover:border-zinc-500 hover:bg-zinc-800/50' => $puzzleType !== $type->value,
                    ])
                >
                    <flux:icon :name="$type->icon()" @class([
                        'size-6',
                        'text-amber-400' => $puzzleType === $type->value,
                        'text-zinc-500' => $puzzleType !== $type->value,
                    ]) />
                    <span @class([
                        'text-sm font-medium',
                        'text-amber-300' => $puzzleType === $type->value,
                        'text-zinc-300' => $puzzleType !== $type->value,
                    ])>{{ __($type->label()) }}</span>
                    <span class="text-[11px] leading-tight text-zinc-500">{{ __($type->description()) }}</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Grid Dimensions --}}
    <div class="grid grid-cols-2 gap-4">
        <flux:field>
            <flux:label class="text-zinc-200">{{ $this->selectedPuzzleType->requiresSquare() ? __('Size') : __('Width') }}</flux:label>
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
                <flux:label class="text-zinc-200">{{ __('Height') }}</flux:label>
                <flux:input type="number" wire:model.live.debounce.300ms="newHeight" min="3" max="40" />
                <flux:error name="newHeight" />
            </flux:field>
        @endif
    </div>

    {{-- Diamond Preview --}}
    @if ($this->selectedPuzzleType === PuzzleType::Diamond)
        <div class="flex flex-col items-center gap-2">
            <flux:label class="text-zinc-200">{{ __('Preview') }}</flux:label>
            <x-grid-thumbnail :grid="PuzzleType::Diamond->generateGrid($newWidth, $newHeight)" :width="$newWidth" :height="$newHeight" :cell-size="6" :max-width="120" />
        </div>
    @endif

    {{-- Freestyle Preview (no templates; show a random published freestyle puzzle of this size) --}}
    @if ($this->selectedPuzzleType === PuzzleType::Freestyle)
        <div class="flex flex-col items-center gap-2" wire:key="freestyle-preview-{{ $newWidth }}x{{ $newHeight }}">
            <flux:label class="text-zinc-200">{{ __('Preview') }}</flux:label>
            @if ($this->freestylePreview)
                <x-grid-thumbnail :grid="$this->freestylePreview->grid" :styles="$this->freestylePreview->styles" :width="$newWidth" :height="$newHeight" :cell-size="6" :max-width="120" />
            @else
                <x-grid-thumbnail :grid="Crossword::emptyGrid($newWidth, $newHeight)" :width="$newWidth" :height="$newHeight" :cell-size="6" :max-width="120" />
            @endif
        </div>
    @endif

    {{-- Grid Template (Standard only) --}}
    @if ($this->selectedPuzzleType === PuzzleType::Standard)
        <div class="relative min-h-[8rem]" wire:key="template-section-{{ $puzzleType }}-{{ $newWidth }}x{{ $newHeight }}">
            <div wire:loading.delay wire:target="newWidth, newHeight, puzzleType" class="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-zinc-900/60">
                <flux:icon.loading class="size-5 text-zinc-400" />
            </div>
            @if(count($this->templates) > 0)
                <flux:label class="mb-2 text-zinc-200">{{ __('Grid Template') }} <span class="text-zinc-500 text-xs font-normal">{{ __('(optional)') }}</span></flux:label>
                <div class="flex min-h-[6.5rem] gap-3 overflow-x-auto pb-2">
                    {{-- Blank grid option --}}
                    <button
                        type="button"
                        wire:click="$set('selectedTemplate', null)"
                        class="flex shrink-0 flex-col items-center gap-1.5 rounded-lg border-2 p-2 transition-colors {{ $selectedTemplate === null ? 'border-amber-500 bg-amber-500/10' : 'border-zinc-700 hover:border-zinc-500' }}"
                    >
                        <x-grid-thumbnail :grid="Crossword::emptyGrid($newWidth, $newHeight)" :width="$newWidth" :height="$newHeight" :cell-size="6" :max-width="80" />
                        <span class="whitespace-nowrap text-xs text-zinc-400">{{ __('Blank') }}</span>
                    </button>

                    @foreach($this->templates as $index => $template)
                        <button
                            type="button"
                            wire:click="$set('selectedTemplate', {{ $index }})"
                            class="flex shrink-0 flex-col items-center gap-1.5 rounded-lg border-2 p-2 transition-colors {{ $selectedTemplate === $index ? 'border-amber-500 bg-amber-500/10' : 'border-zinc-700 hover:border-zinc-500' }}"
                        >
                            <x-grid-thumbnail :grid="$template['grid']" :styles="$template['styles'] ?? null" :width="$newWidth" :height="$newHeight" :cell-size="6" :max-width="80" />
                            <span class="whitespace-nowrap text-xs text-zinc-400">{{ $template['name'] }}</span>
                        </button>
                    @endforeach
                </div>
            @else
                <div class="flex h-full min-h-[6.5rem] items-center justify-center">
                    <flux:text size="sm" class="text-zinc-500">{{ __('Templates are available for square grids (3×3 to 27×27).') }}</flux:text>
                </div>
            @endif
        </div>
    @endif

    <div class="flex flex-col items-center gap-2 pt-2">
        @if ($this->atPuzzleLimit)
            <div class="flex">
                <flux:icon.exclamation-triangle class=" size-4 mr-2 text-red-400" />
                <flux:text size="sm" class="text-center text-red-400"> {{ $this->limitMessage }}</flux:text>
            </div>

        @endif
        <button
            type="button"
            wire:click="createPuzzle"
            @disabled($this->atPuzzleLimit)
            class="rounded-xl bg-amber-500 px-8 py-3.5 text-base font-semibold text-zinc-950 shadow-lg shadow-amber-500/20 transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none disabled:hover:bg-amber-500"
        >
            {{ __('Start building') }}
        </button>
    </div>
</div>
