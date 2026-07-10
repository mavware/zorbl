<?php

use App\Services\AiThemeBuilder;
use Illuminate\Support\Facades\Http;

function fakeThemeToolResponse(array $entries, string $assumptions = ''): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_theme',
                'name' => 'submit_theme_entries',
                'input' => ['entries' => $entries, 'assumptions' => $assumptions],
            ]],
        ]),
    ]);
}

test('returns theme entries parsed from the tool response', function () {
    config(['services.anthropic.key' => 'test-key']);

    fakeThemeToolResponse([
        ['entry' => 'SEA CHANGE', 'length' => 9, 'explanation' => 'Hidden body of water.'],
        ['entry' => 'LAKE EFFECT', 'length' => 9, 'explanation' => 'Category member.'],
    ], assumptions: 'Assumed bodies of water.');

    $result = app(AiThemeBuilder::class)->build('Bodies of water hidden in phrases', wordplayStyle: 'hidden words');

    expect($result['success'])->toBeTrue()
        ->and($result['entries'])->toHaveCount(2)
        ->and($result['entries'][0]['entry'])->toBe('SEA CHANGE')
        ->and($result['entries'][0]['length'])->toBe(9)
        ->and($result['assumptions'])->toBe('Assumed bodies of water.')
        ->and($result['message'])->toContain('2 theme entries');
});

test('sends the theme, wordplay style, and concept to the API', function () {
    config(['services.anthropic.key' => 'test-key']);

    fakeThemeToolResponse([
        ['entry' => 'PUN INTENDED', 'length' => 11, 'explanation' => 'A pun.'],
    ]);

    app(AiThemeBuilder::class)->build(
        'Puns about wordplay',
        theme: 'Wordplay',
        wordplayStyle: 'puns',
    );

    Http::assertSent(function ($request) {
        $content = $request['messages'][0]['content'] ?? '';

        return $request['model'] === 'claude-opus-4-8'
            && str_contains($content, 'Theme: Wordplay')
            && str_contains($content, 'Wordplay style: puns')
            && str_contains($content, 'Concept / prompt: Puns about wordplay');
    });
});

test('derives the letter count when the model omits it', function () {
    config(['services.anthropic.key' => 'test-key']);

    fakeThemeToolResponse([
        ['entry' => 'SEA CHANGE', 'explanation' => 'Hidden body of water.'],
    ]);

    $result = app(AiThemeBuilder::class)->build('Bodies of water');

    // "SEA CHANGE" -> letters only "SEACHANGE" = 9
    expect($result['success'])->toBeTrue()
        ->and($result['entries'][0]['length'])->toBe(9);
});

test('skips the API and reports when no theme is supplied', function () {
    Http::fake();

    $result = app(AiThemeBuilder::class)->build('', theme: '   ');

    expect($result['success'])->toBeFalse()
        ->and($result['entries'])->toBe([])
        ->and($result['message'])->toContain('Describe a theme');

    Http::assertNothingSent();
});

test('reports a helpful message when the API key is missing', function () {
    config(['services.anthropic.key' => null]);

    $result = app(AiThemeBuilder::class)->build('Bodies of water');

    expect($result['success'])->toBeFalse()
        ->and($result['entries'])->toBe([])
        ->and($result['message'])->toContain('API key is not configured');
});

test('fails gracefully when the API returns an error', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response('rate limited', 429),
    ]);

    $result = app(AiThemeBuilder::class)->build('Bodies of water');

    expect($result['success'])->toBeFalse()
        ->and($result['entries'])->toBe([])
        ->and($result['message'])->toContain('AI service returned an error');
});

test('fails when the model returns no usable entries', function () {
    config(['services.anthropic.key' => 'test-key']);

    fakeThemeToolResponse([
        ['entry' => '   ', 'length' => 0, 'explanation' => 'blank'],
        'not-an-object',
    ]);

    $result = app(AiThemeBuilder::class)->build('Bodies of water');

    expect($result['success'])->toBeFalse()
        ->and($result['entries'])->toBe([])
        ->and($result['message'])->toContain('could not generate');
});
