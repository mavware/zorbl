<?php

use Zorbl\CrosswordIO\Crossword;

it('creates a crossword from an array', function () {
    $data = [
        'width' => 3,
        'height' => 3,
        'grid' => [[1, 2, '#'], [3, 0, 4], ['#', 5, 0]],
        'solution' => [['C', 'A', '#'], ['B', 'O', 'T'], ['#', 'L', 'O']],
        'clues_across' => [['number' => 1, 'clue' => 'CA']],
        'clues_down' => [['number' => 1, 'clue' => 'CB']],
        'title' => 'Test',
        'author' => 'Author',
    ];

    $crossword = Crossword::fromArray($data);

    expect($crossword->width)->toBe(3)
        ->and($crossword->height)->toBe(3)
        ->and($crossword->title)->toBe('Test')
        ->and($crossword->author)->toBe('Author')
        ->and($crossword->copyright)->toBeNull()
        ->and($crossword->notes)->toBeNull()
        ->and($crossword->styles)->toBeNull()
        ->and($crossword->metadata)->toBeNull();
});

it('converts back to array', function () {
    $crossword = new Crossword(
        width: 2,
        height: 2,
        grid: [[1, 2], [0, 0]],
        solution: [['A', 'B'], ['C', 'D']],
        clues_across: [['number' => 1, 'clue' => 'AB']],
        clues_down: [['number' => 1, 'clue' => 'AC']],
        title: 'Test',
    );

    $array = $crossword->toArray();

    expect($array['width'])->toBe(2)
        ->and($array['height'])->toBe(2)
        ->and($array['title'])->toBe('Test')
        ->and($array['grid'])->toBe([[1, 2], [0, 0]])
        ->and($array['solution'])->toBe([['A', 'B'], ['C', 'D']]);
});

it('roundtrips through fromArray and toArray', function () {
    $data = [
        'width' => 3,
        'height' => 3,
        'grid' => [[1, 2, '#'], [3, 0, 4], ['#', 5, 0]],
        'solution' => [['C', 'A', '#'], ['B', 'O', 'T'], ['#', 'L', 'O']],
        'clues_across' => [['number' => 1, 'clue' => 'CA']],
        'clues_down' => [['number' => 1, 'clue' => 'CB']],
        'title' => 'Test',
        'author' => 'Author',
        'copyright' => '2024',
        'notes' => 'Some notes',
        'kind' => 'https://ipuz.org/crossword#1',
        'styles' => ['0,0' => ['shapebg' => 'circle']],
        'metadata' => ['publisher' => 'Test Publisher'],
    ];

    $crossword = Crossword::fromArray($data);
    $array = $crossword->toArray();

    expect($array['title'])->toBe('Test')
        ->and($array['author'])->toBe('Author')
        ->and($array['copyright'])->toBe('2024')
        ->and($array['notes'])->toBe('Some notes')
        ->and($array['styles'])->toBe(['0,0' => ['shapebg' => 'circle']])
        ->and($array['metadata'])->toBe(['publisher' => 'Test Publisher']);
});
