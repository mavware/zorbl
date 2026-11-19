# Gemini / Antigravity Autonomous Improvement Session

**Project**: Laravel TALL stack (Tailwind CSS v4, Alpine.js, Laravel, Livewire 3 + Flux UI Pro) crossword construction and solving platform.

**Goal**: Autonomously discover, design, implement, test, and package one high-value improvement on a dedicated branch with an end-to-end pull request.

---

## 1. Environment Bootstrap

Before initiating any code inspection or development:
1. Ensure dependencies are satisfied:
   - `composer install --no-interaction --ignore-platform-reqs` (if `vendor/` is missing).
   - `npm install` (if `node_modules/` is missing).
2. Ensure environment is configured:
   - Verify `.env` exists; if not, copy `.env.example` to `.env` and run `php artisan key:generate --no-interaction`.
   - Never commit or disclose `.env`.
3. Verify local Herd domain:
   - App is hosted at `https://crosswordbuilder.test`.

---

## 2. Survey & Candidate Discovery

1. **Read Guidelines**:
   - Consult `GEMINI.md` for project rules, Pest guidelines, Pint requirements, and MCP tool instructions.
2. **Consult Backlog**:
   - Check `TODO.md` — the `Open` section is the primary source of work.
3. **Inspect Recent Commits & PRs**:
   - Run `git log --oneline -20` to inspect recent work across `gemini/*`, `claude/*`, and `auto/*` branches.
   - Run `gh pr list --state all --limit 25` to ensure the candidate work is not already pending or merged.
4. **Guardrails**:
   - **Rotation Guardrail**: If the last two automated PRs were purely test-focused, rotate to feature, refactoring, UX polish, or performance fix.
   - **Duplication Guardrail**: Grep merged commit titles and PR diffs before starting.
   - **Baseline Test Check**: Run the narrow test file for the candidate area (`php artisan test --compact <path>`). Record any pre-existing failures. **Do not run the entire test suite upfront**.

---

## 3. Implementation Workflow

1. **Branching**:
   - Create a clean git branch named `gemini/<kebab-case-description>` (e.g. `gemini/add-clue-difficulty-rating`).
2. **Implementation**:
   - Follow project conventions (match neighboring files for Livewire components, Blade templates, and models).
   - Use `database-schema` or `database-query` if database structures are involved.
3. **Testing**:
   - Add or update Pest tests in `tests/Feature/` or `tests/Unit/`.
   - Run targeted tests: `php artisan test --compact tests/Feature/<TargetTest>.php`.
4. **Formatting & Assets**:
   - Run Pint: `vendor/bin/pint --dirty --format agent`.
   - If CSS or Blade templates were modified, run `npm run build`.
5. **Backlog Hygiene**:
   - In `TODO.md`, update `- [ ]` to `- [x]` in-place (do not duplicate into Done).
6. **Git Commit & Pull Request**:
   - Commit with a descriptive message explaining the change and rationale.
   - Push branch: `git push -u origin gemini/<kebab-case-description>`.
   - Create PR using `gh pr create --title "..." --body "..."` with the End-of-Run summary.

---

## 4. End-of-Run Summary (PR Body)

The PR body must contain:
- **Branch**: `gemini/<kebab-case-description>`
- **Description**: 2–4 sentences describing what changed and why it was selected.
- **Verification**: Exact Pest test output before and after.
- **Dependencies**: Any new packages added (or state "None").
- **Follow-up Opportunities**: Suggested items for future runs.
