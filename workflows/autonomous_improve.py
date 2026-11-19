"""
Autonomous Improvement Workflow for Gemini / Antigravity
Orchestrates an autonomous cycle:
1. Survey project status and backlog (TODO.md, git log, PRs)
2. Select one high-priority candidate item
3. Pre-run targeted Pest tests
4. Implement change on a dedicated gemini/* branch
5. Run Pint formatting and verify tests pass
6. Generate PR summary
"""

import json

async def main():
    phase("1. Survey & Discovery")
    log("Scanning project backlog and recent repository activity...")

    survey_agent = agent(
        prompt="""
You are an autonomous engineering surveyor for Crossword Builder.
1. Read TODO.md and find uncompleted checklist items in the 'Open' section.
2. Read recent git log (last 10 commits).
3. Select ONE high-value, self-contained improvement that can be safely completed in one turn.
4. Apply the rotation guardrail: if recent PRs were test-only, pick a feature, refactor, or UX polish.
Return a JSON object with:
- candidate_title: string
- area: string (e.g., 'clues', 'livewire', 'models', 'styling')
- reason: string
- target_tests: array of strings (test file paths to verify)
- proposed_branch: string (must start with gemini/)
""",
        schema={
            "type": "object",
            "properties": {
                "candidate_title": {"type": "string"},
                "area": {"type": "string"},
                "reason": {"type": "string"},
                "target_tests": {"type": "array", "items": {"type": "string"}},
                "proposed_branch": {"type": "string"}
            },
            "required": ["candidate_title", "area", "reason", "target_tests", "proposed_branch"]
        },
        role="Backlog Surveyor"
    )

    selection = await survey_agent
    log(f"Selected: {selection['candidate_title']}")
    log(f"Branch: {selection['proposed_branch']}")
    log(f"Target Tests: {selection['target_tests']}")

    phase("2. Baseline Pre-check")
    precheck_agent = agent(
        prompt=f"""
Run the targeted tests for {selection['candidate_title']} before making any changes.
Test files to inspect/run: {selection['target_tests']}.
Return whether baseline passes and any pre-existing failures.
""",
        schema={
            "type": "object",
            "properties": {
                "baseline_passed": {"type": "boolean"},
                "notes": {"type": "string"}
            },
            "required": ["baseline_passed", "notes"]
        },
        role="Baseline Tester"
    )

    precheck = await precheck_agent
    log(f"Baseline result: passed={precheck['baseline_passed']}; notes={precheck['notes']}")

    phase("3. Implementation")
    implementer = agent(
        prompt=f"""
Create branch {selection['proposed_branch']} and implement: {selection['candidate_title']}.
Reason: {selection['reason']}.
Conventions:
- Consult GEMINI.md and relevant skills in .agents/skills/
- Follow PHP 8.5 strict types and constructor property promotion
- Keep Livewire state server-side
- Update TODO.md in place (- [ ] -> - [x])
""",
        schema={"type": "string"},
        role="Feature Implementer"
    )

    impl_result = await implementer
    log(f"Implementation completed: {impl_result}")

    phase("4. Quality Gate & Pint Formatting")
    quality_agent = agent(
        prompt=f"""
Run Pint and re-run tests for {selection['candidate_title']}:
1. Run `vendor/bin/pint --dirty --format agent`
2. Run targeted Pest tests: {selection['target_tests']}
3. If frontend changed, run `npm run build`
Return verification result and test summary.
""",
        schema={
            "type": "object",
            "properties": {
                "all_tests_passed": {"type": "boolean"},
                "summary": {"type": "string"}
            },
            "required": ["all_tests_passed", "summary"]
        },
        role="Quality Gatekeeper"
    )

    quality = await quality_agent
    log(f"Quality gate result: {quality['summary']}")

    phase("5. Summary & PR Packaging")
    log("Ready to push branch and open PR with end-of-run summary.")

await main()
