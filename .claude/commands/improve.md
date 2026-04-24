# Autonomous Improvement Session

**Project**
Laravel TALL stack (Tailwind, Alpine.js, Laravel, Livewire) crossword app. Users build, play, share, and publish crosswords, with creativity-assist tools for construction.

**Your task**
Survey the project, pick one valuable improvement, implement it end-to-end on a new branch, and open a PR summarizing what you did.

---

## Environment bootstrap

Before anything else, ensure the environment is ready:

- Run `composer install --no-interaction --ignore-platform-reqs` if `vendor/` is missing.
- Copy `.env.example` to `.env` and run `php artisan key:generate --no-interaction` if `.env` is missing. Never commit `.env`.
- Install `gh` CLI if missing: download the latest linux amd64 tarball from GitHub releases, extract, and copy to `/usr/local/bin/`. Authenticate with `$GH_TOKEN` if set in the environment.

## Survey first

Before picking anything, get the lay of the land:

- Read `CLAUDE.md` in the repo root and follow its rules (Pint formatting, Pest testing, Laravel Boost conventions, Herd URLs).
- Read `TODO.md` — the `Open` section is your default source of work. Prefer picking from there unless survey reveals a clearly higher-value opportunity.
- `git log --oneline -20` and inspect recent `claude/*` and `auto/*` branches so you don't repeat or undo past automated runs.
- Check open and recent PRs with `gh pr list --repo mavware/zorbl --state all --limit 30` (skip if `gh` isn't authenticated yet), and skim merged `claude/*` / `auto/*` diffs from the last week — do **not** re-pick work that has already been merged.
- List routes, check for pending migrations, scan recent logs for errors or warnings.
- Run the targeted test files relevant to the area you plan to change — note anything already failing before you touch code. **Do not run the full test suite upfront**; it takes 8+ minutes and browser tests have pre-existing failures.
- Skim TODO/FIXME comments and recently modified files.

## Pick one improvement

Sized to finish in this session. Default to a checklist item from `./TODO.md`. Good categories, in rough priority order:

- **Checklist items in `./TODO.md` Open section (default pick).**
- New feature or refinement to an existing one.
- UI/UX polish (Tailwind/Livewire/Alpine).
- Refactor or architectural cleanup.
- A bug or performance fix you spotted during the survey.
- Documentation — README, docblocks, ADRs, architecture notes.
- Tests — unit, feature, or browser tests.
- Dev tooling, logging, or observability that helps future runs.
- A useful PHP/Laravel package, with justification.

**Rotation guardrail.** If the last two merged `claude/*` or `auto/*` PRs were test-only, do **not** pick another test-only improvement — rotate to a feature, refactor, UX polish, or bugfix instead.

**Duplication guardrail.** Before implementing, grep merged PR titles/diffs for the area you plan to touch and confirm you're not redoing something already merged. If you are, pick something else.

## Implementation

1. Create a branch named **exactly** `claude/<kebab-case-descriptor>` describing what you're doing (e.g. `claude/bulk-schedule-contests`, `claude/puzzle-attempt-policy`). Do **not** accept a default random-adjective branch name — rename the branch before your first commit if the environment gave you one.
2. Make the change. Follow existing conventions — check neighboring files, not your assumptions.
3. Verify as you go (routes resolve, queries behave, etc.).
4. Add or update tests for new behavior. Run the affected test files to confirm they pass. Only run the full suite if changes touch shared code (models, middleware, service providers). Browser tests (`Tests\Browser\*`) have pre-existing failures — don't block on those.
5. Update any docs the change touches.
6. Run `vendor/bin/pint --dirty --format agent` on any PHP change before committing.
7. Commit with a clear message: what changed and why.
8. Push the branch with `git push -u origin <branch-name>`.
9. Open a PR with `gh pr create --repo mavware/zorbl --head <branch-name> --base main --title '<title>' --body '<body>'`. The PR body **must** be non-empty and contain the end-of-run summary — never open a PR with an empty body.

## Constraints

- No destructive changes: no dropping tables, deleting migrations, force-pushing, or rewriting history.
- Flag any new major dependency in your summary instead of quietly adding it.
- Prefer small, focused changes. If work grows beyond one session, commit what's stable and note what remains.
- Don't touch `.env`, secrets, or production config.
- **`TODO.md` hygiene.** When you complete a checklist item, flip `- [ ]` → `- [x]` **in place** — do not append a duplicate line to `Done`. Before adding a new follow-up, grep the file to confirm the item isn't already present in either `Open` or `Done`.
- If you hit something needing credentials, keys, or external access — log the need once in `TODO.md` (deduped), then skip that improvement and pick another.
- If tests were already broken before you started, note them in your summary and pick an improvement that doesn't depend on them.

## End-of-run summary

Write this summary and **copy it verbatim into the PR body**:

- Branch name
- What changed (2–4 sentences) and why you picked it
- Test results (before/after)
- Any new dependencies
- Suggested follow-ups for future runs

Then append any new follow-up items to `./TODO.md` (deduped per the hygiene rule above).
