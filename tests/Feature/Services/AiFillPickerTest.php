<?php

use App\Services\AiFillPicker;
use Illuminate\Support\Facades\Http;

test('returns first candidate and skips API when only one fill is supplied', function () {
    Http::fake();

    $picker = app(AiFillPicker::class);

    $result = $picker->pick([
        [['direction' => 'across', 'number' => 1, 'word' => 'CAT']],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['index'])->toBe(0);

    Http::assertNothingSent();
});

test('returns the candidate chosen by the AI', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_choice',
                'name' => 'submit_choice',
                'input' => ['choice' => 2, 'reasoning' => 'More cluable words.'],
            ]],
        ]),
    ]);

    $candidates = [
        [['direction' => 'across', 'number' => 1, 'word' => 'CAT']],
        [['direction' => 'across', 'number' => 1, 'word' => 'DOG']],
        [['direction' => 'across', 'number' => 1, 'word' => 'OWL']],
    ];

    $picker = app(AiFillPicker::class);
    $result = $picker->pick($candidates, title: 'Animals', notes: '');

    expect($result['success'])->toBeTrue()
        ->and($result['index'])->toBe(1)
        ->and($result['message'])->toContain('More cluable words.');
});

test('falls back to first candidate when AI returns an out-of-range choice', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_bad',
                'name' => 'submit_choice',
                'input' => ['choice' => 99, 'reasoning' => 'n/a'],
            ]],
        ]),
    ]);

    $picker = app(AiFillPicker::class);
    $result = $picker->pick([
        [['direction' => 'across', 'number' => 1, 'word' => 'CAT']],
        [['direction' => 'across', 'number' => 1, 'word' => 'DOG']],
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['index'])->toBe(0);
});

test('falls back when the API key is missing', function () {
    config(['services.anthropic.key' => null]);

    $picker = app(AiFillPicker::class);
    $result = $picker->pick([
        [['direction' => 'across', 'number' => 1, 'word' => 'CAT']],
        [['direction' => 'across', 'number' => 1, 'word' => 'DOG']],
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['index'])->toBe(0);
});

test('sends the secret theme labeled as highest priority', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_sec',
                'name' => 'submit_choice',
                'input' => ['choice' => 1, 'reasoning' => 'ok'],
            ]],
        ]),
    ]);

    app(AiFillPicker::class)->pick(
        [
            [['direction' => 'across', 'number' => 1, 'word' => 'CAT']],
            [['direction' => 'across', 'number' => 1, 'word' => 'DOG']],
        ],
        title: 'Public Title',
        notes: 'Public notes',
        pinnedWords: [],
        secretTheme: 'Musical instruments',
    );

    Http::assertSent(function ($request) {
        $content = $request['messages'][0]['content'] ?? '';

        return str_contains($content, 'Secret theme (highest priority): Musical instruments')
            && strpos($content, 'Secret theme') < strpos($content, 'Puzzle title:');
    });
});

test('sends title, notes, and every candidate to the API', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_ok',
                'name' => 'submit_choice',
                'input' => ['choice' => 1, 'reasoning' => 'ok'],
            ]],
        ]),
    ]);

    $picker = app(AiFillPicker::class);
    $picker->pick(
        [
            [['direction' => 'across', 'number' => 1, 'word' => 'CAT']],
            [['direction' => 'across', 'number' => 1, 'word' => 'DOG']],
        ],
        title: 'Pets',
        notes: 'Household animals',
        pinnedWords: [['direction' => 'down', 'number' => 1, 'word' => 'TAP']],
    );

    Http::assertSent(function ($request) {
        $content = $request['messages'][0]['content'] ?? '';

        return str_contains($content, 'Puzzle title: Pets')
            && str_contains($content, 'Puzzle notes: Household animals')
            && str_contains($content, 'Candidate #1:')
            && str_contains($content, 'Candidate #2:')
            && str_contains($content, 'CAT')
            && str_contains($content, 'DOG')
            && str_contains($content, 'TAP');
    });
});
