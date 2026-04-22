<?php

use App\Enums\CrosswordLayout;
use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\Tag;
use App\Services\ClueHarvester;
use App\Livewire\Concerns\ExportsCrossword;
use Zorbl\CrosswordIO\GridNumberer;
use App\Services\WordSuggester;
use App\Services\DifficultyRater;
use App\Services\GridFiller;
use App\Services\AiFillPicker;
use App\Services\AiClueGenerator;
use App\Support\AiUsageTracker;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Crossword Editor')]
class extends Component {
    use ExportsCrossword;

    #[Locked]
    public int $crosswordId;

    #[Computed]
    public function crossword(): Crossword
    {
        return Crossword::findOrFail($this->crosswordId);
    }

    public string $title = '';
    public string $author = '';
    public int $width;
    public int $height;
    public array $grid = [];
    public array $solution = [];
    public array $cluesAcross = [];
    public array $cluesDown = [];
    public ?array $styles = null;

    public string $copyright = '';
    public string $notes = '';
    public string $secretTheme = '';
    public ?CrosswordLayout $layout = null;
    public int $minAnswerLength = 3;

    public bool $isPublished = false;

    public bool $showPublishWarning = false;
    public array $incompleteChecks = [];

    public ?array $prefilled = null;

    public bool $showSettingsModal = false;
    public bool $showUpgradeModal = false;
    public string $upgradeFeature = '';
    public int $resizeWidth;
    public int $resizeHeight;

    public array $tagIds = [];
    public string $tagSearch = '';

    public function mount(Crossword $crossword): void
    {
        $this->authorize('update', $crossword);

        $this->crosswordId = $crossword->id;
        $this->title = $crossword->title ?? '';
        $this->author = $crossword->author ?? Auth::user()->name ?? '';
        $this->width = $crossword->width;
        $this->height = $crossword->height;
        $this->grid = $crossword->grid;
        $this->solution = $crossword->solution;
        $this->cluesAcross = $crossword->clues_across ?? [];
        $this->cluesDown = $crossword->clues_down ?? [];
        $this->styles = $crossword->styles;
        $this->prefilled = $crossword->prefilled;
        $this->copyright = $crossword->copyright ?? copyright(Auth::user()->copyright_name ?? Auth::user()->name ?? '');
        $this->notes = $crossword->notes ?? '';
        $this->secretTheme = $crossword->secret_theme ?? '';
        $this->layout = $crossword->layout;
        $this->minAnswerLength = $crossword->metadata['min_answer_length'] ?? 3;
        $this->isPublished = $crossword->is_published;
        $this->resizeWidth = $crossword->width;
        $this->resizeHeight = $crossword->height;
        $this->tagIds = $crossword->tags()->pluck('tags.id')->all();
    }

    public function save(array $grid, array $solution, ?array $styles, array $cluesAcross, array $cluesDown): void
    {
        $crossword = $this->crossword;
        $this->authorize('update', $crossword);

        $crossword->update([
            'grid'         => $grid,
            'solution'     => $solution,
            'styles'       => $styles,
            'clues_across' => $cluesAcross,
            'clues_down'   => $cluesDown,
        ]);

        if ($this->isPublished) {
            app(ClueHarvester::class)->harvest($crossword);
        }

        $this->skipRender();
        $this->dispatch('saved');
    }

    public function savePrefilled(array $prefilled): void
    {
        $crossword = $this->crossword;
        $this->authorize('update', $crossword);

        // Check if the prefilled grid has any non-empty values
        $hasValues = false;
        foreach ($prefilled as $row) {
            foreach ($row as $cell) {
                if (filled($cell)) {
                    $hasValues = true;

                    break 2;
                }
            }
        }

        $crossword->update([
            'prefilled' => $hasValues ? $prefilled : null,
        ]);

        $this->prefilled = $hasValues ? $prefilled : null;
        $this->skipRender();
        $this->dispatch('prefilled-saved');
    }

    public function saveMetadata(): void
    {
        $this->validate([
            'title'           => ['nullable', 'string', 'max:255'],
            'author'          => ['nullable', 'string', 'max:255'],
            'copyright'       => ['nullable', 'string', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:1000'],
            'secretTheme'     => ['nullable', 'string', 'max:500'],
            'minAnswerLength' => ['required', 'integer', 'min:1', 'max:15'],
        ]);

        $crossword = $this->crossword;
        $this->authorize('update', $crossword);

        $metadata = $crossword->metadata ?? [];
        $metadata['min_answer_length'] = $this->minAnswerLength;

        $crossword->update([
            'title'        => $this->title,
            'author'       => $this->author,
            'copyright'    => $this->copyright,
            'notes'        => $this->notes,
            'secret_theme' => $this->secretTheme !== '' ? $this->secretTheme : null,
            'layout'       => $this->layout,
            'metadata'     => $metadata,
        ]);

        $crossword->tags()->sync($this->tagIds);

        $this->showSettingsModal = false;
        $this->dispatch('settings-updated');
        $this->dispatch('saved');
    }

    /** @return array<int, array{id: int, name: string}> */
    #[Computed]
    public function availableTags(): array
    {
        $query = Tag::query()->orderBy('name');

        if ($this->tagSearch !== '') {
            $query->whereLike('name', "%{$this->tagSearch}%");
        }

        return $query->limit(30)->get(['id', 'name'])->toArray();
    }

    /** @return array<int, array{id: int, name: string}> */
    #[Computed]
    public function selectedTags(): array
    {
        if (empty($this->tagIds)) {
            return [];
        }

        return Tag::whereIn('id', $this->tagIds)->orderBy('name')->get(['id', 'name'])->toArray();
    }

    public function addTag(int $tagId): void
    {
        if (! in_array($tagId, $this->tagIds)) {
            $this->tagIds[] = $tagId;
        }
        $this->tagSearch = '';
        unset($this->availableTags, $this->selectedTags);
    }

    public function removeTag(int $tagId): void
    {
        $this->tagIds = array_values(array_filter($this->tagIds, fn (int $id) => $id !== $tagId));
        unset($this->availableTags, $this->selectedTags);
    }

    public function createTag(): void
    {
        $name = trim($this->tagSearch);
        if ($name === '' || mb_strlen($name) > 50) {
            return;
        }

        $tag = Tag::firstOrCreate(
            ['slug' => \Illuminate\Support\Str::slug($name)],
            ['name' => $name],
        );

        if (! in_array($tag->id, $this->tagIds)) {
            $this->tagIds[] = $tag->id;
        }
        $this->tagSearch = '';
        unset($this->availableTags, $this->selectedTags, $this->suggestedStandardTags);
    }

    public function addStandardTag(string $name): void
    {
        if (! in_array($name, Tag::STANDARD, true)) {
            return;
        }

        $tag = Tag::firstOrCreate(
            ['slug' => \Illuminate\Support\Str::slug($name)],
            ['name' => $name],
        );

        if (! in_array($tag->id, $this->tagIds)) {
            $this->tagIds[] = $tag->id;
        }
        $this->tagSearch = '';
        unset($this->availableTags, $this->selectedTags, $this->suggestedStandardTags);
    }

    /** @return array<int, string> */
    #[Computed]
    public function suggestedStandardTags(): array
    {
        $matching = Tag::standardSuggestions($this->tagSearch);

        if ($matching === []) {
            return [];
        }

        $availableSlugs = array_map(
            fn (array $t): string => \Illuminate\Support\Str::slug($t['name']),
            $this->availableTags,
        );
        $selectedSlugs = array_map(
            fn (array $t): string => \Illuminate\Support\Str::slug($t['name']),
            $this->selectedTags,
        );
        $taken = array_merge($availableSlugs, $selectedSlugs);

        return array_values(array_filter(
            $matching,
            fn (string $name): bool => ! in_array(\Illuminate\Support\Str::slug($name), $taken, true),
        ));
    }

    public function updatedTagSearch(): void
    {
        unset($this->availableTags, $this->suggestedStandardTags);
    }

    public function attemptPublish(): void
    {
        if ($this->isPublished) {
            $this->togglePublished();

            return;
        }

        $crossword = $this->crossword;
        $completeness = $crossword->completeness();

        if ($completeness['percentage'] === 100) {
            $this->togglePublished();

            return;
        }

        $this->incompleteChecks = array_keys(array_filter($completeness['checks'], fn($v) => !$v));
        $this->showPublishWarning = true;
    }

    public function togglePublished(): void
    {
        $crossword = $this->crossword;
        $this->authorize('update', $crossword);

        $this->isPublished = !$this->isPublished;
        $crossword->update(['is_published' => $this->isPublished]);

        $harvester = app(ClueHarvester::class);

        if ($this->isPublished) {
            $harvester->harvest($crossword);

            // Calculate and store difficulty rating
            $rater = app(DifficultyRater::class);
            $rating = $rater->rate($crossword);
            $crossword->update([
                'difficulty_score' => $rating['score'],
                'difficulty_label' => $rating['label'],
            ]);
        } else {
            $harvester->purge($crossword);
        }

        $this->showPublishWarning = false;
        $this->incompleteChecks = [];
    }

    public function cancelPublish(): void
    {
        $this->showPublishWarning = false;
        $this->dispatch('highlight-incomplete', checks: $this->incompleteChecks);
    }

    /**
     * Look up existing clues for a given answer word from other published puzzles.
     *
     * @return array<int, array{clue: string, author: string, puzzle: string}>
     */
    public function lookupClues(string $answer): array
    {
        $answer = mb_strtoupper(trim($answer));

        if (mb_strlen($answer) < 2) {
            return [];
        }

        return ClueEntry::where('answer', $answer)
            ->where(fn($q) => $q->whereNull('crossword_id')->orWhere('crossword_id', '!=', $this->crosswordId))
            ->with(['user:id,name', 'crossword:id,title'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn(ClueEntry $entry) => [
                'clue'   => $entry->clue,
                'author' => $entry->user->name ?? 'Unknown',
                'puzzle' => $entry->crossword->title ?? 'Untitled',
            ])
            ->all();
    }

    /**
     * Suggest words matching a pattern (e.g., "C__NK") for autofill assistance.
     *
     * @return array<int, array{word: string, score: float}>
     */
    public function suggestWords(string $pattern, int $length): array
    {
        if ($length < 2 || $length > 30) {
            return [];
        }

        if (! preg_match('/^[A-Z_]+$/', $pattern)) {
            return [];
        }

        return app(WordSuggester::class)->suggest($pattern, $length);
    }

    /**
     * Fill empty grid slots using heuristic backtracking.
     *
     * @return array{success: bool, fills: list<array{direction: string, number: int, word: string}>, message: string}
     */
    public function heuristicFill(array $solution): array
    {
        $crossword = $this->crossword;
        $this->authorize('update', $crossword);

        return app(GridFiller::class)->fill(
            $this->grid,
            $solution,
            $this->width,
            $this->height,
            $this->styles ?? [],
            $this->minAnswerLength,
        );
    }

    /**
     * Fill empty grid slots by generating several heuristic fills and letting AI pick the best.
     *
     * @return array{success: bool, fills: list<array{direction: string, number: int, word: string}>, message: string, upgrade?: bool}
     */
    public function aiFill(array $solution): array
    {
        $crossword = $this->crossword;
        $this->authorize('update', $crossword);

        $tracker = app(AiUsageTracker::class);
        $user = Auth::user();

        if (! $tracker->canUse($user, 'grid_fill')) {
            return [
                'success' => false,
                'fills' => [],
                'message' => $user->isPro()
                    ? __('You have used all 50 AI fills this month. Resets on the 1st.')
                    : __('AI Autofill is a Pro feature. Upgrade to unlock it.'),
                'upgrade' => ! $user->isPro(),
            ];
        }

        if (trim($this->secretTheme) === '' && trim($this->title) === '') {
            return [
                'success' => false,
                'fills' => [],
                'message' => __('Add a puzzle title or a secret theme in Settings so AI Autofill knows what to aim for.'),
                'needs_theme' => true,
            ];
        }

        $filler = app(GridFiller::class);
        $attempts = 4;
        $perAttemptTimeout = 5;

        $candidates = [];
        $seen = [];
        for ($i = 0; $i < $attempts; $i++) {
            $result = $filler->fill(
                $this->grid,
                $solution,
                $this->width,
                $this->height,
                $this->styles ?? [],
                $this->minAnswerLength,
                timeout: $perAttemptTimeout,
                seed: $i + 1,
            );

            if (! $result['success'] || empty($result['fills'])) {
                continue;
            }

            $signature = $this->fillSignature($result['fills']);
            if (isset($seen[$signature])) {
                continue;
            }
            $seen[$signature] = true;
            $candidates[] = $result['fills'];
        }

        if (empty($candidates)) {
            return [
                'success' => false,
                'fills' => [],
                'message' => __('Could not find valid words to fill the grid. Try filling some letters manually first.'),
            ];
        }

        if (count($candidates) === 1) {
            $tracker->record($user, 'grid_fill');

            return [
                'success' => true,
                'fills' => $candidates[0],
                'message' => 'Filled '.count($candidates[0]).' '.str('word')->plural(count($candidates[0])).' (only one valid arrangement found).',
            ];
        }

        $pinnedWords = $this->pinnedWords($solution);

        $choice = app(AiFillPicker::class)->pick(
            $candidates,
            $this->title,
            $this->notes,
            $pinnedWords,
            $this->secretTheme,
        );

        $tracker->record($user, 'grid_fill');

        return [
            'success' => true,
            'fills' => $candidates[$choice['index']],
            'message' => $choice['message'],
        ];
    }

    /**
     * Stable signature of a fill set for deduplication.
     *
     * @param  list<array{direction: string, number: int, word: string}>  $fills
     */
    private function fillSignature(array $fills): string
    {
        $sorted = $fills;
        usort($sorted, fn ($a, $b) => [$a['direction'], $a['number']] <=> [$b['direction'], $b['number']]);

        return implode('|', array_map(fn ($f) => "{$f['direction']}{$f['number']}={$f['word']}", $sorted));
    }

    /**
     * Words already in the solution grid, shared across every heuristic candidate.
     *
     * @return list<array{direction: string, number: int, word: string}>
     */
    private function pinnedWords(array $solution): array
    {
        $numberer = app(GridNumberer::class);
        $numbered = $numberer->number($this->grid, $this->width, $this->height, $this->styles ?? [], $this->minAnswerLength);

        $pinned = [];
        foreach (['across', 'down'] as $dir) {
            foreach ($numbered[$dir] as $slot) {
                $word = GridFiller::getPattern($solution, $slot, $dir);
                if (! str_contains($word, '_')) {
                    $pinned[] = ['direction' => $dir, 'number' => $slot['number'], 'word' => $word];
                }
            }
        }

        return $pinned;
    }

    /**
     * Generate clues for all filled words using AI.
     *
     * @return array{success: bool, clues: array{across: array<int, string>, down: array<int, string>}, message: string}
     */
    public function aiGenerateClues(array $solution): array
    {
        $crossword = $this->crossword;
        $this->authorize('update', $crossword);

        $tracker = app(AiUsageTracker::class);
        $user = Auth::user();

        if (! $tracker->canUse($user, 'clue_generation')) {
            return [
                'success' => false,
                'clues' => ['across' => [], 'down' => []],
                'message' => $user->isPro()
                    ? __('You have used all 50 AI clue generations this month. Resets on the 1st.')
                    : __('AI Clue Generation is a Pro feature. Upgrade to unlock it.'),
                'upgrade' => ! $user->isPro(),
            ];
        }

        $numberer = app(GridNumberer::class);
        $numbered = $numberer->number($this->grid, $this->width, $this->height, $this->styles ?? [], $this->minAnswerLength);

        $words = [];
        foreach (['across', 'down'] as $dir) {
            foreach ($numbered[$dir] as $slot) {
                $word = GridFiller::getPattern($solution, $slot, $dir);
                if (! str_contains($word, '_')) {
                    $words[] = [
                        'direction' => $dir,
                        'number' => $slot['number'],
                        'word' => $word,
                    ];
                }
            }
        }

        $result = app(AiClueGenerator::class)->generate($words, $this->title, $this->notes);

        if ($result['success'] && ! empty($result['clues'])) {
            $tracker->record($user, 'clue_generation');
        }

        return $result;
    }


    public function resizeGrid(): void
    {
        $this->validate([
            'resizeWidth'  => ['required', 'integer', 'min:3', 'max:30'],
            'resizeHeight' => ['required', 'integer', 'min:3', 'max:30'],
        ]);

        $newGrid = Crossword::emptyGrid($this->resizeWidth, $this->resizeHeight);
        $newSolution = Crossword::emptySolution($this->resizeWidth, $this->resizeHeight);

        // Preserve existing content where dimensions overlap
        $minHeight = min($this->height, $this->resizeHeight);
        $minWidth = min($this->width, $this->resizeWidth);

        for ($row = 0; $row < $minHeight; $row++) {
            for ($col = 0; $col < $minWidth; $col++) {
                $newGrid[$row][$col] = $this->grid[$row][$col];
                $newSolution[$row][$col] = $this->solution[$row][$col];
            }
        }

        // Renumber the grid
        $numberer = app(GridNumberer::class);
        $result = $numberer->number($newGrid, $this->resizeWidth, $this->resizeHeight, [], $this->minAnswerLength);

        $this->width = $this->resizeWidth;
        $this->height = $this->resizeHeight;
        $this->grid = $result['grid'];
        $this->solution = $newSolution;
        $this->styles = null;
        $this->cluesAcross = [];
        $this->cluesDown = [];

        $crossword = $this->crossword;
        $crossword->update([
            'width'        => $this->width,
            'height'       => $this->height,
            'grid'         => $this->grid,
            'solution'     => $this->solution,
            'styles'       => null,
            'clues_across' => [],
            'clues_down'   => [],
        ]);

        $this->showSettingsModal = false;
        $this->dispatch('grid-resized');
    }

    protected function getExportableCrossword(): Crossword
    {
        $crossword = $this->crossword;
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
            'puz' => 'canExportPuz',
            'jpz' => 'canExportJpz',
            'pdf' => 'canExportPdf',
        ];
    }

    protected function onExportUpgradeRequired(string $format): void
    {
        $this->upgradeFeature = 'export';
        $this->showUpgradeModal = true;
    }
}
?>

<div
    x-data="crosswordGrid({
            width: @js($width),
            height: @js($height),
            grid: @js($grid),
            solution: @js($solution),
            styles: @js($styles ?? []),
            cluesAcross: @js($cluesAcross),
            cluesDown: @js($cluesDown),
            minAnswerLength: @js($minAnswerLength),
            prefilled: @js($prefilled),
        })"
    x-on:saved.window="onSaved()"
    x-on:prefilled-saved.window="onSaved()"
    x-on:grid-resized.window="onGridResized()"
    x-on:settings-updated.window="onSettingsUpdated()"
    x-on:highlight-incomplete.window="highlightIncomplete($event.detail.checks)"
    class="flex h-full flex-col"
>
    {{-- Toolbar --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        {{-- Title --}}
        <div class="flex flex-1 items-center gap-2">
            <flux:input
                size="sm"
                placeholder="{{ __('Puzzle title') }}"
                wire:model.blur="title"
                wire:change="saveMetadata"
                class="max-w-48"
            />
        </div>

        <div class="flex items-center gap-2">
            {{-- Save status --}}
            <div class="flex items-center gap-1 pl-2 text-sm text-zinc-400">
                <template x-if="saving">
                    <span>{{ __('Saving...') }}</span>
                </template>
                <template x-if="showSaved">
                        <span class="text-emerald-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="inline size-4" viewBox="0 0 20 20"
                                 fill="currentColor"><path fill-rule="evenodd"
                                                           d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                                           clip-rule="evenodd"/></svg>
                            {{ __('Saved') }}
                        </span>
                </template>
            </div>

            {{-- Mode toggle --}}
            <div class="flex rounded-lg border border-zinc-200 dark:border-zinc-700">
                <span
                    class="rounded-l-lg bg-zinc-800 px-3 py-1 text-sm font-medium text-white dark:bg-zinc-200 dark:text-zinc-900">{{ __('Edit') }}</span>
                <a
                    href="{{ route('crosswords.solver', $crosswordId) }}"
                    wire:navigate
                    class="rounded-r-lg px-3 py-1 text-sm font-medium text-zinc-600 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                >{{ __('Solve') }}</a>
            </div>

            {{-- Symmetry --}}
            <flux:tooltip content="{{ __('Rotational symmetry') }}">
                <button
                    x-on:click="symmetry = !symmetry"
                    :class="symmetry ? 'bg-zinc-800 text-white dark:bg-zinc-200 dark:text-zinc-900' : 'text-zinc-500 dark:text-zinc-400'"
                    class="rounded-lg p-1.5 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/>
                        <path d="M21 3v5h-5"/>
                    </svg>
                </button>
            </flux:tooltip>

            {{-- Fill Grid dropdown --}}
            <flux:dropdown position="bottom" align="end">
                <flux:tooltip content="{{ __('Auto-fill grid') }}">
                    <flux:button variant="ghost" size="sm" icon="sparkles" x-bind:disabled="fillInProgress">
                        <span x-show="!fillInProgress">{{ __('Fill') }}</span>
                        <span x-show="fillInProgress" x-cloak>
                            <flux:icon.loading class="size-4" />
                        </span>
                    </flux:button>
                </flux:tooltip>
                <flux:menu>
                    <flux:menu.item x-on:click="quickFill()" x-bind:disabled="fillInProgress">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M11.983 1.907a.75.75 0 0 0-1.292-.657l-8.5 9.5A.75.75 0 0 0 2.75 12h6.572l-1.305 6.093a.75.75 0 0 0 1.292.657l8.5-9.5A.75.75 0 0 0 17.25 8h-6.572l1.305-6.093Z" />
                            </svg>
                            {{ __('Quick Fill') }}
                        </div>
                        <span class="text-xs text-zinc-400 mx-3">{{ __('Dictionary-based') }}</span>
                    </flux:menu.item>
                    <flux:menu.item x-on:click="aiFill()" x-bind:disabled="fillInProgress" :class="! Auth::user()->isPro() ? 'opacity-60' : ''">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-purple-500" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M15.98 1.804a1 1 0 0 0-1.96 0l-.24 1.192a1 1 0 0 1-.784.785l-1.192.238a1 1 0 0 0 0 1.962l1.192.238a1 1 0 0 1 .785.785l.238 1.192a1 1 0 0 0 1.962 0l.238-1.192a1 1 0 0 1 .785-.785l1.192-.238a1 1 0 0 0 0-1.962l-1.192-.238a1 1 0 0 1-.785-.785l-.238-1.192ZM6.949 5.684a1 1 0 0 0-1.898 0l-.683 2.051a1 1 0 0 1-.633.633l-2.051.683a1 1 0 0 0 0 1.898l2.051.683a1 1 0 0 1 .633.633l.683 2.051a1 1 0 0 0 1.898 0l.683-2.051a1 1 0 0 1 .633-.633l2.051-.683a1 1 0 0 0 0-1.898l-2.051-.683a1 1 0 0 1-.633-.633l-.683-2.051ZM15.98 13.804a1 1 0 0 0-1.96 0l-.24 1.192a1 1 0 0 1-.784.785l-1.192.238a1 1 0 0 0 0 1.962l1.192.238a1 1 0 0 1 .785.785l.238 1.192a1 1 0 0 0 1.962 0l.238-1.192a1 1 0 0 1 .785-.785l1.192-.238a1 1 0 0 0 0-1.962l-1.192-.238a1 1 0 0 1-.785-.785l-.238-1.192Z" />
                            </svg>
                            {{ __('AI Fill') }}
                            @unless (Auth::user()->isPro())
                                <flux:badge color="purple" size="sm">{{ __('Pro') }}</flux:badge>
                            @endunless
                        </div>
                        <span class="text-xs text-zinc-400 mx-3">{{ __('Thematic, Claude-powered') }}</span>
                    </flux:menu.item>
                    <flux:separator />
                    <flux:menu.item x-on:click="aiGenerateClues()" x-bind:disabled="fillInProgress || hasUnfilledSlots" :class="! Auth::user()->isPro() ? 'opacity-60' : ''">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 2c-2.236 0-4.43.18-6.57.524C1.993 2.755 1 4.014 1 5.426v5.148c0 1.413.993 2.67 2.43 2.902 1.168.188 2.352.327 3.55.414.28.02.521.18.642.413l1.713 3.293a.75.75 0 0 0 1.33 0l1.713-3.293a.783.783 0 0 1 .642-.413 41.102 41.102 0 0 0 3.55-.414c1.437-.231 2.43-1.49 2.43-2.902V5.426c0-1.413-.993-2.67-2.43-2.902A41.289 41.289 0 0 0 10 2ZM6.75 6a.75.75 0 0 0 0 1.5h6.5a.75.75 0 0 0 0-1.5h-6.5ZM6 9.25a.75.75 0 0 1 .75-.75h2.5a.75.75 0 0 1 0 1.5h-2.5A.75.75 0 0 1 6 9.25Z" clip-rule="evenodd" />
                            </svg>
                            {{ __('AI Generate Clues') }}
                            @unless (Auth::user()->isPro())
                                <flux:badge color="blue" size="sm">{{ __('Pro') }}</flux:badge>
                            @endunless
                        </div>
                        <div class="text-xs text-zinc-400 mx-3">{{ __('Write clues with AI') }}</div>
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            {{-- Clear dropdown --}}
            <flux:dropdown position="bottom" align="end">
                <flux:tooltip content="{{ __('Clear Options') }}">
                    <flux:button variant="ghost" size="sm" icon="x-mark"/>
                </flux:tooltip>
                <flux:menu>
                    <flux:menu.item x-on:click="clearLetters()">{{ __('Clear letters') }}</flux:menu.item>
                    <flux:menu.item x-on:click="clearAll()"
                                    class="text-red-600 dark:text-red-400">{{ __('Clear everything') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            {{-- Publish toggle --}}
            <flux:tooltip content="{{ $isPublished ? __('Unpublish puzzle') : __('Publish for others to solve') }}">
                <flux:button
                    variant="{{ $isPublished ? 'primary' : 'ghost' }}"
                    size="sm"
                    icon="{{ $isPublished ? 'eye' : 'eye-slash' }}"
                    wire:click="attemptPublish"
                />
            </flux:tooltip>

            {{-- Export --}}
            <flux:dropdown position="bottom" align="end">
                <flux:button variant="ghost" size="sm" icon="arrow-down-tray">
                    {{ __('Export') }}
                </flux:button>
                <flux:menu>
                    <flux:menu.item wire:click="attemptExport('ipuz')">{{ __('.ipuz') }}</flux:menu.item>
                    <flux:menu.item wire:click="attemptExport('puz')" :class="! Auth::user()->planLimits()->canExportPuz() ? 'opacity-60' : ''">
                        {{ __('.puz (Across Lite)') }}
                        @unless (Auth::user()->planLimits()->canExportPuz())
                            <flux:badge color="purple" size="sm">{{ __('Pro') }}</flux:badge>
                        @endunless
                    </flux:menu.item>
                    <flux:menu.item wire:click="attemptExport('jpz')" :class="! Auth::user()->planLimits()->canExportJpz() ? 'opacity-60' : ''">
                        {{ __('.jpz (Crossword Compiler)') }}
                        @unless (Auth::user()->planLimits()->canExportJpz())
                            <flux:badge color="purple" size="sm">{{ __('Pro') }}</flux:badge>
                        @endunless
                    </flux:menu.item>
                    <flux:menu.item wire:click="attemptExport('pdf')" :class="! Auth::user()->planLimits()->canExportPdf() ? 'opacity-60' : ''">
                        {{ __('.pdf (Print-Ready)') }}
                        @unless (Auth::user()->planLimits()->canExportPdf())
                            <flux:badge color="purple" size="sm">{{ __('Pro') }}</flux:badge>
                        @endunless
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            {{-- Settings --}}
            <flux:tooltip content="{{ __('Puzzle settings') }}">
                <flux:button variant="ghost" size="sm" icon="cog-6-tooth" wire:click="$set('showSettingsModal', true)"/>
            </flux:tooltip>
        </div>
    </div>

    {{-- The enum owns both the explicit mapping and the fallback. If no
         layout is picked, CrosswordLayout::auto() picks the best-fit case for
         the current grid width. --}}
    @include(($this->layout ?? CrosswordLayout::auto($this->width))->partial())

    {{-- Publish Warning Modal --}}
    <flux:modal wire:model="showPublishWarning">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Publish Incomplete Puzzle?') }}</flux:heading>
            <flux:text>{{ __('This puzzle is missing the following:') }}</flux:text>

            <ul class="space-y-1.5 text-sm">
                @foreach($incompleteChecks as $check)
                    <li class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 20 20"
                             fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                                  clip-rule="evenodd"/>
                        </svg>
                        @switch($check)
                            @case('title')
                                {{ __('Puzzle title') }}
                                @break
                            @case('author')
                                {{ __('Constructor name') }}
                                @break
                            @case('fill')
                                {{ __('Not all cells have letters') }}
                                @break
                            @case('clues_across')
                                {{ __('Missing across clues') }}
                                @break
                            @case('clues_down')
                                {{ __('Missing down clues') }}
                                @break
                        @endswitch
                    </li>
                @endforeach
            </ul>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelPublish">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="togglePublished">{{ __('Publish Anyway') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Export Warning Modal --}}
    <flux:modal wire:model="showExportWarning">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Export Warning') }}</flux:heading>
            <flux:text>{{ __('This crossword uses features not fully supported by :format export:', ['format' => '.' . $pendingExportFormat]) }}</flux:text>

            <ul class="space-y-1.5 text-sm">
                @foreach($exportWarnings as $warning)
                    <li class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 20 20"
                             fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                                  clip-rule="evenodd"/>
                        </svg>
                        {{ __($warning) }}
                    </li>
                @endforeach
            </ul>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelExport">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="confirmExport">{{ __('Export Anyway') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Upgrade Modal --}}
    <flux:modal wire:model="showUpgradeModal">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-purple-600 dark:text-purple-400" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M15.98 1.804a1 1 0 0 0-1.96 0l-.24 1.192a1 1 0 0 1-.784.785l-1.192.238a1 1 0 0 0 0 1.962l1.192.238a1 1 0 0 1 .785.785l.238 1.192a1 1 0 0 0 1.962 0l.238-1.192a1 1 0 0 1 .785-.785l1.192-.238a1 1 0 0 0 0-1.962l-1.192-.238a1 1 0 0 1-.785-.785l-.238-1.192ZM6.949 5.684a1 1 0 0 0-1.898 0l-.683 2.051a1 1 0 0 1-.633.633l-2.051.683a1 1 0 0 0 0 1.898l2.051.683a1 1 0 0 1 .633.633l.683 2.051a1 1 0 0 0 1.898 0l.683-2.051a1 1 0 0 1 .633-.633l2.051-.683a1 1 0 0 0 0-1.898l-2.051-.683a1 1 0 0 1-.633-.633l-.683-2.051ZM15.98 13.804a1 1 0 0 0-1.96 0l-.24 1.192a1 1 0 0 1-.784.785l-1.192.238a1 1 0 0 0 0 1.962l1.192.238a1 1 0 0 1 .785.785l.238 1.192a1 1 0 0 0 1.962 0l.238-1.192a1 1 0 0 1 .785-.785l1.192-.238a1 1 0 0 0 0-1.962l-1.192-.238a1 1 0 0 1-.785-.785l-.238-1.192Z" />
                    </svg>
                </div>
                <flux:heading size="lg">{{ __('Upgrade to Pro') }}</flux:heading>
            </div>

            <flux:text>
                @if ($upgradeFeature === 'ai_fill')
                    {{ __('AI Fill uses Claude to intelligently fill your grid with thematic words. Upgrade to Pro to unlock this feature.') }}
                @elseif ($upgradeFeature === 'ai_clues')
                    {{ __('AI Generate Clues writes creative, high-quality clues for every word in your puzzle. Upgrade to Pro to unlock this feature.') }}
                @elseif ($upgradeFeature === 'export')
                    {{ __('Export your puzzles to .puz, .jpz, and PDF formats for sharing and printing. Upgrade to Pro to unlock this feature.') }}
                @else
                    {{ __('Unlock AI-powered tools to build better puzzles faster. Upgrade to Pro to get started.') }}
                @endif
            </flux:text>

            <ul class="space-y-2 text-sm">
                <li class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0 text-purple-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    {{ __('50 AI Fills per month') }}
                </li>
                <li class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0 text-purple-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    {{ __('50 AI Clue Generations per month') }}
                </li>
                <li class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0 text-purple-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    {{ __('Export to .puz, .jpz, and PDF') }}
                </li>
                <li class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0 text-purple-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    {{ __('Unlimited puzzles') }}
                </li>
                <li class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0 text-purple-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    {{ __('Constructor analytics dashboard') }}
                </li>
            </ul>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showUpgradeModal', false)">{{ __('Maybe Later') }}</flux:button>
                <flux:button :href="route('billing.index')" wire:navigate variant="primary">
                    {{ __('Upgrade Now') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Settings Modal --}}
    <flux:modal wire:model="showSettingsModal">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Puzzle Settings') }}</flux:heading>

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
                <flux:label>{{ __('Minimum Answer Length') }}</flux:label>
                <flux:input type="number" wire:model="minAnswerLength" min="1" max="15"/>
                <flux:description>{{ __('Shortest allowed word length in the grid.') }}</flux:description>
                <flux:error name="minAnswerLength"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Layout') }}</flux:label>
                <flux:description>{{ __('How the grid and clues are arranged on screen.') }}</flux:description>
                @php $selectedLayout = $this->layout ?? CrosswordLayout::auto($this->width); @endphp
                <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-5">
                    @foreach (CrosswordLayout::ordered() as $case)
                        <button
                            type="button"
                            wire:click="$set('layout', {{ $case->value }})"
                            @class([
                                'group flex flex-col items-center gap-1.5 rounded-lg border p-2 text-left transition-colors',
                                'border-blue-500 bg-blue-50 ring-1 ring-blue-500 dark:border-blue-400 dark:bg-blue-950/40 dark:ring-blue-400' => $selectedLayout === $case,
                                'border-zinc-200 hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-500 dark:hover:bg-zinc-800' => $selectedLayout !== $case,
                            ])
                            aria-pressed="{{ $selectedLayout === $case ? 'true' : 'false' }}"
                            title="{{ $case->label() }}"
                        >
                            <div class="w-full overflow-hidden rounded">
                                @include('partials.layout-icon', ['case' => $case])
                            </div>
                            <span class="line-clamp-2 text-center text-[10px] leading-tight text-zinc-600 dark:text-zinc-400">{{ $case->label() }}</span>
                        </button>
                    @endforeach
                </div>
                <flux:error name="layout"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Notes') }}</flux:label>
                <flux:textarea wire:model="notes" placeholder="{{ __('Notes for solvers (shown before solving)') }}"
                               rows="3"/>
                <flux:error name="notes"/>
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
                        class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
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
                            <div class="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                                {{ __('Suggested') }}
                            </div>
                            @foreach($suggestions as $name)
                                <button
                                    type="button"
                                    wire:click="addStandardTag(@js($name))"
                                    x-on:click="open = false"
                                    class="flex w-full items-center px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-700"
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
                                <div class="px-3 py-2 text-sm text-zinc-400">{{ __('No tags found') }}</div>
                            @endif
                        @endif
                    </div>
                </div>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Secret Theme') }}</flux:label>
                <flux:textarea wire:model="secretTheme"
                               placeholder="{{ __('A theme hint only used to guide AI Autofill (e.g. "80s movies", "things that fly"). Not shown to solvers.') }}"
                               rows="2"/>
                <flux:description>{{ __('Only used by AI Autofill to pick the best fill. Never shown to solvers.') }}</flux:description>
                <flux:error name="secretTheme"/>
            </flux:field>

            <flux:separator/>

            <div>
                <flux:heading size="sm">{{ __('Grid Size') }}</flux:heading>
                <flux:text size="sm"
                           class="mt-1">{{ __('Existing content will be preserved where dimensions overlap. Clues will be reset.') }}</flux:text>

                <div class="mt-3 grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Width') }}</flux:label>
                        <flux:input type="number" wire:model="resizeWidth" min="3" max="30"/>
                        <flux:error name="resizeWidth"/>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Height') }}</flux:label>
                        <flux:input type="number" wire:model="resizeHeight" min="3" max="30"/>
                        <flux:error name="resizeHeight"/>
                    </flux:field>
                </div>

                <div class="mt-3 flex justify-end">
                    <flux:button variant="danger" size="sm"
                                 wire:click="resizeGrid">{{ __('Resize Grid') }}</flux:button>
                </div>
            </div>

            <flux:separator/>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showSettingsModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveMetadata">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
