<?php

use App\Models\Crossword;
use App\Models\DailyPuzzle;
use App\Models\PuzzleAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('returns today\'s daily puzzle', function () {
    $crossword = Crossword::factory()->published()->create();
    DailyPuzzle::factory()->create([
        'date' => today()->toDateString(),
        'crossword_id' => $crossword->id,
    ]);

    Cache::flush();

    $response = $this->getJson('/api/v1/daily-puzzle');

    $response->assertSuccessful()
        ->assertJsonPath('data.type', 'crosswords')
        ->assertJsonPath('data.id', (string) $crossword->id)
        ->assertJsonPath('meta.date', today()->toDateString())
        ->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => [
                    'title',
                    'width',
                    'height',
                ],
                'meta' => [
                    'likes_count',
                    'attempts_count',
                ],
            ],
            'meta' => ['date'],
        ]);
});

it('returns null data when no daily puzzle exists', function () {
    Cache::flush();

    $response = $this->getJson('/api/v1/daily-puzzle');

    $response->assertSuccessful()
        ->assertJsonPath('data', null);
});

it('auto-selects a puzzle when no manual daily puzzle is set', function () {
    $crossword = Crossword::factory()->published()->create([
        'title' => 'Auto Selected Puzzle',
    ]);
    PuzzleAttempt::factory()->create([
        'crossword_id' => $crossword->id,
        'is_completed' => true,
    ]);

    Cache::flush();

    $response = $this->getJson('/api/v1/daily-puzzle');

    $response->assertSuccessful()
        ->assertJsonPath('data.id', (string) $crossword->id);
});

it('includes likes and comments counts in the daily puzzle response', function () {
    $crossword = Crossword::factory()->published()->create();
    DailyPuzzle::factory()->create([
        'date' => today()->toDateString(),
        'crossword_id' => $crossword->id,
    ]);

    Cache::flush();

    $response = $this->getJson('/api/v1/daily-puzzle');

    $response->assertSuccessful()
        ->assertJsonPath('data.meta.likes_count', 0)
        ->assertJsonPath('data.meta.comments_count', 0);
});

it('returns paginated daily puzzle history', function () {
    $crosswords = Crossword::factory()->published()->count(3)->create();
    foreach ($crosswords as $i => $crossword) {
        DailyPuzzle::factory()->create([
            'date' => today()->subDays($i)->toDateString(),
            'crossword_id' => $crossword->id,
        ]);
    }

    $response = $this->getJson('/api/v1/daily-puzzle/history');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'type',
                    'id',
                    'attributes',
                ],
            ],
            'meta' => ['dates'],
            'links',
        ]);
});

it('returns history in descending date order', function () {
    $older = Crossword::factory()->published()->create();
    $newer = Crossword::factory()->published()->create();

    DailyPuzzle::factory()->create([
        'date' => today()->subDays(2)->toDateString(),
        'crossword_id' => $older->id,
    ]);
    DailyPuzzle::factory()->create([
        'date' => today()->toDateString(),
        'crossword_id' => $newer->id,
    ]);

    $response = $this->getJson('/api/v1/daily-puzzle/history');

    $response->assertSuccessful();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([(string) $newer->id, (string) $older->id]);
});

it('excludes future daily puzzles from history', function () {
    $todayPuzzle = Crossword::factory()->published()->create();
    $futurePuzzle = Crossword::factory()->published()->create();

    DailyPuzzle::factory()->create([
        'date' => today()->toDateString(),
        'crossword_id' => $todayPuzzle->id,
    ]);
    DailyPuzzle::factory()->create([
        'date' => today()->addDay()->toDateString(),
        'crossword_id' => $futurePuzzle->id,
    ]);

    $response = $this->getJson('/api/v1/daily-puzzle/history');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', (string) $todayPuzzle->id);
});

it('includes date metadata in history response', function () {
    $crossword = Crossword::factory()->published()->create();
    DailyPuzzle::factory()->create([
        'date' => today()->toDateString(),
        'crossword_id' => $crossword->id,
    ]);

    $response = $this->getJson('/api/v1/daily-puzzle/history');

    $response->assertSuccessful();
    $dates = $response->json('meta.dates');
    expect($dates)->toHaveCount(1);
    expect($dates[0]['crossword_id'])->toBe($crossword->id);
    expect($dates[0]['date'])->toBe(today()->toDateString());
});

it('paginates history results', function () {
    for ($i = 0; $i < 20; $i++) {
        $crossword = Crossword::factory()->published()->create();
        DailyPuzzle::factory()->create([
            'date' => today()->subDays($i)->toDateString(),
            'crossword_id' => $crossword->id,
        ]);
    }

    $response = $this->getJson('/api/v1/daily-puzzle/history');

    $response->assertSuccessful()
        ->assertJsonCount(15, 'data');

    $page2 = $this->getJson('/api/v1/daily-puzzle/history?page=2');
    $page2->assertSuccessful()
        ->assertJsonCount(5, 'data');
});

it('includes crossword relationships in history', function () {
    $crossword = Crossword::factory()->published()->create();
    DailyPuzzle::factory()->create([
        'date' => today()->toDateString(),
        'crossword_id' => $crossword->id,
    ]);

    $response = $this->getJson('/api/v1/daily-puzzle/history');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'type',
                    'id',
                    'attributes' => ['title', 'width', 'height'],
                    'relationships' => ['user'],
                    'meta' => ['likes_count', 'comments_count'],
                ],
            ],
        ]);
});
