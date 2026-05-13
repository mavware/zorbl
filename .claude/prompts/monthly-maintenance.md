# Monthly maintenance pass

You are running an automated monthly maintenance pass on the Zorbl repo from a **remote agent in a sandboxed cloud environment**. You have a fresh git clone. What you don't know yet is whether you have permission to push branches and open PRs — that depends on how this environment was provisioned. Step 0 below figures that out, and the rest of the playbook branches accordingly.

## Step 0 — Capability probe

Before anything else, run this exact block and remember the results:

```bash
echo "=== gh ==="
gh --version 2>&1 | head -1 || echo "no gh"
gh auth status 2>&1 | head -3 || echo "gh: not authenticated"

echo "=== git identity ==="
git config user.email || echo "no user.email"
git config user.name || echo "no user.name"

echo "=== git remotes ==="
git remote -v

echo "=== push probe ==="
git push --dry-run origin HEAD:refs/heads/_probe_can_push_$(date +%s) 2>&1 | head -5 || echo "push: blocked"
```

Decide your mode from the probe:

- **PR mode** — `gh auth status` shows you're logged in AND the `git push --dry-run` line does NOT contain "denied", "fatal", "permission", "rejected", or "blocked". You will commit, push, and open a PR.
- **Report mode** — anything else. You will NOT touch git or `gh` after this point. Your final assistant message is the deliverable.

If `user.email` or `user.name` aren't set and you're in PR mode, set them before any commits:

```bash
git config user.email "noreply@anthropic.com"
git config user.name "Claude (monthly maintenance)"
```

## Ground rules (both modes)

- Use the sandbox to *try* patch + minor version bumps so you can include test results in the output ("tried bumping `laravel/framework` 13.0.4 → 13.0.5; `php artisan test --compact` passed").
- Never try major version bumps — flag them only.
- If `composer outdated` / `npm outdated` / `composer audit` aren't runnable (network down, etc.), say so explicitly rather than guessing.
- If you can read the open web, use it for Anthropic / Stripe docs checks. If you can't, fall back to local lockfile data and note the gap.
- Cap your run at ~15 minutes of work. If you're still going, summarize what you found and stop — the user can re-run you for the rest.

## What to check, every run

For each item: note current state, what you tried, and what action the human (or you, in PR mode) should take.

### 1. Anthropic model — `config/services.php`

- Current default lives in `services.php` under `anthropic.model`.
- Check Anthropic's docs (https://docs.anthropic.com/en/docs/about-claude/models) for the latest Sonnet / Opus / Haiku IDs and deprecation status.
- If the configured model is deprecated or end-of-life within 90 days, recommend a replacement (default to Sonnet — best price/quality for clue + autofill). Never auto-swap; this goes in the "needs human decision" bucket in both modes.

### 2. Stripe SDK + API version

- Run `composer outdated stripe/stripe-php laravel/cashier --direct`.
- Check Stripe's API changelog (https://docs.stripe.com/upgrades) for breaking changes affecting Checkout, subscriptions, webhooks, or customer portal (`grep -rn "Cashier\|subscribed(\|StripeWebhook" app/`).
- Confirm `StripeWebhookController` handles current event types we depend on; flag missing ones.

### 3. Laravel ecosystem packages

`composer outdated --direct` and bucket:

- **Patch bumps**: try them, run `php artisan test --compact`, include the result.
- **Minor bumps**: try if time permits; include the recommendation + test result.
- **Major bumps**: never try. List with the upgrade-guide URL.

Watch: `laravel/framework`, `laravel/cashier`, `laravel/fortify`, `laravel/sanctum`, `laravel/socialite`, `laravel/pulse`, `filament/filament`, `livewire/livewire`, `livewire/flux`, `spatie/laravel-permission`, `spatie/laravel-query-builder`.

### 4. Frontend dependencies

`npm outdated`. Same rules. Specifically: Tailwind, Alpine, Vite, `@tailwindcss/*`.

### 5. Security advisories

`composer audit --format=plain` and `npm audit --omit=dev`. HIGH/CRITICAL go at the top of the output; MODERATE further down; LOW only if it's a direct dep.

### 6. PHP runtime

If a new PHP minor is GA and stable for >90 days, recommend a constraint bump (informational only — affects deploy envs).

### 7. Legal documents + vendor list

- Open `resources/views/pages/legal/⚡privacy.blade.php` (section "Who We Share It With") and `config/legal.php` (`effective_date`).
- Cross-check listed vendors against integrations: `grep -rn "env('STRIPE_\|env('GOOGLE_\|env('ANTHROPIC_" app/ config/`.
- Same check for the Cookie Policy table — make sure listed cookies match what `config/session.php`, Cashier, and the consent flow actually set.
- Material changes go in "needs human decision" with the exact text edit proposed.

### 8. DMCA agent registration

- `pages/legal/⚡dmca.blade.php` says "we will publish our DMCA agent registration number on this page once registration is complete." Nudge if still unset.

### 9. Profanity word list

- Check `config/profanity.php` structurally. (DB access is unlikely from the sandbox, so skip the "recent reports" check unless it's obvious from migrations.)

### 10. Help articles

- `grep -rn "HelpArticle" database/seeders/` to find the seeded set.
- Walk the seed file for articles that contradict obvious code changes (e.g., new pricing in `config/cashier.php`). Flag by slug; don't rewrite.

### 11. Sitemap + OG image health

- Hard to smoke-test from the sandbox without `artisan serve` + curl. Usually skip; note the gap.

## Output

### If you're in PR mode

1. Create branch: `git checkout -b claude/maintenance-$(date +%Y-%m)`
2. Apply only patch + minor bumps that passed `php artisan test --compact`. Commit them, one commit per package or one tight bundle — whatever's cleaner.
3. Push: `git push -u origin HEAD`
4. Open the PR via `gh pr create --draft` with this title and body:

   **Title**: `Monthly maintenance pass — YYYY-MM`

   **Body**:
   ```
   ## Auto-applied
   - (patches you bumped, with old → new versions)

   ## Needs human decision
   - (majors, model swaps, legal text changes)

   ## Security
   - (HIGH/CRITICAL advisories first, then MODERATE)

   ## Stale flags
   - (legal text needing refresh, help articles needing rewrite, DMCA reminder)

   ## Tests
   - php artisan test --compact: X passed, Y failed
   ```

   Keep the body under ~400 words. Link `path:line` references for files.

5. Your final assistant message: a one-line summary plus the PR URL. That's it.

6. **If absolutely nothing drifted**, don't open an empty PR. Just say "Nothing to apply this month." in your final assistant message.

### If you're in Report mode

Your final assistant message IS the deliverable. The user reads it later in claude.ai/code/routines. Structure:

```
# Monthly maintenance — YYYY-MM (report mode — no git push available)

## TL;DR
- (one-line summary)

## To apply locally
For each item, a copy-paste-ready command:

- `composer update laravel/framework --with-dependencies` — bumps 13.0.4 → 13.0.5 (tested in sandbox; suite green)
- Edit `config/services.php` line 40: change `claude-sonnet-4-6` → `claude-sonnet-4-7` (current model deprecates 2026-08-01)

## Needs human decision
- (majors, legal text changes, model swaps)

## Security
- (HIGH/CRITICAL first, then MODERATE)

## Stale / reminders
- (legal text, help articles, DMCA nudge)

## Sandbox test results
- (which `composer update` / `npm update` you tried and what tests said)

## What I skipped and why
- (e.g., "couldn't reach docs.anthropic.com — model deprecation check incomplete")
```

Keep under ~600 words. Quote `path:line` so the human can jump in fast.

## Stop conditions

- If composer/npm aren't usable, give a one-paragraph status and stop in whichever mode you're in.
- If you find more than 20 distinct items, stop at 20 and say so — the user can re-run for the rest.
