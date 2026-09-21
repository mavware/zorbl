# Port the redesign branch's structural changes to main

You are on branch `main`. Branch `redesign` is a visual redesign that also carries a set of structural changes: new layout components, a reorganised sidebar, removed features, bug fixes, PWA/branding assets, new tests, and a switchable theme system. `main` has not moved since `redesign` branched from it, so `git diff main redesign -- <path>` is exactly what the redesign changed to a file, and `git checkout redesign -- <path>` copies a file over verbatim.

Goal: bring the structure and the theme system to `main` so that `main` renders its existing look by default and can switch to the redesign look with `APP_THEME=classical`. Do not merge the branch. Work through the phases below, one commit per phase, running the named tests after each.

## Decisions already made (do not revisit)

- **Default theme on main is `modern`.** Set `'theme' => env('APP_THEME', 'modern')` in `config/app.php` and `APP_THEME=modern` in `.env.example`, change the fallback in `Theme::current()` to `self::Modern`, and update the fallback cases in `tests/Feature/ThemeTest.php` to expect `modern`. `resources/css/themes/classical.css` stays the base `@theme` declaration (Tailwind needs exactly one) and `modern.css` overrides it; with `data-theme="modern"` on `<html>`, main's palette, fonts and radii are restored.
- **Views are ported with their redesign classes intact.** The redesign's semantic classes (`text-ink`, `text-ink-muted`, `border-hairline`, `font-classical`, `btn-classical*`, `btn-header*`, `meta-classical`, `chip-classical`, `field-classical`) are all token-driven, and `modern.css` maps every token onto main's palette and Instrument Sans. Porting a view therefore means copying it from `redesign`, not rewriting its classes. Do not translate classes back to raw zinc/amber utilities.
- **Feature removals on the redesign are intentional** (listed in Phase 4). Port the removals and their test changes. Do not keep the removed code "just in case".
- **Do not touch `resources/css/embed.css`** or `resources/views/embed/solver.blade.php`. The embed solver keeps main's styling.

## Working rules

- Before each phase run `git diff main redesign -- <paths>` for the files in that phase and read it; only then apply. For files listed as "verbatim", use `git checkout redesign -- <path>`.
- After each phase: `vendor/bin/pint --dirty --format agent`, then the phase's tests with `php artisan test --compact <files>`, then `npm run build` if CSS or Blade changed. Fix failures before committing. Commit with a message that names the phase.
- At the end run the whole suite: `php artisan test --compact --parallel`. Everything must pass.
- Do not create documentation files. Do not add dependencies.
- If something in a phase turns out to depend on a later phase, do the dependency first and say so in the commit message rather than skipping it.

## Phase 1: Theme system (verbatim)

Copy these from `redesign` unchanged, then apply the "default theme is modern" decision above:

- `app/Enums/Theme.php`
- `resources/css/themes/classical.css`
- `resources/css/themes/modern.css`
- `tests/Feature/ThemeTest.php`
- `config/app.php` (only the new `theme` block; diff it, do not overwrite the file)
- `.env.example` (only the `APP_THEME` line)

Then port `resources/css/app.css`. Diff it against `redesign`; the redesign version is the one to keep, so take it verbatim. It imports both theme files, both font stylesheets (Lora/Cormorant and Instrument Sans), and defines the shared structural rules: edge-to-edge `[data-flux-main]` with per-page insets and the `data-full-bleed` opt-out, the sidebar current/hover treatment, the `btn-*`, `chip-classical`, `badge-classical`, `field-classical`, `check-classical`, `meta-classical` primitives, and the editor/solver animations that were already on main. Note the base-layer typography reads from `--tracking-*`, `--weight-*`, `--leading-heading` variables so the modern theme can reset them.

Add `data-theme="{{ \App\Enums\Theme::current()->value }}"` to the `<html>` tag in every layout that has one: `layouts/app/sidebar`, `layouts/app/header`, `layouts/auth/card`, `layouts/auth/simple`, `layouts/auth/split`, `layouts/public`, `welcome`, `errors/layout`.

Remove the Instrument Sans `<link>` from `resources/views/partials/head.blade.php` and `resources/views/layouts/public.blade.php` (the CSS now imports it).

Tests: `tests/Feature/ThemeTest.php`.

## Phase 2: Branding and PWA assets (verbatim)

- `public/logo.svg` (new), `public/favicon.ico`, `public/apple-touch-icon.png`, `public/android-chrome-192x192.png`, `public/android-chrome-512x512.png`, `public/site.webmanifest`, `public/offline.html`
- Delete `public/favicon-16x16.png` and `public/favicon-32x32.png`
- `resources/views/components/app-logo-icon.blade.php` (now an `<img>` of `logo.svg`)
- `resources/views/components/app-logo.blade.php` (shows the app name by default, logo is `h-7 w-auto`)
- `resources/views/partials/head.blade.php` favicon block: svg icon, ico fallback at 32x32, apple-touch-icon at 180x180, in that order
- The same three `<link>` tags in `resources/views/layouts/public.blade.php`
- `tests/Feature/Pwa/PwaManifestTest.php` and `tests/Feature/BrandingTest.php`

Tests: those two files.

## Phase 3: Backend fixes and config (diff and apply)

- `app/Models/Crossword.php`: `puzzleTypeLabel()` treats a null `puzzle_type` as Standard (also moves two `use` lines into alphabetical order). Test: `tests/Feature/Crossword/CrosswordPuzzleTypeLabelTest.php`.
- `app/Enums/PuzzleType.php`: shorter `description()` strings.
- `app/Support/PlanLimits.php` + `config/crosswordbuilder.php`: guest puzzle cap comes from `crosswordbuilder.guest_puzzle_limit` (`CROSSWORDBUILDER_GUEST_PUZZLE_LIMIT`, default 1). Add the env line to `.env.example`. Test: the two `planLimits` cases in `tests/Feature/AnonymousUserFlowTest.php` (take the redesign version of that file, minus the sidebar sign-up cases until Phase 5).
- Generated source titles for untitled puzzles: `pages/clues/⚡index` and `pages/words/⚡show` widen their `crossword` eager-load to `id,title,width,height,puzzle_type,grid,styles` and call `displayTitle()` instead of `title` on the source chip; `pages/puzzles/⚡index` widens the `structuredDataPuzzles()` select the same way. These land with the views in Phase 6, so port `tests/Feature/ClueLibraryTest.php`, `tests/Feature/WordCatalogTest.php` and `tests/Feature/Puzzles/PublicBrowseTest.php` there.

## Phase 4: Feature removals

- **Batch PDF export.** Delete `PdfExporter::exportBatch()`, `resources/views/exports/crossword-batch-pdf.blade.php`, and `tests/Feature/BatchPdfExportTest.php`. The Build page keeps a single-puzzle export from the card menu; `tests/Feature/BuildPagePdfExportTest.php` (new) covers it and lands with the Build page in Phase 6.
- **Build/Solve dashboard switch.** Delete `resources/views/components/dashboard-switch.blade.php`. Build and Solve become separate sidebar items (Phase 5) and each page gets a plain heading. Test changes are in `tests/Feature/DashboardTest.php` (take the redesign version).
- **Daily puzzle banner component.** Delete `resources/views/components/daily-puzzle-banner.blade.php` (unused after the Solve page port).
- **"Download for offline solving" button** on the solver page and its test `tests/Feature/Crossword/OfflineDownloadTest.php` (delete the test file; the case is gone on redesign).
- **Keyboard shortcuts help button** on the solver page and `tests/Feature/KeyboardShortcutsOverlayTest.php` (delete).
- **Constructor analytics page content**: the analytics component is reduced to overview cards on the Build page, and the cell-difficulty cases are dropped. `tests/Feature/Crossword/ConstructorAnalyticsTest.php` (take the redesign version). The component itself ports in Phase 6.

Deleting the views before their pages are ported will break the pages that include them, so do Phase 4 and Phase 5/6 in the same working session and only commit when tests pass; if you prefer, fold Phase 4's deletions into the phase that ports the page that used each one.

## Phase 5: Layout and navigation (verbatim)

- `resources/views/layouts/app/sidebar.blade.php`: sidebar has no outer padding, header gets its own padding and bottom rule; nav groups are `px-4` with inset `flux:separator`s between them; Build (wrench-screwdriver) and Solve (play) replace the single Dashboard item, Favorites moves into the first group; the footer nav gets a top rule; anonymous users see a `Sign up` button (`data-test="sidebar-sign-up-button"` on desktop, `mobile-sign-up-button` in the mobile header) instead of the user menu.
- `resources/views/layouts/app/header.blade.php`, `resources/views/layouts/public.blade.php`, `resources/views/errors/layout.blade.php`, `resources/views/partials/guest-banner.blade.php`: diff and apply.
- New shared components, verbatim: `components/page-header.blade.php` (kicker/title/subtitle/meta/badges/leading slots, `level`, `size`, `bleed`, `rule` props, emits `data-page-header`), `components/header-button.blade.php`, `components/switch-classical.blade.php`.
- `resources/views/dashboard.blade.php` and `resources/views/partials/settings-heading.blade.php` use `<x-page-header>`.
- `resources/views/pages/settings/layout.blade.php`: settings nav is a plain `<nav><ul>` built from a route array with `aria-current`, and the heading is an `<x-page-header size="md" :level="2" :bleed="false">`.

Tests: `tests/Feature/DashboardTest.php`, `tests/Feature/AnonymousUserFlowTest.php` (full redesign version now), `tests/Feature/BrandingTest.php`.

## Phase 6: Pages and components

Port each file below by copying it from `redesign` (`git checkout redesign -- "<path>"`). The per-file notes say what changed structurally so you can sanity-check the result and know which tests cover it. Where a note says "skin only" the file can still be copied; it costs nothing and keeps the classical theme complete.

**Shared components** (copy first; the pages depend on them)

- `components/⚡constructor-stats.blade.php` (new): the five computed counts (`publishedCount`, `draftCount`, `totalSolves`, `totalCompletions`, `totalLikes`) and the overview-card strip, extracted from `⚡constructor-analytics`. Rendered on the Build page as `<livewire:constructor-stats key="constructor-stats" />`.
- `components/⚡constructor-analytics.blade.php`: loses those five props, the `cellDifficulty()` computed and the "Puzzle Insights" heatmap section, and its page heading. `flux:table` becomes a native `<table>` driven by a `$columns` array with `wire:click="sortBy(...)"` header buttons. The rating-trend chart is now rendered server-side from `$this->ratingTrend` (gridlines, points and labels via Blade loops with PHP-computed min/max) and measures its width with a ResizeObserver in `x-init`.
- `components/⚡puzzle-card.blade.php`: card order is title and author, then thumbnail, then badges, meta and actions. The daily banner strip becomes an inline star line and the solved indicator an inline chip. Flux badge/heading/text/button are replaced with plain markup. Like button gains `aria-pressed`. Passes `frame-class`, `open-class`, `block-class` to the thumbnail.
- `components/⚡puzzle-discovery.blade.php`: root gains `@container`; Flux inputs and selects become native controls; heading and sort move into a bordered header row; the empty state is rebuilt; the results grid becomes `repeat(auto-fill, minmax(268px, 1fr))`.
- `components/⚡welcome-builder.blade.php`: footer becomes a single row with a new "No sign-in needed for your first puzzle." message when under the limit; labels are uppercased in the source copy.
- `components/grid-thumbnail.blade.php`: new `fluid`, `frameClass`, `openClass`, `blockClass` props; with `fluid` the wrapper is `grid w-full` with no fixed pixel width. Callers pass the colours instead of the component hardcoding them.
- `components/puzzle-completeness-bar.blade.php`: label row ("Complete" / "Grid & clues") plus percentage above a 2px bar; the conditional colour ramp is gone.
- `components/puzzle-details.blade.php`: unused `$completeness` block removed; `<div>`s become `<span>`s separated by `&middot;` with truncation.
- `components/leaderboard-rank.blade.php`, `components/leaderboard-empty.blade.php`: outlined rank circles, fixed-size non-podium box, `flux:text` to `<p>`. Skin apart from that.

**Pages**

- `pages/crosswords/⚡index.blade.php` (Build page, the largest change). PHP: drops the `PdfExporter` and `StreamedResponse` imports and the whole multi-select batch export (`$selectedPuzzles`, `$showBatchPdfModal`, `$batchPdfOrientation`, `$batchPdfTitle`, `togglePuzzleSelection`, `selectAllPuzzles`, `clearSelection`, `openBatchPdfExport`, `exportBatchPdf`, `cancelBatchPdfExport`); adds `use App\Livewire\Concerns\ExportsCrossword` (the trait already exists on main), `?int $pdfExportPuzzleId`, `choosePdfExportFor(int $id)`, computed `pdfExportCrossword()`, and the trait hooks `getExportableCrossword()`, `getPdfIncludeSolution()`, `getExportPlanGates()` (`pdf => 'canExportPdf'`) and `onExportUpgradeRequired()` (toasts "Create a free account to export PDFs."). Markup: `<x-page-header kicker="Your workshop" title="Build puzzles" :bleed="false">` with two `<x-header-button>`s; root gets `data-full-bleed` and sections carry their own gutters; `<livewire:constructor-stats>` above the filters and an `<hr>` before the analytics block; filters become a native search input, a peer-radio segmented control and a `field-classical` select ("Sort: Newest"); the selection toolbar and per-card checkboxes are gone; cards are `<article>`s with an explicit "Open editor" button and an always-visible dropdown that gains "Export PDF"; an Alpine wrapper (`data-test="puzzle-results"`) collapses the grid to its first row with a "Show all puzzles" / "Show fewer" toggle (`data-test="toggle-all-puzzles-button"`) and a "Showing X of Y puzzles" counter; the batch modal is replaced by a per-puzzle "PDF Export Settings" modal (orientation radios, header image upload with remove/restore, narrative textarea, `cancelPdfExport` / `confirmPdfExport`). Tests: `BuildPagePdfExportTest`, `MyPuzzlesFilterTest`, `BuildPageResultsCollapseTest` (browser), `ConstructorAnalyticsTest`, `DashboardTest`.
- `pages/crosswords/⚡solving.blade.php` (Solve page): `<x-dashboard-switch>` replaced by `<x-page-header kicker="Your solving" title="Solve">` plus two header buttons. Daily banner simplified to a single `$dailySolved` flag (the gradient and icon-colour variables are deleted). Attempt cards restructured like the build cards (`<article>`, title and status chip first, thumbnail link, labelled progress bar, explicit "Continue" / "Review" button, always-visible dropdown, auto-fill grid). Contest, Following, Trending and Newest sections become plain markup with ruled section headers and `divide-y` rows. Streak, stats and community stats become bordered tiles, the two stat cards merged into one 2-up grid.
- `pages/crosswords/⚡stats.blade.php`: `<x-page-header kicker="Your progress" title="Solve Statistics">` with a back button; summary cards merged into one divided grid; grid-size and difficulty blocks become `<dl>`; the solve-history `flux:table` becomes a native sortable `<table>`.
- `pages/⚡leaderboard.blade.php`: `<x-page-header kicker="Community">`; tabs become a peer-radio segmented control; all four `flux:table`s become native tables with `wire:key` per row and a current-user inset-shadow highlight; the four "Your Rank" cards are restructured. (A literal `\n` bug in this file was fixed on `redesign` before you started; if you see `\n` text in the file, it means you copied a stale version.)
- `pages/clues/⚡index.blade.php`: eager-load widening and `displayTitle()` (see Phase 3); `<x-page-header kicker="Reference">` and header button; native search and select; native sortable table; inline edit inputs use `field-classical`; pagination rendered via `$this->clues->links()` guarded by `hasPages()`.
- `pages/words/⚡index.blade.php` and `⚡show.blade.php`: index gets the page header (subtitle carries the word count), native inputs, a native sortable table whose rows contain a real `<a wire:navigate>` on the word, manual pagination. Show gets the same eager-load widening and `displayTitle()` as clues, a bordered `<h1>` band, and a native table with manual pagination.
- `pages/puzzles/⚡index.blade.php`: `structuredDataPuzzles()` select widening (see Phase 3) plus the page header. Structural only.
- `pages/puzzles/⚡daily-history.blade.php`: page header with back button; cards become `<article>` with title and author above the thumbnail; today's ring becomes an amber border.
- `pages/constructors/⚡index.blade.php`: page header; native search and select ("Sort: …"); rebuilt empty state; auto-fill grid; stats row gains a rule and `divide-x`.
- `pages/constructors/⚡show.blade.php`: profile header rebuilt on `<x-page-header>` using the `leading` (avatar), `subtitle` (bio) and `meta` (join date and counts) slots; Follow becomes an `<x-header-button>` with the report button as a sibling; native filters with a peer-radio difficulty control; puzzle cards reordered with a new "Solve" pseudo-button; empty-state text promoted to `<h3>`.
- `pages/contests/⚡index.blade.php` and `⚡show.blade.php`: index gets a page header for the active section and `<h2>`s with rules for Upcoming and Past, meta lines with `&middot;` separators, auto-fill grids. Show rebuilds the header on `<x-page-header kicker="Contest">` with `badges` and `meta` slots; solved badge becomes a chip with an inline check; Solve button becomes an anchor.
- `pages/favorites/⚡index.blade.php`: `<x-page-header kicker="Your collection" :rule="false">` with header button; list tabs become real `<button role="tab">`s inside `role="tablist"` with `aria-selected` and `wire:key`; cards become `<article>`s with an explicit "Solve" button and an always-visible dropdown.
- `pages/roadmap/_item.blade.php` and `⚡index.blade.php`: item status icon in a bordered tile; the meta row is wrapped in `@if($item->target_date || $item->completed_date)`; the action dropdown is no longer hover-only. Index gets the page header, a native type select, ruled section headers with chip counts, rebuilt empty state.
- `pages/settings/*.blade.php` and `settings/two-factor/⚡recovery-codes.blade.php` (one shared pattern): section headers become `<h2>/<p>` in a ruled block and each sub-section is wrapped in `border-t pt-8`; every Flux form control becomes a native `<label>/<input>/<textarea>/<select>` with `field-classical` and an `@error` paragraph; `flux:button` becomes `btn-classical`; `flux:switch` becomes `<x-switch-classical>` with `aria-label`s (notifications); `flux:checkbox` becomes a native `check-classical` box (profile); appearance's `flux:radio.group` becomes a peer-radio control bound to `x-model="$flux.appearance"`; webhooks' empty callout becomes a dashed empty state and its icon buttons gain `aria-label`s. Note: password inputs lose Flux's show/hide toggle. If a settings test exercises a Flux control by name, take the redesign version of that test.
- `pages/support/⚡create`, `⚡index`, `⚡show`: page headers with an icon-only back `<x-header-button aria-label="…">`; create's help callout becomes a bordered box with an inline link; index's ticket list becomes one bordered `divide-y` list with a native status select; show's responses section gains a ruled header and a native textarea. Status and priority chips gain amber emphasis for `in_progress`, `resolved`, `high` and `urgent`.
- `welcome.blade.php`: nav logo becomes `<x-app-logo-icon>` plus name, container widens to `max-w-7xl`, Dashboard / Sign up become outline buttons. The hero becomes a two-column grid: a new left copy column (kicker line "Free forever · N constructors", `<h1>` "Build a crossword in ten minutes.", descriptive paragraph) and the Build/Solve toggle plus panels on the right, with the toggle in the panel header row and short headings ("Browse tons of puzzles." / "Start with a shape.") beside it. The signup modal and both panels are re-nested inside the right column.

Tests for this phase: `tests/Feature/BuildPagePdfExportTest.php`, `tests/Feature/Crossword/MyPuzzlesFilterTest.php`, `tests/Browser/BuildPageResultsCollapseTest.php`, `tests/Feature/Crossword/ConstructorAnalyticsTest.php`, `tests/Feature/ClueLibraryTest.php`, `tests/Feature/WordCatalogTest.php`, `tests/Feature/Puzzles/PublicBrowseTest.php`, plus any existing test that names one of the ported pages (grep `tests/` for the route names).

## Phase 7: Verify both themes

1. `npm run build`; confirm the built `app-*.css` contains both `:root[data-theme=modern]` and `:root[data-theme=modern].dark` blocks and the `--tracking-body`, `--weight-heading` variables.
2. Full suite: `php artisan test --compact --parallel`.
3. With the app served by Herd, load the Build page, the Solve page, Settings > Profile, the public browse page and the welcome page, first with `APP_THEME=modern` (default) and then with `APP_THEME=classical` in `.env` (run `php artisan config:clear` between). Under `modern` the pages must use Instrument Sans, the cool zinc ramp and default radii; under `classical` they must use Lora/Cormorant, the warm ramp and 4px corners. Note anything that reads wrong under `modern` (an outline button, uppercase chip or hairline rule that clashes with the rest of main's look) in the final report rather than fixing it; that is the follow-up normalisation pass.
4. Restore `.env` to `APP_THEME=modern`.

## Final report

List each phase with its commit hash, the test files that ran, anything skipped and why, and the list of visual issues from step 7.3.
