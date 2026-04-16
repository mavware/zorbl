<?php

use Database\Seeders\ActivitySeeder;
use Zorbl\CrosswordIO\GridNumberer;

it('parses both across and down clues from xd files with separate sections', function () {
    // 3x3 grid: numbering is 1(across+down), 2(down), 3(across)
    $xdContent = <<<'XD'
Title: Test Puzzle
Author: Test Author

ABC
D#E
FGH

A1. First across clue ~ ABC
A3. Third across clue ~ FGH

D1. First down clue ~ ADF
D2. Second down clue ~ CEH
XD;

    $seeder = new ActivitySeeder;
    $numberer = new GridNumberer;

    $method = new ReflectionMethod($seeder, 'parseXdFile');
    $result = $method->invoke($seeder, $xdContent, $numberer);

    expect($result)->not->toBeNull();

    $acrossClues = collect($result['clues_across'])->pluck('clue', 'number')->all();
    $downClues = collect($result['clues_down'])->pluck('clue', 'number')->all();

    expect($acrossClues)->toBe([
        1 => 'First across clue',
        3 => 'Third across clue',
    ]);

    expect($downClues)->toBe([
        1 => 'First down clue',
        2 => 'Second down clue',
    ]);
});

it('parses clues when across and down are in the same section', function () {
    $xdContent = <<<'XD'
Title: Test Puzzle
Author: Test Author

ABC
D#E
FGH

A1. First across clue ~ ABC
A3. Third across clue ~ FGH
D1. First down clue ~ ADF
D2. Second down clue ~ CEH
XD;

    $seeder = new ActivitySeeder;
    $numberer = new GridNumberer;

    $method = new ReflectionMethod($seeder, 'parseXdFile');
    $result = $method->invoke($seeder, $xdContent, $numberer);

    expect($result)->not->toBeNull();

    $downClues = collect($result['clues_down'])->pluck('clue', 'number')->all();

    expect($downClues)->toBe([
        1 => 'First down clue',
        2 => 'Second down clue',
    ]);
});
