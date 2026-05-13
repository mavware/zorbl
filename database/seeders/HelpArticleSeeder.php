<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

class HelpArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            // Getting started
            [
                'category' => 'getting-started',
                'slug' => 'what-is-zorbl',
                'title' => 'What is Zorbl?',
                'summary' => 'A community-driven crossword platform where you can build, publish, and solve original puzzles.',
                'body' => <<<'MD'
                Zorbl is a free platform for people who love crosswords. You can:

                - Build puzzles with a visual grid editor that handles symmetry and numbering automatically.
                - Publish them for solvers around the world.
                - Solve puzzles from independent constructors you won't find on big-paper sites.
                - Run contests with leaderboards.

                Free forever for building, publishing, and solving. An optional Pro plan adds AI-assisted features for constructors who want a head start.
                MD,
                'sort_order' => 10,
            ],
            [
                'category' => 'getting-started',
                'slug' => 'do-i-need-experience',
                'title' => 'Do I need crossword construction experience?',
                'summary' => 'No. The editor takes care of symmetry, numbering, and the bookkeeping so you can focus on the puzzle.',
                'body' => <<<'MD'
                No experience required. The visual editor handles rotational symmetry, cell numbering, and the boring bookkeeping automatically. If you can spell, you can build a mini puzzle in an afternoon.

                A few tips for your first build:

                1. Start with a smaller grid (a 5×5 or 7×7 mini) before tackling a 15×15.
                2. Place your block pattern first, then fill in words.
                3. Use the **clue library** to see how other constructors clued the same answer.
                MD,
                'sort_order' => 20,
            ],

            // Constructing
            [
                'category' => 'constructing',
                'slug' => 'import-existing-puzzles',
                'title' => 'Can I import puzzles I built elsewhere?',
                'summary' => 'Yes — Zorbl reads .ipuz, .puz (Across Lite), and .jpz files.',
                'body' => <<<'MD'
                You can import existing puzzles in any of the standard formats:

                - `.ipuz` — the modern open standard, supported by most editors.
                - `.puz` — Across Lite's binary format.
                - `.jpz` — Crossword Compiler's format.

                Drop the file onto the upload area in the editor. The grid, clues, and metadata come across automatically.

                You can also **export** any puzzle you build to all three formats, plus a printable PDF.
                MD,
                'sort_order' => 10,
            ],
            [
                'category' => 'constructing',
                'slug' => 'grid-sizes-and-shapes',
                'title' => 'What grid sizes and shapes are supported?',
                'summary' => 'Anything from a 4×4 mini through a Sunday-sized 21×21, plus diamonds, asymmetric layouts, and void cells.',
                'body' => <<<'MD'
                Zorbl supports:

                - **Standard rectangular grids** from 4×4 up through 21×21.
                - **Diamond grids** and other non-rectangular shapes via void cells.
                - **Barred puzzles** where bars between cells act as word boundaries (in place of black squares).
                - **Asymmetric layouts** if rotational symmetry isn't your goal.

                Void cells are different from black blocks: they aren't part of the puzzle at all (no number, no count toward word length).
                MD,
                'sort_order' => 20,
            ],
            [
                'category' => 'constructing',
                'slug' => 'when-to-publish',
                'title' => 'When should I publish my puzzle?',
                'summary' => 'After every cell is filled and every clue is written — Zorbl runs a quick completeness check before publish.',
                'body' => <<<'MD'
                Before publishing, the editor runs a completeness check that surfaces missing clues, unfilled cells, and invalid words. You're free to publish a draft once it passes.

                A few practical tips:

                - Test-solve your own puzzle from scratch before publishing.
                - Pick a difficulty rating that matches what an unaided solver experiences.
                - Add a couple of tags so it shows up in topic browses.
                - Don't worry about getting it perfect — you can edit a published puzzle and re-publish if you spot a typo.
                MD,
                'sort_order' => 30,
            ],

            // Solving
            [
                'category' => 'solving',
                'slug' => 'auto-save-progress',
                'title' => 'Does my solve progress auto-save?',
                'summary' => 'Yes. Your in-progress solve syncs across devices and tabs in real time.',
                'body' => <<<'MD'
                Yes. As soon as you start typing in a puzzle, your progress is saved to your account. You can:

                - Close the tab and come back later — your solve picks up where you left off.
                - Switch devices mid-solve.
                - Use **pencil mode** for tentative letters that don't count toward completion.

                If you're solving without an account, your progress is stored in your browser's local storage for that puzzle.
                MD,
                'sort_order' => 10,
            ],
            [
                'category' => 'solving',
                'slug' => 'check-and-reveal',
                'title' => 'How do "Check" and "Reveal" work?',
                'summary' => 'Check shows which letters are wrong; Reveal fills them in. Both flag the solve as assisted on your leaderboard.',
                'body' => <<<'MD'
                The solver toolbar has two assistance modes:

                - **Check** — marks any wrong letters in the current cell, word, or grid. You stay in control.
                - **Reveal** — fills in the correct letter(s) for you.

                Using either will flag the solve as "assisted" when it appears on the leaderboard, so unassisted solves can still be compared cleanly. Times for assisted solves are still tracked, just visually distinguished.
                MD,
                'sort_order' => 20,
            ],

            // Account & billing
            [
                'category' => 'account-billing',
                'slug' => 'free-vs-pro',
                'title' => 'What does the Pro plan add?',
                'summary' => 'AI-assisted grid filling and clue generation, plus advanced constructor analytics.',
                'body' => <<<'MD'
                Building, publishing, and solving puzzles is free forever. The optional Pro plan adds tools for constructors who want a faster workflow:

                - **AI autofill** — backtracking solver plus Claude-powered thematic fills.
                - **AI clue generation** — single-click clue suggestions.
                - **Constructor analytics** — solve-time distributions, completion rates, ratings over time.

                You can cancel at any time from billing settings. The plan stays active through the end of the period you've paid for.
                MD,
                'sort_order' => 10,
            ],
            [
                'category' => 'account-billing',
                'slug' => 'delete-or-export-data',
                'title' => 'How do I delete my account or export my data?',
                'summary' => 'Both are self-serve from your profile settings.',
                'body' => <<<'MD'
                Both options live on your profile settings page:

                - **Download your data** — a JSON file containing your profile, puzzles, attempts, clues, comments, favorites, and other account data.
                - **Delete account** — permanently removes your account, cancels any active subscription, revokes API tokens, and deletes your personal data.

                Once you delete your account it cannot be recovered. Routine backups age out within 90 days.
                MD,
                'sort_order' => 20,
            ],

            // Contests
            [
                'category' => 'contests',
                'slug' => 'how-contests-work',
                'title' => 'How do contests work?',
                'summary' => 'Contests bundle one or more puzzles into a timed window with a live leaderboard.',
                'body' => <<<'MD'
                A contest is a bundle of one or more puzzles, a start and end time, and a live leaderboard. Solvers race to complete every puzzle in the window. Constructors can:

                - Create a meta-answer puzzle whose solution is hidden inside the other puzzles.
                - Set the contest to private (invite-only) or public.
                - Watch the leaderboard update in real time as solvers finish.

                Solvers see contest puzzles only after the start time, and assisted solves are flagged separately from clean solves.
                MD,
                'sort_order' => 10,
            ],
        ];

        foreach ($articles as $attributes) {
            HelpArticle::updateOrCreate(
                ['slug' => $attributes['slug']],
                array_merge($attributes, [
                    'is_published' => true,
                    'published_at' => now()->subDay(),
                ]),
            );
        }
    }
}
