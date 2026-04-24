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

function seedExtraThreeLetterWords(): void
{
    $words = [
        'BAT', 'RAT', 'HAT', 'MAT', 'FAT', 'SAT',
        'OAR', 'EAR', 'ERA', 'ARE', 'BAR', 'CAR',
        'BEE', 'RED', 'ODE', 'BOA', 'ERE', 'ORE',
        'ABC', 'ETA', 'ACT', 'OPT', 'APT', 'OUT',
    ];
    $rows = [];
    foreach ($words as $i => $w) {
        $rows[] = [
            'word' => $w,
            'length' => 3,
            'score' => 60 - $i,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    Word::insert($rows);
}

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
use App\Services\WordSuggester;
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

test('heuristic fill returns gracefully when no fill exists in the dictionary', function () {
    // Wipe the dictionary so all 3 retry attempts in the editor are guaranteed
    // to fail. The call should still return cleanly (not throw or hang).
    Word::query()->delete();

    $user = User::factory()->create();
    $crossword = makeSmallCrossword($user);
    $solution = [['', '', ''], ['', '', ''], ['', '', '']];

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('heuristicFill', $solution)
        ->assertNoRedirect();
});

test('heuristic fill honours the grid passed from the client over server state', function () {
    // The bug: $this->grid is only refreshed on mount/resize, so block edits
    // made in the editor (which live in Alpine state until autosave fires)
    // don't propagate to the server when Quick Fill is invoked. The client
    // now passes the live grid as a parameter — make sure the server uses it.
    $user = User::factory()->create();
    $crossword = makeSmallCrossword($user);

    // Server-side state thinks the grid is fully open. Pass a grid with a
    // block at (1,1) that should change the slot layout.
    $stale = [[1, 2, 3], [4, 0, 0], [5, 0, 0]];
    $live = [[1, 2, 3], [4, '#', 5], [6, 0, 0]];
    $solution = [['', '', ''], ['', '#', ''], ['', '', '']];

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('grid', $stale)
        ->call('heuristicFill', $solution, $live, null)
        ->assertNoRedirect();
});

test('GridFiller seed produces different placements across runs', function () {
    // The editor's 3-retry loop relies on this: different seeds explore the
    // search space differently. With enough candidates, two runs with
    // different seeds should produce different first-slot fills.
    seedExtraThreeLetterWords();

    $numberer = app(GridNumberer::class);
    $numbered = $numberer->number([[0, 0, 0], [0, 0, 0], [0, 0, 0]], 3, 3, [], 3);
    $blank = [['', '', ''], ['', '', ''], ['', '', '']];

    $signatures = collect(range(1, 10))->map(function (int $seed) use ($numbered, $blank) {
        $result = app(GridFiller::class)->fill(
            $numbered['grid'], $blank, 3, 3, [], 3, seed: $seed,
        );

        return collect($result['fills'])->sortBy(['direction', 'number'])->pluck('word')->implode('|');
    })->unique();

    expect($signatures->count())->toBeGreaterThan(1);
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

test('ai fill generates heuristic candidates and asks AI to pick one', function () {
    // Extra words so seeded heuristic runs produce distinct fills, triggering the AI picker.
    seedExtraThreeLetterWords();
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_pick',
                'name' => 'submit_choice',
                'input' => ['choice' => 1, 'reasoning' => 'Most cluable words.'],
            ]],
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
        $tool = $request['tools'][0] ?? null;

        return str_contains($request->url(), 'api.anthropic.com')
            && ($tool['name'] ?? '') === 'submit_choice';
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

test('ai fill service rejects words not in the dictionary', function () {
    config(['services.anthropic.key' => 'test-key']);

    // Mock a tool_use response containing a pattern-matching but nonexistent word.
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_test',
                    'name' => 'submit_fills',
                    'input' => [
                        'fills' => [
                            ['direction' => 'across', 'number' => 1, 'word' => 'RCLET'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $filler = app(AiGridFiller::class);
    $result = $filler->fill(
        [['direction' => 'across', 'number' => 1, 'length' => 5, 'pattern' => '_____']],
        [],
    );

    expect($result['fills'])->toBeEmpty()
        ->and($result['success'])->toBeFalse();
});

test('ai fill service accepts a dictionary word via tool-use', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_ok',
                    'name' => 'submit_fills',
                    'input' => [
                        'fills' => [
                            ['direction' => 'across', 'number' => 1, 'word' => 'CAT'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $filler = app(AiGridFiller::class);
    $result = $filler->fill(
        [['direction' => 'across', 'number' => 1, 'length' => 3, 'pattern' => '___']],
        [],
    );

    expect($result['success'])->toBeTrue()
        ->and($result['fills'])->toHaveCount(1)
        ->and($result['fills'][0]['word'])->toBe('CAT');
});

test('computeIntersections finds crossings in a 3x3 open grid', function () {
    $slots = [
        ['direction' => 'across', 'number' => 1, 'row' => 0, 'col' => 0, 'length' => 3],
        ['direction' => 'across', 'number' => 4, 'row' => 1, 'col' => 0, 'length' => 3],
        ['direction' => 'across', 'number' => 5, 'row' => 2, 'col' => 0, 'length' => 3],
        ['direction' => 'down', 'number' => 1, 'row' => 0, 'col' => 0, 'length' => 3],
        ['direction' => 'down', 'number' => 2, 'row' => 0, 'col' => 1, 'length' => 3],
        ['direction' => 'down', 'number' => 3, 'row' => 0, 'col' => 2, 'length' => 3],
    ];

    $intersections = AiGridFiller::computeIntersections($slots);

    expect($intersections)->toHaveCount(9);

    $hasOneAcrossAtOneDown = collect($intersections)->contains(
        fn ($ix) => $ix['across_number'] === 1
            && $ix['down_number'] === 1
            && $ix['across_pos'] === 1
            && $ix['down_pos'] === 1,
    );

    $hasFourAcrossAtTwoDown = collect($intersections)->contains(
        fn ($ix) => $ix['across_number'] === 4
            && $ix['down_number'] === 2
            && $ix['across_pos'] === 2
            && $ix['down_pos'] === 2,
    );

    expect($hasOneAcrossAtOneDown)->toBeTrue()
        ->and($hasFourAcrossAtTwoDown)->toBeTrue();
});

test('ai fill tool schema uses per-slot enums from candidate lists', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_e',
                'name' => 'submit_fills',
                'input' => ['across_1' => 'CAT'],
            ]],
        ]),
    ]);

    $filler = app(AiGridFiller::class);
    $result = $filler->fill(
        [[
            'direction' => 'across',
            'number' => 1,
            'length' => 3,
            'pattern' => '___',
            'candidates' => ['CAT', 'COT', 'CUT'],
        ]],
        [],
    );

    expect($result['success'])->toBeTrue()
        ->and($result['fills'][0]['word'])->toBe('CAT');

    Http::assertSent(function ($request) {
        $tool = $request['tools'][0] ?? null;
        $props = $tool['input_schema']['properties'] ?? [];
        $enum = $props['across_1']['enum'] ?? null;

        return $tool['name'] === 'submit_fills'
            && $enum === ['CAT', 'COT', 'CUT']
            && in_array('across_1', $tool['input_schema']['required'] ?? [], true);
    });
});

test('ai fill rejects a word that duplicates one already in the grid', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_dup',
                'name' => 'submit_fills',
                'input' => ['across_1' => 'CAT'],
            ]],
        ]),
    ]);

    $filler = app(AiGridFiller::class);
    $result = $filler->fill(
        [['direction' => 'across', 'number' => 1, 'length' => 3, 'pattern' => '___', 'candidates' => ['CAT']]],
        [['direction' => 'down', 'number' => 2, 'word' => 'CAT']],
    );

    expect($result['fills'])->toBeEmpty()
        ->and($result['success'])->toBeFalse();
});

test('ai fill rejects a word duplicated across AI-generated slots', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_dup2',
                'name' => 'submit_fills',
                'input' => [
                    'across_1' => 'SET',
                    'across_2' => 'SET',
                ],
            ]],
        ]),
    ]);

    Word::insert([
        ['word' => 'SET', 'length' => 3, 'score' => 73.0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $filler = app(AiGridFiller::class);
    $result = $filler->fill(
        [
            ['direction' => 'across', 'number' => 1, 'length' => 3, 'pattern' => '___', 'candidates' => ['SET']],
            ['direction' => 'across', 'number' => 2, 'length' => 3, 'pattern' => '___', 'candidates' => ['SET']],
        ],
        [],
    );

    expect($result['fills'])->toHaveCount(1)
        ->and($result['fills'][0]['word'])->toBe('SET');
});

test('WordSuggester minScore filters out low-scoring candidates', function () {
    Word::insert([
        ['word' => 'LOW', 'length' => 3, 'score' => 10.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'MID', 'length' => 3, 'score' => 50.0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $suggester = app(WordSuggester::class);

    $unfiltered = array_column($suggester->suggest('___', 3, 100), 'word');
    $filtered = array_column($suggester->suggest('___', 3, 100, minScore: 25), 'word');

    expect($unfiltered)->toContain('LOW')
        ->and($filtered)->not->toContain('LOW')
        ->and($filtered)->toContain('MID');
});

test('ai fill picker receives numbered candidate fills and theme', function () {
    seedExtraThreeLetterWords();
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_pick2',
                'name' => 'submit_choice',
                'input' => ['choice' => 1, 'reasoning' => 'Best on theme.'],
            ]],
        ]),
    ]);

    $user = createProUserForAutofill();
    $crossword = makeSmallCrossword($user);
    $crossword->update(['title' => 'Feline Friends', 'notes' => 'Words related to cats']);

    $solution = [['', '', ''], ['', '', ''], ['', '', '']];

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('aiFill', $solution);

    Http::assertSent(function ($request) {
        $userContent = $request['messages'][0]['content'] ?? '';
        $tool = $request['tools'][0] ?? null;

        return str_contains($userContent, 'Puzzle title: Feline Friends')
            && str_contains($userContent, 'Candidate #1:')
            && str_contains($userContent, 'Candidate #2:')
            && ($tool['name'] ?? '') === 'submit_choice'
            && ($tool['input_schema']['properties']['choice']['type'] ?? '') === 'integer';
    });
});

test('ai fill refuses to run when title and secret theme are both empty', function () {
    Http::fake();
    config(['services.anthropic.key' => 'test-key']);

    $user = createProUserForAutofill();
    $crossword = makeSmallCrossword($user);
    $crossword->update(['title' => '', 'notes' => 'some notes', 'secret_theme' => null]);

    $solution = [['', '', ''], ['', '', ''], ['', '', '']];

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('aiFill', $solution);

    Http::assertNothingSent();
});

test('ai fill forwards the secret theme to the picker', function () {
    seedExtraThreeLetterWords();
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_secret',
                'name' => 'submit_choice',
                'input' => ['choice' => 1, 'reasoning' => 'Best fit.'],
            ]],
        ]),
    ]);

    $user = createProUserForAutofill();
    $crossword = makeSmallCrossword($user);
    $crossword->update([
        'title' => 'Public Title',
        'notes' => 'Public notes',
        'secret_theme' => 'Hidden: words about cats',
    ]);

    $solution = [['', '', ''], ['', '', ''], ['', '', '']];

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('aiFill', $solution);

    Http::assertSent(function ($request) {
        $content = $request['messages'][0]['content'] ?? '';

        return str_contains($content, 'Secret theme (highest priority): Hidden: words about cats')
            && str_contains($content, 'Puzzle title: Public Title')
            && str_contains($content, 'Puzzle notes: Public notes');
    });
});

test('saveMetadata persists and clears the secret theme', function () {
    $user = User::factory()->create();
    $crossword = makeSmallCrossword($user);

    $component = Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('secretTheme', 'Secret Beatles references')
        ->call('saveMetadata');

    $component->assertSet('secretTheme', 'Secret Beatles references');
    expect($crossword->fresh()->secret_theme)->toBe('Secret Beatles references');

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword->fresh()])
        ->set('secretTheme', '')
        ->call('saveMetadata');

    expect($crossword->fresh()->secret_theme)->toBeNull();
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

test('free user ai fill is blocked and does not call api', function () {
    Http::fake();

    $user = User::factory()->create();
    $crossword = makeSmallCrossword($user);

    $solution = [['', '', ''], ['', '', ''], ['', '', '']];

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('aiFill', $solution)
        ->assertNoRedirect();

    Http::assertNothingSent();
});

test('free user ai generate clues is blocked and does not call api', function () {
    Http::fake();

    $user = User::factory()->create();
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

    Http::assertNothingSent();
});

test('editor has upgrade modal properties', function () {
    $user = User::factory()->create();
    $crossword = makeSmallCrossword($user);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('showUpgradeModal', false)
        ->assertSet('upgradeFeature', '')
        ->set('upgradeFeature', 'grid_fill')
        ->set('showUpgradeModal', true)
        ->assertSet('showUpgradeModal', true)
        ->assertSet('upgradeFeature', 'grid_fill');
});
