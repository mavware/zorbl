# TALL Stack Feature Development Prompt (Gemini / Antigravity)

Use this guide when building a new feature or major capability in Crossword Builder.

## 1. Context & Architecture Requirements

- **PHP Version**: 8.5 with strict types and constructor property promotion.
- **Frontend Stack**: Tailwind CSS v4, Alpine.js, Livewire 3 with Flux UI Pro components (`<flux:...>`).
- **Database & Eloquent**:
  - Always inspect existing schemas via `database-schema` before creating migrations.
  - Create useful factories and seeders alongside models (`php artisan make:model <Name> -mf`).
  - Follow API conventions with Eloquent API Resources when building endpoints.

## 2. Antigravity Execution Plan

1. **Clarification**: If requirements are underspecified or architectural choices exist, use `ask_question` to confirm intent with the user.
2. **Branching**:
   - `git checkout -b gemini/<feature-name>`
3. **Domain Skills Activation**:
   - For tests, consult `.agents/skills/pest-testing/SKILL.md`.
   - For Livewire, consult `.agents/skills/livewire-development/SKILL.md`.
   - For Flux UI, consult `.agents/skills/fluxui-development/SKILL.md`.
   - For Database / Models, consult `.agents/skills/laravel-best-practices/SKILL.md`.
4. **Code Construction**:
   - Create models/migrations via `php artisan make:model ...`.
   - Implement Livewire components ensuring state stays server-side.
5. **Quality Gate**:
   - Run Pint: `vendor/bin/pint --dirty --format agent`.
   - Run Pest tests: `php artisan test --compact tests/Feature/<FeatureTest>.php`.
   - Verify assets: `npm run build`.
6. **Artifact Generation**:
   - Create a markdown artifact summarizing the architecture, routes added, and test coverage.
