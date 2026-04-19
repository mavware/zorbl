<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::forget('marketing.welcome_stats');
});

test('welcome page renders for guests with hero, register CTA, and SEO meta', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('From blank grid', false)
        ->assertSee('published puzzle in', false)
        ->assertSee('Start building free', false)
        ->assertSee('Free forever — no credit card.', false)
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
        ->assertDontSee('Start building free', false)
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
