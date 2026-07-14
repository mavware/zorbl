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
    | Max Guesses
    |--------------------------------------------------------------------------
    |
    | The maximum number of guesses a player is allowed per puzzle attempt.
    |
    */

    'max_guesses' => (int) env('CROSSWORDBUILDER_MAX_GUESSES', 6),
];
