<?php

use App\Models\Crossword;
use App\Models\DailyPuzzle;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

test('today() returns the manually selected daily puzzle', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Selected Daily']);

    DailyPuzzle::create([
        'date' => today(),
        'crossword_id' => $crossword->id,
        'selected_by' => User::factory()->create()->id,
    ]);

    Cache::flush();

    $result = DailyPuzzle::today();

    expect($result)->not->toBeNull()
        ->and($result->crossword->title)->toBe('Selected Daily');
});

test('today() returns null when no daily puzzle is set', function () {
    Cache::flush();

    expect(DailyPuzzle::today())->toBeNull();
});

test('todayOrAuto() falls back to auto-selection when no manual pick exists', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Auto Selected']);
    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $crossword->id]);

    Cache::flush();

    $result = DailyPuzzle::todayOrAuto();

    expect($result)->not->toBeNull()
        ->and($result->is_published)->toBeTrue();
});

test('todayOrAuto() prefers manual selection over auto-selection', function () {
    $manual = Crossword::factory()->published()->create(['title' => 'Manual Pick']);
    $other = Crossword::factory()->published()->create(['title' => 'Other Puzzle']);
    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $other->id]);

    DailyPuzzle::create([
        'date' => today(),
        'crossword_id' => $manual->id,
    ]);

    Cache::flush();

    $result = DailyPuzzle::todayOrAuto();

    expect($result->id)->toBe($manual->id);
});

test('auto-selection is deterministic for a given date', function () {
    Crossword::factory()->count(5)->published()->create()->each(function ($crossword) {
        PuzzleAttempt::factory()->completed()->create(['crossword_id' => $crossword->id]);
    });

    Cache::flush();

    $first = DailyPuzzle::autoSelect(today());

    Cache::flush();

    $second = DailyPuzzle::autoSelect(today());

    expect($first->id)->toBe($second->id);
});

test('auto-selection picks different puzzles for different dates', function () {
    Crossword::factory()->count(10)->published()->create()->each(function ($crossword) {
        PuzzleAttempt::factory()->completed()->create(['crossword_id' => $crossword->id]);
    });

    $results = collect();
    for ($i = 0; $i < 10; $i++) {
        Cache::flush();
        $date = today()->addDays($i)->toDateString();
        $puzzle = DailyPuzzle::autoSelect($date);
        if ($puzzle) {
            $results->push($puzzle->id);
        }
    }

    expect($results->unique()->count())->toBeGreaterThan(1);
});

test('auto-selection returns null when no published puzzles have completions', function () {
    Crossword::factory()->published()->create();

    Cache::flush();

    expect(DailyPuzzle::autoSelect(today()))->toBeNull();
});

test('auto-selection excludes puzzles without titles', function () {
    $untitled = Crossword::factory()->published()->create(['title' => '']);
    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $untitled->id]);

    Cache::flush();

    expect(DailyPuzzle::autoSelect(today()))->toBeNull();
});

test('daily puzzle date must be unique', function () {
    $crossword1 = Crossword::factory()->published()->create();
    $crossword2 = Crossword::factory()->published()->create();

    DailyPuzzle::create(['date' => today(), 'crossword_id' => $crossword1->id]);

    expect(fn () => DailyPuzzle::create(['date' => today(), 'crossword_id' => $crossword2->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('daily puzzle has crossword relationship', function () {
    $crossword = Crossword::factory()->published()->create();
    $daily = DailyPuzzle::factory()->create(['crossword_id' => $crossword->id]);

    expect($daily->crossword->id)->toBe($crossword->id);
});

test('daily puzzle has optional selector relationship', function () {
    $admin = User::factory()->create();
    $daily = DailyPuzzle::factory()->create(['selected_by' => $admin->id]);

    expect($daily->selector->id)->toBe($admin->id);
});

test('daily puzzle selector can be null', function () {
    $daily = DailyPuzzle::factory()->create(['selected_by' => null]);

    expect($daily->selector)->toBeNull();
});

test('dashboard shows daily puzzle when one exists', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Daily Highlight']);
    DailyPuzzle::create(['date' => today(), 'crossword_id' => $crossword->id]);

    Cache::flush();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::dashboard')
        ->assertSee('Puzzle of the Day')
        ->assertSee('Daily Highlight');
});

test('dashboard shows auto-selected daily puzzle when no manual pick exists', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Auto Daily']);
    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $crossword->id]);

    Cache::flush();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::dashboard')
        ->assertSee('Puzzle of the Day')
        ->assertSee('Auto Daily');
});

test('public puzzles page shows daily puzzle', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Public Daily']);
    DailyPuzzle::create(['date' => today(), 'crossword_id' => $crossword->id]);

    Cache::flush();

    $this->get(route('puzzles.index'))
        ->assertOk()
        ->assertSee('Puzzle of the Day')
        ->assertSee('Public Daily');
});
