# Resuming: crossword template training set for Claude

## What we're building and why

A curated training set of crossword templates so Claude can learn what makes a template work and generate new ones with point of view. Each entry combines: the grid, computed structural stats, a small set of discrete style tags, and hand-written prose commentary (philosophy / strengths / compromises / best_for / avoid_when).

Repo: `/Users/michael/Herd/zorbl` · Stack: Laravel 13, PHP 8.5, Pest 4, MySQL · Plan file: `~/.claude/plans/if-i-wanted-to-vectorized-locket.md`.

## What's already in place

**Schema (done):**
- `templates` table — 31 curated templates seeded (7 × 5×5, 24 × 15×15). `TemplateSeeder` is the source of truth.
- `template_tag` pivot — `(template_id, tag)` unique. The `tag` column is cast to `App\Enums\TemplateStyle` (a **12-case PHP enum**, not a row in the `tags` table). Model: `App\Models\TemplateTag`. Relationship: `Template::templateTags(): HasMany`.
- `template_annotations` table — 1:1 with templates. Columns: `philosophy` (text NOT NULL), `strengths` + `compromises` (json arrays of strings, nullable), `best_for` + `avoid_when` (text nullable). Model: `App\Models\TemplateAnnotation`. Relationship: `Template::annotation(): HasOne`.

**Stats (done):** `App\Services\TemplateStatsService` returns an `App\Support\TemplateStats` DTO via `forTemplate(Template)` or `forGrid(...)`. Computes block density, word counts/lengths, symmetry (rotational + mirror), connectivity, fully-checked. Reuses `Zorbl\CrosswordIO\GridNumberer` for bar-aware word slots. Tests in `tests/Unit/Services/TemplateStatsServiceTest.php`. **Stats are computed on-the-fly, not stored** — query in PHP after fetching.

**Auto-tagger (done):** `Database\Seeders\TemplateTagSeeder`, wired into `DatabaseSeeder`. Idempotent — skips templates that already have any tags so manual edits aren't overwritten. Derives tags from stats per these rules:
- `MiniStyle` if width ≤ 7
- `RotationalSymmetric` else `Asymmetric`
- `WideOpen` if density ≤ 10%; `Blocky` if ≥ 19%
- `BarGrid` if `styles` non-empty
- `RelaxedRules` if `min_word_length < 3` OR not fully-checked
- 15×15 only: `ThemelessFriendly` if avg word length ≥ 5.5 OR density ≤ 10%; else `ThemedFriendly`
- `TripleStack` if width ≥ 13 AND no bars AND ≥3 consecutive all-open rows
- `OpenCorners` / `ClosedCorners` are **intentionally NOT auto-derived** — too subjective; assigned by hand during annotation review.

**Annotations written (9 of 31):** all 7 × 5×5 templates + "15" + "4". Voice/format are the reference for the rest of the set.

## Where we paused

Michael went offline to add **more unconventional templates** because the existing 24 × 15×15s skew heavily toward "rotational-symmetric, themed-friendly NYT-style at ~16% density" — which makes the remaining 22 conventional templates hard to differentiate meaningfully. The targeted gaps to fill were:
- 2 more bar-grid examples (one 15×15 with sparing bars, one bar-only 7×7)
- 2 more wide-open 15×15 variants (different signatures than "15")
- 1 asymmetric 15×15 (currently only have asymmetric 5×5s)
- 1-2 medium grids (7×7 or 9×9 — bridges the size gap)
- Optional: 1 grid-art / shaped template

He may not have done all of these — confirm what's actually in the DB before assuming.

## When you resume, do this

1. **Inventory the new state.** Run:
   ```bash
   php artisan tinker --execute 'echo App\Models\Template::count();'
   php artisan tinker --execute '
   $missing = App\Models\Template::whereDoesntHave("annotation")->orderBy("width")->orderBy("name")->get(["id","name","width","height"]);
   foreach ($missing as $t) echo "$t->id\t$t->name\t{$t->width}x{$t->height}\n";'
   ```
2. **Confirm with Michael** which new templates he added and any patterns he was going for. Don't auto-assume from the data.
3. **Run the auto-tagger** so new templates get baseline tags: `php artisan db:seed --class=TemplateTagSeeder`. Spot-check; flag any tags Michael wants to override.
4. **Then annotate everything unannotated.** Order: unconventional new templates first (each has a clear distinctive feature so easier), then the 22 conventional 15×15s with the new templates as contrast points.

## Voice guide for annotations

**Read the 9 existing annotations first** — they're the canon:
```bash
php artisan tinker --execute '
foreach (App\Models\Template::with("annotation")->whereHas("annotation")->get() as $t) {
    echo "=== {$t->name} ===\n" . json_encode($t->annotation->only(["philosophy","strengths","compromises","best_for","avoid_when"]), JSON_PRETTY_PRINT) . "\n\n";
}'
```

Match these qualities:
- **Philosophy** (1-2 sentences): state the layout's *intent*, not its mechanics. "A maximalist openness statement" ✓ — "has 21 blocks at 9.3% density" ✗.
- **Strengths** (exactly 3 bullets): each names a *concrete* design win — specific row counts, specific entry lengths, specific structural moves. No vague praise.
- **Compromises** (exactly 3 bullets): each names a *concrete* trade-off the constructor accepts.
- **best_for** (1 sentence): puzzle type, difficulty target, or construction context — not abstractions.
- **avoid_when** (1 sentence): contexts where this template hurts more than helps. Should add info not already in best_for; if it doesn't, leave it null.
- **Length:** ~120-180 words per template total.
- **Stance:** opinionated. The training set's value is in *judgment*, not description. If a template has weaknesses, name them.

**Before drafting each annotation:** read the actual grid in the seeder, run `app(App\Services\TemplateStatsService::class)->forTemplate($t)`, and find 1-2 *distinctive* features (a striking block cluster, an unusually long max entry, a particular corner shape, where the theme rows sit). Build the annotation around that distinctiveness. Generic boilerplate is the failure mode to avoid.

## Key gotchas

- The `tags` table and `Tag` model are for **crossword topical tags** (Sports, Movies, …) — unrelated to template style tags. Don't conflate.
- No Filament admin for annotations / template tags yet — out of scope.
- Grid format: 2D array of `0` (white) or `'#'` (block), indexed `[row][col]`. Bars stored separately in the `styles` JSON column, keyed `"row,col"` with shape `{bars: ["top"|"bottom"|"left"|"right", ...]}`.
- "Internal Bars" and "Airy Asymetrical" both correctly carry `relaxed-rules` because length-1 runs exist in their grids.
- Pint is enforced — run `vendor/bin/pint --dirty --format agent` after edits.
