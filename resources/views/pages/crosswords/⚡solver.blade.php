<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
use App\Services\AchievementService;
use App\Services\ContestService;
use App\Livewire\Concerns\ExportsCrossword;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Solve Crossword')] class extends Component {
    use ExportsCrossword;

    #[Locked]
    public int $crosswordId;

    #[Locked]
    public int $attemptId;

    #[Locked]
    public bool $isOwner = false;

    #[Locked]
    public bool $isPublished = false;

    #[Locked]
    public int $authorUserId = 0;

    public string $title = '';
    public string $authorName = '';
    public int $width;
    public int $height;
    public array $grid = [];
    public array $solution = [];
    public array $progress = [];
    public array $cluesAcross = [];
    public array $cluesDown = [];
    public ?array $styles = null;
    public ?array $prefilled = null;

    #[Computed]
    public function isLiked(): bool
    {
        return CrosswordLike::where('user_id', Auth::id())
            ->where('crossword_id', $this->crosswordId)
            ->exists();
    }

    #[Computed]
    public function likesCount(): int
    {
        return CrosswordLike::where('crossword_id', $this->crosswordId)->count();
    }

    public array $pencilCells = [];

    public int $elapsedSeconds = 0;

    public bool $isSolved = false;

    public string $commentBody = '';

    public int $commentRating = 0;

    #[Computed]
    public function comments()
    {
        return PuzzleComment::where('crossword_id', $this->crosswordId)
            ->with('user')
            ->latest()
            ->get();
    }

    #[Computed]
    public function averageRating(): ?float
    {
        $avg = PuzzleComment::where('crossword_id', $this->crosswordId)
            ->whereNotNull('rating')
            ->where('rating', '>', 0)
            ->avg('rating');

        return $avg ? round($avg, 1) : null;
    }

    #[Computed]
    public function userComment(): ?PuzzleComment
    {
        return PuzzleComment::where('user_id', Auth::id())
            ->where('crossword_id', $this->crosswordId)
            ->first();
    }

    public function submitComment(): void
    {
        $this->validate([
            'commentBody' => 'required|string|max:1000',
            'commentRating' => 'integer|min:0|max:5',
        ]);

        PuzzleComment::updateOrCreate(
            ['user_id' => Auth::id(), 'crossword_id' => $this->crosswordId],
            [
                'body' => $this->commentBody,
                'rating' => $this->commentRating > 0 ? $this->commentRating : null,
            ],
        );

        $this->commentBody = '';
        $this->commentRating = 0;
        unset($this->comments, $this->averageRating, $this->userComment);
    }

    public function deleteComment(): void
    {
        PuzzleComment::where('user_id', Auth::id())
            ->where('crossword_id', $this->crosswordId)
            ->delete();

        unset($this->comments, $this->averageRating, $this->userComment);
    }

    public function mount(Crossword $crossword): void
    {
        $this->authorize('solve', $crossword);

        $user = Auth::user();
        $this->isOwner = $user->id === $crossword->user_id;
        $this->isPublished = (bool) $crossword->is_published;
        $this->authorUserId = $crossword->user_id;

        // Find or create the user's attempt for this puzzle
        $initialProgress = $crossword->prefilled ?? Crossword::emptySolution($crossword->width, $crossword->height);
        $attempt = PuzzleAttempt::firstOrCreate(
            ['user_id' => $user->id, 'crossword_id' => $crossword->id],
            [
                'progress' => $initialProgress,
                'started_at' => now(),
            ],
        );

        // Set started_at for legacy attempts that don't have it
        if (! $attempt->started_at) {
            $attempt->update(['started_at' => now()]);
        }

        // Merge special prefilled cells into existing progress
        // Single A-Z letters are only set on initial attempt creation,
        // but rebus (multi-char), symbols, and emoji should always be synced
        // since solvers can't edit them and they may have been added after the attempt started
        if ($crossword->prefilled && ! $attempt->wasRecentlyCreated) {
            $progress = $attempt->progress;
            $dirty = false;

            foreach ($crossword->prefilled as $r => $row) {
                foreach ($row as $c => $cell) {
                    if (! filled($cell)) {
                        continue;
                    }

                    $isSimpleLetter = preg_match('/^[A-Z]$/u', $cell);

                    if (! $isSimpleLetter && ($progress[$r][$c] ?? '') !== $cell) {
                        $progress[$r][$c] = $cell;
                        $dirty = true;
                    }
                }
            }

            if ($dirty) {
                $attempt->update(['progress' => $progress]);
            }
        }

        $this->crosswordId = $crossword->id;
        $this->attemptId = $attempt->id;
        $this->title = $crossword->title ?? 'Untitled Puzzle';
        $this->authorName = $crossword->user->name ?? '';
        $this->width = $crossword->width;
        $this->height = $crossword->height;
        $this->grid = $crossword->grid;
        $this->solution = $crossword->solution;
        $this->progress = $attempt->progress;
        $this->cluesAcross = $crossword->clues_across ?? [];
        $this->cluesDown = $crossword->clues_down ?? [];
        $this->styles = $crossword->styles;
        $this->prefilled = $crossword->prefilled;
        $this->pencilCells = $attempt->pencil_cells ?? [];
        $this->elapsedSeconds = $attempt->solve_time_seconds ?? 0;
        $this->isSolved = (bool) $attempt->is_completed;
    }

    public function toggleLike(): void
    {
        $like = CrosswordLike::where('user_id', Auth::id())
            ->where('crossword_id', $this->crosswordId)
            ->first();

        if ($like) {
            $like->delete();
        } else {
            CrosswordLike::create([
                'user_id' => Auth::id(),
                'crossword_id' => $this->crosswordId,
            ]);
        }

        unset($this->isLiked, $this->likesCount);
    }

    public function saveProgress(array $progress, bool $isCompleted = false, int $elapsedSeconds = 0, array $pencilCells = []): void
    {
        $attempt = PuzzleAttempt::findOrFail($this->attemptId);

        if ($attempt->user_id !== Auth::id()) {
            abort(403);
        }

        $this->progress = $progress;
        $this->pencilCells = $pencilCells;

        $data = [
            'progress' => $progress,
            'pencil_cells' => $pencilCells,
            'is_completed' => $isCompleted,
            'solve_time_seconds' => $elapsedSeconds,
        ];

        if ($isCompleted && ! $attempt->completed_at) {
            $data['completed_at'] = now();
            $this->isSolved = true;

            // Process streaks and achievements
            $achievements = app(AchievementService::class)->processSolve(Auth::user(), $elapsedSeconds);

            if (count($achievements) > 0) {
                $this->dispatch('achievements-earned', achievements: collect($achievements)->map(fn ($a) => [
                    'label' => $a->label,
                    'description' => $a->description,
                    'icon' => $a->icon,
                ])->all());
            }

            // Process contest solve if this crossword belongs to any contests
            $crossword = Crossword::find($this->crosswordId);
            if ($crossword && $crossword->contests()->exists()) {
                app(ContestService::class)->processContestSolve(Auth::user(), $crossword);
            }
        }

        $attempt->update($data);

        $this->dispatch('progress-saved');
    }

    protected function getExportableCrossword(): Crossword
    {
        $crossword = Crossword::findOrFail($this->crosswordId);
        $this->authorize('solve', $crossword);

        return $crossword;
    }
}
?>

<div
    x-data="crosswordSolver({
        width: @js($width),
        height: @js($height),
        grid: @js($grid),
        solution: @js($solution),
        progress: @js($progress),
        styles: @js($styles ?? []),
        prefilled: @js($prefilled),
        cluesAcross: @js($cluesAcross),
        cluesDown: @js($cluesDown),
        initialElapsed: @js($elapsedSeconds),
        initialSolved: @js($isSolved),
        initialPencilCells: @js($pencilCells),
    })"
    x-on:progress-saved.window="onSaved()"
    x-on:achievements-earned.window="showAchievements($event.detail.achievements)"
    class="relative flex h-full flex-col"
>
    {{-- Skip to content link --}}
    <a href="#crossword-grid" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:rounded focus:bg-blue-600 focus:px-4 focus:py-2 focus:text-white">
        {{ __('Skip to crossword grid') }}
    </a>

    {{-- Toolbar --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="flex flex-1 items-center gap-3">
            <flux:heading size="lg">{{ $title }}</flux:heading>
            @if(!$isOwner && $authorName)
                <flux:text size="sm" class="text-zinc-400">
                    {{ __('by') }}
                    <a href="{{ route('constructors.show', $authorUserId) }}" wire:navigate class="text-zinc-500 hover:text-blue-600 dark:hover:text-blue-400">{{ $authorName }}</a>
                </flux:text>
            @endif
            <button
                wire:click="toggleLike"
                class="flex items-center gap-1 rounded-lg px-2 py-1 text-sm transition-colors {{ $this->isLiked ? 'text-red-500' : 'text-zinc-400 hover:text-red-400' }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="{{ $this->isLiked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                </svg>
                <span>{{ $this->likesCount }}</span>
            </button>
        </div>

        <div class="flex items-center gap-1">
            {{-- Pencil mode toggle --}}
            <flux:tooltip content="{{ __('Pencil mode (P)') }}">
                <button
                    x-on:click="pencilMode = !pencilMode"
                    :class="pencilMode ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : 'text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200'"
                    class="rounded-lg p-1.5 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
                        <path d="m15 5 4 4"/>
                    </svg>
                </button>
            </flux:tooltip>

            {{-- Timer --}}
            <div class="mr-2 flex items-center gap-1.5 rounded-lg bg-zinc-100 px-2.5 py-1 font-mono text-sm tabular-nums dark:bg-zinc-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                <span x-text="formattedTime()" :class="solved ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-700 dark:text-zinc-300'"></span>
            </div>

            {{-- Mode toggle (only show Edit for owners) --}}
            @if($isOwner)
                <div class="flex rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <a
                        href="{{ route('crosswords.editor', $crosswordId) }}"
                        wire:navigate
                        class="rounded-l-lg px-3 py-1 text-sm font-medium text-zinc-600 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                    >{{ __('Edit') }}</a>
                    <span class="rounded-r-lg bg-zinc-800 px-3 py-1 text-sm font-medium text-white dark:bg-zinc-200 dark:text-zinc-900">{{ __('Solve') }}</span>
                </div>
            @endif

            {{-- Check answers --}}
            <flux:tooltip content="{{ __('Check answers') }}">
                <button
                    x-on:click="checkAnswers()"
                    class="rounded-lg p-1.5 text-zinc-500 transition-colors hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5"/>
                    </svg>
                </button>
            </flux:tooltip>

            {{-- Reveal letter --}}
            <flux:tooltip content="{{ __('Reveal letter') }}">
                <button
                    x-on:click="revealLetter()"
                    class="rounded-lg p-1.5 text-zinc-500 transition-colors hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </flux:tooltip>

            {{-- Clear progress --}}
            <flux:dropdown position="bottom" align="end">
                <flux:button variant="ghost" size="sm" icon="x-mark" />
                <flux:menu>
                    <flux:menu.item x-on:click="clearProgress()">{{ __('Clear all letters') }}</flux:menu.item>
                    <flux:menu.item x-on:click="clearErrors()" class="text-amber-600 dark:text-amber-400">{{ __('Clear incorrect letters') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            {{-- Download for offline --}}
            <flux:dropdown position="bottom" align="end">
                <flux:tooltip content="{{ __('Download for offline solving') }}">
                    <flux:button variant="ghost" size="sm" icon="arrow-down-tray" />
                </flux:tooltip>
                <flux:menu>
                    <flux:menu.item wire:click="attemptExport('ipuz')">{{ __('.ipuz') }}</flux:menu.item>
                    <flux:menu.item wire:click="attemptExport('puz')">{{ __('.puz (Across Lite)') }}</flux:menu.item>
                    <flux:menu.item wire:click="attemptExport('jpz')">{{ __('.jpz (Crossword Compiler)') }}</flux:menu.item>
                    <flux:menu.item wire:click="exportPdf">{{ __('.pdf (Print-Ready)') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            {{-- Embed code (published puzzles only) --}}
            @if($isPublished)
                <div x-data="{ showEmbed: false }">
                    <flux:tooltip content="{{ __('Embed this puzzle') }}">
                        <flux:button variant="ghost" size="sm" icon="code-bracket" x-on:click="showEmbed = true" />
                    </flux:tooltip>

                    <template x-teleport="body">
                        <div x-show="showEmbed" x-cloak x-on:keydown.escape.window="showEmbed = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" x-on:click.self="showEmbed = false">
                            <div class="mx-4 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-800" x-on:click.stop>
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="text-lg font-semibold">{{ __('Embed Puzzle') }}</h3>
                                    <button x-on:click="showEmbed = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                                    </button>
                                </div>

                                {{-- iframe embed --}}
                                <div class="mb-4">
                                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('iframe (recommended)') }}</label>
                                    <div class="relative">
                                        <textarea
                                            readonly
                                            rows="2"
                                            class="w-full rounded-lg border border-zinc-300 bg-zinc-50 px-3 py-2 font-mono text-xs text-zinc-600 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-400"
                                            x-ref="iframeCode"
                                        >&lt;iframe src="{{ route('embed.solver', $crosswordId) }}" width="100%" height="700" frameborder="0" style="border:none;"&gt;&lt;/iframe&gt;</textarea>
                                        <button
                                            x-on:click="navigator.clipboard.writeText($refs.iframeCode.value); $el.textContent = '{{ __('Copied!') }}'; setTimeout(() => $el.textContent = '{{ __('Copy') }}', 2000)"
                                            class="absolute top-2 right-2 rounded bg-zinc-200 px-2 py-0.5 text-xs font-medium text-zinc-600 hover:bg-zinc-300 dark:bg-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-600"
                                        >{{ __('Copy') }}</button>
                                    </div>
                                </div>

                                {{-- Script embed --}}
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Script tag') }}</label>
                                    <div class="relative">
                                        <textarea
                                            readonly
                                            rows="3"
                                            class="w-full rounded-lg border border-zinc-300 bg-zinc-50 px-3 py-2 font-mono text-xs text-zinc-600 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-400"
                                            x-ref="scriptCode"
                                        >&lt;div data-zorbl-embed data-crossword-id="{{ $crosswordId }}" data-api-url="{{ url('/api/embed') }}/"&gt;&lt;/div&gt;
&lt;link rel="stylesheet" href="{{ Vite::asset('resources/css/embed.css') }}"&gt;
&lt;script src="{{ Vite::asset('resources/js/embed.js') }}" defer&gt;&lt;/script&gt;</textarea>
                                        <button
                                            x-on:click="navigator.clipboard.writeText($refs.scriptCode.value); $el.textContent = '{{ __('Copied!') }}'; setTimeout(() => $el.textContent = '{{ __('Copy') }}', 2000)"
                                            class="absolute top-2 right-2 rounded bg-zinc-200 px-2 py-0.5 text-xs font-medium text-zinc-600 hover:bg-zinc-300 dark:bg-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-600"
                                        >{{ __('Copy') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            @endif

            {{-- Save status --}}
            <div class="flex items-center gap-1 pl-2 text-sm text-zinc-400">
                <template x-if="pencilMode && !solved">
                    <span class="mr-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Pencil') }}</span>
                </template>
                <template x-if="saving">
                    <span>{{ __('Saving...') }}</span>
                </template>
                <template x-if="showSaved">
                    <span class="text-emerald-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="inline size-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                        {{ __('Saved') }}
                    </span>
                </template>
                <template x-if="solved">
                    <span class="font-semibold text-emerald-500">{{ __('Solved!') }}</span>
                </template>
            </div>
        </div>
    </div>

    {{-- Main solver layout --}}
    <div class="flex flex-1 gap-4 overflow-hidden max-lg:flex-col lg:max-h-[calc(100dvh-8rem)]">
        {{-- Across clues panel (desktop) --}}
        <div class="hidden w-64 flex-col overflow-hidden lg:flex">
            <flux:heading size="sm" class="mb-2 shrink-0">{{ __('Across') }}</flux:heading>
            <div class="flex-1 space-y-0.5 overflow-y-auto" x-ref="acrossPanel">
                <template x-for="clue in computedCluesAcross" :key="'across-' + clue.number">
                    <div
                        x-on:click="selectClue('across', clue.number)"
                        x-on:focus="selectClue('across', clue.number)"
                        x-on:keydown.tab.prevent="focusNextClue($el, 'across', false)"
                        x-on:keydown.shift.tab.prevent="focusNextClue($el, 'across', true)"
                        :class="activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100 dark:bg-blue-900/40' : 'hover:bg-zinc-100 dark:hover:bg-zinc-700/50'"
                        class="cursor-pointer rounded px-2 py-1"
                        :id="'clue-across-' + clue.number"
                        tabindex="0"
                    >
                        <div class="flex items-start gap-1.5">
                            <span class="mt-px text-xs font-bold text-zinc-500" x-text="clue.number"></span>
                            <div class="flex-1">
                                <span class="text-sm text-zinc-700 dark:text-zinc-300" x-text="clue.clue || '—'"></span>
                                <span class="text-xs text-zinc-400" x-text="'(' + clue.length + ')'"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Screen reader live region for active clue --}}
        <div class="sr-only" aria-live="polite" aria-atomic="true" x-text="activeClueAnnouncement()"></div>

        {{-- Grid --}}
        <div class="flex min-w-0 flex-1 items-start justify-center overflow-hidden">
            <div
                class="relative"
                :style="'width: ' + Math.min(600, width * 40) + 'px;'"
                x-on:keydown="handleKeydown($event)"
                tabindex="0"
                x-ref="gridContainer"
                id="crossword-grid"
                role="grid"
                :aria-label="'Crossword grid, ' + width + ' columns by ' + height + ' rows'"
            >
                <div
                    class="grid border border-zinc-800 dark:border-zinc-300 [--bar-color:var(--color-zinc-800)] dark:[--bar-color:var(--color-zinc-300)]"
                    :style="'grid-template-columns: repeat(' + width + ', 1fr);'"
                >
                    <template x-for="(row, rowIdx) in grid" :key="'row-' + rowIdx">
                        <template x-for="(cell, colIdx) in row" :key="'cell-' + rowIdx + '-' + colIdx">
                            <div
                                x-on:click="selectCell(rowIdx, colIdx)"
                                :class="[cellClasses(rowIdx, colIdx), isVoid(rowIdx, colIdx) ? '' : 'border border-zinc-300 dark:border-zinc-600']"
                                :style="cellBarStyles(rowIdx, colIdx)"
                                class="relative box-border flex aspect-square items-center justify-center select-none"
                                role="gridcell"
                                :aria-selected="rowIdx === selectedRow && colIdx === selectedCol ? 'true' : 'false'"
                                :aria-label="isBlock(rowIdx, colIdx) ? 'Black cell' : (typeof cell === 'number' && cell > 0 ? cell + ' ' : '') + (progress[rowIdx]?.[colIdx] || 'empty') + (isPencil(rowIdx, colIdx) ? ' pencil' : '')"
                            >
                                {{-- Clue number --}}
                                <template x-if="typeof cell === 'number' && cell > 0">
                                    <span
                                        class="absolute top-0 left-0.5 text-zinc-700 dark:text-zinc-400 leading-none"
                                        :style="'font-size: ' + Math.max(8, Math.min(11, 600 / width * 0.22)) + 'px'"
                                        x-text="cell"
                                    ></span>
                                </template>

                                {{-- Circle annotation --}}
                                <template x-if="hasCircle(rowIdx, colIdx)">
                                    <svg class="pointer-events-none absolute inset-0.5 size-[calc(100%-4px)]" viewBox="0 0 100 100">
                                        <circle cx="50" cy="50" r="46" fill="none" stroke="currentColor" stroke-width="2" class="text-zinc-400 dark:text-zinc-500" />
                                    </svg>
                                </template>

                                {{-- Rebus indicator --}}
                                <template x-if="rebusMode && rowIdx === selectedRow && colIdx === selectedCol">
                                    <span class="absolute top-0 right-0.5 text-xs leading-none text-blue-500">R</span>
                                </template>

                                {{-- Letter --}}
                                <span
                                    class="font-semibold uppercase"
                                    :class="letterClass(rowIdx, colIdx)"
                                    :style="letterFontStyle(rowIdx, colIdx)"
                                    x-text="isBlock(rowIdx, colIdx) ? '' : (progress[rowIdx]?.[colIdx] || '')"
                                ></span>

                                {{-- Incorrect marker --}}
                                <template x-if="checked[rowIdx + ',' + colIdx] === 'wrong'">
                                    <span class="absolute top-0 right-0.5 text-red-500 leading-none" :style="'font-size: ' + Math.max(6, Math.min(9, 600 / width * 0.18)) + 'px'">✗</span>
                                </template>

                                {{-- Revealed marker --}}
                                <template x-if="revealed[rowIdx + ',' + colIdx]">
                                    <span class="absolute bottom-0 right-0.5 text-blue-500 leading-none" :style="'font-size: ' + Math.max(6, Math.min(9, 600 / width * 0.18)) + 'px'">◆</span>
                                </template>
                            </div>
                        </template>
                    </template>
                </div>
            </div>
        </div>

        {{-- Down clues panel (desktop) --}}
        <div class="hidden w-64 flex-col overflow-hidden lg:flex">
            <flux:heading size="sm" class="mb-2 shrink-0">{{ __('Down') }}</flux:heading>
            <div class="flex-1 space-y-0.5 overflow-y-auto" x-ref="downPanel">
                <template x-for="clue in computedCluesDown" :key="'down-' + clue.number">
                    <div
                        x-on:click="selectClue('down', clue.number)"
                        x-on:focus="selectClue('down', clue.number)"
                        x-on:keydown.tab.prevent="focusNextClue($el, 'down', false)"
                        x-on:keydown.shift.tab.prevent="focusNextClue($el, 'down', true)"
                        :class="activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100 dark:bg-blue-900/40' : 'hover:bg-zinc-100 dark:hover:bg-zinc-700/50'"
                        class="cursor-pointer rounded px-2 py-1"
                        :id="'clue-down-' + clue.number"
                        tabindex="0"
                    >
                        <div class="flex items-start gap-1.5">
                            <span class="mt-px text-xs font-bold text-zinc-500" x-text="clue.number"></span>
                            <div class="flex-1">
                                <span class="text-sm text-zinc-700 dark:text-zinc-300" x-text="clue.clue || '—'"></span>
                                <span class="text-xs text-zinc-400" x-text="'(' + clue.length + ')'"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Mobile clue panels --}}
        <div class="lg:hidden">
            <div class="flex border-b border-zinc-200 dark:border-zinc-700">
                <button
                    x-on:click="mobileClueTab = 'across'"
                    :class="mobileClueTab === 'across' ? 'border-zinc-800 text-zinc-900 dark:border-zinc-200 dark:text-zinc-100' : 'border-transparent text-zinc-500'"
                    class="border-b-2 px-4 py-2 text-sm font-medium"
                >{{ __('Across') }}</button>
                <button
                    x-on:click="mobileClueTab = 'down'"
                    :class="mobileClueTab === 'down' ? 'border-zinc-800 text-zinc-900 dark:border-zinc-200 dark:text-zinc-100' : 'border-transparent text-zinc-500'"
                    class="border-b-2 px-4 py-2 text-sm font-medium"
                >{{ __('Down') }}</button>
            </div>
            <div class="max-h-48 space-y-0.5 overflow-y-auto py-2">
                <template x-if="mobileClueTab === 'across'">
                    <div>
                        <template x-for="clue in computedCluesAcross" :key="'m-across-' + clue.number">
                            <div
                                x-on:click="selectClue('across', clue.number)"
                                x-on:focus="selectClue('across', clue.number)"
                                x-on:keydown.tab.prevent="focusNextClue($el, 'across', false)"
                                x-on:keydown.shift.tab.prevent="focusNextClue($el, 'across', true)"
                                :class="activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100 dark:bg-blue-900/40' : ''"
                                class="cursor-pointer rounded px-2 py-1"
                                tabindex="0"
                            >
                                <div class="flex items-start gap-1.5">
                                    <span class="mt-px text-xs font-bold text-zinc-500" x-text="clue.number"></span>
                                    <div class="flex-1">
                                        <span class="text-sm text-zinc-700 dark:text-zinc-300" x-text="clue.clue || '—'"></span>
                                        <span class="text-xs text-zinc-400" x-text="'(' + clue.length + ')'"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="mobileClueTab === 'down'">
                    <div>
                        <template x-for="clue in computedCluesDown" :key="'m-down-' + clue.number">
                            <div
                                x-on:click="selectClue('down', clue.number)"
                                x-on:focus="selectClue('down', clue.number)"
                                x-on:keydown.tab.prevent="focusNextClue($el, 'down', false)"
                                x-on:keydown.shift.tab.prevent="focusNextClue($el, 'down', true)"
                                :class="activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100 dark:bg-blue-900/40' : ''"
                                class="cursor-pointer rounded px-2 py-1"
                                tabindex="0"
                            >
                                <div class="flex items-start gap-1.5">
                                    <span class="mt-px text-xs font-bold text-zinc-500" x-text="clue.number"></span>
                                    <div class="flex-1">
                                        <span class="text-sm text-zinc-700 dark:text-zinc-300" x-text="clue.clue || '—'"></span>
                                        <span class="text-xs text-zinc-400" x-text="'(' + clue.length + ')'"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Comments & Ratings (visible when solved) --}}
    @if($isSolved)
        <div class="mt-6 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" wire:key="comments-section">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Comments & Ratings') }}</flux:heading>
                @if($this->averageRating)
                    <div class="flex items-center gap-1.5 text-sm">
                        <div class="flex items-center gap-0.5">
                            @for($i = 1; $i <= 5; $i++)
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 {{ $i <= round($this->averageRating) ? 'text-amber-400' : 'text-zinc-300 dark:text-zinc-600' }}" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
                            @endfor
                        </div>
                        <span class="font-medium text-zinc-600 dark:text-zinc-400">{{ $this->averageRating }}</span>
                        <span class="text-zinc-400">({{ $this->comments->whereNotNull('rating')->count() }})</span>
                    </div>
                @endif
            </div>

            {{-- Comment form (if user hasn't commented yet) --}}
            @if(!$this->userComment)
                <form wire:submit="submitComment" class="mb-6 space-y-3">
                    <div class="flex items-center gap-1">
                        <flux:text size="sm" class="mr-2 text-zinc-500">{{ __('Rating:') }}</flux:text>
                        @for($i = 1; $i <= 5; $i++)
                            <button type="button" wire:click="$set('commentRating', {{ $commentRating === $i ? 0 : $i }})" class="focus:outline-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-6 transition-colors {{ $i <= $commentRating ? 'text-amber-400' : 'text-zinc-300 hover:text-amber-300 dark:text-zinc-600 dark:hover:text-amber-500' }}" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
                            </button>
                        @endfor
                    </div>
                    <flux:textarea wire:model="commentBody" :placeholder="__('Share your thoughts about this puzzle...')" rows="2" />
                    @error('commentBody') <flux:text size="sm" class="text-red-500">{{ $message }}</flux:text> @enderror
                    <div class="flex justify-end">
                        <flux:button type="submit" size="sm" variant="primary">{{ __('Post Comment') }}</flux:button>
                    </div>
                </form>
            @else
                <div class="mb-6 rounded-lg border border-blue-100 bg-blue-50/50 p-3 dark:border-blue-900/30 dark:bg-blue-950/20">
                    <div class="mb-1 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <flux:text size="sm" class="font-medium">{{ __('Your review') }}</flux:text>
                            @if($this->userComment->rating)
                                <div class="flex items-center gap-0.5">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 {{ $i <= $this->userComment->rating ? 'text-amber-400' : 'text-zinc-300 dark:text-zinc-600' }}" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
                                    @endfor
                                </div>
                            @endif
                        </div>
                        <flux:button wire:click="deleteComment" variant="ghost" size="sm" icon="trash" />
                    </div>
                    <flux:text size="sm">{{ $this->userComment->body }}</flux:text>
                </div>
            @endif

            {{-- Comments list --}}
            @if($this->comments->isEmpty())
                <flux:text size="sm" class="text-zinc-400">{{ __('Be the first to leave a comment!') }}</flux:text>
            @else
                <div class="space-y-4">
                    @foreach($this->comments as $comment)
                        @if($comment->user_id !== Auth::id())
                            <div class="flex gap-3">
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-xs font-bold text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                                    {{ $comment->user->initials() }}
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:text size="sm" class="font-medium">{{ $comment->user->name }}</flux:text>
                                        @if($comment->rating)
                                            <div class="flex items-center gap-0.5">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3 {{ $i <= $comment->rating ? 'text-amber-400' : 'text-zinc-300 dark:text-zinc-600' }}" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
                                                @endfor
                                            </div>
                                        @endif
                                        <flux:text size="sm" class="text-zinc-400">{{ $comment->created_at->diffForHumans() }}</flux:text>
                                    </div>
                                    <flux:text size="sm" class="mt-1">{{ $comment->body }}</flux:text>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Achievement Toasts --}}
    <div class="fixed right-4 bottom-4 z-50 space-y-2">
        <template x-for="toast in achievementToasts" :key="toast.id">
            <div
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="translate-y-4 opacity-0"
                x-transition:enter-end="translate-y-0 opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="translate-y-0 opacity-100"
                x-transition:leave-end="translate-y-4 opacity-0"
                class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 shadow-lg dark:border-amber-800 dark:bg-amber-950"
            >
                <div class="flex size-8 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-amber-600 dark:text-amber-400" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
                </div>
                <div>
                    <div class="text-sm font-semibold text-amber-900 dark:text-amber-100" x-text="toast.label"></div>
                    <div class="text-xs text-amber-700 dark:text-amber-300" x-text="toast.description"></div>
                </div>
            </div>
        </template>
    </div>

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
</div>
