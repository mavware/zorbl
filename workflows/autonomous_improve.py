"""
Autonomous Improvement Workflow for Gemini / Antigravity (Crossword Builder)

Orchestrates an end-to-end improvement cycle using Gemini 4 model tiering:
- Fast tier ("flash" / Hydrogen-class): parallel backlog & git/PR survey, baseline tests, quality gate
- Frontier tier ("inherit" / Neon or Carbon): implementation & focused code review

Optional Args:
- target_area (str): Optional area to focus on (e.g. "clues", "livewire", "api", "styling")
- dry_run (bool): When true, stops after verification without pushing or opening a PR (default: false)
"""


async def main():
    target_area = args.get("target_area", "") if "args" in globals() and isinstance(args, dict) else ""
    dry_run = bool(args.get("dry_run", False)) if "args" in globals() and isinstance(args, dict) else False

    phase("1. Parallel Survey & Discovery")
    log(f"Surveying backlog and recent activity (target_area={target_area or 'any'}, dry_run={dry_run})...")

    survey_tasks = [
        (
            "Backlog Scanner",
            f"""
Read TODO.md and list up to 8 high-value uncompleted checklist items (`- [ ]`) from the Open section.
{f"Prioritize items related to: {target_area}" if target_area else ""}
Return concise candidate titles, areas, and likely files/tests.
""",
        ),
        (
            "History & PR Auditor",
            """
Inspect recent repository history so we avoid duplicate work and respect the rotation guardrail:
1. Run `git log --oneline -20`
2. Run `gh pr list --repo mavware/zorbl --state all --limit 25` (if authenticated; note if unavailable)
Summarize:
- Recent merged/open automated PRs (`gemini/*`, `claude/*`, `auto/*`)
- Whether the last 2 automated PRs were test-only (rotation guardrail trigger)
- Areas already recently touched that should not be duplicated
""",
        ),
    ]

    backlog_summary, history_summary = await parallel(
        survey_tasks,
        lambda item: agent(
            prompt=item[1],
            schema={"type": "string"},
            role=item[0],
            model="flash",
        ),
    )

    selection = await agent(
        prompt=f"""
You are the engineering planner for Crossword Builder (Laravel 13, Livewire 4, Flux UI Free v2, Pest 4, Vitest).
Choose ONE self-contained, high-value improvement completable in a single session.
{f"Preferred focus area: {target_area}" if target_area else ""}

Backlog findings:
{backlog_summary}

Recent history & PR audit:
{history_summary}

Rules:
- Enforce the rotation guardrail: if the last 2 automated PRs were test-only, do NOT pick a test-only task.
- Avoid duplicating any pending or recently merged PR.
- Return a clean kebab-case branch name starting with `gemini/`.
- Identify the narrowest Pest test paths (and `tests/js/` paths if touching JS) to verify before and after.
""",
        schema={
            "type": "object",
            "properties": {
                "candidate_title": {"type": "string"},
                "area": {"type": "string"},
                "reason": {"type": "string"},
                "target_tests": {"type": "array", "items": {"type": "string"}},
                "touches_frontend": {"type": "boolean"},
                "proposed_branch": {"type": "string"},
            },
            "required": [
                "candidate_title",
                "area",
                "reason",
                "target_tests",
                "touches_frontend",
                "proposed_branch",
            ],
        },
        role="Candidate Planner",
        model="inherit",
    )

    log(f"Selected: {selection['candidate_title']} ({selection['area']})")
    log(f"Branch: {selection['proposed_branch']}")
    log(f"Target tests: {selection['target_tests']}")

    phase("2. Baseline Pre-check")
    precheck = await agent(
        prompt=f"""
Run ONLY the targeted baseline tests before any edits:
- Pest tests: {selection['target_tests']} using `php artisan test --compact <path>`
{"- Also run `npm test` (Vitest) since frontend files may be touched." if selection['touches_frontend'] else ""}
Do NOT run the full test suite or browser tests.
Report whether the baseline passes and record exact pass/fail counts.
""",
        schema={
            "type": "object",
            "properties": {
                "baseline_passed": {"type": "boolean"},
                "summary": {"type": "string"},
            },
            "required": ["baseline_passed", "summary"],
        },
        role="Baseline Tester",
        model="flash",
    )
    log(f"Baseline: passed={precheck['baseline_passed']} | {precheck['summary']}")

    phase("3. Implementation")
    impl_result = await agent(
        prompt=f"""
Implement the selected improvement cleanly on branch `{selection['proposed_branch']}`:
- Title: {selection['candidate_title']}
- Area: {selection['area']}
- Rationale: {selection['reason']}
- Target tests: {selection['target_tests']}

Follow GEMINI.md, AGENTS.md, and relevant `.agents/rules/` and `.agents/skills/`:
1. Create and switch to branch `{selection['proposed_branch']}` (never commit to `main`).
2. Match sibling conventions: Laravel 13, Livewire 4 (`⚡*.blade.php` SFCs), Flux UI Free v2 ONLY (no Pro components), Tailwind v4 semantic theme tokens, explicit PHP parameter/return types, constructor property promotion, curly braces, TitleCase enums. Do NOT add `declare(strict_types=1)` to `app/` files.
3. Add or update targeted Pest tests (and Vitest tests in `tests/js/` if editing `resources/js/`).
4. If completing an item from TODO.md, flip `- [ ]` to `- [x]` in place.
5. Never touch `.env`, secrets, or run destructive DB commands (`migrate:fresh`/`migrate:reset`).
""",
        schema={
            "type": "object",
            "properties": {
                "summary": {"type": "string"},
                "changed_files": {"type": "array", "items": {"type": "string"}},
                "dependencies_added": {"type": "string"},
            },
            "required": ["summary", "changed_files", "dependencies_added"],
        },
        role="Feature Implementer",
        model="inherit",
    )
    log(f"Implemented across {len(impl_result['changed_files'])} file(s): {impl_result['summary']}")

    phase("4. Quality Gate & Review")
    quality = await agent(
        prompt=f"""
Run the full quality gate for `{selection['candidate_title']}`:
1. Run `vendor/bin/pint --dirty --format agent`
2. Run `vendor/bin/phpstan analyse`
3. Run targeted Pest tests: {selection['target_tests']}
{"4. Run `npm test` and `npm run build`." if selection['touches_frontend'] else "4. If any CSS/JS/Blade files changed, run `npm test` and `npm run build`."}
5. Verify `git status -s` contains no `.env`, secret files, or personal machine paths.
If Pint or minor test/PHPStan issues appear, fix them and re-run until green.
""",
        schema={
            "type": "object",
            "properties": {
                "all_checks_passed": {"type": "boolean"},
                "verification_report": {"type": "string"},
            },
            "required": ["all_checks_passed", "verification_report"],
        },
        role="Quality Gatekeeper",
        model="flash",
    )
    log(f"Quality gate: passed={quality['all_checks_passed']} | {quality['verification_report']}")

    phase("5. Packaging & PR")
    if dry_run or not quality["all_checks_passed"]:
        reason = "dry_run=true" if dry_run else "quality gate failed"
        log(f"Skipping push/PR ({reason}). Changes remain on branch {selection['proposed_branch']}.")
        return

    pr_result = await agent(
        prompt=f"""
Commit, push, and open a PR for branch `{selection['proposed_branch']}` against `main` in `mavware/zorbl`:
- Title: {selection['candidate_title']}
- Implementation summary: {impl_result['summary']}
- Baseline tests: {precheck['summary']}
- After verification: {quality['verification_report']}
- Dependencies added: {impl_result['dependencies_added']}

Steps:
1. Ensure you are on `{selection['proposed_branch']}` (never push to `main`).
2. Commit staged changes with a clear message explaining what changed and why.
3. Push: `git push -u origin {selection['proposed_branch']}`.
4. Create PR with `gh pr create --repo mavware/zorbl --base main --title "..." --body "..."` including Branch, Description, Verification (before/after), Dependencies, and Follow-up Opportunities.
Return the PR URL (or commit SHA if `gh` is unavailable).
""",
        schema={"type": "string"},
        role="PR Packager",
        model="flash",
    )
    log(f"PR Packaging result: {pr_result}")


await main()
