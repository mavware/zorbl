<?php

use App\Models\HelpArticle;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Slugs of the articles this migration owns, so down() removes exactly what
     * it added without touching the original seeded set.
     *
     * @var list<string>
     */
    private array $slugs = [
        'keyboard-shortcuts',
        'follow-constructors',
        'writing-better-clues',
        'print-and-pdf-export',
        'embed-on-your-site',
        'add-a-meta-puzzle',
        'difficulty-ratings',
        'daily-puzzle-streaks',
        'run-your-own-contest',
    ];

    public function up(): void
    {
        foreach ($this->articles() as $article) {
            // Idempotent, and fires the observer that invalidates the sitemap cache.
            HelpArticle::updateOrCreate(['slug' => $article['slug']], $article);
        }
    }

    public function down(): void
    {
        HelpArticle::whereIn('slug', $this->slugs)->delete();
    }

    /**
     * @return list<array{category: string, slug: string, title: string, summary: string, body: string, sort_order: int}>
     */
    private function articles(): array
    {
        return [
            // ---- Getting started ----------------------------------------
            [
                'category' => 'getting-started',
                'slug' => 'keyboard-shortcuts',
                'title' => 'Are there keyboard shortcuts for solving?',
                'summary' => 'Yes. Arrow keys move around the grid, Tab jumps between clues, and typing fills cells.',
                'body' => <<<'MD'
                The solver is built to keep your hands on the keyboard:

                - **Arrow keys** move the cursor around the grid.
                - **Type a letter** to fill the current cell and advance to the next one.
                - **Backspace** clears a cell and steps back.
                - **Tab** jumps to the next clue; the current word stays highlighted so you always know where you are.
                - Click the active clue (or the cell you're on) to **switch between Across and Down**.

                Prefer tentative letters? Turn on **pencil mode** — pencilled-in guesses show lighter and don't count toward completion until you commit to them.
                MD,
                'sort_order' => 30,
            ],
            [
                'category' => 'getting-started',
                'slug' => 'follow-constructors',
                'title' => 'How do I follow my favorite constructors?',
                'summary' => 'Open any constructor\'s public profile and hit Follow to keep track of their new puzzles.',
                'body' => <<<'MD'
                Every constructor with published puzzles has a public profile listing their work and their solving stats. You can reach a profile by clicking a constructor's name anywhere their puzzles appear, or from the **Constructors** directory.

                On a profile you can:

                - **Follow** the constructor so their new puzzles are easy to find.
                - Browse and filter everything they've published by difficulty or popularity.
                - See how their puzzles have been received across the community.

                Profiles are public, so you can share a link to your own profile to show off your puzzles — someone doesn't need an account to view it.
                MD,
                'sort_order' => 40,
            ],

            // ---- Constructing --------------------------------------------
            [
                'category' => 'constructing',
                'slug' => 'writing-better-clues',
                'title' => 'How do I write better clues?',
                'summary' => 'Match the clue\'s part of speech and tense to the answer, and lean on the clue library for inspiration.',
                'body' => <<<'MD'
                A good clue is fair but not obvious. A few habits go a long way:

                1. **Match part of speech and tense.** If the answer is plural, the clue should read as plural. If the answer is past tense, so is the clue.
                2. **Keep the difficulty consistent.** A puzzle where every clue is a curveball is exhausting; mix straightforward definitions with the occasional bit of wordplay.
                3. **Avoid using the answer's own words** (or close relatives) inside the clue.
                4. **Read it cold.** If you didn't already know the answer, would the clue get you there?

                When you're stuck, open the **clue library** — it shows how other constructors have clued the same answer, which is a fast way to find a fresh angle or confirm your instinct.
                MD,
                'sort_order' => 40,
            ],
            [
                'category' => 'constructing',
                'slug' => 'print-and-pdf-export',
                'title' => 'Can I print a puzzle or export it as a PDF?',
                'summary' => 'Yes. Export any puzzle to a print-ready PDF, and batch-export several puzzles at once.',
                'body' => <<<'MD'
                Every puzzle can be exported to a clean, print-ready **PDF** with the grid and clues laid out for solving on paper. Choose portrait or landscape to suit the grid's shape.

                Building a booklet or a run of puzzles? From your Build dashboard you can **select several puzzles and export them together** in one batch — handy for events, classrooms, or a printed collection.

                If you'd rather keep working in another tool, you can also export to the standard `.ipuz`, `.puz`, and `.jpz` formats. Your puzzles are always yours to take with you.
                MD,
                'sort_order' => 50,
            ],
            [
                'category' => 'constructing',
                'slug' => 'embed-on-your-site',
                'title' => 'Can I embed a puzzle on my own website?',
                'summary' => 'Yes. Enable embedding on a published puzzle and drop the provided snippet into any site.',
                'body' => <<<'MD'
                You can put a fully playable puzzle on your own blog, newsletter site, or class page:

                1. **Publish** the puzzle.
                2. Turn on **Allow embedding** in the puzzle's settings.
                3. Copy the embed snippet and paste it wherever you want the puzzle to appear.

                Visitors solve the embedded puzzle right on your page, and their progress is saved in their browser so they can leave and come back. Embedding stays under your control: switch it off at any time and the puzzle stops loading on external sites.
                MD,
                'sort_order' => 60,
            ],
            [
                'category' => 'constructing',
                'slug' => 'add-a-meta-puzzle',
                'title' => 'What is a meta puzzle, and how do I add one?',
                'summary' => 'A meta hides a final answer in the solved grid. Set a prompt and the accepted answers, and solvers submit their guess.',
                'body' => <<<'MD'
                A **meta** (or "meta puzzle") adds a second layer: once the grid is solved, the solver has to figure out a final answer the puzzle is secretly pointing to — a word, phrase, or name hidden in the theme.

                To add one to your puzzle:

                1. Write a **meta prompt** — the question that tells solvers what they're hunting for (for example, *"What classic film is this puzzle celebrating?"*).
                2. Add one or more **accepted answers**. Matching ignores case and spacing, so solvers aren't tripped up by punctuation.
                3. Optionally choose whether the correct answer is revealed after a solver submits.

                Solvers enter their guess after finishing the grid and find out immediately whether they cracked it. A good meta turns a solid puzzle into a memorable one — use them sparingly and make the "aha" worth the hunt.
                MD,
                'sort_order' => 70,
            ],

            // ---- Solving --------------------------------------------------
            [
                'category' => 'solving',
                'slug' => 'difficulty-ratings',
                'title' => 'How are puzzle difficulty ratings determined?',
                'summary' => 'Ratings start from the grid\'s structure and are refined by how real solvers actually perform.',
                'body' => <<<'MD'
                Every published puzzle carries a difficulty label — **Easy**, **Medium**, **Hard**, or **Expert** — backed by a score from 1.0 to 5.0.

                The score starts from **structural signals** in the grid:

                - **Grid size** — bigger grids generally take longer.
                - **Block density** — how open or closed the grid is.
                - **Word lengths** — the balance of long and short entries.

                Once enough people have solved a puzzle, the rating is **refined by real solve times**, so a grid that looks easy but plays tricky (or the reverse) settles toward how it actually solves. That's why a brand-new puzzle's rating can shift a little as more solvers finish it.
                MD,
                'sort_order' => 30,
            ],
            [
                'category' => 'solving',
                'slug' => 'daily-puzzle-streaks',
                'title' => 'How do daily puzzle streaks work?',
                'summary' => 'Solve the daily puzzle each day to build a streak. Miss a day and the current streak resets.',
                'body' => <<<'MD'
                There's a fresh featured puzzle every day. Solve it and your **current streak** ticks up by one; keep going day after day and it climbs.

                A few things worth knowing:

                - Your **longest streak** is remembered separately, so a missed day never erases your all-time best.
                - Miss the daily and your *current* streak resets to zero — but you can start a new one the very next day.
                - Streaks are tied to your account, so they follow you across every device you sign in on.

                Missed a few days? The **daily puzzle history** lets you catch up on past dailies whenever you like.
                MD,
                'sort_order' => 40,
            ],

            // ---- Contests -------------------------------------------------
            [
                'category' => 'contests',
                'slug' => 'run-your-own-contest',
                'title' => 'How do I run my own contest?',
                'summary' => 'Bundle a set of puzzles, set an open window, and share the link — a live leaderboard tracks the race.',
                'body' => <<<'MD'
                Contests turn a set of puzzles into a friendly competition — great for puzzle communities, events, or a themed weekend.

                To run one:

                1. **Choose the puzzles** you want to include.
                2. **Set the window** — when the contest opens and closes.
                3. **Share the link.** Solvers race through the puzzles during the window.

                A **live leaderboard** ranks participants as they finish, so everyone can watch the standings update in real time. When the window closes, the results are locked in. It's a low-effort way to give your puzzles a sense of occasion.
                MD,
                'sort_order' => 20,
            ],
        ];
    }
};
