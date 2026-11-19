# Gemini & Antigravity Project Instructions

> **Note:** Laravel Boost guidelines and installed ecosystem rules are loaded from `AGENTS.md`. This file defines Crossword Builder–specific guardrails, workflows, and Gemini/Antigravity optimizations without duplicating `AGENTS.md`.

## Project & Runtime Snapshot

- **Application**: Crossword Builder (`github.com/mavware/zorbl`, **public repository**)
- **Local URL**: `https://crosswordbuilder.test` via **Laravel Herd** (never run `php artisan serve`).
- **Verified Stack** (always confirm via `composer show --direct` / `package.json` before assuming APIs):
  - **Backend**: PHP 8.4+ (CI on 8.5; local Herd PHP), **Laravel 13**, **Livewire 4** (single-file `⚡` components), **Flux UI Free v2** (`livewire/flux` — Flux Pro is *not* installed), **Filament v5**, Fortify, Cashier (Stripe), Sanctum, Socialite, Spatie Permission & Query Builder, Nightwatch, Pulse.
  - **Frontend**: **Tailwind CSS v4**, **Alpine.js v3**, **Vite v8**, **Vitest v3** (`tests/js/`).
  - **Quality**: **Pest v4** (+ Playwright browser plugin), **Larastan v3** (PHPStan level 2), **Laravel Pint v1**.

## Safety & Repository Guardrails

1. **No destructive database/storage commands**: Never run `php artisan migrate:fresh`, `php artisan migrate:reset`, or delete `database/*.sqlite*` or `storage/`.
2. **Public repo secret hygiene**: Never read/print/commit `.env`, `.env.production`, API keys, user PII, or personal machine paths (e.g., `/Users/<name>/...`). Use portable commands (`php`, `artisan`, `vendor/bin/...`).
3. **Branching**: Never push directly to `main` or force-push. Work on feature branches named `gemini/<kebab-case-description>`.
4. **Ignore large/generated data**: Respect `.geminiignore` (excludes 6.3 MB `database/data/wordlist.txt`, 1 MB `xd-puzzles-parsed.json`, SQLite DBs, lockfiles, `_ide_helper.php`).

## Verification & Quality Commands

- **Targeted Pest tests** (always run narrowest path first; avoid full 8+ min suite upfront):
  - `php artisan test --compact tests/Feature/YourTest.php`
  - `vendor/bin/pest tests/Feature/YourTest.php --filter="test name"`
- **Parallel Unit + Feature check** (matches CI; Browser tests have separate Playwright setup):
  - `vendor/bin/pest --parallel --compact --testsuite=Unit,Feature`
- **JavaScript unit tests**: `npm test` (Vitest for `tests/js/**/*.test.js`)
- **Static analysis**: `vendor/bin/phpstan analyse` (Larastan level 2 per `phpstan.neon.dist`)
- **Formatting (mandatory after any PHP edit)**: `vendor/bin/pint --dirty --format agent`
- **Frontend build**: `npm run build` after CSS/JS/Blade changes

## Codebase Conventions Not Covered in `AGENTS.md`

- **Type declarations**: Use explicit parameter and return types, constructor property promotion, curly braces on all control structures, and TitleCase enum keys. Do **not** add `declare(strict_types=1)` to `app/` files (the codebase omits it for consistency).
- **Modular rules & skills**:
  - Path-scoped Antigravity rules live in `.agents/rules/` (loaded automatically when editing matching files).
  - Domain skills live in `.agents/skills/` (loaded progressively on demand).
  - Workspace MCP (`laravel-boost`) is configured in `.agents/mcp_config.json` (Antigravity) and `.gemini/settings.json` (Gemini CLI).

## Gemini 4 Model & Antigravity Workflow Strategy

- **Model tiering**:
  - **Gemini 4 Neon** (`gemini-4-neon`, default): Daily frontier coding, Livewire/Laravel feature work, debugging, and reviews.
  - **Gemini 4 Carbon** (`gemini-4-carbon`): Escalate for deep architectural design, multi-file security audits, or complex long-horizon debugging.
  - **Gemini 4 Hydrogen** (`gemini-4-hydrogen` / `flash` subagent tier): Fast, low-cost execution for file surveys, targeted test runs, formatting checks, and parallel subagent fan-out.
- **Dynamic workflows**: Use `run_workflow` with `workflows/autonomous_improve.py` for phased autonomous improvement cycles (supports optional `Args` like `{"target_area": "clues", "dry_run": true}`).
- **Subagents**: Delegate read-only codebase sweeps to `research`; keep independent edits isolated with `workspace="branch"` when running parallel agents.
- **Interactive clarity**: Use `ask_question` before committing to ambiguous architectural or schema trade-offs.
