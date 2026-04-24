# Puzzle Engagement Ranker — Deferred Implementation Plan

**Status:** Deferred — do not execute until gate conditions below are met.
**Owner intent:** Learn what makes a puzzle engaging from player behavior, then bias AI-assisted generation toward puzzles the ranker predicts will be engaging. Heuristic fill stays; AI contributes themes, clues, and *selection*.

---

## Gate Conditions (check before executing any phase)

Run this query first. Only proceed to Phase 1 when **all** thresholds are met.

```php
// Via php artisan tinker --execute
DB::table('puzzle_attempts')->where('is_completed', true)->count();        // need >= 2,000
DB::table('crosswords')->where('is_published', true)->count();             // need >= 300
DB::table('crossword_likes')->count() + DB::table('puzzle_comments')->count(); // need >= 500 combined
```

If under threshold, stop and tell the user: "Not enough engagement signal yet — come back when we have ≥2k completed attempts across ≥300 published puzzles."

Phase 2 (online ranker) requires additionally: ≥20 puzzles/week published at steady state, and at least 4 weeks of data post-Phase 1.

---

## Architecture Overview

```
  [Theme Seed LLM]  ─────▶  [GridFiller (heuristic)]  ─────▶  [AiClueGenerator]
         │                           │                              │
         └──── N candidates ─────────┴──────── N candidates ────────┘
                                     │
                                     ▼
                      [Feature Extractor] ──▶ [Quality Ranker] ──▶ top-k ship
                                     ▲                │
                                     │                ▼
                            [PuzzleFeatures table]  [Bandit assignment → A/B]
                                     ▲                │
                                     │                ▼
                            [Engagement Aggregator] ◀─ puzzle_attempts, crossword_likes, puzzle_comments
```

Everything AI currently tries to do directly (fill grids) stays with `GridFiller`. The LLM moves to jobs it's good at — ideation and clue writing — and a learned model picks winners.

---

## Phase 1 — Engagement Signal + Feature Store

Goal: capture the two sides of the training pair (puzzle features, engagement outcomes) with no behavior change to puzzle generation yet.

### 1.1 Migration: `puzzle_features` table

File: `database/migrations/<date>_create_puzzle_features_table.php`
Command: `php artisan make:migration create_puzzle_features_table --no-interaction`

Columns (one row per crossword, computed at publish time):

- `id` (bigint PK)
- `crossword_id` (foreignId, unique, cascadeOnDelete)
- `grid_width` (unsignedTinyInteger)
- `grid_height` (unsignedTinyInteger)
- `word_count` (unsignedSmallInteger)
- `avg_word_length` (float)
- `black_square_ratio` (float)
- `theme_answer_count` (unsignedTinyInteger) — long entries ≥ 7 letters
- `theme_density` (float) — theme letters / total letters
- `wordlist_freshness` (float, 0–100) — avg of `words.score` for fill entries; `null` for entries not in `words`
- `crosswordese_hits` (unsignedSmallInteger) — count of entries matching a curated blocklist
- `proper_noun_ratio` (float)
- `multi_word_ratio` (float) — entries that contain a space in the stored source
- `avg_clue_length` (float) — characters
- `clue_wit_score` (float, 0–1, nullable) — LLM-judge score, filled async
- `difficulty_rating` (float, nullable) — mirror of `crosswords.difficulty_rating`
- `puzzle_type` (string) — mirror of `crosswords.puzzle_type`
- `generator_variant` (string, nullable) — which pipeline produced it (for Phase 2 bandit)
- `features_version` (unsignedSmallInteger) — bump when extractor logic changes so we can re-extract
- `computed_at` (timestamp)
- `timestamps()`

Index: `(puzzle_type, features_version)` and unique on `crossword_id`.

### 1.2 Migration: `puzzle_engagement_daily` rollup

File: `database/migrations/<date>_create_puzzle_engagement_daily_table.php`

One row per crossword per UTC date — cheap to recompute, easy to window.

- `id`
- `crossword_id` (foreignId, cascadeOnDelete)
- `date` (date)
- `attempts_started` (unsignedInteger)
- `attempts_completed` (unsignedInteger)
- `abandonment_rate` (float) — `1 - completed/started`, null when started=0
- `median_solve_seconds` (unsignedInteger, nullable)
- `expected_solve_seconds` (unsignedInteger, nullable) — from `DifficultyRater`
- `solve_speed_ratio` (float, nullable) — `median / expected` (lower = easier than expected)
- `likes_count` (unsignedInteger)
- `comments_count` (unsignedInteger)
- `shares_count` (unsignedInteger) — 0 until share tracking exists
- `timestamps()`

Unique index: `(crossword_id, date)`. Index: `(date)`.

### 1.3 Feature extractor service

File: `app/Services/PuzzleFeatureExtractor.php`
Command: `php artisan make:class Services/PuzzleFeatureExtractor --no-interaction`

```php
public function extract(Crossword $crossword): PuzzleFeatures
```

Implementation notes:
- Read `grid`, `solution`, `clues_across`, `clues_down` from the model (already JSON).
- `wordlist_freshness`: batch-lookup entries in `words` table by `word` column, use pre-computed `score`; fallback to `null` if no hit rate metric needed later.
- `crosswordese_hits`: seed a list in `config/puzzle_ranker.php` (ETUI, OLEO, OREO, ESNE, ADIT, ALOE, ARIA, ANOA, EPEE, ERNE, EWER, OLIO, SNEE, STOA, UNAU…). Keep the list editable; do not hardcode.
- `multi_word_ratio`: requires source phrase preservation. If the solution grid doesn't retain spacing, derive from `clue_entries` where available.
- `clue_wit_score`: do not compute synchronously. Dispatch `ScorePuzzleCluesJob` after extraction.

### 1.4 Clue wit scorer (LLM-as-judge)

File: `app/Jobs/ScorePuzzleCluesJob.php`

- Batch all clues for one puzzle into a single Anthropic request.
- Rubric (keep in `resources/prompts/clue-wit-rubric.md`, not inline in code):
  - +wit for wordplay, misdirection, cultural reference, dual meaning
  - −wit for bare definition, "sushi bar offering" patterns, dated references
- Return `0..1` per clue, average, write to `puzzle_features.clue_wit_score`.
- Log token usage to `ai_usages`.

### 1.5 Hooks

**Publish hook:** in whatever action handles `crosswords.is_published = true` flipping on, dispatch:
```php
ExtractPuzzleFeaturesJob::dispatch($crossword);
```
Find the existing publish path before writing this — do not add a model observer that fires on every save.

**Daily aggregation:** `php artisan make:command AggregatePuzzleEngagement --no-interaction`, scheduled nightly in `routes/console.php`. Computes `puzzle_engagement_daily` rows for the previous UTC day. Idempotent — uses `updateOrCreate` on `(crossword_id, date)`.

### 1.6 Tests (Pest)

- `tests/Feature/PuzzleFeatureExtractorTest.php` — given a known crossword factory, asserts each feature column has the expected computed value.
- `tests/Feature/AggregatePuzzleEngagementCommandTest.php` — seeds attempts/likes/comments across a 2-day window, runs the command, asserts rollup rows.
- `tests/Unit/CrosswordeseDetectionTest.php` — dataset of words, expects hits.

Run: `php artisan test --compact --filter=PuzzleFeature` and `--filter=Engagement`.

### 1.7 Backfill

One-time artisan command: `php artisan puzzle:backfill-features` — extracts features for all already-published crosswords. Runs `ExtractPuzzleFeaturesJob` on the queue, chunked.

**Exit Phase 1 when:** features table populated for all published crosswords, daily rollup running for ≥2 weeks, `clue_wit_score` populated for ≥80% of puzzles.

---

## Phase 2 — Offline Ranker (batch-trained quality model)

Goal: predict an engagement score per puzzle from features. Use it to rank candidates at generation time.

### 2.1 Label definition

Composite engagement score per crossword (computed in a view, not stored):

```
engagement = 0.5 * completion_rate
           + 0.2 * min(likes_per_attempt * 10, 1.0)
           + 0.1 * min(comments_per_attempt * 20, 1.0)
           + 0.2 * (1 - abs(solve_speed_ratio - 1.0))   # reward puzzles solving near expected
```

Weights live in `config/puzzle_ranker.php`. Only include puzzles with ≥30 attempts in the training window.

### 2.2 Model choice

**Start with linear regression + monotonic constraints** — no Python dependency, fits in PHP via a small trainer class using normal-equation solve. The feature set is small (~15 dims). Interpretable coefficients matter more than last-mile accuracy at this scale.

Upgrade to gradient-boosted trees (via a Python sidecar or `rubix/ml` composer package) only when linear R² plateaus below 0.3 on a held-out set.

### 2.3 Artisan command: `puzzle:train-ranker`

File: `app/Console/Commands/TrainPuzzleRanker.php`

- Loads features + engagement into an in-memory matrix.
- Solves linear regression with ridge regularization (λ = 1.0 default).
- Writes coefficients to `storage/app/ranker/coefficients-v{N}.json` with metadata (training size, date, feature names, R², held-out MAE).
- Bumps `config('puzzle_ranker.active_version')` by writing to a DB-backed config or to a `ranker_models` table (prefer the table — migrations over config writes).

### 2.4 Migration: `ranker_models`

- `id`, `version` (unique), `coefficients` (json), `metrics` (json), `feature_names` (json), `trained_on` (timestamp), `is_active` (boolean), `timestamps()`.

### 2.5 Scorer service

File: `app/Services/PuzzleRanker.php`
```php
public function score(PuzzleFeatures $features): float
public function scoreCrossword(Crossword $crossword): float   // extracts features on the fly for unpublished candidates
```

Loads active coefficients once per request, caches in-memory.

### 2.6 Tests

- `tests/Unit/PuzzleRankerTest.php` — frozen coefficient fixture + known feature vector = expected score.
- `tests/Feature/TrainPuzzleRankerCommandTest.php` — synthetic data with a known linear relationship, train, assert recovered coefficients are within tolerance.

**Exit Phase 2 when:** ranker trained, R² > 0.2 on held-out set, deployed to generation pipeline (Phase 3).

---

## Phase 3 — Generate-Many-Then-Rank Pipeline

Goal: for every AI-assisted puzzle request, produce N candidates and ship the ranker's top pick.

### 3.1 Theme seed service

File: `app/Services/AiThemeSeeder.php`

```php
public function seeds(int $count, ?string $topic = null): array
// returns [['theme' => 'BEACH DAY', 'long_entries' => ['SUNSCREEN', 'BOARDWALK', 'LIFEGUARD'], 'tone' => 'playful'], ...]
```

Uses Anthropic. Cached by `(topic, date)` so repeated calls don't burn tokens.

### 3.2 Orchestrator

File: `app/Services/PuzzleCandidateGenerator.php`

```php
public function generate(GenerationRequest $req): Crossword
```

Pipeline:
1. `AiThemeSeeder::seeds(N = config('puzzle_ranker.candidates', 5))`.
2. For each seed, call `GridFiller` with theme entries pinned — heuristic does the hard part.
3. Drop candidates that fail to fill or violate constraints.
4. For survivors, call `AiClueGenerator` for clues.
5. `PuzzleFeatureExtractor::extract()` on each.
6. `PuzzleRanker::score()` on each.
7. Persist the top-scoring candidate; discard the rest (or persist as `is_published=false` drafts for later reuse).
8. Log all candidates + scores to a new `puzzle_candidate_logs` table for Phase 4 analysis.

### 3.3 Cost guard

Wire into `PlanLimits` — N candidates ≈ N× the clue-generation tokens. Charge one unit of AI quota regardless (absorbing cost on our side) *or* let paid users opt into higher N. Default N=3 for free-tier-adjacent flows, N=5 for premium.

### 3.4 Migration: `puzzle_candidate_logs`

- `id`, `generator_variant` (string), `theme_seed` (json), `features` (json), `predicted_score` (float), `was_shipped` (boolean), `crossword_id` (nullable FK — only set if shipped), `timestamps()`.

### 3.5 Tests

- Feature test that calls the generator with a stubbed `AiThemeSeeder` returning 3 fixed seeds, stubbed `AiClueGenerator`, real `GridFiller`, real `PuzzleRanker`. Asserts the shipped puzzle matches the highest predicted score.

---

## Phase 4 — Online Learning (multi-armed bandit)

Goal: let the system discover which *generator variants* produce better puzzles without manual A/B plumbing.

### 4.1 Variant registry

`config/puzzle_ranker.php` lists generator variants, e.g.:
- `theme-first-playful`
- `theme-first-cryptic`
- `fill-first-fresh-wordlist`
- `clue-tone-newsy`

Each variant is a frozen config bundle (theme prompt, wordlist scoring weights, clue prompt).

### 4.2 Thompson sampling

File: `app/Services/GeneratorBandit.php`

- Maintains `(variant, alpha, beta)` per variant in a `generator_bandit_arms` table.
- On each generation: sample each Beta(α, β), pick variant with highest sample, record choice.
- On engagement aggregation (daily command): convert puzzle's engagement score into a pseudo-reward in [0,1]. Update (α, β) for the variant that produced it.

### 4.3 Guardrails

- Every variant gets a floor of 10% traffic ("epsilon floor") until it has ≥50 shipped puzzles with engagement data.
- New variants added via migration-only (no runtime variant creation).
- Retire variants whose posterior mean is below the 10th percentile for ≥100 puzzles.

### 4.4 Tests

- Simulated reward streams over 500 iterations → higher-mean arm converges to ≥60% traffic share.

---

## Phase 5 — LLM-as-Judge Pre-filter (cost optimization)

Only needed if Phase 3 generation cost becomes a concern.

Insert between pipeline steps 4 and 5:
- Cheap LLM (Haiku) scores each candidate against a short rubric.
- Drop the bottom 50% before the expensive feature extraction + ranker path.
- Calibrate quarterly: does judge score correlate with ranker score? If not, drop the judge.

---

## Resumption Checklist for Future Claude

When resuming this plan, do these in order:

1. **Re-check gate conditions** (top of file). Data thresholds must still be met.
2. **Read the current `app/Services/` directory** — the service names in this plan may conflict with services added since this was written. Prefer integrating with existing classes (`GridFiller`, `AiGridFiller`, `AiClueGenerator`, `AiFillPicker`, `DifficultyRater`, `WordSuggester`) over creating parallel ones.
3. **Check `database/migrations/`** — confirm no conflicting tables have appeared (`puzzle_features`, `puzzle_engagement_daily`, `ranker_models`, `generator_bandit_arms`, `puzzle_candidate_logs`).
4. **Ask the user to confirm scope** before migrating schema — this is a multi-week effort; do not start Phase 1 assuming approval.
5. **Run one phase at a time.** Each phase ends with tests green and a visible artifact (rows in a table, a trained model file). Do not start the next phase until the current one is observably working in production.
6. **Update the CLAUDE.md skills list** if a new domain skill becomes relevant (e.g., a ranker/ML skill).

## What NOT to do

- Do **not** replace `GridFiller` with an LLM-based filler. The user confirmed heuristic fill beats AI fill; this plan preserves that.
- Do **not** precompute features on every model save — only on publish.
- Do **not** train the ranker on puzzles with fewer than 30 attempts — noise dominates signal.
- Do **not** let the bandit route >90% of traffic to one variant; always keep exploration floor.
- Do **not** add a Python dependency in Phase 2 — the linear model is deliberate.
