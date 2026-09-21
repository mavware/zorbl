<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Guest Solve Limit
    |--------------------------------------------------------------------------
    |
    | The number of distinct puzzles an unauthenticated visitor may solve
    | before being prompted to create an account. Tracked via the
    | `crosswordbuilder_guest_solved` cookie.
    |
    */

    'guest_solve_limit' => (int) env('CROSSWORDBUILDER_GUEST_SOLVE_LIMIT', 10),

    /*
    |--------------------------------------------------------------------------
    | Guest Puzzle Limit
    |--------------------------------------------------------------------------
    |
    | The number of puzzles a guest builder (anonymous account) may create
    | before being prompted to sign up. Registered users are unlimited.
    |
    */

    'guest_puzzle_limit' => (int) env('CROSSWORDBUILDER_GUEST_PUZZLE_LIMIT', 1),

    /*
    |--------------------------------------------------------------------------
    | Max Guesses
    |--------------------------------------------------------------------------
    |
    | The maximum number of guesses a player is allowed per puzzle attempt.
    |
    */

    'max_guesses' => (int) env('CROSSWORDBUILDER_MAX_GUESSES', 6),

    /*
    |--------------------------------------------------------------------------
    | External Contributors
    |--------------------------------------------------------------------------
    |
    | People who support Crossword Builder outside the site's own subscription
    | flow. They are added to the supporter count and funding progress shown
    | in the "Support our work" section of the billing page.
    |
    */

    'external_contributors' => (int) env('CROSSWORDBUILDER_EXTERNAL_CONTRIBUTORS', 0),

    /*
    |--------------------------------------------------------------------------
    | Monthly Funding Goal
    |--------------------------------------------------------------------------
    |
    | The monthly funding target, in whole dollars, shown as the goal in the
    | "Support our work" section of the billing page.
    |
    */

    'funding_goal_dollars' => (int) env('CROSSWORDBUILDER_FUNDING_GOAL_DOLLARS', 500),

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Toggles for whole features. A disabled feature keeps its code and data
    | but is hidden from users and admins: routes 404, the admin resource is
    | unreachable, and scheduled jobs are skipped.
    |
    */

    'features' => [
        'contests' => (bool) env('CROSSWORDBUILDER_CONTESTS_ENABLED', false),
    ],
];
