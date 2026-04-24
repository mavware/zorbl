# TODO

Checklist for automated and manual follow-up work. The automation script
scans this file for `- [ ]` items and flips them to `- [x]` when done.

## Open

- [x] Tagging system for puzzles
- [x] The template modal still jumps around when the user changes the width or height of the puzzle
- [x] Type of puzzle selection when creating a new puzzle (standard, diamond, freestyle)
- [ ] Create a step-by-step marketing plan for the site
- [x] Ability to search for a puzzle by tag
- [x] Ability to select tags that you never want to see puzzles associated with
- [ ] Ability to sign in with a passkey
- [ ] Ability to sign in with google
- [ ] Double check the printable/downloadable PDFs for the puzzle editor/solver
- [x] Ensure that freestyle puzzles can be exported as PDFs with the removed squares
- [ ] Execute puzzle entgagement ranker plan at `.claude/plans/puzzle-engagement-ranker.md` (deferred — gated on ≥2k completed attempts across ≥300 published puzzles; see gate conditions in file)
- [ ] Add Livewire component tests for settings, favorites, roadmap, and support pages
- [x] Audit Livewire page components for authorization — ensure they use policies instead of inline ownership checks
- [ ] Add feature tests for admin-assigned-ticket access via SupportTicketPolicy
- [ ] Let users set up webhooks for their puzzles for common events like puzzle completion or puzzle attempt submission, etc...
- [ ] Add Filament test coverage for ContestResource CRUD (create, edit, delete) — currently only basic tests exist in ContestAdminTest
- [ ] Add plagiarism detection that gives a percentage of similarity to the other puzzles.  For copyright protection, this should be done with a third-party service like PlagiarismCheck.com or a custom implementation using machine learning algorithms.
- [ ] Render bar styles (thick cell dividers) in PDF exports — bars affect numbering but are not visually rendered in the PDF output
- [x] Wire PuzzleAttemptPolicy `update` check into the solver page's `saveProgress` method for defense-in-depth
- [x] If the user is not a paid user, the AI fill and AI clue generation should prompt them to upgrade to a paid account
- [x] Add tag filtering to the crosswords API endpoint (query param `?tag=slug`)
- [x] Seed a default set of common crossword tags (e.g. Pop Culture, Sports, Science, History, Movies, Music, Geography, Food, Literature, Current Events)
- [x] ability to change color of cell background

## Done

<!-- completed items move here, preserving their `- [x]` state -->

