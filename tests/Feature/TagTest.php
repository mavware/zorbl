<?php

use App\Models\Crossword;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Livewire\Livewire;

// --- Model behavior ---

test('tag auto-generates slug from name on creation', function () {
    $tag = Tag::create(['name' => 'Pop Culture']);

    expect($tag->slug)->toBe('pop-culture');
});

test('tag preserves explicitly set slug', function () {
    $tag = Tag::create(['name' => 'Pop Culture', 'slug' => 'custom-slug']);

    expect($tag->slug)->toBe('custom-slug');
});

test('tag name must be unique', function () {
    Tag::create(['name' => 'Sports']);

    expect(fn () => Tag::create(['name' => 'Sports']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('tag has crosswords relationship', function () {
    $tag = Tag::factory()->create();
    $crossword = Crossword::factory()->create();

    $tag->crosswords()->attach($crossword);

    expect($tag->crosswords)->toHaveCount(1)
        ->and($tag->crosswords->first()->id)->toBe($crossword->id);
});

test('crossword has tags relationship', function () {
    $crossword = Crossword::factory()->create();
    $tags = Tag::factory()->count(3)->create();

    $crossword->tags()->attach($tags);

    expect($crossword->tags)->toHaveCount(3);
});

test('deleting a tag detaches from crosswords', function () {
    $tag = Tag::factory()->create();
    $crossword = Crossword::factory()->create();

    $crossword->tags()->attach($tag);
    $tag->delete();

    expect($crossword->tags()->count())->toBe(0);
});

test('deleting a crossword detaches from tags', function () {
    $tag = Tag::factory()->create();
    $crossword = Crossword::factory()->create();

    $crossword->tags()->attach($tag);
    $crossword->delete();

    expect($tag->crosswords()->count())->toBe(0);
});

// --- Editor tag management ---

test('editor loads existing tags for a crossword', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();
    $tag = Tag::factory()->create(['name' => 'History']);
    $crossword->tags()->attach($tag);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('tagIds', [$tag->id]);
});

test('editor can add a tag to a crossword via saveMetadata', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();
    $tag = Tag::factory()->create(['name' => 'Science']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('addTag', $tag->id)
        ->call('saveMetadata')
        ->assertDispatched('saved');

    expect($crossword->fresh()->tags->pluck('id')->all())->toBe([$tag->id]);
});

test('editor can remove a tag from a crossword', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();
    $tag = Tag::factory()->create(['name' => 'Music']);
    $crossword->tags()->attach($tag);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('removeTag', $tag->id)
        ->call('saveMetadata')
        ->assertDispatched('saved');

    expect($crossword->fresh()->tags)->toHaveCount(0);
});

test('editor can create a new tag inline', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('tagSearch', 'Brand New Tag')
        ->call('createTag')
        ->call('saveMetadata')
        ->assertDispatched('saved');

    $tag = Tag::where('name', 'Brand New Tag')->first();
    expect($tag)->not->toBeNull()
        ->and($tag->slug)->toBe('brand-new-tag')
        ->and($crossword->fresh()->tags->pluck('id')->all())->toBe([$tag->id]);
});

test('creating a duplicate tag name reuses the existing tag', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();
    $existing = Tag::factory()->create(['name' => 'Movies', 'slug' => 'movies']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('tagSearch', 'Movies')
        ->call('createTag')
        ->call('saveMetadata');

    expect(Tag::where('slug', 'movies')->count())->toBe(1)
        ->and($crossword->fresh()->tags->pluck('id')->all())->toBe([$existing->id]);
});

// --- Standard tag suggestions ---

test('editor suggests standard tags when search is empty', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('suggestedStandardTags', Tag::STANDARD);
});

test('editor filters standard suggestions by search term', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('tagSearch', 'theme')
        ->assertSet('suggestedStandardTags', ['Themed', 'Themeless']);
});

test('editor hides standard suggestions that already exist in the database', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();
    Tag::create(['name' => 'Themed']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet(
            'suggestedStandardTags',
            array_values(array_filter(Tag::STANDARD, fn ($n) => $n !== 'Themed'))
        );
});

test('editor hides standard suggestions that are already selected', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();
    $tag = Tag::create(['name' => 'Cryptic']);
    $crossword->tags()->attach($tag);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet(
            'suggestedStandardTags',
            array_values(array_filter(Tag::STANDARD, fn ($n) => $n !== 'Cryptic'))
        );
});

test('editor adds a standard tag, creating it if missing', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('addStandardTag', 'Grid Art')
        ->call('saveMetadata')
        ->assertDispatched('saved');

    $tag = Tag::where('slug', 'grid-art')->first();
    expect($tag)->not->toBeNull()
        ->and($tag->name)->toBe('Grid Art')
        ->and($crossword->fresh()->tags->pluck('id')->all())->toBe([$tag->id]);
});

test('editor reuses an existing tag when adding a standard suggestion', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();
    $existing = Tag::create(['name' => 'Mini']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('addStandardTag', 'Mini')
        ->call('saveMetadata');

    expect(Tag::where('slug', 'mini')->count())->toBe(1)
        ->and($crossword->fresh()->tags->pluck('id')->all())->toBe([$existing->id]);
});

test('editor ignores attempts to add non-standard tags via addStandardTag', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('addStandardTag', 'Not A Standard Tag')
        ->call('saveMetadata');

    expect(Tag::where('name', 'Not A Standard Tag')->exists())->toBeFalse()
        ->and($crossword->fresh()->tags)->toHaveCount(0);
});

// --- Browse page ---

test('browse page shows tags on puzzle cards', function () {
    $crossword = Crossword::factory()->published()->create();
    $tag = Tag::factory()->create(['name' => 'Geography']);
    $crossword->tags()->attach($tag);

    $this->get(route('puzzles.index'))
        ->assertOk()
        ->assertSee('Geography');
});

test('browse page filters puzzles by tag', function () {
    $tag = Tag::factory()->create(['name' => 'Animals', 'slug' => 'animals']);
    $tagged = Crossword::factory()->published()->create(['title' => 'Animal Crossword']);
    $tagged->tags()->attach($tag);
    $untagged = Crossword::factory()->published()->create(['title' => 'Plain Crossword']);

    Livewire::test('pages::puzzles.index')
        ->set('tag', 'animals')
        ->assertSee('Animal Crossword')
        ->assertDontSee('Plain Crossword');
});

test('browse page shows all puzzles when no tag filter is set', function () {
    $tag = Tag::factory()->create(['name' => 'Food', 'slug' => 'food']);
    $tagged = Crossword::factory()->published()->create(['title' => 'Food Puzzle']);
    $tagged->tags()->attach($tag);
    $untagged = Crossword::factory()->published()->create(['title' => 'Other Puzzle']);

    Livewire::test('pages::puzzles.index')
        ->assertSee('Food Puzzle')
        ->assertSee('Other Puzzle');
});
