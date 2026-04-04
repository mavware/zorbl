# Autofill Grid Assistance — Implementation Plan

## Overview

Add a word suggestion feature to the crossword editor that suggests words fitting the current slot pattern (filled letters + blanks), scored by letter frequency and crossword-worthiness. The feature follows the existing clue suggestion UI pattern — a second button alongside the library icon that opens an inline suggestion panel below the active clue.

---

## Architecture Decisions

### Word List Storage: Dedicated `words` table (Option B)

A dedicated `words` table seeded from a bundled text file. Rationale:
- SQLite (the project database) handles `LIKE` with indexed `length` column efficiently for lists under 200K
- Pre-computed `score` column allows instant `ORDER BY` without runtime calculation
- The table can grow organically as puzzles are published (sync from `clue_entries`)
- Clean separation from `clue_entries` which serves a different purpose (clue text lookup)

### Scoring: Pre-computed at seed/import time

Each word gets a `score` float computed as the average English letter frequency of its characters, normalized to 0-100. Words also appearing in `clue_entries` get a 1.15x bonus multiplier. The score is stored so queries just do `ORDER BY score DESC`.

### UI: Inline in clue panel (extending existing pattern)

A second icon button appears next to the existing library icon on the active clue. It opens a word suggestion panel in the same inline style as the existing clue library panel, but with blue accent colors (vs amber) to differentiate. Mirrors existing patterns exactly.

### Pattern Matching: SQL LIKE

Frontend builds a pattern string from the current slot (e.g., `"C__NK"`) and sends it with the slot length. Backend converts blanks to SQL `_` wildcards and queries `WHERE length = ? AND word LIKE ?`.

---

## Step-by-Step Implementation

### Step 1: Create the `words` table migration

**File:** `database/migrations/2026_04_03_200000_create_words_table.php`

Schema:
- `id` (bigint, primary key)
- `word` (string, unique index, uppercase)
- `length` (unsigned tinyint, indexed)
- `score` (float, default 0)
- `in_clue_library` (boolean, default false)
- `created_at` / `updated_at`

Key indexes:
- Composite index on `(length, score)` for the primary query pattern
- Unique index on `word` to prevent duplicates

### Step 2: Create the Word model

**File:** `app/Models/Word.php`

Simple Eloquent model with `$fillable`: `word`, `length`, `score`, `in_clue_library`.

Include a static `computeScore(string $word): float` method using standard English letter frequencies:
- E=12.7, T=9.1, A=8.2, O=7.5, I=7.0, N=6.7, S=6.3, H=6.1, R=6.0, D=4.3, L=4.0, C=2.8, U=2.8, M=2.4, W=2.4, F=2.2, G=2.0, Y=2.0, P=1.9, B=1.5, V=1.0, K=0.8, J=0.15, X=0.15, Q=0.10, Z=0.07
- Score = average frequency of letters, rounded to 2 decimal places
- Common-letter words (STARE ~7.46) score higher than obscure-letter words (JAZZY ~0.47)

### Step 3: Bundle a word list file

**File:** `database/data/crossword-words.txt`

Plain text, one uppercase word per line, 50K-100K curated crossword words. Sourced from public domain word lists filtered to 3-21 letter words. At ~80K words averaging 6 characters, this is roughly 500KB.

### Step 4: Create the WordListSeeder

**File:** `database/seeders/WordListSeeder.php`

1. Read `database/data/crossword-words.txt` line by line
2. Compute letter frequency score via `Word::computeScore()`
3. Batch query `clue_entries` to identify known crossword words, set `in_clue_library`
4. Apply 1.15x bonus to score for library words
5. Bulk upsert in chunks of 1000 via `Word::upsert()` keyed on `word`
6. Must be idempotent — re-running updates scores without creating duplicates

Register in `database/seeders/DatabaseSeeder.php` alongside existing seeders.

### Step 5: Create the WordSuggester service

**File:** `app/Services/WordSuggester.php`

Single method: `suggest(string $pattern, int $length, int $limit = 20): array`

- Validate pattern length matches `$length`
- Convert pattern: letters stay as-is, `_` or empty becomes SQL `_` wildcard
- Query: `Word::where('length', $length)->whereRaw('word LIKE ?', [$sqlPattern])->orderByDesc('score')->limit($limit)`
- Return array of `['word' => ..., 'score' => ..., 'inLibrary' => ...]`

Edge cases: fully filled pattern does exact match, fully empty pattern returns top words by length.

### Step 6: Add `suggestWords` Livewire method

**File:** `resources/views/pages/crosswords/⚡editor.blade.php` (PHP section, around line 227)

Add alongside the existing `lookupClues()` method:

```php
public function suggestWords(string $pattern, int $length): array
{
    $pattern = strtoupper(preg_replace('/[^A-Z_]/', '_', $pattern));
    if ($length < 2 || $length > 30 || strlen($pattern) !== $length) {
        return [];
    }
    return app(WordSuggester::class)->suggest($pattern, $length);
}
```

Also add `use App\Services\WordSuggester;` to the imports at the top.

### Step 7: Add Alpine.js word suggestion state and methods

**File:** `resources/js/crossword-grid.js`

**New state properties** (add near line 23, alongside existing `clueSuggestions*`):
```
wordSuggestions: [],
wordSuggestionsLoading: false,
wordSuggestionsPattern: '',
showWordSuggestions: false,
```

**Update watchers in `init()`** (lines 39-45) to also close word suggestions:
```javascript
this.$watch('activeClueNumber', () => {
    this.closeSuggestions();
    this.closeWordSuggestions();
});
this.$watch('direction', () => {
    this.closeSuggestions();
    this.closeWordSuggestions();
});
```

**New methods** (add after the existing clue suggestions section, around line 948):

`getPatternForSlot(dir, number)` — builds pattern from solution grid. For each cell in the slot: if it has a letter, use it uppercase; otherwise use `_`.

`toggleWordSuggestions()` — mirrors `toggleSuggestions()`. Closes clue suggestions if open, then opens/closes word suggestions.

`closeWordSuggestions()` — resets all word suggestion state.

`fetchWordSuggestions()` — gets pattern via `getPatternForSlot()`, calls `await this.$wire.suggestWords(pattern, length)`, stores results. Skips fetch if pattern unchanged.

`applyWordSuggestion(word)` — gets cells via `getWordCells()`, fills each cell with the corresponding letter from the word, calls `closeWordSuggestions()` and `markDirty()`.

### Step 8: Add word suggestion UI to the editor blade template

**File:** `resources/views/pages/crosswords/⚡editor.blade.php`

Additions in **6 locations** (mirroring all places the clue library UI appears):

**A) Button** — A second icon next to the existing library icon in each clue row. Uses blue styling (vs amber for library). Same `x-show` condition. Uses a lightbulb or sparkles SVG icon.

Locations for the button (inside the `<div class="flex items-center gap-1">` alongside the library button):
1. Desktop across panel (~line 501-513)
2. Desktop down panel (~line 694-709)
3. Mobile across panel (~line 786-798)
4. Mobile down panel (~line 850-862)

**B) Suggestion panel** — Rendered inline below the clue, after the existing clue suggestion template. Uses blue accent border (`border-blue-300 dark:border-blue-600`).

Locations for the panel (after each `{{-- Clue suggestions --}}` template block):
1. Desktop across (~after line 543)
2. Desktop down (~after line 739)
3. Mobile across (~after line 819)
4. Mobile down (~after corresponding block)

Panel structure:
- Loading state: "Loading suggestions..." italic text
- Results: header "Word suggestions", then list of clickable word items
- Each item shows the word text, plus a star icon if `suggestion.inLibrary` is true
- Click calls `applyWordSuggestion(suggestion.word)`
- Mobile panels show `.slice(0, 5)` to save space

### Step 9: Sync words in ClueHarvester

**File:** `app/Services/ClueHarvester.php`

In `harvest()`, after upserting `clue_entries`, also upsert unique answer words into the `words` table with `in_clue_library = true` and the library bonus applied to the score.

In `purge()`, after deleting clue entries, check if the purged words still exist in other clue entries. If not, update `in_clue_library = false` and recompute score without the bonus.

Add `use App\Models\Word;` import.

### Step 10 (Optional): Artisan import command

**File:** `app/Console/Commands/ImportWordList.php`

Command `crossword:import-words {file?}` that reads a word list file and bulk upserts into the `words` table. Accepts optional `--sync-library` flag to update `in_clue_library` flags. Useful for future word list additions without modifying the seeder.

---

## Implementation Order

1. Migration + Model (Steps 1-2)
2. Word list file + Seeder (Steps 3-4), run `php artisan migrate` and `php artisan db:seed --class=WordListSeeder`
3. WordSuggester service (Step 5)
4. Livewire method (Step 6)
5. Alpine.js methods and state (Step 7)
6. Blade template UI (Step 8)
7. ClueHarvester sync (Step 9)
8. Import command (Step 10, optional)
9. Tests

---

## Edge Cases

- **Empty word list**: Returns `[]` gracefully, UI shows nothing
- **Fully filled slot**: Exact match confirms word exists in dictionary
- **Fully empty slot**: Returns top-scored words of that length
- **Rebus cells**: Use first character only for pattern, or treat as blank
- **Performance**: Composite `(length, score)` index means LIKE runs on ~8K rows per length — sub-millisecond on SQLite
- **Word list quality**: Filter profanity/slurs during seeding

---

## Testing Plan

1. `Word::computeScore()` — verify STARE > JAZZY, THE > QUX
2. `WordSuggester::suggest()` — pattern matching, exact match, empty table, invalid pattern
3. Livewire `suggestWords()` — response structure, sanitization
4. `WordListSeeder` — idempotency (run twice, same count)
5. `ClueHarvester` sync — publish puzzle, verify words table updated
