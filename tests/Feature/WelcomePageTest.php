<?php

use App\Models\Crossword;
use App\Models\DailyPuzzle;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::forget('marketing.welcome_stats');
    Cache::forget('daily_puzzle_id:'.today()->toDateString());
    Cache::forget('daily_puzzle_auto_id:'.today()->toDateString());
});

test('welcome page renders for guests with hero, register CTA, and SEO meta', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('From blank grid', false)
        ->assertSee('published puzzle in', false)
        ->assertSee('Start building', false)
        ->assertSee(route('register'), false)
        ->assertSee('<meta name="description"', false)
        ->assertSee('<meta property="og:title"', false)
        ->assertSee('<meta name="twitter:card"', false);
});

test('welcome page swaps CTAs for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertOk()
        ->assertSee('Build a puzzle', false)
        ->assertSee('Solve puzzles', false)
        ->assertSee('Go to dashboard', false)
        ->assertDontSee('Start building', false)
        ->assertDontSee('Create your free account', false);
});

test('trust strip is hidden when stats are below the credibility floor', function () {
    $response = $this->get('/');

    // "constructors" appears in feature/FAQ copy too; assert against
    // phrases that only live in the stats strip.
    $response->assertOk()
        ->assertDontSee('puzzles published', false)
        ->assertDontSee('solves this week', false);
});

test('trust strip renders stats that clear the credibility floor', function () {
    $constructor = User::factory()->create();
    Crossword::factory()->count(50)->for($constructor)->create(['is_published' => true]);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('puzzles published', false)
        ->assertSee('50', false);
});

test('welcome page shows the puzzle of the day card linking to the solver', function () {
    $constructor = User::factory()->create(['name' => 'Ada Lovelace']);
    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Daily Delight',
    ]);

    DailyPuzzle::create([
        'date' => today(),
        'crossword_id' => $crossword->id,
    ]);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('Puzzle of the Day', false)
        ->assertSee('Daily Delight', false)
        ->assertSee('Ada Lovelace', false)
        ->assertSee(route('puzzles.solve', $crossword), false);
});

test('welcome page links the daily puzzle to the auth solver for signed-in users', function () {
    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Daily Delight',
    ]);

    DailyPuzzle::create([
        'date' => today(),
        'crossword_id' => $crossword->id,
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertOk()
        ->assertSee('Daily Delight', false)
        ->assertSee(route('crosswords.solver', $crossword), false);
});

test('low solve counts are suppressed even when other stats appear', function () {
    $constructor = User::factory()->create();
    Crossword::factory()->count(50)->for($constructor)->create(['is_published' => true]);

    // 10 completed solves — below the 50 floor, must be hidden.
    PuzzleAttempt::factory()->count(10)->create([
        'is_completed' => true,
        'completed_at' => now()->subDay(),
    ]);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('puzzles published', false)
        ->assertDontSee('solves this week', false);
});
