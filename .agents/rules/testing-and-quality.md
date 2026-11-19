---
trigger: glob
globs: "app/**/*.php, tests/**/*.php, database/**/*.php"
description: "PHP conventions, Pest 4 testing, Larastan level 2 analysis, and Pint formatting rules."
---

# PHP, Pest 4 & Quality Rules

- **Code Style**:
  - Use explicit parameter and return types, PHP 8 constructor property promotion, curly braces on all control structures, and TitleCase enum keys.
  - Do **not** add `declare(strict_types=1)` to `app/` files; existing application classes omit it.
  - Always run `vendor/bin/pint --dirty --format agent` after modifying PHP files. Never run `--test` instead of fixing.
- **Static Analysis**:
  - Run `vendor/bin/phpstan analyse` (Larastan v3, level 2 via `phpstan.neon.dist`) when changing models, services, enums, or method signatures.
- **Pest 4 Testing**:
  - Consult `.agents/skills/pest-testing/SKILL.md` and `.agents/skills/testing-best-practices/SKILL.md` before writing non-trivial tests.
  - Create tests via `php artisan make:test --pest NameTest --no-interaction` (add `--unit` for unit tests; never prefix `Feature/` or `Unit/`).
  - Use existing model factories and factory states instead of manual `create()` arrays.
  - Run targeted tests first (`php artisan test --compact tests/Feature/TargetTest.php`). Do not run the full suite upfront (it takes 8+ minutes); use `vendor/bin/pest --parallel --compact --testsuite=Unit,Feature` for broader verification.
  - Never delete existing tests without explicit user approval.
- **Database Safety**:
  - Inspect schema with Boost `database-schema` before writing migrations or models.
  - Never run `migrate:fresh` or `migrate:reset`.
