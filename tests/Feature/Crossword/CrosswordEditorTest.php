<?php

use App\Models\Crossword;
use App\Models\User;
use Livewire\Livewire;

test('guests cannot access the editor', function () {
    $crossword = Crossword::factory()->create();

    $this->get(route('crosswords.editor', $crossword))->assertRedirect(route('login'));
});

test('users can view their own puzzle editor', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['title' => 'Test Puzzle']);

    $this->actingAs($user);

    $this->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->assertSee('Test Puzzle');
});

test('users cannot view other users puzzle editor', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $crossword = Crossword::factory()->for($other)->create();

    $this->actingAs($user);

    $this->get(route('crosswords.editor', $crossword))->assertForbidden();
});

test('users can save grid state', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 3,
        'height' => 3,
    ]);

    $this->actingAs($user);

    $newGrid = [
        [1, 2, '#'],
        [3, 0, 0],
        ['#', 4, 0],
    ];
    $newSolution = [
        ['A', 'B', '#'],
        ['C', 'D', 'E'],
        ['#', 'F', 'G'],
    ];

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('save', $newGrid, $newSolution, null, [['number' => 1, 'clue' => 'Test']], [])
        ->assertDispatched('saved');

    $crossword->refresh();
    expect($crossword->grid)->toBe($newGrid)
        ->and($crossword->solution)->toBe($newSolution)
        ->and($crossword->clues_across[0]['clue'])->toBe('Test');
});

test('users can update metadata', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('title', 'Updated Title')
        ->set('author', 'New Author')
        ->call('saveMetadata')
        ->assertDispatched('saved');

    $crossword->refresh();
    expect($crossword->title)->toBe('Updated Title')
        ->and($crossword->author)->toBe('New Author');
});

test('settings modal saves all metadata fields', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('title', 'My Puzzle')
        ->set('author', 'Jane Doe')
        ->set('copyright', '© 2026 Jane Doe')
        ->set('notes', 'A themed puzzle about animals')
        ->set('minAnswerLength', 4)
        ->call('saveMetadata')
        ->assertSet('showSettingsModal', false)
        ->assertDispatched('saved');

    $crossword->refresh();
    expect($crossword->title)->toBe('My Puzzle')
        ->and($crossword->author)->toBe('Jane Doe')
        ->and($crossword->copyright)->toBe('© 2026 Jane Doe')
        ->and($crossword->notes)->toBe('A themed puzzle about animals')
        ->and($crossword->metadata['min_answer_length'])->toBe(4);
});

test('settings modal loads existing metadata on mount', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Existing Title',
        'author' => 'Existing Author',
        'copyright' => '© 2025',
        'notes' => 'Some notes',
        'metadata' => ['min_answer_length' => 5],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('title', 'Existing Title')
        ->assertSet('author', 'Existing Author')
        ->assertSet('copyright', '© 2025')
        ->assertSet('notes', 'Some notes')
        ->assertSet('minAnswerLength', 5);
});

test('settings modal validates minimum answer length', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('minAnswerLength', 0)
        ->call('saveMetadata')
        ->assertHasErrors(['minAnswerLength']);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('minAnswerLength', 20)
        ->call('saveMetadata')
        ->assertHasErrors(['minAnswerLength']);
});

test('author defaults to user name when not set', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    $crossword = Crossword::factory()->for($user)->create(['author' => null]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('author', 'Alice');
});

test('author preserves existing value when set', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    $crossword = Crossword::factory()->for($user)->create(['author' => 'Custom Author']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('author', 'Custom Author');
});

test('copyright defaults to user copyright name', function () {
    $user = User::factory()->create(['copyright_name' => 'Pen Name']);
    $crossword = Crossword::factory()->for($user)->create(['copyright' => null]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('copyright', copyright('Pen Name'));
});

test('copyright defaults to user name when no copyright name set', function () {
    $user = User::factory()->create(['name' => 'Alice', 'copyright_name' => null]);
    $crossword = Crossword::factory()->for($user)->create(['copyright' => null]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('copyright', copyright('Alice'));
});

test('copyright preserves existing value when set', function () {
    $user = User::factory()->create(['copyright_name' => 'Pen Name']);
    $crossword = Crossword::factory()->for($user)->create(['copyright' => '© 2026 Custom']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('copyright', '© 2026 Custom');
});

test('settings modal preserves existing metadata keys', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'metadata' => ['custom_key' => 'custom_value', 'min_answer_length' => 3],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('minAnswerLength', 5)
        ->call('saveMetadata');

    $crossword->refresh();
    expect($crossword->metadata['custom_key'])->toBe('custom_value')
        ->and($crossword->metadata['min_answer_length'])->toBe(5);
});

test('users can resize the grid', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 5,
        'height' => 5,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('resizeWidth', 7)
        ->set('resizeHeight', 7)
        ->call('resizeGrid')
        ->assertDispatched('grid-resized');

    $crossword->refresh();
    expect($crossword->width)->toBe(7)
        ->and($crossword->height)->toBe(7)
        ->and($crossword->grid)->toHaveCount(7)
        ->and($crossword->grid[0])->toHaveCount(7);
});

test('users can toggle puzzle published state', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    expect($crossword->is_published)->toBeFalse();

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('togglePublished')
        ->assertSet('isPublished', true);

    $crossword->refresh();
    expect($crossword->is_published)->toBeTrue();

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('togglePublished')
        ->assertSet('isPublished', false);

    $crossword->refresh();
    expect($crossword->is_published)->toBeFalse();
});
