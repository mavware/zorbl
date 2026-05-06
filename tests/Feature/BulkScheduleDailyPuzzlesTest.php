<?php

use App\Models\Crossword;
use App\Models\DailyPuzzle;
use App\Models\PuzzleAttempt;
use App\Models\Tag;
use Illuminate\Support\Facades\Artisan;

test('it schedules daily puzzles for the given date range', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Eligible']);
    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $crossword->id]);

    Artisan::call('daily-puzzles:bulk-schedule', [
        'from' => '2099-01-01',
        'to' => '2099-01-03',
    ]);

    expect(Artisan::output())->toContain('Scheduled: 3')
        ->and(DailyPuzzle::whereDate('date', '>=', '2099-01-01')->whereDate('date', '<=', '2099-01-03')->count())->toBe(3);
});

test('it fails when to date is before from date', function () {
    $this->artisan('daily-puzzles:bulk-schedule', [
        'from' => '2099-01-05',
        'to' => '2099-01-01',
    ])->assertFailed();
});

test('it fails when no eligible puzzles exist', function () {
    $this->artisan('daily-puzzles:bulk-schedule', [
        'from' => '2099-01-01',
        'to' => '2099-01-03',
    ])->assertFailed();
});

test('it skips dates with existing assignments by default', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Existing']);
    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $crossword->id]);

    DailyPuzzle::create([
        'date' => '2099-02-02',
        'crossword_id' => $crossword->id,
    ]);

    Artisan::call('daily-puzzles:bulk-schedule', [
        'from' => '2099-02-01',
        'to' => '2099-02-03',
    ]);

    $output = Artisan::output();
    expect($output)->toContain('Scheduled: 2')
        ->and($output)->toContain('Skipped: 1');
});

test('it replaces existing assignments with --overwrite', function () {
    $original = Crossword::factory()->published()->create(['title' => 'Original']);
    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $original->id]);

    $replacement = Crossword::factory()->published()->create(['title' => 'Replacement']);
    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $replacement->id]);

    DailyPuzzle::create([
        'date' => '2099-03-01',
        'crossword_id' => $original->id,
    ]);

    Artisan::call('daily-puzzles:bulk-schedule', [
        'from' => '2099-03-01',
        'to' => '2099-03-03',
        '--overwrite' => true,
    ]);

    expect(Artisan::output())->toContain('Replaced: 1');
});

test('it filters puzzles by tag slug', function () {
    $tag = Tag::factory()->create(['slug' => 'history']);

    $tagged = Crossword::factory()->published()->create(['title' => 'History Puzzle']);
    $tagged->tags()->attach($tag);
    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $tagged->id]);

    $untagged = Crossword::factory()->published()->create(['title' => 'No Tag']);
    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $untagged->id]);

    Artisan::call('daily-puzzles:bulk-schedule', [
        'from' => '2099-04-01',
        'to' => '2099-04-01',
        '--tag' => 'history',
    ]);

    $daily = DailyPuzzle::whereDate('date', '2099-04-01')->first();
    expect($daily)->not->toBeNull()
        ->and($daily->crossword_id)->toBe($tagged->id);
});

test('it excludes unpublished puzzles', function () {
    $published = Crossword::factory()->published()->create(['title' => 'Published']);
    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $published->id]);

    Crossword::factory()->create(['title' => 'Draft', 'is_published' => false]);

    Artisan::call('daily-puzzles:bulk-schedule', [
        'from' => '2099-05-01',
        'to' => '2099-05-01',
    ]);

    $daily = DailyPuzzle::whereDate('date', '2099-05-01')->first();
    expect($daily)->not->toBeNull()
        ->and($daily->crossword_id)->toBe($published->id);
});

test('it excludes puzzles without completed attempts', function () {
    $withAttempts = Crossword::factory()->published()->create(['title' => 'Completed']);
    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $withAttempts->id]);

    Crossword::factory()->published()->create(['title' => 'No Attempts']);

    Artisan::call('daily-puzzles:bulk-schedule', [
        'from' => '2099-06-01',
        'to' => '2099-06-01',
    ]);

    $daily = DailyPuzzle::whereDate('date', '2099-06-01')->first();
    expect($daily)->not->toBeNull()
        ->and($daily->crossword_id)->toBe($withAttempts->id);
});

test('it schedules a single day when from and to are the same', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Single Day']);
    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $crossword->id]);

    Artisan::call('daily-puzzles:bulk-schedule', [
        'from' => '2099-07-01',
        'to' => '2099-07-01',
    ]);

    expect(Artisan::output())->toContain('Scheduled: 1')
        ->and(DailyPuzzle::whereDate('date', '2099-07-01')->exists())->toBeTrue();
});
