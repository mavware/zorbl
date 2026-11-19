# Port the redesign branch's structural changes to main (Gemini / Antigravity Edition)

You are on branch `main`. Branch `redesign` is a visual redesign that also carries a set of structural changes: new layout components, a reorganised sidebar, removed features, bug fixes, PWA/branding assets, new tests, and a switchable theme system. `main` has not moved since `redesign` branched from it, so `git diff main redesign -- <path>` is exactly what the redesign changed to a file, and `git checkout redesign -- <path>` copies a file over verbatim.

**Goal**: Bring the structure and theme system to `main` so that `main` renders its existing look by default and can switch to the redesign look with `APP_THEME=classical`. Do not merge the branch. Work through the phases below, one commit per phase, running the named tests after each.

---

## Decisions already made (do not revisit)

- **Default theme on main is `modern`.** Set `'theme' => env('APP_THEME', 'modern')` in `config/app.php` and `APP_THEME=modern` in `.env.example`, change the fallback in `Theme::current()` to `self::Modern`, and update the fallback cases in `tests/Feature/ThemeTest.php` to expect `modern`. `resources/css/themes/classical.css` stays the base `@theme` declaration (Tailwind needs exactly one) and `modern.css` overrides it; with `data-theme="modern"` on `<html>`, main's palette, fonts and radii are restored.
- **Views are ported with their redesign classes intact.** The redesign's semantic classes (`text-ink`, `text-ink-muted`, `border-hairline`, `font-classical`, `btn-classical*`, `btn-header*`, `meta-classical`, `chip-classical`, `field-classical`) are all token-driven, and `modern.css` maps every token onto main's palette and Instrument Sans. Porting a view therefore means copying it from `redesign`, not rewriting its classes. Do not translate classes back to raw zinc/amber utilities.
- **Feature removals on the redesign are intentional** (listed in Phase 4). Port the removals and their test changes. Do not keep the removed code "just in case".
- **Do not touch `resources/css/embed.css`** or `resources/views/embed/solver.blade.php`. The embed solver keeps main's styling.

---

## Working Rules & Quality Enforcement

1. Before each phase run `git diff main redesign -- <paths>` for the files in that phase and read it; only then apply. For files listed as "verbatim", use `git checkout redesign -- <path>`.
2. After each phase:
   - Format PHP: `vendor/bin/pint --dirty --format agent`
   - Run phase tests: `php artisan test --compact <test_files>`
   - Build assets: `npm run build` if CSS or Blade changed.
   - Fix any failures before committing.
   - Commit with a clear commit message that explicitly names the phase (e.g. `feat(theme): port phase 1 theme system to main`).
3. Maintain an execution log in an Antigravity markdown artifact summarizing progress after each phase.
4. At the end run the full test suite: `php artisan test --compact --parallel`. Everything must pass.

---

## Phase 1: Theme system (verbatim)

Copy these from `redesign` unchanged, then apply the "default theme is modern" decision:
- `app/Enums/Theme.php`
- `resources/css/themes/classical.css`
- `resources/css/themes/modern.css`
- `tests/Feature/ThemeTest.php`
- `config/app.php` (only the new `theme` block; diff it, do not overwrite the file)
- `.env.example` (only the `APP_THEME` line)

Port `resources/css/app.css` verbatim from `redesign`. Add `data-theme="{{ \App\Enums\Theme::current()->value }}"` to the `<html>` tag in every layout that has one: `layouts/app/sidebar`, `layouts/app/header`, `layouts/auth/card`, `layouts/auth/simple`, `layouts/auth/split`, `layouts/public`, `welcome`, `errors/layout`.

Remove the Instrument Sans `<link>` from `resources/views/partials/head.blade.php` and `resources/views/layouts/public.blade.php`.

Test: `php artisan test --compact tests/Feature/ThemeTest.php`

---

## Phase 2: Branding and PWA assets (verbatim)

- `public/logo.svg`, `public/favicon.ico`, `public/apple-touch-icon.png`, `public/android-chrome-192x192.png`, `public/android-chrome-512x512.png`, `public/site.webmanifest`, `public/offline.html`
- Delete `public/favicon-16x16.png` and `public/favicon-32x32.png`
- `resources/views/components/app-logo-icon.blade.php`
- `resources/views/components/app-logo.blade.php`
- `resources/views/partials/head.blade.php` & `resources/views/layouts/public.blade.php` favicon blocks.

Tests: `tests/Feature/Pwa/PwaManifestTest.php`, `tests/Feature/BrandingTest.php`

---

## Phase 3: Backend fixes and config (diff and apply)

- `app/Models/Crossword.php`: `puzzleTypeLabel()` treats null as Standard. Test: `tests/Feature/Crossword/CrosswordPuzzleTypeLabelTest.php`
- `app/Enums/PuzzleType.php`: shorter `description()` strings.
- `app/Support/PlanLimits.php` + `config/crosswordbuilder.php`: guest puzzle cap from `crosswordbuilder.guest_puzzle_limit`. Test: `tests/Feature/AnonymousUserFlowTest.php`

---

## Phase 4: Feature removals

- **Batch PDF export**: Delete `PdfExporter::exportBatch()`, `resources/views/exports/crossword-batch-pdf.blade.php`, and `tests/Feature/BatchPdfExportTest.php`.
- **Build/Solve dashboard switch**: Delete `resources/views/components/dashboard-switch.blade.php`.
- **Daily puzzle banner component**: Delete `resources/views/components/daily-puzzle-banner.blade.php`.
- **Download for offline solving**: Remove button and delete `tests/Feature/Crossword/OfflineDownloadTest.php`.
- **Keyboard shortcuts help button**: Remove button and delete `tests/Feature/KeyboardShortcutsOverlayTest.php`.
- **Constructor analytics page content**: Reduced to overview cards on Build page. Update `tests/Feature/Crossword/ConstructorAnalyticsTest.php`.

---

## Phase 5: Layout and navigation (verbatim)

- `resources/views/layouts/app/sidebar.blade.php`
- `resources/views/layouts/app/header.blade.php`, `resources/views/layouts/public.blade.php`, `resources/views/errors/layout.blade.php`, `resources/views/partials/guest-banner.blade.php`
- New shared components: `components/page-header.blade.php`, `components/header-button.blade.php`, `components/switch-classical.blade.php`
- `resources/views/dashboard.blade.php`, `resources/views/partials/settings-heading.blade.php`, `resources/views/pages/settings/layout.blade.php`

Tests: `tests/Feature/DashboardTest.php`, `tests/Feature/AnonymousUserFlowTest.php`, `tests/Feature/BrandingTest.php`

---

## Phase 6: Pages and components

Copy from `redesign` (`git checkout redesign -- "<path>"`):
- Components: `⚡constructor-stats.blade.php`, `⚡constructor-analytics.blade.php`, `⚡puzzle-card.blade.php`, `⚡puzzle-discovery.blade.php`, `⚡welcome-builder.blade.php`, `grid-thumbnail.blade.php`, `puzzle-completeness-bar.blade.php`, `puzzle-details.blade.php`, `leaderboard-rank.blade.php`, `leaderboard-empty.blade.php`.
- Pages: `pages/crosswords/⚡index.blade.php`, `pages/crosswords/⚡solving.blade.php`, `pages/crosswords/⚡stats.blade.php`, `pages/⚡leaderboard.blade.php`, `pages/clues/⚡index.blade.php`, `pages/words/⚡index.blade.php` & `⚡show.blade.php`, `pages/puzzles/⚡index.blade.php`, `pages/puzzles/⚡daily-history.blade.php`, `pages/constructors/⚡index.blade.php` & `⚡show.blade.php`, `pages/contests/⚡index.blade.php` & `⚡show.blade.php`, `pages/favorites/⚡index.blade.php`, `pages/roadmap/*`, `pages/settings/*`, `pages/support/*`, `welcome.blade.php`.

Tests: `tests/Feature/BuildPagePdfExportTest.php`, `tests/Feature/Crossword/MyPuzzlesFilterTest.php`, `tests/Browser/BuildPageResultsCollapseTest.php`, `tests/Feature/Crossword/ConstructorAnalyticsTest.php`, `tests/Feature/ClueLibraryTest.php`, `tests/Feature/WordCatalogTest.php`, `tests/Feature/Puzzles/PublicBrowseTest.php`.

---

## Phase 7: Theme Verification & Final Report

1. Run `npm run build`.
2. Full suite: `php artisan test --compact --parallel`.
3. Check visual rendering under `APP_THEME=modern` and `APP_THEME=classical`.
4. Restore `APP_THEME=modern` in `.env`.
5. Output final summary artifact linking commits and tests passed.
