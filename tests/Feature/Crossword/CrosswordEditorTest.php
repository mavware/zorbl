<?php

use App\Enums\CrosswordLayout;
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

test('publishing a puzzle auto-calculates difficulty rating', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 15,
        'height' => 15,
    ]);

    $this->actingAs($user);

    expect($crossword->difficulty_score)->toBeNull()
        ->and($crossword->difficulty_label)->toBeNull();

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('togglePublished')
        ->assertSet('isPublished', true);

    $crossword->refresh();
    expect($crossword->difficulty_score)->not->toBeNull()
        ->and($crossword->difficulty_label)->toBeIn(['Easy', 'Medium', 'Hard', 'Expert']);
});

test('unpublishing a puzzle does not clear difficulty rating', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($user)->create([
        'width' => 15,
        'height' => 15,
        'difficulty_score' => 2.5,
        'difficulty_label' => 'Medium',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('togglePublished')
        ->assertSet('isPublished', false);

    $crossword->refresh();
    expect($crossword->is_published)->toBeFalse()
        ->and($crossword->difficulty_score)->toBe(2.5)
        ->and($crossword->difficulty_label)->toBe('Medium');
});

test('clues render in two side panels when the grid is 17 or fewer cells wide', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 15,
        'height' => 15,
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    // Exactly two w-64 desktop columns (across on the left, down on the right).
    expect(substr_count($html, 'hidden w-64 flex-col overflow-hidden lg:flex'))->toBe(2);
    expect($html)->not->toContain('hidden w-64 flex-col gap-4 overflow-hidden lg:flex');
});

test('clues stack in a single column when the grid is wider than 17 cells', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 21,
        'height' => 21,
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    // Stacked column carries Across above Down; no separate w-64 solo columns.
    expect($html)->toContain('hidden w-64 flex-col gap-4 overflow-hidden lg:flex');
    expect(substr_count($html, 'hidden w-64 flex-col overflow-hidden lg:flex'))->toBe(0);
    // Across heading appears before Down heading in the stacked layout.
    expect(strpos($html, '>Across<'))->toBeLessThan(strpos($html, '>Down<'));
});

test('layout loads from the model into the editor as the enum case', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'layout' => CrosswordLayout::CluesRight,
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('layout', CrosswordLayout::CluesRight);
});

test('saveMetadata persists the selected layout enum', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['layout' => null]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('layout', CrosswordLayout::GridCenterCluesStacked)
        ->call('saveMetadata');

    expect($crossword->fresh()->layout)->toBe(CrosswordLayout::GridCenterCluesStacked);
});

test('saveMetadata clears the layout back to auto when set to null', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'layout' => CrosswordLayout::CluesLeft,
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('layout', null)
        ->call('saveMetadata');

    expect($crossword->fresh()->layout)->toBeNull();
});

test('CluesBottom layout renders both clue panels side-by-side beneath the grid', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 15,
        'height' => 15,
        'layout' => CrosswordLayout::CluesBottom,
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    // Outer wrapper uses column direction (clues below grid) rather than the row-based auto layout.
    expect($html)->toContain('flex flex-1 flex-col gap-4 overflow-hidden lg:max-h-[calc(100dvh-8rem)]');
    // Clues row is a side-by-side flex container beneath the grid.
    expect($html)->toContain('hidden min-h-0 flex-1 gap-4 overflow-hidden lg:flex');
    // The auto layout's side panels are not used in this layout.
    expect(substr_count($html, 'hidden w-64 flex-col overflow-hidden lg:flex'))->toBe(0);
    expect($html)->not->toContain('hidden w-64 flex-col gap-4 overflow-hidden lg:flex');
    // Both directions still present via the shared clue-panel partial.
    expect(strpos($html, '>Across<'))->toBeLessThan(strpos($html, '>Down<'));
});

test('every CrosswordLayout case has a renderable partial', function (CrosswordLayout $case) {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 15,
        'height' => 15,
        'layout' => $case,
    ]);

    $this->actingAs($user);

    // Resolving the partial name and rendering the editor must not throw.
    expect($case->partial())->toStartWith('partials.layouts.')
        ->and(view()->exists($case->partial()))->toBeTrue();

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('layout', $case);
})->with(CrosswordLayout::cases());

test('layout picker renders exactly one SVG preview card per CrosswordLayout case', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['layout' => null]);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    $expected = count(CrosswordLayout::cases());
    expect(substr_count($html, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 24"'))->toBe($expected);

    foreach (CrosswordLayout::cases() as $case) {
        expect($html)->toContain($case->label());
    }

    // No "Auto" card anymore — the width-based default is the selected case.
    expect($html)->not->toContain('>Auto<');
});

test('when no layout is set, the width-based auto default card is marked pressed', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 15,
        'height' => 15,
        'layout' => null,
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    // Exactly one card is pressed, and it's the auto-resolved default for this grid width.
    expect(substr_count($html, 'aria-pressed="true"'))->toBe(1)
        ->and(CrosswordLayout::auto(15))->toBe(CrosswordLayout::AcrossLeftDownRight)
        ->and($html)->toContain(sprintf(
            'wire:click="$set(\'layout\', %d)"',
            CrosswordLayout::AcrossLeftDownRight->value,
        ));
});

test('wide grids with no layout set mark the stacked default card pressed', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 21,
        'height' => 21,
        'layout' => null,
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    expect(substr_count($html, 'aria-pressed="true"'))->toBe(1)
        ->and(CrosswordLayout::auto(21))->toBe(CrosswordLayout::CluesRight);
});

test('the selected layout card is marked pressed and the others are not', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'layout' => CrosswordLayout::CluesBottom,
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    expect(substr_count($html, 'aria-pressed="true"'))->toBe(1);
});

test('users can save cell background colors via styles', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 3,
        'height' => 3,
    ]);

    $this->actingAs($user);

    $styles = [
        '0,1' => ['color' => '#FECACA'],
        '1,2' => ['color' => '#BAE6FD', 'shapebg' => 'circle'],
    ];

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('save', $crossword->grid, $crossword->solution, $styles, [], [])
        ->assertDispatched('saved');

    $crossword->refresh();
    expect($crossword->styles)->toBe($styles);
});

test('cell background colors are loaded on editor mount', function () {
    $user = User::factory()->create();
    $styles = [
        '0,0' => ['color' => '#FEF08A'],
        '2,1' => ['color' => '#BBF7D0', 'bars' => ['top']],
    ];
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 3,
        'height' => 3,
        'styles' => $styles,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('styles', $styles);
});

test('cell background colors persist alongside other style properties', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 3,
        'height' => 3,
        'styles' => ['0,0' => ['shapebg' => 'circle']],
    ]);

    $this->actingAs($user);

    $updatedStyles = [
        '0,0' => ['shapebg' => 'circle', 'color' => '#E9D5FF'],
    ];

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('save', $crossword->grid, $crossword->solution, $updatedStyles, [], [])
        ->assertDispatched('saved');

    $crossword->refresh();
    expect($crossword->styles['0,0']['shapebg'])->toBe('circle')
        ->and($crossword->styles['0,0']['color'])->toBe('#E9D5FF');
});
