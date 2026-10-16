<?php

use App\Enums\CrosswordLayout;
use App\Enums\PuzzleType;
use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\Tag;
use App\Notifications\NewPuzzlePublished;
use App\Services\ClueHarvester;
use App\Services\ClueQualityChecker;
use App\Livewire\Concerns\ExportsCrossword;
use Illuminate\Support\Facades\Notification;
use CrosswordBuilder\CrosswordIO\GridNumberer;
use App\Services\WordSuggester;
use App\Services\DifficultyRater;
use App\Services\GridFiller;
use App\Services\AiFillPicker;
use App\Services\AiClueGenerator;
use App\Support\AiUsageTracker;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Crossword Editor')]
class extends Component {
    use ExportsCrossword;
    use WithFileUploads;

    /** Wall-clock budget shared by all Quick Fill attempts in one request. */
    private const int HEURISTIC_FILL_BUDGET_SECONDS = 18;

    /** Wall-clock budget shared by all candidate-generation attempts in one AI fill request. */
    private const int AI_FILL_CANDIDATE_BUDGET_SECONDS = 15;

    /** Cap on any single GridFiller attempt. */
    private const int FILL_ATTEMPT_TIMEOUT_SECONDS = 10;

    /** Raised max_execution_time for fill requests; headroom only, the budgets above bound the work. */
    private const int FILL_EXECUTION_TIME_LIMIT_SECONDS = 90;

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

    public string $metaAnswerPrompt = '';
    /** @var list<string> */
    public array $metaAnswers = [];
    public bool $metaAnswerReveal = true;
    public string $newMetaAnswer = '';
    public ?CrosswordLayout $layout = null;
    public int $minAnswerLength = 3;

    public ?string $cellColor = null;
    public ?string $blockColor = null;
    public ?string $circleColor = null;
    public ?string $letterColor = null;
    public ?string $lineColor = null;

    public bool $isPublished = false;
    public bool $allowEmbed = true;
    public bool $freestyleLocked = false;
    public PuzzleType $puzzleType = PuzzleType::Standard;

    public bool $showPublishWarning = false;
    public array $incompleteChecks = [];

    /**
     * Exactly which slots stand between the puzzle and completion, shown
     * under "See details" in the publish warning. Each entry names a slot by
     * number and direction with its current letter pattern (? for a blank).
     *
     * @var array{answers: list<array{direction: string, number: int, pattern: string}>, clues: list<array{direction: string, number: int, pattern: string}>}
     */
    public array $incompleteDetails = ['answers' => [], 'clues' => []];

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
        $this->metaAnswerPrompt = $crossword->meta_answer_prompt ?? '';
        $this->metaAnswers = $crossword->meta_answers ?? [];
        $this->metaAnswerReveal = $crossword->meta_answer_reveal ?? true;
        $this->layout = $crossword->layout;
        $this->minAnswerLength = $crossword->metadata['min_answer_length'] ?? 3;
        $colors = $crossword->metadata['colors'] ?? [];
        $this->cellColor = $colors['cell'] ?? null;
        $this->blockColor = $colors['block'] ?? null;
        $this->circleColor = $colors['circle'] ?? null;
        $this->letterColor = $colors['letter'] ?? null;
        $this->lineColor = $colors['line'] ?? null;
        $this->isPublished = $crossword->is_published;
        $this->allowEmbed = (bool) $crossword->allow_embed;
        $this->freestyleLocked = $crossword->freestyle_locked ?? false;
        $this->puzzleType = $crossword->puzzle_type;
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

        // Keep Livewire's in-memory snapshot in sync with what was just
        // persisted, so subsequent server-side actions (heuristicFill, aiFill,
        // attemptPublish, etc.) see the latest grid even before a page reload.
        $this->grid = $grid;
        $this->solution = $solution;
        $this->styles = $styles;
        $this->cluesAcross = $cluesAcross;
        $this->cluesDown = $cluesDown;

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
            'title'            => ['nullable', 'string', 'max:255'],
            'author'           => ['nullable', 'string', 'max:255'],
            'copyright'        => ['nullable', 'string', 'max:255'],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'secretTheme'      => ['nullable', 'string', 'max:500'],
            'metaAnswerPrompt' => ['nullable', 'string', 'max:500'],
            'metaAnswers'      => ['nullable', 'array', 'max:10'],
            'metaAnswers.*'    => ['required', 'string', 'max:255'],
            'minAnswerLength'  => ['required', 'integer', 'min:1', 'max:15'],
            'cellColor'        => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'blockColor'       => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'circleColor'      => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'letterColor'      => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'lineColor'        => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $crossword = $this->crossword;
        $this->authorize('update', $crossword);

        $metadata = $crossword->metadata ?? [];
        $metadata['min_answer_length'] = $this->minAnswerLength;

        $colors = array_filter([
            'cell'   => $this->cellColor,
            'block'  => $this->blockColor,
            'circle' => $this->circleColor,
            'letter' => $this->letterColor,
            'line'   => $this->lineColor,
        ]);
        if ($colors !== []) {
            $metadata['colors'] = $colors;
        } else {
            unset($metadata['colors']);
        }

        $filteredMetaAnswers = array_values(array_filter(
            array_map('trim', $this->metaAnswers),
            fn (string $a) => $a !== '',
        ));

        $crossword->update([
            'title'               => $this->title,
            'author'              => $this->author,
            'copyright'           => $this->copyright,
            'notes'               => $this->notes,
            'secret_theme'        => $this->secretTheme !== '' ? $this->secretTheme : null,
            'meta_answer_prompt'  => $this->metaAnswerPrompt !== '' ? $this->metaAnswerPrompt : null,
            'meta_answers'        => ! empty($filteredMetaAnswers) ? $filteredMetaAnswers : null,
            'meta_answer_reveal'  => $this->metaAnswerReveal,
            'layout'              => $this->layout,
            'metadata'            => $metadata,
            'allow_embed'         => $this->allowEmbed,
        ]);

        $crossword->tags()->sync($this->tagIds);

        $this->showSettingsModal = false;
        $this->dispatch('settings-updated');
        $this->dispatch('saved');
    }

    public function addMetaAnswer(): void
    {
        $answer = trim($this->newMetaAnswer);
        if ($answer === '' || count($this->metaAnswers) >= 10) {
            return;
        }

        $this->metaAnswers[] = $answer;
        $this->newMetaAnswer = '';
    }

    public function removeMetaAnswer(int $index): void
    {
        unset($this->metaAnswers[$index]);
        $this->metaAnswers = array_values($this->metaAnswers);
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
        $this->incompleteDetails = $this->collectIncompleteDetails($crossword, $this->incompleteChecks);
        $this->showPublishWarning = true;
    }

    /**
     * Walk the numbered slots and pick out the answers still holding blank
     * letters and the clue slots without text, but only for the checks that
     * actually failed (a freestyle grid may publish with empty cells).
     *
     * @param  list<string>  $failedChecks
     * @return array{answers: list<array{direction: string, number: int, pattern: string}>, clues: list<array{direction: string, number: int, pattern: string}>}
     */
    private function collectIncompleteDetails(Crossword $crossword, array $failedChecks): array
    {
        $numbered = app(GridNumberer::class)->number(
            $crossword->grid,
            $crossword->width,
            $crossword->height,
            $crossword->styles ?? [],
            $this->minAnswerLength,
        );

        $answers = [];
        $clues = [];

        foreach (['across', 'down'] as $direction) {
            $clueMap = collect($direction === 'across' ? $crossword->clues_across ?? [] : $crossword->clues_down ?? [])
                ->keyBy('number');

            foreach ($numbered[$direction] as $slot) {
                $pattern = GridFiller::getPattern($crossword->solution ?? [], $slot, $direction);
                $entry = [
                    'direction' => $direction,
                    'number' => $slot['number'],
                    'pattern' => str_replace('_', '?', $pattern),
                ];

                if (in_array('fill', $failedChecks, true) && str_contains($pattern, '_')) {
                    $answers[] = $entry;
                }

                $clueText = $clueMap->get($slot['number'])['clue'] ?? '';
                if (in_array("clues_{$direction}", $failedChecks, true) && trim((string) $clueText) === '') {
                    $clues[] = $entry;
                }
            }
        }

        return ['answers' => $answers, 'clues' => $clues];
    }

    /**
     * Freestyle phase 2: lock the grid, void unused cells, and reveal clues.
     * Only valid for Freestyle puzzles.
     */
    public function lockFreestyleGrid(): void
    {
        $crossword = $this->crossword;
        $this->authorize('update', $crossword);

        if ($crossword->puzzle_type !== PuzzleType::Freestyle) {
            return;
        }

        $crossword->convertEmptyCellsToVoid();
        $crossword->update(['freestyle_locked' => true]);
        $crossword->refresh();

        $this->freestyleLocked = true;
        $this->grid = $crossword->grid;
        $this->solution = $crossword->solution;
        $this->cluesAcross = $crossword->clues_across ?? [];
        $this->cluesDown = $crossword->clues_down ?? [];

        $this->dispatch('freestyle-locked', locked: true);
    }

    /**
     * Freestyle phase 1: restore voided cells back to empty so the user can
     * keep editing the grid. Hides the clues panel again.
     */
    public function unlockFreestyleGrid(): void
    {
        $crossword = $this->crossword;
        $this->authorize('update', $crossword);

        if ($crossword->puzzle_type !== PuzzleType::Freestyle) {
            return;
        }

        $crossword->restoreVoidCellsToEmpty();
        $crossword->update(['freestyle_locked' => false]);
        $crossword->refresh();

        $this->freestyleLocked = false;
        $this->grid = $crossword->grid;
        $this->solution = $crossword->solution;
        $this->cluesAcross = $crossword->clues_across ?? [];
        $this->cluesDown = $crossword->clues_down ?? [];

        $this->dispatch('freestyle-locked', locked: false);
    }

    public function togglePublished(): void
    {
        $crossword = $this->crossword;
        $this->authorize('update', $crossword);

        $this->isPublished = !$this->isPublished;

        if ($this->isPublished) {
            $this->authorize('publish', $crossword);
        }

        $crossword->update(['is_published' => $this->isPublished]);

        $harvester = app(ClueHarvester::class);

        if ($this->isPublished) {
            $harvester->harvest($crossword);

            $rater = app(DifficultyRater::class);
            $rating = $rater->rate($crossword);
            $crossword->update([
                'difficulty_score' => $rating['score'],
                'difficulty_label' => $rating['label'],
            ]);

            $user = Auth::user();
            $followers = $user->followers;
            if ($followers->isNotEmpty()) {
                Notification::send($followers, new NewPuzzlePublished($crossword, $user));
            }
        } else {
            $harvester->purge($crossword);
        }

        $this->showPublishWarning = false;
        $this->incompleteChecks = [];
        $this->incompleteDetails = ['answers' => [], 'clues' => []];
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
            ->approved()
            ->where(fn($q) => $q->whereNull('crossword_id')->orWhere('crossword_id', '!=', $this->crosswordId))
            ->with(['user:id,name', 'crossword:id,title'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn(ClueEntry $entry) => [
                'clue'   => $entry->clue,
                'author' => $entry->user->name ?? 'Unknown',
                'puzzle' => $entry->crossword?->displayTitle() ?? 'Untitled',
            ])
            ->all();
    }

    /**
     * Check the puzzle's clues for quality issues. Answers come from the
     * Alpine grid state (null while a slot is unfilled), so the results
     * track unsaved edits.
     *
     * @param  list<array{direction: string, number: int, clue: string|null, answer: string|null}>  $clues
     * @return array<string, list<array{code: string, severity: string, message: string}>>
     */
    #[Renderless]
    public function checkClueQuality(array $clues): array
    {
        $clues = collect($clues)
            ->take(2000)
            ->filter(fn($clue) => is_array($clue) && in_array($clue['direction'] ?? null, ['across', 'down'], true))
            ->map(fn(array $clue) => [
                'direction' => $clue['direction'],
                'number'    => (int) ($clue['number'] ?? 0),
                'clue'      => mb_substr((string) ($clue['clue'] ?? ''), 0, 1000),
                'answer'    => isset($clue['answer']) ? mb_substr((string) $clue['answer'], 0, 100) : null,
            ])
            ->values()
            ->all();

        return app(ClueQualityChecker::class)->checkPuzzle($clues);
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
     * Fill empty grid slots using heuristic backtracking. Each attempt uses a
     * fresh random seed so the search explores different orderings; if one
     * attempt fails (or only partially fills), retry up to 2 more times and
     * keep the best result.
     *
     * All attempts share a single wall-clock budget so the request stays well
     * inside PHP's max_execution_time regardless of how many retries run.
     *
     * `$grid` / `$styles` come straight from the Alpine state — `$this->grid`
     * is only refreshed on mount/resize, so it can be stale relative to the
     * user's most recent block/void/bar edits.
     *
     * @return array{success: bool, fills: list<array{direction: string, number: int, word: string}>, message: string}
     */
    public function heuristicFill(array $solution, ?array $grid = null, ?array $styles = null): array
    {
        $crossword = $this->crossword;
        $this->authorize('update', $crossword);

        $this->extendExecutionTimeForFill();

        $filler = app(GridFiller::class);
        $best = null;
        $effectiveGrid = $grid ?? $this->grid;
        $effectiveStyles = $styles ?? $this->styles ?? [];
        $deadline = microtime(true) + self::HEURISTIC_FILL_BUDGET_SECONDS;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $remaining = (int) floor($deadline - microtime(true));
            if ($remaining < 1) {
                break;
            }

            $result = $filler->fill(
                $effectiveGrid,
                $solution,
                $this->width,
                $this->height,
                $effectiveStyles,
                $this->minAnswerLength,
                timeout: min(self::FILL_ATTEMPT_TIMEOUT_SECONDS, $remaining),
                seed: random_int(1, PHP_INT_MAX),
            );

            if ($result['success']) {
                return $result;
            }

            if ($best === null || count($result['fills']) > count($best['fills'])) {
                $best = $result;
            }
        }

        return $best;
    }

    /**
     * Give fill requests headroom beyond the default 30s max_execution_time.
     * The fill budgets below are what actually bound the work; this only stops
     * a slow database or AI round-trip from turning into a fatal error.
     *
     * Skipped on the CLI so the test runner's own (unlimited) time limit is
     * left alone.
     */
    private function extendExecutionTimeForFill(): void
    {
        if (PHP_SAPI !== 'cli') {
            set_time_limit(self::FILL_EXECUTION_TIME_LIMIT_SECONDS);
        }
    }

    /**
     * Fill empty grid slots by generating several heuristic fills and letting AI pick the best.
     *
     * `$grid` / `$styles` come from the Alpine state — see heuristicFill() for why.
     *
     * @return array{success: bool, fills: list<array{direction: string, number: int, word: string}>, message: string, upgrade?: bool}
     */
    public function aiFill(array $solution, ?array $grid = null, ?array $styles = null): array
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
                    : __('AI Autofill is a thank-you for supporters. Support Crossword Builder to unlock it.'),
                'upgrade' => ! $user->isPro(),
            ];
        }

        if (trim($this->secretTheme) === '' && trim($this->title) === '') {
            return [
                'success' => false,
                'fills' => [],
                'message' => __('Add a puzzle title or an AI Theme Hint in Settings so AI Autofill knows what to aim for.'),
                'needs_theme' => true,
            ];
        }

        $this->extendExecutionTimeForFill();

        $filler = app(GridFiller::class);
        $attempts = 4;
        $perAttemptTimeout = 5;
        $effectiveGrid = $grid ?? $this->grid;
        $effectiveStyles = $styles ?? $this->styles ?? [];
        $deadline = microtime(true) + self::AI_FILL_CANDIDATE_BUDGET_SECONDS;

        $candidates = [];
        $seen = [];
        for ($i = 0; $i < $attempts; $i++) {
            $remaining = (int) floor($deadline - microtime(true));
            if ($remaining < 1) {
                break;
            }

            $result = $filler->fill(
                $effectiveGrid,
                $solution,
                $this->width,
                $this->height,
                $effectiveStyles,
                $this->minAnswerLength,
                timeout: min($perAttemptTimeout, $remaining),
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
                    : __('AI Clue Generation is a thank-you for supporters. Support Crossword Builder to unlock it.'),
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
            'resizeWidth'  => ['required', 'integer', 'min:3', 'max:40'],
            'resizeHeight' => ['required', 'integer', 'min:3', 'max:40'],
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
            gridLocked: @js($freestyleLocked),
            puzzleType: @js($puzzleType->value),
            defaultColors: {
                cell: @js($cellColor),
                block: @js($blockColor),
                circle: @js($circleColor),
                letter: @js($letterColor),
                line: @js($lineColor),
            },
        })"
    x-on:freestyle-locked.window="onFreestyleLocked($event.detail.locked)"
    x-on:keydown.window="handleShortcutKeydown($event)"
    x-on:saved.window="onSaved()"
    x-on:prefilled-saved.window="onSaved()"
    x-on:grid-resized.window="onGridResized()"
    x-on:settings-updated.window="onSettingsUpdated()"
    x-on:highlight-incomplete.window="highlightIncomplete($event.detail.checks)"
    {{-- Full height only from `lg`, where the layouts cap themselves to the
         viewport and scroll internally. Below that the page scrolls, and a
         stretched root would push the stacked clue list away from the grid
         by whatever viewport height a small grid leaves over.

         From `lg` the root is a two-column grid: the settings sidebar
         (editor-settings-pane) takes the first column across both rows, and
         the toolbar and workspace stack in the second. The first track is
         2rem narrower than the sidebar looks because the sidebar pulls
         itself out through main's 2rem start gutter to sit flush against
         the page edge. --}}
    class="flex flex-col lg:grid lg:h-full lg:grid-cols-[18rem_minmax(0,1fr)] lg:grid-rows-[auto_minmax(0,1fr)] lg:gap-x-6"
>
    {{-- Desktop settings sidebar, first so it fills the first grid column. --}}
    @include('partials.editor-settings-pane')

    {{-- Toolbar. From `sm` a three-column grid: the Edit / Solve toggle at
         the left edge of the builder, the editing tools centred, and the save
         status with Publish at the right edge. Below `sm` the three wrap as
         flex rows. --}}
    {{-- Mobile title + settings row, in flow above the toolbar. --}}
    @include('partials.editor-mobile-title-row')

    <div class="mb-4 flex flex-wrap items-center justify-center gap-1.5 sm:grid sm:grid-cols-[1fr_auto_1fr] sm:gap-2" data-test="editor-toolbar">
        {{-- The puzzle title and settings cog are not part of the toolbar: on
             desktop they live in the settings sidebar beside the grid, and on
             mobile in their own row just above (editor-mobile-title-row). --}}
        {{-- Mode toggle, on the left. --}}
        <div class="flex rounded-lg border border-line sm:justify-self-start" data-test="editor-mode-toggle">
            <span
                class="rounded-l-lg bg-zinc-800 px-3 py-1 text-sm font-medium text-white dark:bg-zinc-200 dark:text-zinc-900">{{ __('Edit') }}</span>
            <a
                href="{{ route('crosswords.solver', $crosswordId) }}"
                wire:navigate
                class="rounded-r-lg px-3 py-1 text-sm font-medium text-zinc-700 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
            >{{ __('Solve') }}</a>
        </div>

        {{-- Tools (undo/redo, clear, fill), centred from `sm`. Below `sm`
             the cluster sits on the same row as Edit / Solve, the whole row
             centred with tighter gaps; anything that does not fit wraps
             beneath. --}}
        <div class="flex flex-wrap items-center gap-0.5 sm:justify-center sm:gap-2" data-test="editor-tools-row">
            {{-- Undo / Redo --}}
            <div class="flex items-center">
                <flux:tooltip content="{{ __('Undo') }}" kbd="Ctrl+Z">
                    <button
                        type="button"
                        x-on:click="undo(); $refs.gridContainer?.focus()"
                        :disabled="!canUndo"
                        class="rounded-lg p-1.5 transition-colors disabled:opacity-30"
                        :class="canUndo ? 'text-fg-muted hover:text-zinc-800 dark:hover:text-zinc-200' : 'text-fg-muted'"
                        aria-label="{{ __('Undo') }}"
                        aria-keyshortcuts="Control+Z"
                        data-test="editor-undo-button"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/>
                        </svg>
                    </button>
                </flux:tooltip>

                <flux:tooltip content="{{ __('Redo') }}" kbd="Ctrl+Shift+Z">
                    <button
                        type="button"
                        x-on:click="redo(); $refs.gridContainer?.focus()"
                        :disabled="!canRedo"
                        class="rounded-lg p-1.5 transition-colors disabled:opacity-30"
                        :class="canRedo ? 'text-fg-muted hover:text-zinc-800 dark:hover:text-zinc-200' : 'text-fg-muted'"
                        aria-label="{{ __('Redo') }}"
                        aria-keyshortcuts="Control+Shift+Z"
                        data-test="editor-redo-button"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3L21 13"/>
                        </svg>
                    </button>
                </flux:tooltip>
            </div>

                        {{-- Clear dropdown --}}
            <flux:dropdown position="bottom" align="end">
                <flux:tooltip content="{{ __('Clear Options') }}">
                    <flux:button variant="ghost" size="sm" class="max-sm:px-2!">
                        {{ __('Clear') }}
                    </flux:button>
                </flux:tooltip>
                <flux:menu>
                    <flux:menu.item x-on:click="clearLetters()">{{ __('Clear letters') }}</flux:menu.item>
                    <flux:menu.item x-on:click="clearAll()"
                                    class="text-red-600 dark:text-red-400">{{ __('Reset to blank grid') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            {{-- Fill Grid dropdown --}}
            <flux:dropdown position="bottom" align="end">
                <flux:tooltip content="{{ __('Auto-fill grid') }}">
                    <flux:button variant="ghost" size="sm" icon="sparkles" x-bind:disabled="fillInProgress">
                        <span x-show="!fillInProgress">{{ __('Auto-Fill') }}</span>
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
                        <span class="text-xs text-zinc-500 mx-3">{{ __('Dictionary-based') }}</span>
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
                        <span class="text-xs text-zinc-500 mx-3">{{ __('Thematic, Claude-powered') }}</span>
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
                        <div class="text-xs text-zinc-500 mx-3">{{ __('Write clues with AI') }}</div>
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            {{-- Freestyle: lock the grid (void empty cells) or unlock to keep editing --}}
            @if ($puzzleType === PuzzleType::Freestyle)
                @if ($freestyleLocked)
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="pencil-square"
                        wire:click="unlockFreestyleGrid"
                        wire:confirm="{{ __('Restore voided cells and return to grid editing? Empty cells will reappear.') }}"
                    >{{ __('Edit grid') }}</flux:button>
                @else
                    <flux:button
                        variant="primary"
                        size="sm"
                        icon="lock-closed"
                        wire:click="lockFreestyleGrid"
                    >{{ __('Lock grid & write clues') }}</flux:button>
                @endif
            @endif
        </div>

        {{-- Progress + Publish. The two rings share one pill so the
             cluster reads as a single status readout beside the action it
             gates. Each ring is an SVG circle with pathLength="100", so the
             fill percentage is its dash offset directly. Desktop only: on
             mobile the Publish button beside the clue tabs carries a single
             pooled ring instead. --}}
        <div class="flex shrink-0 items-center gap-2 sm:justify-self-end">
            {{-- Save status. Hidden while idle so it takes no room; when shown
                 it sits right before the progress pill so save state and
                 fill state read as one status group. --}}
            <div class="flex items-center gap-1 text-sm text-zinc-500" x-show="saving || showSaved" data-test="editor-save-status">
                <template x-if="saving">
                    <span>{{ __('Saving...') }}</span>
                </template>
                <template x-if="showSaved">
                    <span class="flex items-center gap-1 text-emerald-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        <span class="max-sm:sr-only">{{ __('Saved') }}</span>
                    </span>
                </template>
            </div>

            {{-- Publish toggle (editor-publish-button), carrying the pooled
                 cells-and-clues completion ring: the toolbar has no separate
                 progress meters. Desktop only whenever the mobile clue tabs
                 render, since they carry their own copy; an unlocked
                 freestyle grid has no tabs yet, so the toolbar button stays
                 on every width. --}}
            @php
                $mobileTabsRender = ! ($puzzleType === PuzzleType::Freestyle && ! $freestyleLocked);
            @endphp
            <div class="{{ $mobileTabsRender ? 'hidden lg:contents' : 'contents' }}" data-test="editor-toolbar-publish">
                @include('partials.editor-publish-button', ['testId' => 'editor-publish-button', 'withRing' => true])
            </div>
        </div>
    </div>

    {{-- Desktop: the workspace is the second row of the root grid, beside the
         settings sidebar and under the toolbar, holding whichever puzzle
         layout is active. Below `lg` the wrapper drops out of the box tree
         (display: contents) and the layout stacks under the toolbar exactly
         as before; settings move to the modal. --}}
    <div class="max-lg:contents lg:flex lg:min-h-0 lg:min-w-0" data-test="editor-workspace">
        {{-- The enum owns both the explicit mapping and the fallback. If no
             layout is picked, CrosswordLayout::auto() picks the best-fit case for
             the current grid width. --}}
        @include(($this->layout ?? CrosswordLayout::auto($this->width))->partial())
    </div>

    {{-- Mobile suggestions bottom sheet (hidden at lg and above, where the
         settings sidebar's Suggestions tab takes over). --}}
    @include('partials.editor-suggestions-sheet')

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

            {{-- See details: the exact slots behind the summary above. Each
                 answer shows its current letters with ? for a blank; each
                 clue slot shows the answer it is waiting on. Collapsed by
                 default so the short summary stays the first thing read. --}}
            @if ($incompleteDetails['answers'] !== [] || $incompleteDetails['clues'] !== [])
                <div x-data="{ showDetails: false }" data-test="publish-warning-details">
                    <button
                        type="button"
                        x-on:click="showDetails = !showDetails"
                        :aria-expanded="showDetails ? 'true' : 'false'"
                        class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                        data-test="publish-warning-details-toggle"
                    >
                        <span x-text="showDetails ? '{{ __('Hide details') }}' : '{{ __('See details') }}'">{{ __('See details') }}</span>
                        <flux:icon.chevron-down variant="mini" class="size-4 transition-transform duration-150" ::class="showDetails ? 'rotate-180' : ''" />
                    </button>

                    <div x-show="showDetails" x-cloak class="border-line bg-elevated mt-3 max-h-64 space-y-4 overflow-y-auto rounded-lg border p-3 text-sm">
                        @foreach ([
                            'answers' => __('Answers with missing letters'),
                            'clues' => __('Clues without text'),
                        ] as $group => $groupLabel)
                            @if ($incompleteDetails[$group] !== [])
                                <div data-test="publish-warning-{{ $group }}">
                                    <div class="text-fg-muted text-xs font-semibold tracking-wide uppercase">
                                        {{ $groupLabel }}
                                        <span class="tnum font-normal">({{ count($incompleteDetails[$group]) }})</span>
                                    </div>
                                    <ul class="mt-1.5 space-y-1">
                                        @foreach ($incompleteDetails[$group] as $slot)
                                            <li class="flex items-baseline gap-2">
                                                <span class="text-fg tnum font-medium">{{ $slot['number'] }} {{ $slot['direction'] === 'across' ? __('Across') : __('Down') }}</span>
                                                <span class="text-fg-muted font-mono text-[13px] tracking-[0.18em]">{{ $slot['pattern'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelPublish">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="togglePublished">{{ __('Publish Anyway') }}</flux:button>
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
                @if ($this->crossword->pdf_image && !$pdfRemoveImage)
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
                <flux:heading size="lg">
                    @if (Auth::user()->isAnonymous())
                        {{ __('Create a free account') }}
                    @else
                        {{ __('Support our work') }}
                    @endif
                </flux:heading>
            </div>

            @if (Auth::user()->isAnonymous())
                <flux:text>
                    @if ($upgradeFeature === 'export')
                        {{ __('Export your puzzles to .puz, .jpz, and PDF formats for sharing and printing. Create a free account to unlock exports.') }}
                    @else
                        {{ __('Create a free account to save your work, publish puzzles, and unlock more tools.') }}
                    @endif
                </flux:text>

                <div class="flex justify-end gap-2">
                    <flux:button wire:click="$set('showUpgradeModal', false)">{{ __('Maybe Later') }}</flux:button>
                    <flux:button :href="route('register')" wire:navigate variant="primary">
                        {{ __('Sign up free') }}
                    </flux:button>
                </div>
            @else
            <flux:text>
                @if ($upgradeFeature === 'ai_fill')
                    {{ __('AI Fill uses Claude to intelligently fill your grid with thematic words. It is one of the extras we offer as a thank-you to the supporters who keep Crossword Builder free for everyone.') }}
                @elseif ($upgradeFeature === 'ai_clues')
                    {{ __('AI Generate Clues writes creative, high-quality clues for every word in your puzzle. It is one of the extras we offer as a thank-you to the supporters who keep Crossword Builder free for everyone.') }}
                @elseif ($upgradeFeature === 'export')
                    {{ __('Export your puzzles to .puz, .jpz, and PDF formats for sharing and printing.') }}
                @else
                    {{ __('AI-powered tools help you build better puzzles faster. They are our thank-you to the supporters who keep Crossword Builder free for everyone.') }}
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
            </ul>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showUpgradeModal', false)">{{ __('Maybe Later') }}</flux:button>
                <flux:button :href="route('billing.index')" wire:navigate variant="primary">
                    {{ __('Support Crossword Builder') }}
                </flux:button>
            </div>
            @endif
        </div>
    </flux:modal>

    {{-- Mobile settings modal (hidden at lg and above, where the sidebar lives). --}}
    @include('partials.editor-settings-modal')
</div>
