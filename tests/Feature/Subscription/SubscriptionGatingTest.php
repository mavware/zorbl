<?php

use App\Models\Crossword;
use App\Models\User;
use App\Models\Word;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Zorbl\CrosswordIO\GridNumberer;

function makeProUser(): User
{
    $user = User::factory()->create(['stripe_id' => 'cus_test_'.uniqid()]);
    Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_fake',
    ]);

    return $user;
}

function makeTestCrossword(User $user): Crossword
{
    $numberer = app(GridNumberer::class);
    $rawGrid = [[0, 0, 0], [0, 0, 0], [0, 0, 0]];
    $numbered = $numberer->number($rawGrid, 3, 3, [], 3);

    return Crossword::factory()->for($user)->create([
        'width' => 3,
        'height' => 3,
        'grid' => $numbered['grid'],
        'solution' => [['', '', ''], ['', '', ''], ['', '', '']],
    ]);
}

beforeEach(function () {
    Word::insert([
        ['word' => 'CAT', 'length' => 3, 'score' => 60.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'ACE', 'length' => 3, 'score' => 65.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'TEA', 'length' => 3, 'score' => 62.0, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

describe('puzzle creation limits', function () {
    it('allows free users to create up to 5 puzzles', function () {
        $user = User::factory()->create();

        // Create 4 existing puzzles
        Crossword::factory()->for($user)->count(4)->create();

        Livewire::actingAs($user)
            ->test('pages::crosswords.index')
            ->set('newWidth', 5)
            ->set('newHeight', 5)
            ->call('createPuzzle')
            ->assertHasNoErrors()
            ->assertRedirect();
    });

    it('blocks free users from creating a 6th puzzle', function () {
        $user = User::factory()->create();

        Crossword::factory()->for($user)->count(5)->create();

        Livewire::actingAs($user)
            ->test('pages::crosswords.index')
            ->set('newWidth', 5)
            ->set('newHeight', 5)
            ->call('createPuzzle')
            ->assertHasErrors('newWidth');
    });

    it('allows grandfathered free users to create up to 10 puzzles', function () {
        $user = User::factory()->create(['grandfathered_at' => now()]);

        Crossword::factory()->for($user)->count(9)->create();

        Livewire::actingAs($user)
            ->test('pages::crosswords.index')
            ->set('newWidth', 5)
            ->set('newHeight', 5)
            ->call('createPuzzle')
            ->assertHasNoErrors()
            ->assertRedirect();
    });

    it('allows pro users to create unlimited puzzles', function () {
        $user = makeProUser();

        Crossword::factory()->for($user)->count(20)->create();

        Livewire::actingAs($user)
            ->test('pages::crosswords.index')
            ->set('newWidth', 5)
            ->set('newHeight', 5)
            ->call('createPuzzle')
            ->assertHasNoErrors()
            ->assertRedirect();
    });
});

describe('AI feature gating', function () {
    it('blocks free users from AI fill', function () {
        $user = User::factory()->create();
        $crossword = makeTestCrossword($user);

        $component = Livewire::actingAs($user)
            ->test('pages::crosswords.editor', ['crossword' => $crossword])
            ->call('aiFill', [['', '', ''], ['', '', ''], ['', '', '']]);

        // The call should succeed (no authorization error) but return an upgrade message
        $component->assertNoRedirect();
    });

    it('blocks free users from AI clue generation', function () {
        $user = User::factory()->create();
        $crossword = makeTestCrossword($user);

        Livewire::actingAs($user)
            ->test('pages::crosswords.editor', ['crossword' => $crossword])
            ->call('aiGenerateClues', [['C', 'A', 'T'], ['A', 'C', 'E'], ['T', 'E', 'A']])
            ->assertNoRedirect();
    });
});

describe('export gating', function () {
    it('allows free users to export ipuz', function () {
        $user = User::factory()->create();
        $crossword = makeTestCrossword($user);

        Livewire::actingAs($user)
            ->test('pages::crosswords.editor', ['crossword' => $crossword])
            ->call('exportIpuz')
            ->assertNoRedirect();
    });

    it('blocks free users from exporting puz', function () {
        $user = User::factory()->create();
        $crossword = makeTestCrossword($user);

        Livewire::actingAs($user)
            ->test('pages::crosswords.editor', ['crossword' => $crossword])
            ->call('exportPuz')
            ->assertForbidden();
    });

    it('blocks free users from exporting jpz', function () {
        $user = User::factory()->create();
        $crossword = makeTestCrossword($user);

        Livewire::actingAs($user)
            ->test('pages::crosswords.editor', ['crossword' => $crossword])
            ->call('exportJpz')
            ->assertForbidden();
    });

    it('blocks free users from exporting pdf', function () {
        $user = User::factory()->create();
        $crossword = makeTestCrossword($user);

        Livewire::actingAs($user)
            ->test('pages::crosswords.editor', ['crossword' => $crossword])
            ->call('exportPdf')
            ->assertForbidden();
    });

    it('allows pro users to export puz', function () {
        $user = makeProUser();
        $crossword = makeTestCrossword($user);

        Livewire::actingAs($user)
            ->test('pages::crosswords.editor', ['crossword' => $crossword])
            ->call('exportPuz')
            ->assertNoRedirect();
    });
});

describe('favorite list limits', function () {
    it('allows free users to create up to 3 lists', function () {
        $user = User::factory()->create();

        $user->favoriteLists()->createMany([
            ['name' => 'List 1'],
            ['name' => 'List 2'],
        ]);

        Livewire::actingAs($user)
            ->test('pages::favorites.index')
            ->set('newListName', 'List 3')
            ->call('createList')
            ->assertHasNoErrors();
    });

    it('blocks free users from creating a 4th list', function () {
        $user = User::factory()->create();

        $user->favoriteLists()->createMany([
            ['name' => 'List 1'],
            ['name' => 'List 2'],
            ['name' => 'List 3'],
        ]);

        Livewire::actingAs($user)
            ->test('pages::favorites.index')
            ->set('newListName', 'List 4')
            ->call('createList')
            ->assertHasErrors('newListName');
    });

    it('allows pro users to create unlimited lists', function () {
        $user = makeProUser();

        $user->favoriteLists()->createMany([
            ['name' => 'List 1'],
            ['name' => 'List 2'],
            ['name' => 'List 3'],
        ]);

        Livewire::actingAs($user)
            ->test('pages::favorites.index')
            ->set('newListName', 'List 4')
            ->call('createList')
            ->assertHasNoErrors();
    });
});

describe('admin access', function () {
    it('treats admin users as pro without a subscription', function () {
        Role::findOrCreate('Admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('Admin');

        expect($user->isPro())->toBeTrue()
            ->and($user->planLimits()->isPro())->toBeTrue()
            ->and($user->planLimits()->maxPuzzles())->toBe(PHP_INT_MAX)
            ->and($user->planLimits()->monthlyAiFills())->toBe(50)
            ->and($user->planLimits()->canExportPuz())->toBeTrue();
    });
});
