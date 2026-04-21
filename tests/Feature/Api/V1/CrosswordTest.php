<?php

use App\Models\Crossword;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists published crosswords', function () {
    Crossword::factory()->published()->count(2)->create();
    Crossword::factory()->create(); // unpublished

    $response = $this->getJson('/api/v1/crosswords');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('does not include solution in index response', function () {
    Crossword::factory()->published()->withSolution()->create();

    $response = $this->getJson('/api/v1/crosswords');

    $response->assertSuccessful();
    expect($response->json('data.0.attributes'))->not->toHaveKey('solution');
});

it('shows a single published crossword with grid data', function () {
    $crossword = Crossword::factory()->published()->withBlocks()->create();

    $this->getJson("/api/v1/crosswords/{$crossword->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.type', 'crosswords')
        ->assertJsonPath('data.id', (string) $crossword->id)
        ->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => [
                    'grid',
                    'clues_across',
                ],
            ],
        ]);
});

it('does not include solution in show response', function () {
    $crossword = Crossword::factory()->published()->withSolution()->create();

    $response = $this->getJson("/api/v1/crosswords/{$crossword->id}");

    $response->assertSuccessful();
    expect($response->json('data.attributes'))->not->toHaveKey('solution');
});

it('returns 404 for unpublished crossword from guest', function () {
    $crossword = Crossword::factory()->create();

    $this->getJson("/api/v1/crosswords/{$crossword->id}")
        ->assertNotFound();
});

it('allows owner to view unpublished crossword', function () {
    $owner = User::factory()->create();
    $crossword = Crossword::factory()->for($owner)->create();

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/crosswords/{$crossword->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', (string) $crossword->id);
});

it('filters by difficulty_label', function () {
    Crossword::factory()->published()->create(['difficulty_label' => 'easy']);
    Crossword::factory()->published()->create(['difficulty_label' => 'hard']);

    $this->getJson('/api/v1/crosswords?filter[difficulty_label]=easy')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attributes.difficulty_label', 'easy');
});

it('sorts by created_at', function () {
    $older = Crossword::factory()->published()->create(['created_at' => now()->subDays(2)]);
    $newer = Crossword::factory()->published()->create(['created_at' => now()->subDay()]);

    $response = $this->getJson('/api/v1/crosswords?sort=-created_at');

    $response->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id')->toArray();
    expect($ids[0])->toBe((string) $newer->id)
        ->and($ids[1])->toBe((string) $older->id);
});

it('paginates results', function () {
    Crossword::factory()->published()->count(20)->create();

    $response = $this->getJson('/api/v1/crosswords?page=2');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta',
        ]);
});

it('filters by tag slug', function () {
    $tag = Tag::factory()->create(['name' => 'Science']);
    $tagged = Crossword::factory()->published()->create();
    $tagged->tags()->attach($tag);
    Crossword::factory()->published()->create();

    $response = $this->getJson("/api/v1/crosswords?filter[tag]={$tag->slug}");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', (string) $tagged->id);
});

it('returns empty list when filtering by non-existent tag', function () {
    Crossword::factory()->published()->create();

    $this->getJson('/api/v1/crosswords?filter[tag]=non-existent')
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('includes tags in index response', function () {
    $tag = Tag::factory()->create(['name' => 'History']);
    $crossword = Crossword::factory()->published()->create();
    $crossword->tags()->attach($tag);

    $response = $this->getJson('/api/v1/crosswords');

    $response->assertSuccessful();
    $relationships = $response->json('data.0.relationships.tags');
    expect($relationships)->toHaveCount(1)
        ->and($relationships[0]['attributes']['name'])->toBe('History')
        ->and($relationships[0]['attributes']['slug'])->toBe('history');
});
