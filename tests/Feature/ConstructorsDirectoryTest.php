<?php

use App\Models\Crossword;
use App\Models\Follow;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('constructors.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the constructors directory', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('constructors.index'))
        ->assertOk();
});

test('only users with published puzzles appear as constructors', function () {
    $user = User::factory()->create();

    $constructor = User::factory()->create(['name' => 'Puzzle Builder']);
    Crossword::factory()->published()->create(['user_id' => $constructor->id]);

    $draftOnly = User::factory()->create(['name' => 'Draft Only']);
    Crossword::factory()->create(['user_id' => $draftOnly->id, 'is_published' => false]);

    Livewire::actingAs($user)
        ->test('pages::constructors.index')
        ->assertSee('Puzzle Builder')
        ->assertDontSee('Draft Only');
});

test('constructors are sorted by most puzzles by default', function () {
    $user = User::factory()->create();

    $prolific = User::factory()->create(['name' => 'Prolific']);
    Crossword::factory()->count(5)->published()->create(['user_id' => $prolific->id]);

    $casual = User::factory()->create(['name' => 'Casual']);
    Crossword::factory()->published()->create(['user_id' => $casual->id]);

    $component = Livewire::actingAs($user)->test('pages::constructors.index');
    $names = $component->get('constructors')->pluck('name')->all();

    expect($names[0])->toBe('Prolific')
        ->and($names[1])->toBe('Casual');
});

test('constructors can be sorted by most followers', function () {
    $user = User::factory()->create();

    $popular = User::factory()->create(['name' => 'Popular']);
    Crossword::factory()->published()->create(['user_id' => $popular->id]);
    Follow::create(['follower_id' => User::factory()->create()->id, 'following_id' => $popular->id]);
    Follow::create(['follower_id' => User::factory()->create()->id, 'following_id' => $popular->id]);

    $unpopular = User::factory()->create(['name' => 'Unpopular']);
    Crossword::factory()->published()->create(['user_id' => $unpopular->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::constructors.index')
        ->set('sortBy', 'most_followers');

    $names = $component->get('constructors')->pluck('name')->all();

    expect($names[0])->toBe('Popular');
});

test('constructors can be sorted by newest', function () {
    $user = User::factory()->create();

    $older = User::factory()->create(['name' => 'Old Timer', 'created_at' => now()->subYear()]);
    Crossword::factory()->published()->create(['user_id' => $older->id]);

    $newer = User::factory()->create(['name' => 'Newcomer', 'created_at' => now()]);
    Crossword::factory()->published()->create(['user_id' => $newer->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::constructors.index')
        ->set('sortBy', 'newest');

    $names = $component->get('constructors')->pluck('name')->all();

    expect($names[0])->toBe('Newcomer');
});

test('constructors can be searched by name', function () {
    $user = User::factory()->create();

    $match = User::factory()->create(['name' => 'Alice Builder']);
    Crossword::factory()->published()->create(['user_id' => $match->id]);

    $noMatch = User::factory()->create(['name' => 'Bob Creator']);
    Crossword::factory()->published()->create(['user_id' => $noMatch->id]);

    Livewire::actingAs($user)
        ->test('pages::constructors.index')
        ->set('search', 'Alice')
        ->assertSee('Alice Builder')
        ->assertDontSee('Bob Creator');
});

test('constructor cards show published puzzle count', function () {
    $user = User::factory()->create();

    $constructor = User::factory()->create(['name' => 'Card Test']);
    Crossword::factory()->count(3)->published()->create(['user_id' => $constructor->id]);
    Crossword::factory()->create(['user_id' => $constructor->id, 'is_published' => false]);

    $component = Livewire::actingAs($user)->test('pages::constructors.index');
    $result = $component->get('constructors')->firstWhere('name', 'Card Test');

    expect($result->published_puzzles_count)->toBe(3);
});

test('constructor cards show total solves count', function () {
    $user = User::factory()->create();

    $constructor = User::factory()->create(['name' => 'Solve Test']);
    $crossword = Crossword::factory()->published()->create(['user_id' => $constructor->id]);
    PuzzleAttempt::factory()->count(2)->completed()->create(['crossword_id' => $crossword->id]);
    PuzzleAttempt::factory()->create(['crossword_id' => $crossword->id, 'is_completed' => false]);

    $component = Livewire::actingAs($user)->test('pages::constructors.index');
    $result = $component->get('constructors')->firstWhere('name', 'Solve Test');

    expect((int) $result->total_solves)->toBe(2);
});

test('constructor cards show follower count', function () {
    $user = User::factory()->create();

    $constructor = User::factory()->create(['name' => 'Follow Test']);
    Crossword::factory()->published()->create(['user_id' => $constructor->id]);
    Follow::create(['follower_id' => User::factory()->create()->id, 'following_id' => $constructor->id]);
    Follow::create(['follower_id' => User::factory()->create()->id, 'following_id' => $constructor->id]);

    $component = Livewire::actingAs($user)->test('pages::constructors.index');
    $result = $component->get('constructors')->firstWhere('name', 'Follow Test');

    expect($result->followers_count)->toBe(2);
});

test('constructor cards show bio when present', function () {
    $user = User::factory()->create();

    $constructor = User::factory()->withBio('Crossword enthusiast')->create(['name' => 'Bio Test']);
    Crossword::factory()->published()->create(['user_id' => $constructor->id]);

    Livewire::actingAs($user)
        ->test('pages::constructors.index')
        ->assertSee('Crossword enthusiast');
});

test('empty state is shown when no constructors match search', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::constructors.index')
        ->set('search', 'NonexistentName')
        ->assertSee('No constructors found')
        ->assertSee('Try a different search term.');
});

test('empty state is shown when no constructors exist', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::constructors.index')
        ->assertSee('No constructors found')
        ->assertSee('No constructors have published puzzles yet.');
});

test('search resets pagination', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::constructors.index')
        ->set('paginators.page', 2)
        ->set('search', 'test');

    expect($component->get('paginators.page'))->toBe(1);
});

test('sort change resets pagination', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::constructors.index')
        ->set('paginators.page', 2)
        ->set('sortBy', 'newest');

    expect($component->get('paginators.page'))->toBe(1);
});

test('sidebar shows constructors link', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee('Constructors');
});
