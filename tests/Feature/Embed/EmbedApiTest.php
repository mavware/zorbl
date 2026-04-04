<?php

use App\Models\Crossword;

test('embed API returns puzzle data for published crossword', function () {
    $crossword = Crossword::factory()->published()->withBlocks()->withSolution()->create([
        'title' => 'Embed Test',
        'author' => 'Test Author',
    ]);

    $response = $this->getJson(route('api.embed.show', $crossword));

    $response->assertOk()
        ->assertJsonStructure([
            'id', 'title', 'author', 'width', 'height',
            'grid', 'clues_across', 'clues_down',
            'styles', 'prefilled',
            'solution', 'solution_encoding',
        ])
        ->assertJson([
            'id' => $crossword->id,
            'title' => 'Embed Test',
            'author' => 'Test Author',
            'width' => $crossword->width,
            'height' => $crossword->height,
            'solution_encoding' => 'xor_b64',
        ]);
});

test('embed API returns 404 for unpublished crossword', function () {
    $crossword = Crossword::factory()->create(['is_published' => false]);

    $this->getJson(route('api.embed.show', $crossword))
        ->assertNotFound();
});

test('embed API includes CORS headers', function () {
    $crossword = Crossword::factory()->published()->create();

    $response = $this->getJson(route('api.embed.show', $crossword));

    $response->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*');
});

test('embed API solution is obfuscated', function () {
    $crossword = Crossword::factory()->published()->withBlocks()->withSolution()->create();

    $response = $this->getJson(route('api.embed.show', $crossword));
    $data = $response->json();

    // Solution should be a base64 string, not an array
    expect($data['solution'])->toBeString()
        ->and($data['solution_encoding'])->toBe('xor_b64');

    // Verify we can decode it back to the original solution
    $key = 'zorbl_'.$crossword->id;
    $decoded = base64_decode($data['solution']);
    $result = '';
    for ($i = 0; $i < strlen($decoded); $i++) {
        $result .= chr(ord($decoded[$i]) ^ ord($key[$i % strlen($key)]));
    }
    $decodedSolution = json_decode($result, true);

    expect($decodedSolution)->toBe($crossword->solution);
});

test('embed API does not include sensitive fields', function () {
    $crossword = Crossword::factory()->published()->create();

    $response = $this->getJson(route('api.embed.show', $crossword));

    $response->assertOk()
        ->assertJsonMissing(['user_id'])
        ->assertJsonMissing(['user_progress']);
});

test('embed API does not require authentication', function () {
    $crossword = Crossword::factory()->published()->create();

    // No actingAs — unauthenticated request
    $this->getJson(route('api.embed.show', $crossword))
        ->assertOk();
});
