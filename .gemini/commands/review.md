# Gemini / Antigravity Review & Quality Gate Command

Execute pre-commit / pre-PR review, Pint formatting, and safety checks.

## Steps

1. **Check Dirty Changes**:
   ```bash
   git status -s
   git diff --stat
   ```

2. **Automated Pint Code Formatting**:
   - Always run Pint formatting before review completion:
     ```bash
     vendor/bin/pint --dirty --format agent
     ```

3. **Check for Dangerous Patterns**:
   - Verify no accidental `.env` or credential edits.
   - Verify no raw SQL injections or unprotected mass assignments.
   - Verify strict types and constructor property promotion for new PHP classes.

4. **Verify Targeted Tests**:
   - Run affected tests:
     ```bash
     php artisan test --compact <affected-tests>
     ```

5. **Generate Summary**:
   - Present changed files, test verification status, and quality observations.
