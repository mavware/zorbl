<?php

use App\Models\Word;
use App\Services\GridFiller;

beforeEach(function () {
    // Seed a small test word list for a simple 3x3 grid
    Word::insert([
        ['word' => 'CAT', 'length' => 3, 'score' => 60.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'COT', 'length' => 3, 'score' => 55.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'CUT', 'length' => 3, 'score' => 50.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'ACE', 'length' => 3, 'score' => 65.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'ATE', 'length' => 3, 'score' => 58.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'TOE', 'length' => 3, 'score' => 52.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'TAP', 'length' => 3, 'score' => 48.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'APE', 'length' => 3, 'score' => 45.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'OAT', 'length' => 3, 'score' => 44.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'OPE', 'length' => 3, 'score' => 30.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'TEA', 'length' => 3, 'score' => 62.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'PEA', 'length' => 3, 'score' => 42.0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Every slot must spell a dictionary word, including ones completed by
    // crossings, so the list needs enough words for real 3x3 word squares.
    Word::insert(array_map(fn (string $word, int $i): array => [
        'word' => $word,
        'length' => 3,
        'score' => 40 - $i,
        'created_at' => now(),
        'updated_at' => now(),
    ], GRID_FILLER_EXTRA_WORDS, array_keys(GRID_FILLER_EXTRA_WORDS)));
});

const GRID_FILLER_EXTRA_WORDS = [
    'EAT', 'ONE', 'TEN', 'NET', 'PET', 'PEN', 'TAR', 'ART', 'SEA',
    'TIE', 'TIN', 'ANT', 'TAN', 'NAP', 'PAN', 'PAT', 'TOP', 'POT', 'ROT',
    'ROE', 'COD', 'DEN',
];

it('never leaves a slot spelling a non-word, even one completed by crossings', function () {
    Word::query()->delete();

    // The only way to fill the top row and both outer columns is CAT / COT / TEA,
    // which would force the un-chosen middle slots to spell "OPE"/"ATE"-style
    // leftovers that are missing from this deliberately thin dictionary.
    Word::insert(array_map(fn (string $word): array => [
        'word' => $word, 'length' => 3, 'score' => 50.0, 'created_at' => now(), 'updated_at' => now(),
    ], ['CAT', 'COT', 'TEA', 'APE', 'TOE', 'ATE']));

    $filler = app(GridFiller::class);
    $grid = [[1, 2, 3], [4, 0, 0], [5, 0, 0]];
    $empty = [['', '', ''], ['', '', ''], ['', '', '']];

    $result = $filler->fill($grid, $empty, 3, 3, [], 3);

    expect($result['success'])->toBeFalse();

    $validWords = Word::pluck('word')->all();
    foreach ($result['fills'] as $fill) {
        expect($fill['word'])->toBeIn($validWords);
    }
});

it('reports words completed by crossings as fills and keeps them unique', function () {
    $filler = app(GridFiller::class);
    $grid = [[1, 2, 3], [4, 0, 0], [5, 0, 0]];
    $empty = [['', '', ''], ['', '', ''], ['', '', '']];

    $result = $filler->fill($grid, $empty, 3, 3, [], 3);

    expect($result['success'])->toBeTrue()
        ->and($result['fills'])->toHaveCount(6);

    $validWords = Word::pluck('word')->all();
    $words = array_column($result['fills'], 'word');

    expect($words)->toHaveCount(count(array_unique($words)));
    foreach ($words as $word) {
        expect($word)->toBeIn($validWords);
    }
});

it('fills a simple grid with valid words', function () {
    $filler = app(GridFiller::class);

    // 3x3 open grid — all cells are white
    $grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];

    $solution = [
        ['', '', ''],
        ['', '', ''],
        ['', '', ''],
    ];

    $result = $filler->fill($grid, $solution, 3, 3, [], 3);

    expect($result['success'])->toBeTrue()
        ->and($result['fills'])->not->toBeEmpty();

    // All filled words should be 3 letters
    foreach ($result['fills'] as $fill) {
        expect(strlen($fill['word']))->toBe(3)
            ->and($fill['direction'])->toBeIn(['across', 'down']);
    }
});

it('respects pre-filled letters', function () {
    $filler = app(GridFiller::class);

    $grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];

    // Pre-fill first row with CAT
    $solution = [
        ['C', 'A', 'T'],
        ['', '', ''],
        ['', '', ''],
    ];

    $result = $filler->fill($grid, $solution, 3, 3, [], 3);

    // Fills should not include 1 across since it's already filled
    $acrossFills = collect($result['fills'])->where('direction', 'across')->where('number', 1);
    expect($acrossFills)->toBeEmpty();

    // Down words should start with C, A, T respectively
    foreach ($result['fills'] as $fill) {
        if ($fill['direction'] === 'down') {
            if ($fill['number'] === 1) {
                expect($fill['word'][0])->toBe('C');
            } elseif ($fill['number'] === 2) {
                expect($fill['word'][0])->toBe('A');
            } elseif ($fill['number'] === 3) {
                expect($fill['word'][0])->toBe('T');
            }
        }
    }
});

it('returns success false for impossible grid', function () {
    $filler = app(GridFiller::class);

    // Grid where pre-filled letters make it impossible
    $grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];

    // ZZZ is not a word and forces impossible crossing constraints
    $solution = [
        ['Z', 'Z', 'Z'],
        ['', '', ''],
        ['', '', ''],
    ];

    $result = $filler->fill($grid, $solution, 3, 3, [], 3);

    expect($result['success'])->toBeFalse();
});

it('returns already filled message when grid is complete', function () {
    $filler = app(GridFiller::class);

    $grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];

    $solution = [
        ['C', 'A', 'T'],
        ['O', 'T', 'E'],
        ['T', 'E', 'A'],
    ];

    $result = $filler->fill($grid, $solution, 3, 3, [], 3);

    expect($result['success'])->toBeTrue()
        ->and($result['fills'])->toBeEmpty()
        ->and($result['message'])->toContain('already fully filled');
});

it('does not use the same word twice', function () {
    $filler = app(GridFiller::class);

    // 3x3 open grid
    $grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];

    $solution = [
        ['', '', ''],
        ['', '', ''],
        ['', '', ''],
    ];

    $result = $filler->fill($grid, $solution, 3, 3, [], 3);

    expect($result['success'])->toBeTrue();

    $words = array_map(fn ($fill) => $fill['word'], $result['fills']);
    expect($words)->toHaveCount(count(array_unique($words)));
});

it('does not repeat a pre-filled word', function () {
    $filler = app(GridFiller::class);

    $grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];

    // Pre-fill first row with CAT
    $solution = [
        ['C', 'A', 'T'],
        ['', '', ''],
        ['', '', ''],
    ];

    $result = $filler->fill($grid, $solution, 3, 3, [], 3);

    expect($result['success'])->toBeTrue();

    // None of the filled words should be CAT since it's already in the grid
    $filledWords = array_map(fn ($fill) => $fill['word'], $result['fills']);
    expect($filledWords)->not->toContain('CAT');
});

it('handles grid with blocks', function () {
    $filler = app(GridFiller::class);

    // 3x3 grid with a block in center
    $grid = [
        [1, 0, '#'],
        [0, '#', 2],
        ['#', 3, 0],
    ];

    $solution = [
        ['', '', '#'],
        ['', '#', ''],
        ['#', '', ''],
    ];

    // With a block pattern, slots may be too short (length < 3)
    // This verifies it doesn't crash
    $result = $filler->fill($grid, $solution, 3, 3, [], 2);

    expect($result)->toHaveKeys(['success', 'fills', 'message']);
});

it('produces varied fills for different seeds', function () {
    // Add enough 3-letter words that distinct valid fillings exist.
    Word::insert([
        ['word' => 'BAT', 'length' => 3, 'score' => 60.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'RAT', 'length' => 3, 'score' => 55.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'EAR', 'length' => 3, 'score' => 58.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'ARE', 'length' => 3, 'score' => 60.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'BAR', 'length' => 3, 'score' => 58.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'CAR', 'length' => 3, 'score' => 60.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'ODE', 'length' => 3, 'score' => 48.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'BOA', 'length' => 3, 'score' => 45.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'RED', 'length' => 3, 'score' => 58.0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $filler = app(GridFiller::class);
    $grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];
    $empty = [['', '', ''], ['', '', ''], ['', '', '']];

    $signatures = [];
    foreach (range(1, 6) as $seed) {
        $result = $filler->fill($grid, $empty, 3, 3, [], 3, seed: $seed);
        if ($result['success']) {
            $words = array_map(fn ($f) => $f['direction'].$f['number'].'='.$f['word'], $result['fills']);
            sort($words);
            $signatures[implode('|', $words)] = true;
        }
    }

    expect(count($signatures))->toBeGreaterThan(1);
});

it('same seed produces the same fill', function () {
    $filler = app(GridFiller::class);
    $grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];
    $empty = [['', '', ''], ['', '', ''], ['', '', '']];

    $first = $filler->fill($grid, $empty, 3, 3, [], 3, seed: 42);
    $second = $filler->fill($grid, $empty, 3, 3, [], 3, seed: 42);

    expect($first['success'])->toBeTrue()
        ->and($second['success'])->toBeTrue()
        ->and($first['fills'])->toEqual($second['fills']);
});

it('reports a timeout instead of searching once the deadline has passed', function () {
    $filler = app(GridFiller::class);
    $grid = [[1, 2, 3], [4, 0, 0], [5, 0, 0]];
    $empty = [['', '', ''], ['', '', ''], ['', '', '']];

    $result = $filler->fill($grid, $empty, 3, 3, [], 3, timeout: 0);

    expect($result['success'])->toBeFalse()
        ->and($result['timed_out'])->toBeTrue()
        ->and($result['fills'])->toBe([])
        ->and($result['message'])->toContain('Ran out of time');
});

it('flags a successful fill as not timed out', function () {
    $filler = app(GridFiller::class);
    $grid = [[1, 2, 3], [4, 0, 0], [5, 0, 0]];
    $empty = [['', '', ''], ['', '', ''], ['', '', '']];

    $result = $filler->fill($grid, $empty, 3, 3, [], 3);

    expect($result['success'])->toBeTrue()
        ->and($result['timed_out'])->toBeFalse();
});

it('returns promptly after the timeout on a grid with a huge, unsolvable search space', function () {
    Word::query()->delete();

    // Random 5-letter strings over a small alphabet, so every partial pattern
    // has candidates and the solver keeps descending. Position 3 is always one
    // of ABC and position 4 one of DEF, so the cell shared by 4-Across (pos 4)
    // and 5-Down (pos 3) can never agree: no complete fill exists, and the
    // solver only discovers that after exhausting a tree of tens of thousands
    // of placements. Without a hard stop this search runs for minutes.
    mt_srand(42);
    $words = [];
    while (count($words) < 400) {
        $word = '';
        for ($i = 0; $i < 3; $i++) {
            $word .= 'ABCDEF'[mt_rand(0, 5)];
        }
        $word .= 'ABC'[mt_rand(0, 2)].'DEF'[mt_rand(0, 2)];
        $words[$word] = true;
    }
    Word::insert(array_map(fn (string $word): array => [
        'word' => $word, 'length' => 5, 'score' => 50.0, 'created_at' => now(), 'updated_at' => now(),
    ], array_keys($words)));

    $grid = [
        [1, 2, 3, 4, 5],
        [6, 0, 0, 0, 0],
        [7, 0, 0, 0, 0],
        [8, 0, 0, 0, 0],
        [9, 0, 0, 0, 0],
    ];
    $empty = array_fill(0, 5, array_fill(0, 5, ''));

    // Some slot orders stumble on the contradiction within a few hundred placements and finish
    // early, so the seed is one whose order explores the big tree and has to be stopped.
    $start = microtime(true);
    $result = app(GridFiller::class)->fill($grid, $empty, 5, 5, [], 3, timeout: 1, seed: 3);
    $elapsed = microtime(true) - $start;

    expect($result['success'])->toBeFalse()
        ->and($result['timed_out'])->toBeTrue()
        ->and($elapsed)->toBeLessThan(2.5);
});

it('tries acceptable words before poor ones whatever the seed', function () {
    Word::query()->delete();

    $rows = [['word' => 'ACE', 'length' => 3, 'score' => GridFiller::QUALITY_THRESHOLD, 'created_at' => now(), 'updated_at' => now()]];
    foreach (['ZAX', 'QIS', 'XIS', 'JUS', 'KEX', 'ZZZ', 'QQQ', 'XXX', 'JJJ', 'KKK', 'VAV', 'WUZ'] as $poor) {
        $rows[] = ['word' => $poor, 'length' => 3, 'score' => Word::SCORE_UNVETTED, 'created_at' => now(), 'updated_at' => now()];
    }
    Word::insert($rows);

    $filler = app(GridFiller::class);

    // A single across slot with no crossings, so the first candidate always wins.
    $grid = [[1, 0, 0]];
    $empty = [['', '', '']];

    foreach (range(1, 15) as $seed) {
        $result = $filler->fill($grid, $empty, 3, 1, [], 3, seed: $seed);

        expect($result['success'])->toBeTrue()
            ->and($result['fills'])->toBe([['direction' => 'across', 'number' => 1, 'word' => 'ACE']])
            ->and($result['score'])->toBe(GridFiller::QUALITY_THRESHOLD);
    }
});

it('still places poor words when nothing acceptable fits', function () {
    Word::query()->delete();
    Word::insert([
        ['word' => 'ZAX', 'length' => 3, 'score' => Word::SCORE_UNVETTED, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $result = app(GridFiller::class)->fill([[1, 0, 0]], [['', '', '']], 3, 1, [], 3, seed: 7);

    expect($result['success'])->toBeTrue()
        ->and($result['fills'])->toBe([['direction' => 'across', 'number' => 1, 'word' => 'ZAX']])
        ->and($result['score'])->toBe(Word::SCORE_UNVETTED);
});

it('favors higher-scoring words within the acceptable tier without fixing the order', function () {
    Word::query()->delete();
    Word::insert([
        ['word' => 'ACE', 'length' => 3, 'score' => 55.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'ORT', 'length' => 3, 'score' => 40.0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $filler = app(GridFiller::class);
    $picked = ['ACE' => 0, 'ORT' => 0];

    foreach (range(1, 60) as $seed) {
        $result = $filler->fill([[1, 0, 0]], [['', '', '']], 3, 1, [], 3, seed: $seed);
        $picked[$result['fills'][0]['word']]++;
    }

    // ACE is e^1.5 ≈ 4.5 times as likely as ORT, so both appear but ACE clearly more often.
    expect($picked['ORT'])->toBeGreaterThan(0)
        ->and($picked['ACE'])->toBeGreaterThan($picked['ORT'] * 2);
});

it('reports the average score of the words it placed', function () {
    $result = app(GridFiller::class)->fill([[1, 2, 3], [4, 0, 0], [5, 0, 0]], [['', '', ''], ['', '', ''], ['', '', '']], 3, 3, [], 3, seed: 3);

    expect($result['success'])->toBeTrue();

    $words = array_column($result['fills'], 'word');
    $expected = round(Word::whereIn('word', $words)->sum('score') / count($words), 1);

    expect($result['score'])->toBe($expected);
});

it('picks the highest-scored candidate first when unseeded', function () {
    Word::query()->delete();
    Word::insert([
        ['word' => 'ORT', 'length' => 3, 'score' => 40.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'ACE', 'length' => 3, 'score' => 55.0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $result = app(GridFiller::class)->fill([[1, 0, 0]], [['', '', '']], 3, 1, [], 3);

    expect($result['fills'][0]['word'])->toBe('ACE');
});

it('uses only words at or above the score floor, including words completed by crossings', function () {
    Word::query()->delete();
    Word::insert([
        ['word' => 'ACE', 'length' => 3, 'score' => 55.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'ZAX', 'length' => 3, 'score' => Word::SCORE_UNVETTED, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $filler = app(GridFiller::class);

    $strict = $filler->fill([[1, 0, 0]], [['Z', '', '']], 3, 1, [], 3, seed: 1, minScore: GridFiller::QUALITY_THRESHOLD);
    $relaxed = $filler->fill([[1, 0, 0]], [['Z', '', '']], 3, 1, [], 3, seed: 1);

    expect($strict['success'])->toBeFalse()
        ->and($strict['fills'])->toBe([])
        ->and($relaxed['success'])->toBeTrue()
        ->and($relaxed['fills'][0]['word'])->toBe('ZAX');
});
