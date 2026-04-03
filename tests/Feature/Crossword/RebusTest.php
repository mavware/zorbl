<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use App\Services\IpuzExporter;
use App\Services\IpuzImporter;
use App\Services\PuzExporter;

test('crossword can store multi-letter solution values', function () {
    $crossword = Crossword::factory()->create([
        'width' => 3,
        'height' => 1,
        'grid' => [[1, 0, 0]],
        'solution' => [['THEME', 'A', 'B']],
    ]);

    $crossword->refresh();
    expect($crossword->solution[0][0])->toBe('THEME');
});

test('puzzle attempt can store multi-letter progress values', function () {
    $crossword = Crossword::factory()->create([
        'width' => 3,
        'height' => 1,
        'grid' => [[1, 0, 0]],
        'solution' => [['THEME', 'A', 'B']],
    ]);

    $attempt = PuzzleAttempt::factory()->for($crossword)->create([
        'progress' => [['THEME', 'A', '']],
    ]);

    $attempt->refresh();
    expect($attempt->progress[0][0])->toBe('THEME');
});

test('solve progress counts rebus cells correctly', function () {
    $crossword = Crossword::factory()->create([
        'width' => 2,
        'height' => 1,
        'grid' => [[1, 0]],
    ]);

    $attempt = PuzzleAttempt::factory()->for($crossword)->create([
        'progress' => [['THEME', '']],
    ]);

    // 1 of 2 cells filled
    expect($attempt->solveProgress())->toBe(50);
});

test('solver can save multi-letter progress', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 1,
        'grid' => [[1, 0]],
        'solution' => [['THEME', 'A']],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => [['', '']],
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', [['THEME', 'A']], false, 30);

    $attempt = PuzzleAttempt::where('user_id', $user->id)
        ->where('crossword_id', $crossword->id)
        ->first();

    expect($attempt->progress[0][0])->toBe('THEME');
});

test('ipuz export preserves rebus values in solution', function () {
    $crossword = Crossword::factory()->create([
        'width' => 2,
        'height' => 1,
        'grid' => [[1, 0]],
        'solution' => [['THEME', 'A']],
        'clues_across' => [['number' => 1, 'clue' => 'Test']],
        'clues_down' => [],
    ]);

    $exporter = app(IpuzExporter::class);
    $data = $exporter->export($crossword);

    expect($data['solution'][0][0])->toBe('THEME')
        ->and($data['solution'][0][1])->toBe('A');
});

test('ipuz import preserves rebus values', function () {
    $ipuz = json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 2, 'height' => 1],
        'puzzle' => [[1, 0]],
        'solution' => [['THEME', 'A']],
        'clues' => [
            'Across' => [[1, 'Test clue']],
            'Down' => [],
        ],
    ]);

    $importer = app(IpuzImporter::class);
    $result = $importer->import($ipuz);

    expect($result['solution'][0][0])->toBe('THEME')
        ->and($result['solution'][0][1])->toBe('A');
});

test('puz export includes rebus GRBS and RTBL sections', function () {
    $crossword = Crossword::factory()->create([
        'width' => 3,
        'height' => 1,
        'grid' => [[1, 0, 0]],
        'solution' => [['THEME', 'A', 'B']],
        'clues_across' => [['number' => 1, 'clue' => 'Test']],
        'clues_down' => [],
    ]);

    $exporter = app(PuzExporter::class);
    $binary = $exporter->export($crossword);

    // The export should contain GRBS and RTBL section markers
    expect($binary)->toContain('GRBS')
        ->and($binary)->toContain('RTBL')
        ->and($binary)->toContain('THEME');
});

test('completeness check counts rebus cells as filled', function () {
    $crossword = Crossword::factory()->create([
        'width' => 2,
        'height' => 1,
        'grid' => [[1, 0]],
        'solution' => [['THEME', 'A']],
        'title' => 'Rebus Test',
        'author' => 'Tester',
        'clues_across' => [['number' => 1, 'clue' => 'A theme']],
        'clues_down' => [],
    ]);

    $completeness = $crossword->completeness();

    expect($completeness['checks']['fill'])->toBeTrue();
});
