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
- [x] Ability to sign in with a passkey
- [x] Ability to sign in with google
- [x] Double check the printable/downloadable PDFs for the puzzle editor/solver
- [x] Ensure that freestyle puzzles can be exported as PDFs with the removed squares
- [ ] Execute puzzle engagement ranker plan at `.claude/plans/puzzle-engagement-ranker.md` (deferred — gated on ≥2k completed attempts across ≥300 published puzzles; see gate conditions in file)
- [x] Add Livewire component tests for settings, favorites, roadmap, and support pages
- [x] Audit Livewire page components for authorization — ensure they use policies instead of inline ownership checks
- [x] Add feature tests for admin-assigned-ticket access via SupportTicketPolicy
- [x] Let users set up webhooks for their puzzles for common events like puzzle completion or puzzle attempt submission, etc...
- [x] Add Filament test coverage for ContestResource CRUD (create, edit, delete) — currently only basic tests exist in ContestAdminTest
- [ ] Add plagiarism detection that gives a percentage of similarity to the other puzzles.  For copyright protection, this should be done with a third-party service like PlagiarismCheck.com or a custom implementation using machine learning algorithms.
- [x] Render bar styles (thick cell dividers) in PDF exports — bars affect numbering but are not visually rendered in the PDF output
- [x] Wire PuzzleAttemptPolicy `update` check into the solver page's `saveProgress` method for defense-in-depth
- [x] If the user is not a paid user, the AI fill and AI clue generation should prompt them to upgrade to a paid account
- [x] Add tag filtering to the crosswords API endpoint (query param `?tag=slug`)
- [x] Seed a default set of common crossword tags (e.g. Pop Culture, Sports, Science, History, Movies, Music, Geography, Food, Literature, Current Events)
- [x] ability to change color of cell background
- [x] Render bar-style word boundaries in PDF exports (bars are used for numbering but not visually drawn in the PDF)
- [x] Ask Claude to find an algorithm to generate a template based on existing template data
- [x] Add completed_attempts_count and avg_solve_time to the crosswords API resource meta for parity with the UI
- [x] Consider caching per-puzzle solve stats for high-traffic discovery pages
- [x] Add solve time vs. average indicator to puzzle cards on the /solving page for completed puzzles
- [x] Add difficulty breakdown (Easy/Medium/Hard/Expert) stats to the solving stats page alongside Times by Grid Size
- [x] Add a notification preferences page so users can opt out of specific notification types (e.g. new puzzle published, likes, comments)
- [x] Add email channel option for puzzle-published notifications (alongside the existing database channel)
- [x] Add a rating trend chart or sparkline to the constructor analytics page showing how ratings change over time
- [x] Add pagination to the solve history table on the stats page to handle users with many completed puzzles
- [x] Add an Artisan command to bulk-schedule daily puzzles for a date range
- [x] Show a "solved" badge on the daily puzzle card if the user has already completed it
- [x] Add a daily puzzle history page so users can catch up on missed days
- [x] Add a minimum-rating filter to the puzzle discovery secondary filter panel (e.g. "4+ stars only")
- [x] Show completion rate percentage on puzzle discovery cards (requires withCount for completed attempts)
- [x] Space bar should add black square, but also navigate to the next available square
- [ ] Ability to create a puzzle when not logged in (for anonymous users) (possibly creating a user via ip?)
- [ ] Create a command that periodically updates legal functions (privacy policy, terms of service, cookie policy, where the cookie banner shows, etc...).  Based on real laws.
- [x] Ability to arrange the printable/downloadable versions of the puzzle in a different orientation.
- [ ] Ability to add an image to the printable/downloadable arrangements of the puzzle.
- [ ] Ability to add narrative text to the printable/downloadable arrangements of the puzzle.
- [ ] Ability to convert selected contiguous cells to a custom image (or a single cell if only one is selected)
- [ ] Ability to arrange several puzzles in a single PDF file.
- [ ] Ability to add a custom image or text page to the PDF file.
- [ ] Ability to add a section that prompts the solver to enter one or more custom answer.  Constructor will choose if the solver gets feedback if they enter the correct answers.
- [ ] Constructors get a section that shows what answers the solvers entered into the answer field (just the distinct answers with the count)
- [ ] Ability to work with multiple constructors at once (form teams)
- [ ] Publicly accessible file format converter.  Lets anonymous users upload a puzzle in any format and convert it to any other format.
- [ ] Optional puzzle titles.  Not sure what will be the stand-in if they choose not to put a title.
- [ ] In the solver, keep a local record of actions taken, so the user can undo or redo them.  add a keyboard shortcut for command+z to undo and command+shift+z to redo.
 
## For later

  1. Production .env audit — APP_DEBUG=false, real Stripe keys (not test), MAIL_* configured, LEGAL_ENTITY / LEGAL_CONTACT_EMAIL / LEGAL_DMCA_EMAIL filled in, ANTHROPIC_API_KEY
   if AI is on, GOOGLE_CLIENT_* if OAuth is on.
  2. Real support inboxes — legal@, dmca@, support@ need to actually deliver to a human you check. The Privacy/Terms/DMCA pages already reference them.
  3. Stripe live mode — webhook endpoint registered in the Stripe dashboard, signing secret in env, customer portal configured. Test the full subscribe → cancel → resubscribe
  loop once before launch.
  4. Queue worker + scheduler running in prod — Laravel Pulse, daily puzzle scheduling, async notifications all depend on these. (Laravel Cloud handles both; bare-metal hosting
   needs supervisor.)


## Done

<!-- completed items move here, preserving their `- [x]` state -->

