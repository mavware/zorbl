<?php

use App\Services\ClueQualityChecker;

beforeEach(function () {
    $this->checker = new ClueQualityChecker;
});

/**
 * @return list<string>
 */
function issueCodes(array $issues): array
{
    return array_column($issues, 'code');
}

test('a clean clue has no issues', function () {
    expect($this->checker->check('Feline pet', 'CAT'))->toBe([]);
});

test('an empty clue has no issues', function () {
    expect($this->checker->check('   ', 'CAT'))->toBe([]);
});

test('a clue containing the whole answer is an error', function (string $clue, string $answer) {
    $issues = $this->checker->check($clue, $answer);

    expect(issueCodes($issues))->toContain('answer_in_clue')
        ->and(collect($issues)->firstWhere('code', 'answer_in_clue')['severity'])->toBe(ClueQualityChecker::SEVERITY_ERROR);
})->with([
    'single word' => ['Cat nap spot', 'CAT'],
    'different case' => ['A big CAT', 'cat'],
    'multi-word answer across clue words' => ['Ice cream flavor', 'ICECREAM'],
    'answer with spaces' => ['Scoop of ice cream', 'ICE CREAM'],
]);

test('a clue word sharing the answer root is a warning', function (string $clue, string $answer) {
    $issues = $this->checker->check($clue, $answer);

    expect(issueCodes($issues))->toContain('answer_root_in_clue')
        ->and(collect($issues)->firstWhere('code', 'answer_root_in_clue')['severity'])->toBe(ClueQualityChecker::SEVERITY_WARNING);
})->with([
    'agent noun' => ["Runner's goal", 'RUN'],
    'plural' => ['Several runs', 'RUN'],
    'progressive' => ['Baking, e.g.', 'BAKE'],
    'agent noun for a gerund answer' => ['What a singer does', 'SINGING'],
]);

test('a clue word forming part of a compound answer is a warning', function (string $clue, string $answer) {
    expect(issueCodes($this->checker->check($clue, $answer)))->toContain('partial_answer_in_clue');
})->with([
    'clue word inside answer' => ['Tall garden flower', 'SUNFLOWER'],
    'answer inside clue word' => ['Baseball need', 'BALL'],
]);

test('unrelated words that merely contain the answer letters are not flagged', function (string $clue, string $answer) {
    expect($this->checker->check($clue, $answer))->toBe([]);
})->with([
    'era inside generation' => ['Generation', 'ERA'],
    'art inside start' => ['Fresh start', 'ART'],
    'team inside steamed' => ['Steamed dumpling', 'TEAM'],
    'stopword inside answer' => ['Lacking', 'WITHOUT'],
]);

test('clues that depend on other clues are flagged', function (string $clue, string $reference) {
    $issues = $this->checker->check($clue, 'OREO');

    expect(issueCodes($issues))->toContain('cross_reference')
        ->and(collect($issues)->firstWhere('code', 'cross_reference')['message'])->toContain($reference);
})->with([
    'with 22 across' => ['Goes with 22 Across', '22-Across'],
    'hyphenated down' => ['See 17-Down', '17-Down'],
    'lowercase' => ['Partner of 5 across', '5-Across'],
    'starred clues' => ['What the starred clues have in common', 'starred clues'],
]);

test('a 3D reference is not treated as a clue reference', function () {
    expect(issueCodes($this->checker->check('3D movie accessory', 'GLASSES')))->not->toContain('cross_reference');
});

test('placeholder clues are flagged', function (string $clue) {
    expect(issueCodes($this->checker->check($clue, 'OREO')))->toBe(['placeholder']);
})->with(['TODO', 'tbd', '???', 'xxx', 'Clue', 'Fix this TODO']);

test('very short and very long clues are flagged', function () {
    expect(issueCodes($this->checker->check('No', 'NAY')))->toBe(['too_short'])
        ->and(issueCodes($this->checker->check(str_repeat('word ', 30), 'NAY')))->toBe(['too_long']);
});

test('unbalanced quotes and brackets are flagged', function (string $clue) {
    expect(issueCodes($this->checker->check($clue, 'OREO')))->toBe(['unbalanced_punctuation']);
})->with([
    'open paren' => ['Cookie (brand'],
    'odd quotes' => ['"Twist and shout cookie'],
    'curly quotes' => ['“Milk’s favorite cookie'],
]);

test('balanced punctuation is fine', function () {
    expect($this->checker->check('"Milk\'s favorite cookie" (brand)', 'OREO'))->toBe([]);
});

test('checking a puzzle flags duplicates and keys results by slot', function () {
    $results = $this->checker->checkPuzzle([
        ['direction' => 'across', 'number' => 1, 'clue' => 'Sandwich cookie', 'answer' => 'OREO'],
        ['direction' => 'down', 'number' => 3, 'clue' => 'sandwich cookie ', 'answer' => 'HYDROX'],
        ['direction' => 'down', 'number' => 4, 'clue' => 'Feline pet', 'answer' => 'CAT'],
        ['direction' => 'down', 'number' => 5, 'clue' => '', 'answer' => 'DOG'],
    ]);

    expect(array_keys($results))->toBe(['across-1', 'down-3'])
        ->and(issueCodes($results['across-1']))->toBe(['duplicate'])
        ->and(issueCodes($results['down-3']))->toBe(['duplicate']);
});

test('checking a puzzle works for unfilled answers', function () {
    $results = $this->checker->checkPuzzle([
        ['direction' => 'across', 'number' => 1, 'clue' => 'See 5-Down', 'answer' => null],
    ]);

    expect(issueCodes($results['across-1']))->toBe(['cross_reference']);
});
