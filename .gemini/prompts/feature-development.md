# TALL Stack Feature Development Guide (Gemini / Antigravity)

Use this guide when building a new feature or major capability in Crossword Builder (`github.com/mavware/zorbl`).

## 1. Verified Stack & Architecture Requirements

- **Backend**: PHP 8.4+ (CI 8.5), **Laravel 13**, **Livewire 4** (single-file `⚡*.blade.php` components), **Filament v5** (admin panel).
  - Explicit parameter/return types, constructor property promotion, curly braces on all control structures, TitleCase enum keys. Do **not** add `declare(strict_types=1)` to `app/` files.
- **Frontend**: **Tailwind CSS v4**, **Alpine.js v3**, **Flux UI Free v2** (`livewire/flux` — no Flux Pro components), **Vite v8**, **Vitest v3**.
  - Use semantic theme token classes (`text-ink`, `text-ink-muted`, `border-hairline`, `btn-classical*`, etc.) so both `modern` and `classical` themes render cleanly.
- **Database & Eloquent**:
  - Inspect existing schemas via Laravel Boost `database-schema` before writing migrations or models.
  - Create models with factories/migrations via `php artisan make:model <Name> -mf --no-interaction`.
  - Follow existing API resource/versioning conventions under `app/Http/Controllers/Api/V1/`.

## 2. Execution Plan

1. **Clarification**: If requirements or schema/UI trade-offs are ambiguous, use `ask_question` before coding.
2. **Branching**: `git checkout -b gemini/<kebab-case-feature-name>`.
3. **Skills & Docs**:
   - Query Laravel Boost `search-docs` for version-specific APIs before coding.
   - Consult relevant skills in `.agents/skills/` (`livewire-development`, `fluxui-development`, `filament-development`, `laravel-best-practices`, `pest-testing`, `testing-best-practices`, `cashier-stripe-development`).
4. **Implementation**:
   - Keep Livewire state server-side; validate and authorize inside actions.
   - Reuse existing Blade components, policies, and model factory states.
5. **Quality Gate**:
   - Format PHP: `vendor/bin/pint --dirty --format agent`
   - Static analysis: `vendor/bin/phpstan analyse`
   - Targeted Pest tests: `php artisan test --compact tests/Feature/<FeatureTest>.php`
   - JS tests & assets (if frontend changed): `npm test` and `npm run build`.
6. **Summary**: Summarize routes/files changed, schema updates, and exact test/analysis results.
