<?php

use App\Models\Crossword;
use App\Models\User;
use App\Models\Word;
use App\Services\AiClueGenerator;
use App\Services\AiGridFiller;
use App\Services\GridFiller;
use Illuminate\Support\Facades\Http;
use Laravel\Cashier\Subscription;
use Zorbl\CrosswordIO\GridNumberer;

function createProUserForAutofill(): User
{
    $user = User::factory()->create(['stripe_id' => 'cus_test_'.uniqid()]);
    Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_fake',
    ]);

    return $user;
}
use Livewire\Livewire;

beforeEach(function () {
    Word::insert([
        ['word' => 'CAT', 'length' => 3, 'score' => 60.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'COT', 'length' => 3, 'score' => 55.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'CUT', 'length' => 3, 'score' => 50.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'ACE', 'length' => 3, 'score' => 65.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'ATE', 'length' => 3, 'score' => 58.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'TOE', 'length' => 3, 'score' => 52.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'TEA', 'length' => 3, 'score' => 62.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'TAP', 'length' => 3, 'score' => 48.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'APE', 'length' => 3, 'score' => 45.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'OAT', 'length' => 3, 'score' => 44.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'PEA', 'length' => 3, 'score' => 42.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'OPE', 'length' => 3, 'score' => 30.0, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

function makeSmallCrossword(User $user): Crossword
{
    $numberer = app(GridNumberer::class);

    $rawGrid = [
        [0, 0, 0],
        [0, 0, 0],
        [0, 0, 0],
    ];

    $numbered = $numberer->number($rawGrid, 3, 3, [], 3);

    return Crossword::factory()->for($user)->create([
        'width' => 3,
        'height' => 3,
        'grid' => $numbered['grid'],
        'solution' => [
            ['', '', ''],
            ['', '', ''],
            ['', '', ''],
        ],
    ]);
}

test('heuristic fill can be called from editor', function () {
    $user = User::factory()->create();
    $crossword = makeSmallCrossword($user);

    $solution = [['', '', ''], ['', '', ''], ['', '', '']];

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('heuristicFill', $solution)
        ->assertNoRedirect();
});

test('heuristic fill service fills a simple grid', function () {
    $numberer = app(GridNumberer::class);
    $rawGrid = [[0, 0, 0], [0, 0, 0], [0, 0, 0]];
    $numbered = $numberer->number($rawGrid, 3, 3, [], 3);

    $filler = app(GridFiller::class);
    $result = $filler->fill(
        $numbered['grid'],
        [['', '', ''], ['', '', ''], ['', '', '']],
        3, 3, [], 3,
    );

    expect($result['success'])->toBeTrue()
        ->and($result['fills'])->not->toBeEmpty();

    foreach ($result['fills'] as $fill) {
        expect(strlen($fill['word']))->toBe(3)
            ->and($fill['direction'])->toBeIn(['across', 'down']);
    }
});

test('non-owner cannot use heuristic fill', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $crossword = makeSmallCrossword($owner);

    Livewire::actingAs($other)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertForbidden();
});

test('ai fill calls anthropic api', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                [
                    'type' => 'text',
                    'text' => '[{"direction":"across","number":1,"word":"CAT"},{"direction":"down","number":1,"word":"COT"}]',
                ],
            ],
        ]),
    ]);

    $user = createProUserForAutofill();
    $crossword = makeSmallCrossword($user);

    $solution = [['', '', ''], ['', '', ''], ['', '', '']];

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('aiFill', $solution)
        ->assertNoRedirect();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.anthropic.com');
    });
});

test('ai fill service returns error when api key is missing', function () {
    config(['services.anthropic.key' => null]);

    $filler = app(AiGridFiller::class);
    $result = $filler->fill(
        [['direction' => 'across', 'number' => 1, 'length' => 3, 'pattern' => '___']],
        [],
    );

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('API key');
});

test('ai fill service validates response against patterns', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                [
                    'type' => 'text',
                    'text' => '[{"direction":"across","number":1,"word":"CAT"},{"direction":"across","number":2,"word":"TOOLONG"}]',
                ],
            ],
        ]),
    ]);

    $filler = app(AiGridFiller::class);
    $result = $filler->fill(
        [
            ['direction' => 'across', 'number' => 1, 'length' => 3, 'pattern' => 'C__'],
            ['direction' => 'across', 'number' => 2, 'length' => 3, 'pattern' => '___'],
        ],
        [],
    );

    // CAT matches pattern C__, TOOLONG doesn't match length 3
    expect($result['fills'])->toHaveCount(1)
        ->and($result['fills'][0]['word'])->toBe('CAT');
});

test('ai generate clues calls anthropic api', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                [
                    'type' => 'text',
                    'text' => '{"across":{"1":"Feline pet"},"down":{"1":"Baby bed"}}',
                ],
            ],
        ]),
    ]);

    $user = createProUserForAutofill();
    $crossword = makeSmallCrossword($user);

    $solution = [
        ['C', 'A', 'T'],
        ['O', 'T', 'E'],
        ['T', 'E', 'A'],
    ];

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('aiGenerateClues', $solution)
        ->assertNoRedirect();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.anthropic.com');
    });
});

test('ai clue generator returns error when api key is missing', function () {
    config(['services.anthropic.key' => null]);

    $generator = app(AiClueGenerator::class);
    $result = $generator->generate(
        [['direction' => 'across', 'number' => 1, 'word' => 'CAT']],
    );

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('API key');
});

test('ai clue generator parses valid response', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                [
                    'type' => 'text',
                    'text' => '{"across":{"1":"Feline pet","4":"Bed type"},"down":{"1":"Warm drink","2":"Consumed food"}}',
                ],
            ],
        ]),
    ]);

    $generator = app(AiClueGenerator::class);
    $result = $generator->generate([
        ['direction' => 'across', 'number' => 1, 'word' => 'CAT'],
        ['direction' => 'across', 'number' => 4, 'word' => 'COT'],
        ['direction' => 'down', 'number' => 1, 'word' => 'TEA'],
        ['direction' => 'down', 'number' => 2, 'word' => 'ATE'],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['clues']['across'])->toHaveCount(2)
        ->and($result['clues']['down'])->toHaveCount(2)
        ->and($result['clues']['across'][1])->toBe('Feline pet');
});
