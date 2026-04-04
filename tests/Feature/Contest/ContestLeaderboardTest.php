<?php

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\User;

test('leaderboard page displays ranked entries', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    $solver = User::factory()->create(['name' => 'Top Solver']);
    ContestEntry::factory()->for($contest)->for($solver)->create([
        'meta_solved' => true,
        'meta_submitted_at' => now(),
        'rank' => 1,
        'puzzles_completed' => 3,
        'total_solve_time_seconds' => 600,
    ]);

    $this->actingAs($user)
        ->get(route('contests.leaderboard', $contest))
        ->assertOk()
        ->assertSee('Top Solver');
});

test('leaderboard highlights current user row', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    ContestEntry::factory()->for($contest)->for($user)->create([
        'rank' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('contests.leaderboard', $contest))
        ->assertOk()
        ->assertSee($user->name);
});
