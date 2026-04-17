# Autonomous Improvement Session

**Project**
Laravel TALL stack (Tailwind, Alpine.js, Laravel, Livewire) crossword app. Users build, play, share, and publish crosswords, with creativity-assist tools for construction.

**Your task**
Survey the project, pick one valuable improvement, implement it end-to-end on a new branch, and summarize what you did.

---

## Survey first (use Laravel Boost)

Before picking anything, get the lay of the land:

- `git log --oneline -20` and check recent `auto/*` branches so you don't repeat or undo past automated runs
- Boost: list routes, check for pending migrations, scan recent logs for errors or warnings
- Run the test suite — note anything already failing before you touch code
- Skim TODO/FIXME comments and recently modified files
- Check `composer outdated` and obvious rough edges (N+1 queries in logs, missing indexes, untested Livewire components, etc.)

## Pick one improvement

Sized to finish in this session. Good categories:

- New feature or refinement to an existing one
- Tests — unit, feature, or Dusk browser tests
- Documentation — README, docblocks, ADRs, architecture notes
- A useful PHP/Laravel package, with justification
- Refactor or architectural cleanup
- UI/UX polish (Tailwind/Livewire/Alpine)
- Dev tooling, logging, or observability that helps future runs
- A bug or performance fix you spotted during the survey

## Implementation

1. Create a branch: `auto/<short-descriptive-name>`
2. Make the change. Follow existing conventions — check neighboring files, not your assumptions.
3. Use Boost to verify as you go (routes resolve, Tinker checks, queries behave).
4. Add or update tests for new behavior. Run the full suite; it must be green (or no worse than the pre-existing baseline you noted).
5. Update any docs the change touches.
6. Commit with a clear message: what changed and why.
7. Leave the branch for my review — **do not** merge to main or push unless I've asked you to.

## Constraints

- No destructive changes: no dropping tables, deleting migrations, force-pushing, or rewriting history.
- Flag any new major dependency in your summary instead of quietly adding it.
- Prefer changes that improve how the user uses the app or improve the developer experience.
- Prefer small, focused changes. If the work grows beyond one session, commit what's stable and note what remains.
- Don't touch `.env`, secrets, or production config.

## Stop and ask me if

- You need credentials, keys, or external access
- You hit an ambiguous product decision
- Tests were already broken before you started (report and wait)
- The best next step would be a breaking schema or API change
- You can't find anything meaningful left to improve

## End-of-run summary

- Branch name
- What changed (2–4 sentences) and why you picked it
- Test results (before/after)
- Any new dependencies
- Suggested follow-ups for future runs
