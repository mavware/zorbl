<?php

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Crossword;
use App\Models\User;
use Livewire\Livewire;

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

test('leaderboard shows empty state when no entries exist', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest])
        ->assertSee('No entries yet')
        ->assertSee('Be the first to join this contest!');
});

test('leaderboard shows contest title', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create(['title' => 'Spring Crossword Challenge']);

    Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest])
        ->assertSee('Spring Crossword Challenge');
});

test('leaderboard default sort is by rank ascending', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    $first = User::factory()->create(['name' => 'Gold Winner']);
    $second = User::factory()->create(['name' => 'Silver Winner']);
    $third = User::factory()->create(['name' => 'Bronze Winner']);

    ContestEntry::factory()->for($contest)->for($first)->create(['rank' => 1]);
    ContestEntry::factory()->for($contest)->for($second)->create(['rank' => 2]);
    ContestEntry::factory()->for($contest)->for($third)->create(['rank' => 3]);

    Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest])
        ->assertSeeInOrder(['Gold Winner', 'Silver Winner', 'Bronze Winner']);
});

test('leaderboard can sort by puzzles completed', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    $fewPuzzles = User::factory()->create(['name' => 'Casual Solver']);
    $manyPuzzles = User::factory()->create(['name' => 'Power Solver']);

    ContestEntry::factory()->for($contest)->for($fewPuzzles)->create([
        'rank' => 1,
        'puzzles_completed' => 2,
    ]);
    ContestEntry::factory()->for($contest)->for($manyPuzzles)->create([
        'rank' => 2,
        'puzzles_completed' => 5,
    ]);

    Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest])
        ->call('sortBy', 'puzzles_completed')
        ->assertSeeInOrder(['Casual Solver', 'Power Solver']);
});

test('leaderboard can sort by total solve time', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    $fast = User::factory()->create(['name' => 'Fast Solver']);
    $slow = User::factory()->create(['name' => 'Slow Solver']);

    ContestEntry::factory()->for($contest)->for($fast)->create([
        'rank' => 1,
        'total_solve_time_seconds' => 300,
    ]);
    ContestEntry::factory()->for($contest)->for($slow)->create([
        'rank' => 2,
        'total_solve_time_seconds' => 900,
    ]);

    Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest])
        ->call('sortBy', 'total_solve_time')
        ->assertSeeInOrder(['Fast Solver', 'Slow Solver']);
});

test('leaderboard sort toggles direction when clicking same column', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    $first = User::factory()->create(['name' => 'Rank One']);
    $third = User::factory()->create(['name' => 'Rank Three']);

    ContestEntry::factory()->for($contest)->for($first)->create(['rank' => 1]);
    ContestEntry::factory()->for($contest)->for($third)->create(['rank' => 3]);

    Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest])
        ->assertSet('sortDirection', 'asc')
        ->call('sortBy', 'rank')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['Rank Three', 'Rank One']);
});

test('leaderboard sort resets direction when switching columns', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    ContestEntry::factory()->for($contest)->create();

    Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest])
        ->call('sortBy', 'rank')
        ->assertSet('sortDirection', 'desc')
        ->call('sortBy', 'puzzles_completed')
        ->assertSet('sortField', 'puzzles_completed')
        ->assertSet('sortDirection', 'asc');
});

test('leaderboard ignores invalid sort fields', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    $first = User::factory()->create(['name' => 'First Place']);
    $second = User::factory()->create(['name' => 'Second Place']);

    ContestEntry::factory()->for($contest)->for($first)->create(['rank' => 1]);
    ContestEntry::factory()->for($contest)->for($second)->create(['rank' => 2]);

    Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest])
        ->set('sortField', 'malicious_field')
        ->assertSeeInOrder(['First Place', 'Second Place']);
});

test('leaderboard shows total puzzles count', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    $crosswords = Crossword::factory()->count(3)->published()->create();
    $contest->crosswords()->attach($crosswords->pluck('id'));

    $solver = User::factory()->create();
    ContestEntry::factory()->for($contest)->for($solver)->create([
        'rank' => 1,
        'puzzles_completed' => 2,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest]);

    expect($component->get('totalPuzzles'))->toBe(3);
});

test('leaderboard distinguishes meta solved from unsolved entries', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    $solvedUser = User::factory()->create(['name' => 'Meta Solver']);
    $unsolvedUser = User::factory()->create(['name' => 'Still Trying']);

    ContestEntry::factory()->for($contest)->for($solvedUser)->create([
        'rank' => 1,
        'meta_solved' => true,
        'meta_submitted_at' => now(),
    ]);
    ContestEntry::factory()->for($contest)->for($unsolvedUser)->create([
        'rank' => 2,
        'meta_solved' => false,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest]);

    $entries = $component->get('entries');

    expect($entries->first()->meta_solved)->toBeTrue()
        ->and($entries->last()->meta_solved)->toBeFalse();
});

test('leaderboard shows formatted solve time', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    ContestEntry::factory()->for($contest)->create([
        'rank' => 1,
        'total_solve_time_seconds' => 3661,
    ]);

    Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest])
        ->assertSee('1:01:01');
});

test('leaderboard shows dash for null solve time', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    ContestEntry::factory()->for($contest)->create([
        'rank' => 1,
        'total_solve_time_seconds' => null,
    ]);

    $this->actingAs($user)
        ->get(route('contests.leaderboard', $contest))
        ->assertOk();
});

test('leaderboard shows meta submission date', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    $submittedAt = now()->setMonth(3)->setDay(15)->setHour(14)->setMinute(30);

    ContestEntry::factory()->for($contest)->create([
        'rank' => 1,
        'meta_solved' => true,
        'meta_submitted_at' => $submittedAt,
    ]);

    Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest])
        ->assertSee('Mar 15');
});

test('leaderboard guest access is redirected', function () {
    $contest = Contest::factory()->active()->create();

    $this->get(route('contests.leaderboard', $contest))
        ->assertRedirect();
});

test('leaderboard entries load user relationship', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    $solver = User::factory()->create(['name' => 'Eager Loaded User']);
    ContestEntry::factory()->for($contest)->for($solver)->create(['rank' => 1]);

    Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest])
        ->assertSee('Eager Loaded User');
});

test('leaderboard works for ended contests', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->ended()->create(['title' => 'Past Contest']);

    $solver = User::factory()->create(['name' => 'Past Participant']);
    ContestEntry::factory()->for($contest)->for($solver)->create(['rank' => 1]);

    Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest])
        ->assertSee('Past Participant')
        ->assertSee('Past Contest');
});

test('leaderboard shows puzzles completed out of total', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    $crosswords = Crossword::factory()->count(5)->published()->create();
    $contest->crosswords()->attach($crosswords->pluck('id'));

    ContestEntry::factory()->for($contest)->create([
        'rank' => 1,
        'puzzles_completed' => 3,
    ]);

    Livewire::actingAs($user)
        ->test('pages::contests.leaderboard', ['contest' => $contest])
        ->assertSee('3')
        ->assertSee('5');
});
