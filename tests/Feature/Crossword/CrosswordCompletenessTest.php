<?php

use App\Models\Crossword;
use App\Models\User;
use Livewire\Livewire;

test('completeness is 100% for a fully filled puzzle', function () {
    $crossword = Crossword::factory()->create([
        'author' => 'Jane',
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'clues_across' => [
            ['number' => 1, 'clue' => 'First across'],
            ['number' => 3, 'clue' => 'Second across'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'First down'],
            ['number' => 2, 'clue' => 'Second down'],
        ],
    ]);

    $result = $crossword->completeness();

    expect($result['percentage'])->toBe(100)
        ->and($result['checks'])->each->toBeTrue();
});

test('completeness is 100% even without a title', function () {
    $crossword = Crossword::factory()->create([
        'title' => null,
        'author' => 'Jane',
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'clues_across' => [
            ['number' => 1, 'clue' => 'First across'],
            ['number' => 3, 'clue' => 'Second across'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'First down'],
            ['number' => 2, 'clue' => 'Second down'],
        ],
    ]);

    expect($crossword->completeness()['percentage'])->toBe(100);
});

test('completeness detects missing author', function () {
    $crossword = Crossword::factory()->create(['author' => null]);

    expect($crossword->completeness()['checks']['author'])->toBeFalse();
});

test('completeness detects unfilled cells', function () {
    $crossword = Crossword::factory()->create([
        'solution' => [['A', ''], ['C', 'D']],
    ]);

    expect($crossword->completeness()['checks']['fill'])->toBeFalse();
});

test('completeness ignores black cells for fill check', function () {
    $crossword = Crossword::factory()->create([
        'title' => 'Test',
        'author' => 'Author',
        'grid' => [[1, '#'], [2, 0]],
        'solution' => [['A', '#'], ['C', 'D']],
        'clues_across' => [['number' => 2, 'clue' => 'Clue']],
        'clues_down' => [['number' => 1, 'clue' => 'Clue']],
    ]);

    expect($crossword->completeness()['checks']['fill'])->toBeTrue();
});

test('completeness detects missing across clues', function () {
    $crossword = Crossword::factory()->create([
        'clues_across' => [
            ['number' => 1, 'clue' => 'Has clue'],
            ['number' => 3, 'clue' => ''],
        ],
    ]);

    expect($crossword->completeness()['checks']['clues_across'])->toBeFalse();
});

test('completeness detects missing down clues', function () {
    $crossword = Crossword::factory()->create([
        'clues_down' => [
            ['number' => 1, 'clue' => ''],
        ],
    ]);

    expect($crossword->completeness()['checks']['clues_down'])->toBeFalse();
});

test('completeness percentage is calculated correctly', function () {
    $crossword = Crossword::factory()->create([
        'author' => 'Jane',
        'grid' => [[1]],
        'solution' => [['A']],
        'clues_across' => [],
        'clues_down' => [],
    ]);

    $result = $crossword->completeness();

    expect($result['checks']['author'])->toBeTrue()
        ->and($result['checks']['fill'])->toBeTrue()
        ->and($result['checks']['clues_across'])->toBeFalse()
        ->and($result['checks']['clues_down'])->toBeFalse()
        ->and($result['percentage'])->toBe(50);
});

test('publish button shows warning when puzzle is incomplete', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'author' => '',
        'solution' => [['', ''], ['', '']],
        'clues_across' => [['number' => 1, 'clue' => '']],
        'clues_down' => [['number' => 1, 'clue' => '']],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptPublish')
        ->assertSet('showPublishWarning', true)
        ->assertSet('isPublished', false);
});

test('publish proceeds without warning when puzzle is complete', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => null,
        'author' => 'Author',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'clues_across' => [
            ['number' => 1, 'clue' => 'Across 1'],
            ['number' => 3, 'clue' => 'Across 3'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'Down 1'],
            ['number' => 2, 'clue' => 'Down 2'],
        ],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptPublish')
        ->assertSet('showPublishWarning', false)
        ->assertSet('isPublished', true);
});

test('unpublish skips warning', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => '',
        'is_published' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptPublish')
        ->assertSet('showPublishWarning', false)
        ->assertSet('isPublished', false);
});

test('cancel publish dispatches highlight event', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'author' => '',
        'clues_across' => [['number' => 1, 'clue' => '']],
        'clues_down' => [['number' => 1, 'clue' => '']],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptPublish')
        ->call('cancelPublish')
        ->assertSet('showPublishWarning', false)
        ->assertDispatched('highlight-incomplete');
});

test('publish warning lists exactly which answers and clues are missing', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'author' => 'Author',
        'width' => 3,
        'height' => 3,
        'grid' => [[1, 2, 3], [4, 0, 0], [5, 0, 0]],
        'solution' => [['C', 'A', 'T'], ['O', '', 'X'], ['W', '', '']],
        'clues_across' => [
            ['number' => 1, 'clue' => 'Pet'],
            ['number' => 4, 'clue' => ''],
            ['number' => 5, 'clue' => 'Last row'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'Moo'],
            ['number' => 2, 'clue' => '   '],
            ['number' => 3, 'clue' => 'Last column'],
        ],
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptPublish')
        ->assertSet('showPublishWarning', true)
        ->assertSet('incompleteChecks', ['fill', 'clues_across', 'clues_down'])
        ->assertSet('incompleteDetails', [
            'answers' => [
                ['direction' => 'across', 'number' => 4, 'pattern' => 'O?X'],
                ['direction' => 'across', 'number' => 5, 'pattern' => 'W??'],
                ['direction' => 'down', 'number' => 2, 'pattern' => 'A??'],
                ['direction' => 'down', 'number' => 3, 'pattern' => 'TX?'],
            ],
            'clues' => [
                ['direction' => 'across', 'number' => 4, 'pattern' => 'O?X'],
                ['direction' => 'down', 'number' => 2, 'pattern' => 'A??'],
            ],
        ])
        ->assertSeeInOrder([
            'Publish Incomplete Puzzle?',
            'See details',
            'Answers with missing letters',
            '4 Across', 'O?X',
            '5 Across', 'W??',
            '2 Down', 'A??',
            '3 Down', 'TX?',
            'Clues without text',
            '4 Across', 'O?X',
            '2 Down', 'A??',
            'Publish Anyway',
        ]);

    $html = $component->html();
    expect(substr_count($html, 'data-test="publish-warning-details"'))->toBe(1)
        ->and($html)->toContain('data-test="publish-warning-answers"')
        ->toContain('data-test="publish-warning-clues"');
});

test('publish warning details only cover the checks that failed', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'author' => '',
        'width' => 3,
        'height' => 3,
        'grid' => [[1, 2, 3], [4, 0, 0], [5, 0, 0]],
        'solution' => [['C', 'A', 'T'], ['O', 'N', 'E'], ['W', 'E', 'D']],
        'clues_across' => [
            ['number' => 1, 'clue' => 'Pet'],
            ['number' => 4, 'clue' => 'Single'],
            ['number' => 5, 'clue' => 'Married'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'Moo'],
            ['number' => 2, 'clue' => ''],
            ['number' => 3, 'clue' => 'Pulled'],
        ],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptPublish')
        ->assertSet('incompleteChecks', ['author', 'clues_down'])
        ->assertSet('incompleteDetails', [
            'answers' => [],
            'clues' => [
                ['direction' => 'down', 'number' => 2, 'pattern' => 'ANE'],
            ],
        ])
        ->assertSee('Clues without text')
        ->assertDontSee('Answers with missing letters');
});

test('publish warning hides the details section when only the constructor name is missing', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'author' => '',
        'width' => 3,
        'height' => 3,
        'grid' => [[1, 2, 3], [4, 0, 0], [5, 0, 0]],
        'solution' => [['C', 'A', 'T'], ['O', 'N', 'E'], ['W', 'E', 'D']],
        'clues_across' => [
            ['number' => 1, 'clue' => 'Pet'],
            ['number' => 4, 'clue' => 'Single'],
            ['number' => 5, 'clue' => 'Married'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'Moo'],
            ['number' => 2, 'clue' => 'Birth name'],
            ['number' => 3, 'clue' => 'Pulled'],
        ],
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptPublish')
        ->assertSet('incompleteChecks', ['author'])
        ->assertSet('incompleteDetails', ['answers' => [], 'clues' => []])
        ->html();

    expect($html)->not->toContain('data-test="publish-warning-details"');
});

test('publishing anyway clears the incomplete details', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'author' => 'Author',
        'width' => 3,
        'height' => 3,
        'grid' => [[1, 2, 3], [4, 0, 0], [5, 0, 0]],
        'solution' => [['C', 'A', 'T'], ['O', '', 'X'], ['W', '', '']],
        'clues_across' => [['number' => 1, 'clue' => 'Pet']],
        'clues_down' => [['number' => 1, 'clue' => 'Moo']],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptPublish')
        ->assertSet('showPublishWarning', true)
        ->call('togglePublished')
        ->assertSet('isPublished', true)
        ->assertSet('showPublishWarning', false)
        ->assertSet('incompleteChecks', [])
        ->assertSet('incompleteDetails', ['answers' => [], 'clues' => []]);
});
