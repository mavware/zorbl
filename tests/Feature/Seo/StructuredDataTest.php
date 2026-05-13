<?php

use App\Models\Crossword;
use App\Models\User;

test('welcome page includes WebSite schema with a SearchAction', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type":"WebSite"', false)
        ->assertSee('"@type":"SearchAction"', false)
        ->assertSee('search_term_string', false);
});

test('solve page includes Game schema with author and image', function () {
    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $crossword = Crossword::factory()->for($owner)->published()->create([
        'title' => 'Indexable Puzzle',
    ]);

    $response = $this->get(route('puzzles.solve', $crossword));

    $response->assertOk()
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type":"Game"', false)
        ->assertSee('"Indexable Puzzle"', false)
        ->assertSee('"Ada Lovelace"', false)
        ->assertSee(route('puzzles.og', $crossword), false);
});

test('json-ld on solve page parses as valid json', function () {
    $crossword = Crossword::factory()->published()->create([
        'title' => 'Parseable',
    ]);

    $html = $this->get(route('puzzles.solve', $crossword))->getContent();

    expect(preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $match))->toBe(1);

    $decoded = json_decode($match[1], true);
    expect($decoded)
        ->toHaveKey('@type', 'Game')
        ->toHaveKey('name', 'Parseable')
        ->toHaveKey('genre', 'Crossword puzzle');
});
