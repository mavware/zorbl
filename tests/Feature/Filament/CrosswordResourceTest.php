<?php

use App\Filament\Resources\Crosswords\Pages\ListCrosswords;
use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\User;
use App\Notifications\NewPuzzlePublished;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

/**
 * A fully filled and clued 2×2 puzzle.
 *
 * @param  array<string, mixed>  $attributes
 */
function completePuzzle(User $owner, array $attributes = []): Crossword
{
    return Crossword::factory()->for($owner)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'clues_across' => [['number' => 1, 'clue' => 'Across 1'], ['number' => 3, 'clue' => 'Across 3']],
        'clues_down' => [['number' => 1, 'clue' => 'Down 1'], ['number' => 2, 'clue' => 'Down 2']],
        ...$attributes,
    ]);
}

test('non-admin cannot access the puzzles table', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/puzzles')
        ->assertForbidden();
});

test('admin sees every user\'s puzzles, published or not', function () {
    $puzzles = collect([
        completePuzzle(User::factory()->create(), ['is_published' => true]),
        completePuzzle(User::factory()->create(), ['is_published' => false]),
    ]);

    $this->get('/admin/puzzles')->assertOk();

    Livewire::test(ListCrosswords::class)
        ->assertCanSeeTableRecords($puzzles);
});

test('the table shows how complete each puzzle is', function () {
    $complete = completePuzzle(User::factory()->create());
    $missingDownClues = completePuzzle(User::factory()->create(), ['clues_down' => []]);

    Livewire::test(ListCrosswords::class)
        ->assertTableColumnStateSet('completeness', 100, $complete)
        ->assertTableColumnStateSet('completeness', 67, $missingDownClues);
});

test('the table shows the tier of each puzzle\'s owner', function () {
    $guest = User::factory()->create([
        'is_anonymous' => true,
        'anonymous_token' => (string) Str::uuid(),
        'anonymous_created_at' => now(),
        'email' => null,
        'password' => null,
    ]);

    Livewire::test(ListCrosswords::class)
        ->assertTableColumnStateSet('user_tier', 'Free', completePuzzle(User::factory()->create()))
        ->assertTableColumnStateSet('user_tier', 'Pro', completePuzzle(User::factory()->create(['manual_pro_started_at' => now()->subDay()])))
        ->assertTableColumnStateSet('user_tier', 'Guest', completePuzzle($guest));
});

test('the published filter narrows the table', function () {
    $published = completePuzzle(User::factory()->create(), ['is_published' => true]);
    $draft = completePuzzle(User::factory()->create(), ['is_published' => false]);

    Livewire::test(ListCrosswords::class)
        ->filterTable('is_published', true)
        ->assertCanSeeTableRecords([$published])
        ->assertCanNotSeeTableRecords([$draft]);
});

test('admin can publish a puzzle from the table', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $follower = User::factory()->create();
    $owner->followers()->attach($follower);
    $puzzle = completePuzzle($owner, ['is_published' => false]);

    Livewire::test(ListCrosswords::class)
        ->callAction(TestAction::make('togglePublished')->table($puzzle))
        ->assertNotified('Puzzle published');

    $puzzle->refresh();

    expect($puzzle->is_published)->toBeTrue()
        ->and($puzzle->difficulty_label)->not->toBeNull()
        ->and(ClueEntry::where('crossword_id', $puzzle->id)->count())->toBe(4);

    Notification::assertSentTo($follower, NewPuzzlePublished::class);
});

test('admin can unpublish a puzzle from the table', function () {
    $puzzle = completePuzzle(User::factory()->create(), ['is_published' => false]);

    $list = Livewire::test(ListCrosswords::class)
        ->callAction(TestAction::make('togglePublished')->table($puzzle));

    expect(ClueEntry::where('crossword_id', $puzzle->id)->count())->toBe(4);

    $list->callAction(TestAction::make('togglePublished')->table($puzzle))
        ->assertNotified('Puzzle unpublished');

    expect($puzzle->refresh()->is_published)->toBeFalse()
        ->and(ClueEntry::where('crossword_id', $puzzle->id)->count())->toBe(0);
});
