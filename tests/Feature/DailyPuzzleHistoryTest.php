<?php

use App\Models\Crossword;
use App\Models\DailyPuzzle;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

test('daily puzzle history page is publicly accessible', function () {
    $this->get(route('puzzles.daily-history'))
        ->assertOk()
        ->assertSee('Daily Puzzle History');
});

test('daily puzzle history shows past daily puzzles', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Yesterday Puzzle']);
    DailyPuzzle::create([
        'date' => today()->subDay(),
        'crossword_id' => $crossword->id,
    ]);

    Cache::flush();

    $this->get(route('puzzles.daily-history'))
        ->assertOk()
        ->assertSee('Yesterday Puzzle');
});

test('daily puzzle history does not show future daily puzzles', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Future Puzzle']);
    DailyPuzzle::create([
        'date' => today()->addDay(),
        'crossword_id' => $crossword->id,
    ]);

    Cache::flush();

    $this->get(route('puzzles.daily-history'))
        ->assertOk()
        ->assertDontSee('Future Puzzle');
});

test('daily puzzle history shows today badge for current day', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Today Puzzle']);
    DailyPuzzle::create([
        'date' => today(),
        'crossword_id' => $crossword->id,
    ]);

    Cache::flush();

    Livewire::test('pages::puzzles.daily-history')
        ->assertSee('Today')
        ->assertSee('Today Puzzle');
});

test('daily puzzle history shows solved badge for authenticated user', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create(['title' => 'Solved History']);
    DailyPuzzle::create([
        'date' => today()->subDay(),
        'crossword_id' => $crossword->id,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
    ]);

    Cache::flush();

    Livewire::actingAs($user)
        ->test('pages::puzzles.daily-history')
        ->assertSee('Solved')
        ->assertSee('View Solution');
});

test('daily puzzle history does not show solved badge for guests', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Guest History']);
    DailyPuzzle::create([
        'date' => today()->subDay(),
        'crossword_id' => $crossword->id,
    ]);

    Cache::flush();

    $this->get(route('puzzles.daily-history'))
        ->assertOk()
        ->assertDontSee('View Solution')
        ->assertSee('Solve');
});

test('daily puzzle history orders puzzles newest first', function () {
    $older = Crossword::factory()->published()->create(['title' => 'Older Daily']);
    $newer = Crossword::factory()->published()->create(['title' => 'Newer Daily']);

    DailyPuzzle::create(['date' => today()->subDays(3), 'crossword_id' => $older->id]);
    DailyPuzzle::create(['date' => today()->subDay(), 'crossword_id' => $newer->id]);

    Cache::flush();

    Livewire::test('pages::puzzles.daily-history')
        ->assertSeeInOrder(['Newer Daily', 'Older Daily']);
});

test('daily puzzle history paginates results', function () {
    $crosswords = Crossword::factory()->count(25)->published()->create();

    $crosswords->each(function ($crossword, $index) {
        DailyPuzzle::create([
            'date' => today()->subDays($index + 1),
            'crossword_id' => $crossword->id,
        ]);
    });

    Cache::flush();

    Livewire::test('pages::puzzles.daily-history')
        ->assertSee($crosswords->first()->title);
});

test('daily puzzle history startSolving redirects authenticated user to solver', function () {
    $crossword = Crossword::factory()->published()->create();
    DailyPuzzle::create(['date' => today()->subDay(), 'crossword_id' => $crossword->id]);

    Cache::flush();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::puzzles.daily-history')
        ->call('startSolving', $crossword->id)
        ->assertRedirect(route('crosswords.solver', $crossword));
});

test('daily puzzle history startSolving redirects guest to public solver', function () {
    $crossword = Crossword::factory()->published()->create();
    DailyPuzzle::create(['date' => today()->subDay(), 'crossword_id' => $crossword->id]);

    Cache::flush();

    Livewire::test('pages::puzzles.daily-history')
        ->call('startSolving', $crossword->id)
        ->assertRedirect(route('puzzles.solve', $crossword));
});

test('browse puzzles page links to daily puzzle history', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Link Test']);
    DailyPuzzle::create(['date' => today(), 'crossword_id' => $crossword->id]);

    Cache::flush();

    $this->get(route('puzzles.index'))
        ->assertOk()
        ->assertSee('View past puzzles');
});

test('dashboard links to daily puzzle history', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Dashboard Link']);
    DailyPuzzle::create(['date' => today(), 'crossword_id' => $crossword->id]);

    Cache::flush();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::dashboard')
        ->assertSee('View past puzzles');
});
