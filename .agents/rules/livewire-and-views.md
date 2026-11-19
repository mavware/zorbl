---
trigger: glob
globs: "resources/views/**/*.blade.php, resources/css/**/*.css, resources/js/**/*.js"
description: "Livewire 4 single-file components, Flux UI Free v2, theme tokens, Alpine.js, and frontend build/test rules."
---

# Livewire 4 & Frontend Rules

- **Livewire 4 Single-File Components (`⚡*.blade.php`)**: Match sibling component structure in `resources/views/pages/` and `resources/views/components/`. Keep state server-side and validate/authorize in actions. Consult `.agents/skills/livewire-development/SKILL.md`.
- **Flux UI Free v2 ONLY**: Only `livewire/flux` (^2.12, Free) is installed — never use Flux Pro–only components (e.g., `flux:date-picker`, `flux:editor`, `flux:chart`, `flux:calendar`, `flux:command`, `flux:kanban`). Check sibling Blade files and `.agents/skills/fluxui-development/SKILL.md`.
- **Theme System (`modern` & `classical`)**:
  - Use the existing semantic token classes (`text-ink`, `text-ink-muted`, `border-hairline`, `font-classical`, `btn-classical*`, `btn-header*`, `meta-classical`, `chip-classical`, `field-classical`) rather than hardcoding raw zinc/amber utilities that break theme switching.
  - Do not modify `resources/css/embed.css` or `resources/views/embed/solver.blade.php` unless explicitly working on the embeddable solver.
- **Alpine.js & JavaScript**:
  - Reserve Alpine.js for client-only UI interactions (modals, dropdowns, keyboard interactions, animations).
  - Run `npm test` (Vitest) when modifying `resources/js/**` (tests live in `tests/js/**/*.test.js`).
  - Run `npm run build` after CSS/JS/Blade changes to keep the Vite manifest up to date.
