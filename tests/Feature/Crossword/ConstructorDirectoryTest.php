<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\Follow;
use App\Models\User;

test('constructors directory page requires authentication', function () {
    $this->get(route('constructors.index'))
        ->assertRedirect();
});

test('constructors directory page loads successfully', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('constructors.index'))
        ->assertOk()
        ->assertSee('Constructors');
});

test('only users with published puzzles appear as constructors', function () {
    $viewer = User::factory()->create();
    $constructorWithPublished = User::factory()->create(['name' => 'Published Author']);
    $constructorWithDraft = User::factory()->create(['name' => 'Draft Author']);
    $noPublications = User::factory()->create(['name' => 'No Puzzles']);

    Crossword::factory()->published()->for($constructorWithPublished)->create();
    Crossword::factory()->for($constructorWithDraft)->create(['is_published' => false]);

    Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.index')
        ->assertSee('Published Author')
        ->assertDontSee('Draft Author')
        ->assertDontSee('No Puzzles');
});

test('constructors display published puzzle count', function () {
    $viewer = User::factory()->create();
    $constructor = User::factory()->create(['name' => 'Prolific Builder']);

    Crossword::factory()->published()->count(3)->for($constructor)->create();
    Crossword::factory()->for($constructor)->create(['is_published' => false]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.index');

    $results = $component->get('constructors');
    $found = collect($results->items())->firstWhere('name', 'Prolific Builder');
    expect($found->published_puzzles_count)->toBe(3);
});

test('constructors display follower count', function () {
    $viewer = User::factory()->create();
    $constructor = User::factory()->create(['name' => 'Popular Creator']);

    Crossword::factory()->published()->for($constructor)->create();
    $followers = User::factory()->count(4)->create();

    foreach ($followers as $follower) {
        Follow::create(['follower_id' => $follower->id, 'following_id' => $constructor->id]);
    }

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.index');

    $results = $component->get('constructors');
    $found = collect($results->items())->firstWhere('name', 'Popular Creator');
    expect($found->followers_count)->toBe(4);
});

test('constructors display total likes count', function () {
    $viewer = User::factory()->create();
    $constructor = User::factory()->create(['name' => 'Liked Creator']);

    $puzzle1 = Crossword::factory()->published()->for($constructor)->create();
    $puzzle2 = Crossword::factory()->published()->for($constructor)->create();

    CrosswordLike::factory()->count(3)->create(['crossword_id' => $puzzle1->id]);
    CrosswordLike::factory()->count(2)->create(['crossword_id' => $puzzle2->id]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.index');

    $results = $component->get('constructors');
    $found = collect($results->items())->firstWhere('name', 'Liked Creator');
    expect((int) $found->total_likes)->toBe(5);
});

test('constructors can be searched by name', function () {
    $viewer = User::factory()->create();
    $alice = User::factory()->create(['name' => 'Alice Builder']);
    $bob = User::factory()->create(['name' => 'Bob Creator']);

    Crossword::factory()->published()->for($alice)->create();
    Crossword::factory()->published()->for($bob)->create();

    Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.index')
        ->set('search', 'Alice')
        ->assertSee('Alice Builder')
        ->assertDontSee('Bob Creator');
});

test('search shows empty state when no match', function () {
    $viewer = User::factory()->create();
    $constructor = User::factory()->create(['name' => 'Jane Doe']);

    Crossword::factory()->published()->for($constructor)->create();

    Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.index')
        ->set('search', 'Nonexistent')
        ->assertSee('No constructors found')
        ->assertSee('Try a different search term.');
});

test('constructors default to sorted by most puzzles', function () {
    $viewer = User::factory()->create();

    $few = User::factory()->create(['name' => 'Few Puzzles']);
    $many = User::factory()->create(['name' => 'Many Puzzles']);

    Crossword::factory()->published()->count(1)->for($few)->create();
    Crossword::factory()->published()->count(5)->for($many)->create();

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.index');

    $results = $component->get('constructors');
    $items = collect($results->items());
    expect($items->first()->name)->toBe('Many Puzzles');
});

test('constructors can be sorted by most popular', function () {
    $viewer = User::factory()->create();

    $lessLiked = User::factory()->create(['name' => 'Less Liked']);
    $mostLiked = User::factory()->create(['name' => 'Most Liked']);

    $puzzle1 = Crossword::factory()->published()->for($lessLiked)->create();
    $puzzle2 = Crossword::factory()->published()->for($mostLiked)->create();

    CrosswordLike::factory()->count(1)->create(['crossword_id' => $puzzle1->id]);
    CrosswordLike::factory()->count(10)->create(['crossword_id' => $puzzle2->id]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.index')
        ->set('sortBy', 'most_popular');

    $results = $component->get('constructors');
    $items = collect($results->items());
    expect($items->first()->name)->toBe('Most Liked');
});

test('constructors can be sorted by most followers', function () {
    $viewer = User::factory()->create();

    $fewFollowers = User::factory()->create(['name' => 'Few Followers']);
    $manyFollowers = User::factory()->create(['name' => 'Many Followers']);

    Crossword::factory()->published()->for($fewFollowers)->create();
    Crossword::factory()->published()->for($manyFollowers)->create();

    Follow::create(['follower_id' => User::factory()->create()->id, 'following_id' => $fewFollowers->id]);

    foreach (User::factory()->count(5)->create() as $follower) {
        Follow::create(['follower_id' => $follower->id, 'following_id' => $manyFollowers->id]);
    }

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.index')
        ->set('sortBy', 'most_followers');

    $results = $component->get('constructors');
    $items = collect($results->items());
    expect($items->first()->name)->toBe('Many Followers');
});

test('constructors can be sorted by newest', function () {
    $viewer = User::factory()->create();

    $older = User::factory()->create([
        'name' => 'Old Constructor',
        'created_at' => now()->subMonths(6),
    ]);
    $newer = User::factory()->create([
        'name' => 'New Constructor',
        'created_at' => now(),
    ]);

    Crossword::factory()->published()->for($older)->create();
    Crossword::factory()->published()->for($newer)->create();

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.index')
        ->set('sortBy', 'newest');

    $results = $component->get('constructors');
    $items = collect($results->items());
    expect($items->first()->name)->toBe('New Constructor');
});

test('constructor cards link to profile page', function () {
    $viewer = User::factory()->create();
    $constructor = User::factory()->create(['name' => 'Card Constructor']);

    Crossword::factory()->published()->for($constructor)->create();

    Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.index')
        ->assertSee('Card Constructor')
        ->assertSee(route('constructors.show', $constructor));
});

test('constructor bio is shown when set', function () {
    $viewer = User::factory()->create();
    $constructor = User::factory()->withBio('I build puzzles for fun.')->create(['name' => 'Bio Builder']);

    Crossword::factory()->published()->for($constructor)->create();

    Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.index')
        ->assertSee('I build puzzles for fun.');
});

test('sidebar contains constructors link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Constructors');
});
