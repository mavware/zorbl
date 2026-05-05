# Resuming: crossword template training set for Claude

## What we're building and why

A curated training set of crossword templates so Claude can learn what makes a template work and generate new ones with point of view. Each entry combines: the grid, computed structural stats, a small set of discrete style tags, and hand-written prose commentary (philosophy / strengths / compromises / best_for / avoid_when).

Repo: `/Users/michael/Herd/zorbl` · Stack: Laravel 13, PHP 8.5, Pest 4, MySQL · Plan file: `~/.claude/plans/if-i-wanted-to-vectorized-locket.md`.

## What's already in place

**Schema (done):**
- `templates` table — 81 curated templates seeded (9 × 5×5, 17 × 7×7, 7 × 9×9, 1 × 11×11, 47 × 15×15). `TemplateSeeder` is the source of truth.
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

**Annotations written (81 of 81):** complete. The 9 originals (all 7 × 5×5 + "15" + "4") plus a 72-template batch covering: 5 bar grids (Bars 1-5), 17 7×7 minis, 7 9×9 minis, 11×11 Bars 3, 12 cryptic-style 15×15s, and the standard 15×15 fleet (02-09, 10-38 sans gaps, 63, 75). Voice should be considered the canon for any new templates added later.

## Status (current)

The training set is complete as of the last session: 81 templates × (grid + auto-derived stats + auto-tagged styles + hand-written annotation). Ready to be used as in-context examples for prompt-based template generation experiments.

## Where this can go next

- **Refine annotations.** All 72 of the second-batch annotations were written by Claude in one pass; quality is uneven. Spot-check by category (the cryptic and bar-grid clusters got the strongest treatment; the conventional 15×15 cluster is necessarily more formulaic). Rewrite any in your voice as needed.
- **Hand-assign `open-corners` / `closed-corners` tags.** Auto-tagger intentionally skipped these. Worth a sweep when you're already reviewing each grid.
- **Build the prompt-assembly layer.** Take a Template + its TemplateStats DTO + its templateTags + its annotation, render to prompt-ready YAML or JSON, and hand to a Claude API call that asks for new templates with similar properties. That's the payoff this dataset was built for.

## If new templates are added later

1. **Sync the seeder** if Michael added templates via the Filament admin — the DB is now ahead of the seeder. Regenerate `TemplateSeeder::templates()` from the DB (sort by width, height, natural-name).
2. **Auto-tag the new templates:** `php artisan db:seed --class=TemplateTagSeeder` — idempotent, skips templates that already have tags.
3. **Annotate the new templates** in the same voice as the existing 81. Confirm with Michael which sized cluster the new ones belong to so the annotation matches that cluster's tone.

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
