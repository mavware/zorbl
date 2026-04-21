# TODO

Checklist for automated and manual follow-up work. The automation script
scans this file for `- [ ]` items and flips them to `- [x]` when done.

## Open

- [ ] Tagging system for puzzles
- [ ] Ability to search for a puzzle by tag
- [ ] Ability to select tags that you never want to see puzzles associated with
- [ ] Ability to sign in with a passkey
- [ ] Ability to sign in with google
- [ ] Brainstorm ways to generate engaging puzzles with AI
- [ ] Add Livewire component tests for settings, favorites, roadmap, and support pages
- [ ] Audit Livewire page components for authorization — ensure they use policies instead of inline ownership checks
- [ ] Add feature tests for admin-assigned-ticket access via SupportTicketPolicy
- [ ] Let users set up webhooks for their puzzles for common events like puzzle completion or puzzle attempt submission, etc...
- [ ] Add Filament test coverage for ContestResource CRUD (create, edit, delete) — currently only basic tests exist in ContestAdminTest
- [ ] Fix pre-existing CrosswordImportTest failure (redirect assertion in `users can import a crossword`)
- [ ] Wire PuzzleAttemptPolicy `update` check into the solver page's `saveProgress` method for defense-in-depth
- [ ] If the user is not a paid user, the AI fill and AI clue generation should prompt them to upgrade to a paid account
- [ ] Add a "clear publish date" bulk action to complement the schedule publish bulk action
- [ ] Fix browser tests (PuzzleSolverTest) — all 3 tests fail with "Call to a member function sendText() on null"

## Done

<!-- completed items move here, preserving their `- [x]` state -->

