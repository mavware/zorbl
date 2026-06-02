<?php

use App\Models\Crossword;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
});

test('popular tags section is hidden when no tags have published puzzles', function () {
    Crossword::factory()->published()->create();

    Livewire::test('pages::puzzles.index')
        ->assertDontSee('Popular:');
});

test('popular tags section shows tags with published puzzles', function () {
    $tag = Tag::factory()->create(['name' => 'Pop Culture']);
    $crossword = Crossword::factory()->published()->create();
    $crossword->tags()->attach($tag);

    Livewire::test('pages::puzzles.index')
        ->assertSee('Popular:')
        ->assertSee('Pop Culture');
});

test('popular tags excludes tags with only unpublished puzzles', function () {
    $usedTag = Tag::factory()->create(['name' => 'History']);
    $unusedTag = Tag::factory()->create(['name' => 'Orphan Tag']);

    $published = Crossword::factory()->published()->create();
    $published->tags()->attach($usedTag);

    $draft = Crossword::factory()->create(['is_published' => false]);
    $draft->tags()->attach($unusedTag);

    Livewire::test('pages::puzzles.index')
        ->assertSee('History')
        ->assertDontSee('Orphan Tag');
});

test('popular tags are ordered by published puzzle count descending', function () {
    $creator = User::factory()->create();

    $popularTag = Tag::factory()->create(['name' => 'Sports']);
    $lessPopularTag = Tag::factory()->create(['name' => 'Niche']);

    $puzzles = Crossword::factory()->published()->for($creator)->count(3)->create();
    foreach ($puzzles as $puzzle) {
        $puzzle->tags()->attach($popularTag);
    }

    $single = Crossword::factory()->published()->for($creator)->create();
    $single->tags()->attach($lessPopularTag);

    $component = Livewire::test('pages::puzzles.index');
    $html = $component->html();

    $sportsPos = strpos($html, 'Sports');
    $nichePos = strpos($html, 'Niche');

    expect($sportsPos)->toBeLessThan($nichePos);
});

test('popular tags limited to 12 tags', function () {
    $creator = User::factory()->create();

    for ($i = 1; $i <= 15; $i++) {
        $tag = Tag::factory()->create(['name' => "Tag {$i}"]);
        $crossword = Crossword::factory()->published()->for($creator)->create();
        $crossword->tags()->attach($tag);
    }

    $html = Livewire::test('pages::puzzles.index')->html();

    $chipCount = substr_count($html, 'wire:click="selectTag(');
    expect($chipCount)->toBe(12);
});

test('clicking a popular tag sets the tag filter', function () {
    $tag = Tag::factory()->create(['name' => 'Science', 'slug' => 'science']);
    $crossword = Crossword::factory()->published()->create();
    $crossword->tags()->attach($tag);

    Livewire::test('pages::puzzles.index')
        ->assertSet('tag', '')
        ->call('selectTag', 'science')
        ->assertSet('tag', 'science');
});

test('clicking the active tag clears the tag filter', function () {
    $tag = Tag::factory()->create(['name' => 'Music', 'slug' => 'music']);
    $crossword = Crossword::factory()->published()->create();
    $crossword->tags()->attach($tag);

    Livewire::test('pages::puzzles.index')
        ->call('selectTag', 'music')
        ->assertSet('tag', 'music')
        ->call('selectTag', 'music')
        ->assertSet('tag', '');
});

test('selecting a tag filters puzzles to only those with that tag', function () {
    $creator = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Geography', 'slug' => 'geography']);

    $tagged = Crossword::factory()->published()->for($creator)->create(['title' => 'World Capitals']);
    $tagged->tags()->attach($tag);

    Crossword::factory()->published()->for($creator)->create(['title' => 'Untagged Puzzle']);

    Livewire::test('pages::puzzles.index')
        ->call('selectTag', 'geography')
        ->assertSee('World Capitals')
        ->assertDontSee('Untagged Puzzle');
});

test('active tag chip has highlighted styling', function () {
    $tag = Tag::factory()->create(['name' => 'Movies', 'slug' => 'movies']);
    $crossword = Crossword::factory()->published()->create();
    $crossword->tags()->attach($tag);

    $html = Livewire::test('pages::puzzles.index')
        ->call('selectTag', 'movies')
        ->html();

    expect($html)->toContain('bg-amber-500');
});

test('popular tags show published puzzle count', function () {
    $creator = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Food']);

    $puzzles = Crossword::factory()->published()->for($creator)->count(5)->create();
    foreach ($puzzles as $puzzle) {
        $puzzle->tags()->attach($tag);
    }

    $html = Livewire::test('pages::puzzles.index')->html();

    $tagChipStart = strpos($html, 'Food');
    expect($tagChipStart)->not->toBeFalse();

    $slice = substr($html, $tagChipStart, 100);
    expect($slice)->toContain('5');
});

test('popular tags are cached for performance', function () {
    $creator = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Literature']);
    $crossword = Crossword::factory()->published()->for($creator)->create();
    $crossword->tags()->attach($tag);

    Livewire::test('pages::puzzles.index')
        ->assertSee('Literature');

    expect(Cache::has('browse:popular_tags'))->toBeTrue();
});

test('selecting a tag resets pagination to first page', function () {
    $creator = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Logic', 'slug' => 'logic']);

    $puzzles = Crossword::factory()->published()->for($creator)->count(25)->create();
    foreach ($puzzles as $puzzle) {
        $puzzle->tags()->attach($tag);
    }

    Livewire::test('pages::puzzles.index')
        ->call('nextPage')
        ->call('selectTag', 'logic')
        ->assertSee('Logic');
});
