# TODO

Checklist for automated and manual follow-up work. The automation script
scans this file for `- [ ]` items and flips them to `- [x]` when done.

## Open

- [x] Consider scheduled publishing support on Contest (add `publish_at` column, include in `published()` scope, scheduler command to flip draft→upcoming)
- [x] Consider adding scheduled status transitions for active→ended (the `contests:process-ended` command exists but could benefit from more test coverage)
- [x] Add Filament admin action to bulk-schedule publish dates on draft contests
- [ ] In the editor, when you have a cell selected, then click away from the grid, then click back on the cell, the direction of the selection changes.  To make it more clear what is going on, when the user clicks away from the grid (not on a cell or in the clue area), the grid should lose focus and no cell should be selected.
- [ ] Tagging system for puzzles
- [ ] Ability to search for a puzzle by tag
- [ ] Ability to select tags that you never want to see puzzles associated with
- [ ] Ability to sign in with a passkey
- [ ] Ability to sign in with google
- [ ] Brainstorm ways to generate engaging puzzles with AI
- [ ] Add Livewire component tests for settings, favorites, roadmap, and support pages
- [x] Enable Model::preventLazyLoading() in AppServiceProvider for non-production environments
- [ ] Audit Livewire page components for authorization — ensure they use policies instead of inline ownership checks
- [x] Wire PuzzleAttemptPolicy into API controllers and Livewire components that access attempts
- [ ] Add feature tests for admin-assigned-ticket access via SupportTicketPolicy
- [ ] Let users set up webhooks for their puzzles for common events like puzzle completion or puzzle attempt submission, etc...
- [x] If the user is not a paid user, the AI fill and AI clue generation should prompt them to upgrade to a paid account
- [x] AI fill should promt user if there is no title or secret
- [ ] Add all missing crossword layouts
- [ ] Add icons to the crossword layout selector so people know what the layouts look like
- [ ] Add Filament test coverage for ContestResource CRUD (create, edit, delete) — currently only basic tests exist in ContestAdminTest
- [ ] Fix pre-existing CrosswordImportTest failure (redirect assertion in `users can import a crossword`)
- [ ] Add authorization to roadmap page — currently any authenticated user can add/edit/delete items; consider restricting mutations to admins and making the page read-only for regular users
- [ ] Wire PuzzleAttemptPolicy `update` check into the solver page's `saveProgress` method for defense-in-depth
- [ ] If the user is not a paid user, the AI fill and AI clue generation should prompt them to upgrade to a paid account
- [ ] Add a "clear publish date" bulk action to complement the schedule publish bulk action
- [ ] Add Filament tests for ContestResource CRUD operations (currently in tests/Feature/Admin but could be expanded)

## Done

<!-- completed items move here, preserving their `- [x]` state -->
- [x] Add SupportTicketPolicy (users should only view/update their own tickets, admins can see assigned)
- [x] Add PuzzleAttemptPolicy (users should only access their own attempts)
- [x] Consider scheduled publishing support on Contest (add `publish_at` column, include in `published()` scope, scheduler command to flip draft→upcoming)
- [x] Consider adding scheduled status transitions for active→ended (the `contests:process-ended` command exists but could benefit from more test coverage)
- [x] Add Filament admin action to bulk-schedule publish dates on draft contests
- [x] Add Livewire component tests for settings, favorites, roadmap, and support pages
- [x] Enable Model::preventLazyLoading() in AppServiceProvider for non-production environments
- [x] Wire PuzzleAttemptPolicy into API controllers and Livewire components that access attempts
- [x] Enable Model::preventLazyLoading() in AppServiceProvider for non-production environments
- [x] If the user is not a paid user, the AI fill and AI clue generation should prompt them to upgrade to a paid account
- [x] AI fill should promt user if there is no title or secret
