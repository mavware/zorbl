# Gemini & Antigravity Project Instructions

## Foundational Context

This application is **Crossword Builder** (repository: `mavware/zorbl`), a full-featured Laravel TALL stack (Tailwind CSS v4, Alpine.js, Laravel 11/12, Livewire 3 + Flux UI Pro) application running on **PHP 8.5**.

- **Dev Environment**: Served locally via **Laravel Herd** at `https://crosswordbuilder.test`. Never attempt to run `php artisan serve`. Herd is always running and managing services.
- **PHP CLI**: Always use Herd's PHP binary when needed (`/Users/michael/Library/Application Support/Herd/bin/php85` or `php`).
- **Installed Packages**: Always check direct versions before assuming API syntax:
  - PHP: `composer show --direct` or `composer show <vendor/package>`
  - JS: `package.json`

---

## Safety Guardrails & Soft-Denies

1. **NEVER run destructive database commands**:
   - `php artisan migrate:fresh` or `migrate:reset` is strictly forbidden.
   - Do NOT delete or wipe SQLite databases (`database/*.sqlite`) or `storage/` contents.
2. **Protect Secrets & Environment**:
   - Never commit `.env` or print secret keys.
   - This repository is **PUBLIC** (`github.com/mavware/zorbl`). Never push private API keys, user PII, or confidential credentials.
3. **Branch Protection**:
   - Do NOT push directly to `main`. Create feature branches (`gemini/<feature-name>`).

---

## Laravel Boost & MCP Tools

- Prefer Laravel Boost MCP tools over shell commands where applicable:
  - `database-query`: Execute read-only SQL queries to inspect data.
  - `database-schema`: Inspect database tables, indexes, and columns before migrations/models.
  - `get-absolute-url`: Resolve valid URLs with correct scheme, domain, and port for `crosswordbuilder.test`.
  - `browser-logs`: Read recent client-side browser errors and exceptions.
- **Searching Documentation**:
  - Use `search-docs` for framework or package documentation queries (Laravel, Livewire, Filament, Pest).
  - Pass package arrays where applicable (e.g., `packages: ["livewire", "pest"]`).

---

## Code Style & Formatting

- **Pint Enforcement**:
  - Run `vendor/bin/pint --dirty --format agent` after any PHP modification before concluding the task.
  - Do NOT run `vendor/bin/pint --test`. Always apply changes cleanly.
- **PHP 8.5 Standards**:
  - Always use curly braces for control structures, even for single-line blocks.
  - Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) {}`.
  - Explicit parameter and return type declarations on all methods: `public function isAccessible(User $user, ?string $path = null): bool`.
  - Enum keys MUST be TitleCase: `FavoritePerson`, `BestLake`, `Monthly`.
  - Prefer PHPDoc blocks with array shape type definitions over vague inline comments.

---

## Testing with Pest

- **Framework**: All tests use **Pest**. Create tests with `php artisan make:test --pest {name}`.
- **Test Execution**:
  - Run narrow, targeted test suites covering your change:
    - `php artisan test --compact tests/Feature/YourTest.php`
    - Or direct binary: `vendor/bin/pest tests/Feature/YourTest.php --filter=testName`
  - Re-run the affected test after every change to verify the fix.
  - Note: Browser tests (`tests/Browser/*`) may require separate headless setup; prioritize Unit and Feature regression tests.
- **Test Integrity**:
  - Never delete existing tests without explicit user approval.
  - Use model factories and existing factory states rather than manual DB records.

---

## Livewire 3 & Frontend Architecture

- **State Management**: Keep state server-side. Livewire components update reactively.
- **Alpine.js**: Use Alpine for client-only UI interactions (modals, dropdowns, client animations).
- **Flux UI Pro**: Use Flux UI components (`<flux:...>`) according to design conventions. Check sibling views before introducing raw markup.
- **Vite Bundling**: If CSS/Blade changes don't reflect, run `npm run build`.

---

## Antigravity Capabilities & Workflow Automation

### 1. Dynamic Workflows (`run_workflow`)
For multi-phase or autonomous sessions (e.g. survey -> pre-check -> implementation -> verify -> PR), execute automated Python workflow scripts located in `workflows/` (such as `workflows/autonomous_improve.py`).

### 2. Specialized Subagents
When a task divides cleanly into independent angles:
- Delegate research or broad file scanning to `research` subagent.
- Use `self` subagent or define specialized agents (e.g. `Pest-Test-Specialist`, `Livewire-Refactorer`) for isolated parallel execution.

### 3. Visual & Structured Artifacts
- Render architecture workflows and state transitions using **Mermaid** diagrams (`flowchart TD`, `sequenceDiagram`, `stateDiagram-v2`).
- Store detailed architectural proposals, test results, and migration logs in markdown artifacts in the conversation artifact directory.
- Bookmark web resources and local URLs using `.url.json` artifacts (`UserFacing: true` for actionable review targets).

### 4. Interactive Clarification (`ask_question`)
- When requirements are ambiguous, or when architectural trade-offs exist (e.g., choice between database schema designs or Livewire component structures), invoke `ask_question` to present structured options to the user.

---

## Available Skills Reference (`.agents/skills/`)

Trigger the relevant skill from `.agents/skills/` based on the domain of your work:
- `pest-testing`: Pest PHP test creation, assertions, datasets, mocking, and Livewire component testing.
- `laravel-best-practices`: Eloquent performance, query optimization, caching, routes, events, validation.
- `livewire-development`: Livewire 3 components, Alpine hooks, reactivity, forms, actions.
- `fluxui-development`: Flux UI Pro components, patterns, themes, and layouts.
- `cashier-stripe-development` & `stripe-best-practices`: Subscriptions, webhooks, customer billing portal.
- `filament-development`: Filament resource panels, tables, form fields, and actions.
- `fortify-development`: Two-factor auth, registration, password resets, session management.
- `infer-conventions`: Scanning existing patterns, lint conventions, and project architecture.
- `laravel-permission-development`: Spatie permissions, roles, gates, and policies.
- `laravel-query-builder`: Advanced filtering, sorting, and eager loading.
- `pulse-development`: Laravel Pulse performance metrics, slow queries, and recorder config.
- `socialite-development`: OAuth providers (Google, GitHub, Apple) authentication flows.
- `tailwindcss-development`: Tailwind CSS v4 tokens, utility classes, and custom animations.
- `configure-nightwatch`: Laravel Nightwatch observability and monitoring.
- `run-workflow`: Dynamic Python orchestrations with parallel agents and phased pipelines.
