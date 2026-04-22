# TODO

Checklist for automated and manual follow-up work. The automation script
scans this file for `- [ ]` items and flips them to `- [x]` when done.

## Open

- [x] Tagging system for puzzles
- [ ] The template modal still jumps around when the user changes the width or height of the puzzle
- [ ] Type of puzzle selection when creating a new puzzle (standard, diamond, freestyle)
- [ ] Create a step-by-step marketing plan for the site
- [x] Ability to search for a puzzle by tag
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
- [ ] Add tag filtering to the crosswords API endpoint (query param `?tag=slug`)
- [ ] Seed a default set of common crossword tags (e.g. Pop Culture, Sports, Science, History, Movies, Music, Geography, Food, Literature, Current Events)
- [ ] ability to change color of cell background
- [x] Add tag filtering to the crosswords API endpoint (query param `?tag=slug`)
- [x] Seed a default set of common crossword tags (e.g. Pop Culture, Sports, Science, History, Movies, Music, Geography, Food, Literature, Current Events)

## Done

<!-- completed items move here, preserving their `- [x]` state -->

