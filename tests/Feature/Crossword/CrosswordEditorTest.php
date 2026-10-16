<?php

use App\Enums\CrosswordLayout;
use App\Models\Crossword;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

/**
 * The settings form renders once per surface: the desktop sidebar and the
 * mobile flyout. These pull one surface out of the full editor render so a
 * test can count within it.
 */
function editorSettingsFlyoutHtml(string $html): string
{
    preg_match('/<ui-modal[^>]*data-test="settings-flyout".*?<\/ui-modal>/s', $html, $flyout);
    expect($flyout)->not->toBeEmpty();

    return $flyout[0];
}

function editorSettingsFormHtml(string $html, string $surface): string
{
    $start = strpos($html, sprintf('id="%s-settings-tab-details"', $surface));
    $end = strpos($html, sprintf('id="%s-settings-panel-theme"', $surface));
    $end = strpos($html, 'wire:click="saveMetadata"', $end);
    expect($start)->not->toBeFalse()->and($end)->not->toBeFalse();

    return substr($html, $start, $end - $start);
}

function editorSettingsPaneHtml(string $html): string
{
    preg_match('/<aside[^>]*data-test="settings-pane".*?<\/aside>/s', $html, $pane);
    expect($pane)->not->toBeEmpty();

    return $pane[0];
}

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

test('the editor toolbar shows a labeled publish button', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $this->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->assertSeeInOrder(['Cells', 'Clues', 'Publish']);
});

test('the editor clue lists do not show the slot length under each clue input', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $this->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->assertSee('Enter clue...')
        ->assertDontSee("'(' + clue.length + ')'", false);
});

test('the editor clue lists use auto-growing textareas so long clues wrap instead of truncating', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = $this->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toMatch('/<textarea[^>]*x-model="clue\.clue"/')
        ->toContain('field-sizing-content')
        ->toContain('fitClueTextarea($el)')
        ->not->toMatch('/<input[^>]*x-model="clue\.clue"/');
});

test('the editor clue lists let shift+enter insert a newline instead of changing clues', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = $this->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); focusNextClue($el,')
        ->not->toContain('x-on:keydown.enter.prevent="focusNextClue');
});

test('the editor toolbar shows an unpublish label once the puzzle is live', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['is_published' => true]);

    $this->actingAs($user);

    $this->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->assertSee('Unpublish');
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

test('users can save grid with void (null) cells', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 3,
        'height' => 3,
    ]);

    $this->actingAs($user);

    // Void corners — a plus-sign-shaped puzzle.
    $newGrid = [
        [null, 1,    null],
        [2,    0,    0],
        [null, 0,    null],
    ];
    $newSolution = [
        [null, 'A',  null],
        ['B',  'C',  'D'],
        [null, 'E',  null],
    ];

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('save', $newGrid, $newSolution, null, [], [])
        ->assertDispatched('saved');

    $crossword->refresh();
    expect($crossword->grid)->toBe($newGrid)
        ->and($crossword->solution)->toBe($newSolution)
        ->and($crossword->grid[0][0])->toBeNull()
        ->and($crossword->grid[2][2])->toBeNull();
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

test('settings fields autosave as they change and keep the modal open', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['title' => 'Before']);
    $tag = Tag::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('showSettingsModal', true)
        ->assertNotDispatched('saved')
        ->set('title', 'Autosaved')
        ->assertDispatched('saved')
        // Names the field so its green check can flash where the spinner was.
        ->assertDispatched('setting-saved', property: 'title')
        ->assertSet('showSettingsModal', true);

    expect($crossword->fresh()->title)->toBe('Autosaved');

    $component->set('minAnswerLength', 5);
    expect($crossword->fresh()->metadata['min_answer_length'])->toBe(5);

    $component->set('metaAnswerReveal', false);
    expect($crossword->fresh()->meta_answer_reveal)->toBeFalse();

    $component->set('layout', CrosswordLayout::CluesLeft->value);
    expect($crossword->fresh()->layout)->toBe(CrosswordLayout::CluesLeft);

    $component->set('newMetaAnswer', 'OWLS')->call('addMetaAnswer');
    expect($crossword->fresh()->meta_answers)->toBe(['OWLS']);

    $component->call('removeMetaAnswer', 0);
    expect($crossword->fresh()->meta_answers)->toBeNull();

    $component->call('addTag', $tag->id);
    expect($crossword->fresh()->tags->pluck('id')->all())->toBe([$tag->id]);

    $component->call('removeTag', $tag->id);
    expect($crossword->fresh()->tags)->toHaveCount(0);
});

test('grid dimensions do not autosave until Resize Grid is pressed', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 15, 'height' => 15]);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('resizeWidth', 9)
        ->set('resizeHeight', 9)
        ->assertNotDispatched('saved')
        ->html();

    expect($crossword->fresh()->width)->toBe(15)
        ->and($crossword->fresh()->height)->toBe(15);

    // Each autosaving text or number control carries a saved check over its
    // end edge, in both surfaces plus the mobile title row; the dimension
    // inputs do not.
    foreach (['title', 'author', 'copyright', 'notes', 'minAnswerLength', 'metaAnswerPrompt', 'secretTheme'] as $property) {
        expect(preg_match_all(sprintf('/data-test="saved-check"\s+data-property="%s"/', $property), $html))
            ->toBe($property === 'title' ? 3 : 2, $property);
    }
    expect($html)->not->toContain('data-property="resizeWidth"')
        ->not->toContain('data-property="resizeHeight"')
        ->toContain('x-on:setting-saved.window="flashSettingSaved($event.detail.property)"');

    // The dimension inputs stay deferred; every other settings field is live,
    // text and number fields a short debounce after typing.
    $form = editorSettingsPaneHtml($html);
    expect($form)->toContain('wire:model="resizeWidth"')
        ->toContain('wire:model="resizeHeight"')
        ->toContain('wire:model.live.debounce.750ms="title"')
        ->toContain('wire:model.live.debounce.750ms="author"')
        ->toContain('wire:model.live.debounce.750ms="copyright"')
        ->toContain('wire:model.live.debounce.750ms="notes"')
        ->toContain('wire:model.live.debounce.750ms="minAnswerLength"')
        ->toContain('wire:model.live.debounce.750ms="metaAnswerPrompt"')
        ->toContain('wire:model.live.debounce.750ms="secretTheme"')
        ->toContain('wire:model.live="metaAnswerReveal"')
        ->toContain('wire:click="saveMetadata"');
});

test('the title and settings cog sit in a row above the toolbar on mobile', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSeeInOrder(['Puzzle title', 'Puzzle settings', 'Edit', 'Saving...', 'Publish'])
        ->html();

    // The toolbar itself no longer carries the title or the cog.
    preg_match('/<div[^>]*data-test="editor-toolbar"[^>]*>.*?wire:click="attemptPublish"/s', $html, $toolbar);
    expect($toolbar)->not->toBeEmpty()
        ->and($toolbar[0])->not->toContain('Puzzle title')
        ->not->toContain('editor-settings-button');

    // One ordinary in-flow row below `lg`, directly above the toolbar,
    // holding the title input and the cog that opens the settings modal.
    preg_match('/<div[^>]*data-test="editor-title-row"[^>]*>/', $html, $row);
    expect(substr_count($html, 'data-test="editor-title-row"'))->toBe(1)
        ->and($row[0])->not->toContain('fixed')
        ->not->toContain('sticky')
        ->toContain('lg:hidden');

    $rowStart = strpos($html, 'data-test="editor-title-row"');
    $toolbarStart = strpos($html, 'data-test="editor-toolbar"');
    $rowHtml = substr($html, $rowStart, $toolbarStart - $rowStart);
    expect($rowStart)->toBeLessThan($toolbarStart)
        ->and($rowHtml)->toContain('wire:model.live.debounce.750ms="title"')
        ->not->toContain('wire:change=')
        ->toContain("wire:click=\"\$set('showSettingsModal', true)\"")
        ->and(substr_count($html, 'data-test="editor-settings-button"'))->toBe(1)
        ->and($html)->not->toContain('data-test="editor-title-bar"');

    // The suggestions sheet owns the bottom edge of the screen.
    preg_match('/<div[^>]*x-ref="suggestionsSheet"[^>]*>/', $html, $sheet);
    expect($sheet)->not->toBeEmpty()
        ->and($sheet[0])->toContain('bottom-0');
});

test('the editor tools share a row with the mode toggle on mobile', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSeeInOrder(['Edit', 'Undo', 'Redo', 'Auto-fill grid', 'Cells', 'Publish'])
        ->html();

    preg_match('/<div[^>]*data-test="editor-tools-row"[^>]*>/', $html, $row);

    // Below `sm` the tools row is not full-width, so Edit / Solve, Undo,
    // Redo, Clear and Auto-Fill all sit on one centred line.
    preg_match('/<div[^>]*data-test="editor-toolbar"[^>]*>/', $html, $toolbarRow);
    expect($row)->not->toBeEmpty()
        ->and($row[0])->not->toContain('w-full')
        ->and($row[0])->toContain('sm:justify-center')
        ->and($toolbarRow[0])->toContain('justify-center')
        ->and(substr_count($html, 'data-test="editor-undo-button"'))->toBe(1)
        ->and(substr_count($html, 'data-test="editor-redo-button"'))->toBe(1)
        ->and($html)->toContain('x-on:keydown.window="handleShortcutKeydown($event)"');

    // From `sm` the toolbar is a three-column grid: Edit / Solve at the left
    // edge, the tools centred in the middle track, save status and Publish
    // at the right edge. The mode toggle is no longer part of the tools row.
    preg_match('/<div[^>]*data-test="editor-toolbar"[^>]*>/', $html, $toolbar);
    preg_match('/<div[^>]*data-test="editor-mode-toggle"[^>]*>/', $html, $modeToggle);
    expect($toolbar)->not->toBeEmpty()
        ->and($toolbar[0])->toContain('sm:grid sm:grid-cols-[1fr_auto_1fr]')
        ->and($modeToggle)->not->toBeEmpty()
        ->and($modeToggle[0])->toContain('sm:justify-self-start')
        ->and(strpos($html, 'data-test="editor-mode-toggle"'))->toBeLessThan(strpos($html, 'data-test="editor-tools-row"'))
        ->and(strpos($html, 'data-test="editor-tools-row"'))->toBeLessThan(strpos($html, 'data-test="editor-save-status"'))
        ->and($html)->toContain('<div class="flex shrink-0 items-center gap-2 sm:justify-self-end">');

    preg_match('/data-test="editor-tools-row".*?data-test="editor-save-status"/s', $html, $tools);
    expect($tools)->not->toBeEmpty()
        ->and($tools[0])->not->toContain('>Edit<')
        ->and($tools[0])->toContain('data-test="editor-undo-button"')
        ->and($tools[0])->toContain('Auto-fill grid');
});

test('the rotational symmetry toggle lives in the settings flyout, not the toolbar', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    preg_match('/data-test="editor-tools-row".*?wire:click="attemptPublish"/s', $html, $toolbar);
    preg_match('/<ui-modal[^>]*data-test="settings-flyout".*?<\/ui-modal>/s', $html, $flyout);

    expect($toolbar)->not->toBeEmpty()
        ->and($toolbar[0])->not->toContain('Rotational symmetry')
        ->and($flyout)->not->toBeEmpty()
        ->and($flyout[0])->toContain('data-test="symmetry-toggle"')
        ->and($flyout[0])->toContain('Rotational symmetry')
        ->and($flyout[0])->toContain('x-on:change="symmetry = $event.target.checked"');
});

test('settings open in a full-height flyout panel', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    preg_match('/<ui-modal[^>]*data-test="settings-flyout"[^>]*>\s*<dialog[^>]*>/s', $html, $dialog);

    expect($dialog)->not->toBeEmpty()
        ->and($dialog[0])->toContain('data-flux-flyout')
        ->and($dialog[0])->toContain('min-h-dvh');
});

test('settings modal shows the export menu at the top', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSeeInOrder(['Puzzle Settings', 'Export', '.ipuz', '.puz (Across Lite)', '.jpz (Crossword Compiler)', '.pdf (Print-Ready)', 'Title'])
        ->html();

    // Once in the sidebar footer, once in the flyout header.
    expect(substr_count($html, "attemptExport('ipuz')"))->toBe(2);
    expect(strpos(editorSettingsFlyoutHtml($html), "attemptExport('ipuz')"))
        ->toBeLessThan(strpos(editorSettingsFlyoutHtml($html), 'wire:model.live.debounce.750ms="title"'));

    // The sidebar has no heading; Export and Save follow the panels inside
    // the form's scrolling body, after the Suggestions panel, Export first.
    $pane = editorSettingsPaneHtml($html);
    expect($pane)->not->toContain('Puzzle Settings')
        ->and(strpos($pane, 'wire:model.live.debounce.750ms="title"'))->toBeLessThan(strpos($pane, "attemptExport('ipuz')"))
        ->and(strpos($pane, 'data-suggestions-pane'))->toBeLessThan(strpos($pane, 'data-test="settings-pane-actions"'))
        ->and(strpos($pane, "attemptExport('ipuz')"))->toBeLessThan(strpos($pane, 'wire:click="saveMetadata"'))
        ->and(substr_count($pane, 'data-test="settings-pane-actions"'))->toBe(1);

    // The panels' scroll box bleeds past the content and pads back by the
    // same amount, so full-width inputs' focus rings are not clipped.
    preg_match('/<div[^>]*data-test="settings-panels"[^>]*>/', $pane, $panels);
    expect($panels)->not->toBeEmpty()
        ->and($panels[0])->toContain('overflow-y-auto')
        ->toContain('-mx-1.5')
        ->toContain('px-1.5');

    // The row is hidden while the Ideas tab is showing: nothing there to
    // save or export.
    preg_match('/<div[^>]*data-test="settings-pane-actions"[^>]*>/s', $pane, $actions);
    expect($actions)->not->toBeEmpty()
        ->and($actions[0])->toContain('x-show="settingsTab !== \'suggestions\'"');

    // The actions row is part of the form body, not a footer after it.
    preg_match('/<div[^>]*data-test="settings-form".*?data-test="settings-pane-actions"/s', $pane, $inForm);
    expect($inForm)->not->toBeEmpty();
    expect(editorSettingsFlyoutHtml($html))->not->toContain('data-test="settings-pane-actions"');
});

test('settings modal renders the AI theme hint and meta answer prompt as real inputs', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSeeInOrder(['AI Theme Hint', 'Meta Answer', 'Prompt', 'Accepted Answers'])
        ->html();

    // A double quote inside a component attribute stops Blade compiling the
    // tag, which leaks a literal <flux:...> element to the browser and leaves
    // the field label with no input beneath it.
    expect($html)->not->toContain('<flux:');
    expect($html)->toMatch('/<textarea[^>]*wire:model.live.debounce.750ms="secretTheme"/');
    expect($html)->toMatch('/<input[^>]*wire:model.live.debounce.750ms="metaAnswerPrompt"/');
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

test('grid can be resized up to 40 but not beyond', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 5,
        'height' => 5,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('resizeWidth', 40)
        ->set('resizeHeight', 40)
        ->call('resizeGrid')
        ->assertHasNoErrors()
        ->assertDispatched('grid-resized');

    $crossword->refresh();
    expect($crossword->width)->toBe(40)
        ->and($crossword->height)->toBe(40);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('resizeWidth', 41)
        ->set('resizeHeight', 41)
        ->call('resizeGrid')
        ->assertHasErrors(['resizeWidth', 'resizeHeight']);
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

    // The picker is part of the shared settings form, so each surface
    // (sidebar and flyout) carries one card per case.
    $expected = count(CrosswordLayout::cases());
    foreach ([editorSettingsPaneHtml($html), editorSettingsFlyoutHtml($html)] as $surface) {
        expect(substr_count($surface, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 24"'))->toBe($expected);
    }

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

    // Exactly one card is pressed in each surface, and it's the auto-resolved default for this grid width.
    expect(substr_count(editorSettingsPaneHtml($html), 'aria-pressed="true"'))->toBe(1)
        ->and(substr_count(editorSettingsFlyoutHtml($html), 'aria-pressed="true"'))->toBe(1)
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

    expect(substr_count(editorSettingsPaneHtml($html), 'aria-pressed="true"'))->toBe(1)
        ->and(substr_count(editorSettingsFlyoutHtml($html), 'aria-pressed="true"'))->toBe(1)
        ->and(CrosswordLayout::auto(21))->toBe(CrosswordLayout::CluesRight);
});

test('the selected layout card is marked pressed and the others are not', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'layout' => CrosswordLayout::CluesBottom,
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    expect(substr_count(editorSettingsPaneHtml($html), 'aria-pressed="true"'))->toBe(1)
        ->and(substr_count(editorSettingsFlyoutHtml($html), 'aria-pressed="true"'))->toBe(1);
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

test('editor exposes allow_embed defaulting to true', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('allowEmbed', true);
});

test('saveMetadata persists allow_embed toggle', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['allow_embed' => true]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('allowEmbed', false)
        ->call('saveMetadata');

    expect($crossword->fresh()->allow_embed)->toBeFalse();
});

test('editor loads default colors from metadata on mount', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'metadata' => ['colors' => [
            'cell' => '#FEF08A',
            'block' => '#1E293B',
            'circle' => '#DB2777',
            'letter' => '#2563EB',
        ]],
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('cellColor', '#FEF08A')
        ->assertSet('blockColor', '#1E293B')
        ->assertSet('circleColor', '#DB2777')
        ->assertSet('letterColor', '#2563EB');
});

test('saveMetadata persists the grid line color', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('lineColor', '#0F766E')
        ->call('saveMetadata')
        ->assertHasNoErrors();

    expect($crossword->fresh()->metadata['colors'])->toBe(['line' => '#0F766E']);
});

test('saveMetadata validates the grid line color is a hex value', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('lineColor', 'teal')
        ->call('saveMetadata')
        ->assertHasErrors(['lineColor']);
});

test('saveMetadata persists default colors', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('cellColor', '#FEF08A')
        ->set('letterColor', '#2563EB')
        ->call('saveMetadata')
        ->assertHasNoErrors();

    expect($crossword->fresh()->metadata['colors'])->toBe([
        'cell' => '#FEF08A',
        'letter' => '#2563EB',
    ]);
});

test('saveMetadata clearing all default colors removes the colors key', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'metadata' => ['min_answer_length' => 3, 'colors' => ['cell' => '#FEF08A']],
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('cellColor', null)
        ->call('saveMetadata')
        ->assertHasNoErrors();

    $metadata = $crossword->fresh()->metadata;
    expect($metadata)->not->toHaveKey('colors')
        ->and($metadata['min_answer_length'])->toBe(3);
});

test('saveMetadata validates default colors are hex values', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('cellColor', 'red')
        ->call('saveMetadata')
        ->assertHasErrors(['cellColor']);
});

test('every desktop layout leaves the suggestions pane to the settings sidebar, rendered exactly once', function (CrosswordLayout $layout) {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 15,
        'height' => 15,
        'layout' => $layout,
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    expect(substr_count($html, 'data-suggestions-pane'))->toBe(1);
    expect($html)->toContain('handleSuggestionsKeydown($event)');

    // The pane is the sidebar's Suggestions tab, not part of the layout.
    expect(editorSettingsPaneHtml($html))->toContain('data-suggestions-pane');
    expect(strpos($html, 'data-suggestions-pane'))->toBeLessThan(strpos($html, 'data-test="editor-workspace"'));

    // The tabs and list are shared with the mobile sheet, so they render
    // twice: once in the pane, once in the sheet.
    expect(substr_count($html, 'x-ref="suggestionsSheet"'))->toBe(1);
    expect(substr_count($html, ':aria-selected="suggestionsTab === \'words\'"'))->toBe(2);
    expect(substr_count($html, ':aria-selected="suggestionsTab === \'clues\'"'))->toBe(2);
    expect($html)
        ->toContain("setSuggestionsTab('words')")
        ->toContain("setSuggestionsTab('clues')");
})->with(array_combine(
    array_map(fn (CrosswordLayout $case) => $case->name, CrosswordLayout::cases()),
    array_map(fn (CrosswordLayout $case) => [$case], CrosswordLayout::cases()),
));

test('clue rows carry no suggestion shortcuts or inline popovers', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 15,
        'height' => 15,
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    // No clue row carries a suggestions shortcut any more: on mobile the
    // peek strip at the bottom of the screen opens the sheet for the
    // selected slot, and desktop rows defer to the pane. No inline popovers
    // remain either.
    expect($html)->not->toContain("openSuggestionsSheet('words')")
        ->not->toContain("openSuggestionsSheet('clues')");
    expect($html)
        ->not->toContain('toggleWordSuggestions()')
        ->not->toContain('showWordSuggestions')
        ->not->toContain('showSuggestions &&');

    // Bindings inside the pane's x-if can re-run once after the selection
    // clears, so every dereference of the nullable slot must be null-safe.
    expect($html)->not->toMatch('/suggestionsSlot\.[a-z]/');
});

test('the mobile suggestions sheet renders once with a peek strip that toggles it', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    preg_match('/<div[^>]*x-ref="suggestionsSheet"[^>]*>/', $html, $sheet);

    expect($sheet)->not->toBeEmpty()
        ->and($sheet[0])->toContain('data-suggestions-sheet')
        ->and($sheet[0])->toContain('lg:hidden')
        ->and($sheet[0])->toContain('fixed')
        ->and($sheet[0])->toContain('x-show="suggestionsSlot"')
        ->and(substr_count($html, 'data-test="suggestions-sheet-toggle"'))->toBe(1)
        ->and($html)->toContain('toggleSuggestionsSheet()');

    // Bindings inside the sheet must be null-safe once the selection clears.
    expect($html)->not->toMatch('/suggestionsSlot\.[a-z]/');
});

test('the mobile suggestions sheet is not rendered while a freestyle grid is unlocked', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->freestyle()->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('freestyleLocked', false)
        ->html();

    expect($html)->not->toContain('x-ref="suggestionsSheet"');
});

test('settings flyout groups its fields into details, grid and theme tabs', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    $flyout = editorSettingsFlyoutHtml($html);

    $tabsStart = strpos($flyout, 'role="tablist"');
    $detailsStart = strpos($flyout, 'id="modal-settings-panel-details"');
    $tabs = substr($flyout, $tabsStart, $detailsStart - $tabsStart);

    expect($tabs)->toContain('role="tablist"')
        ->and(substr_count($tabs, 'role="tab"'))->toBe(3)
        ->and($tabs)->toContain('x-on:click="settingsTab = \'details\'"')
        ->and($tabs)->toContain('x-on:click="settingsTab = \'grid\'"')
        ->and($tabs)->toContain('x-on:click="settingsTab = \'theme\'"')
        ->and($tabs)->not->toContain('data-test="settings-tab-error-');

    $gridStart = strpos($flyout, 'id="modal-settings-panel-grid"');
    $themeStart = strpos($flyout, 'id="modal-settings-panel-theme"');
    $footerStart = strpos($flyout, 'wire:click="saveMetadata"');

    expect($detailsStart)->toBeLessThan($gridStart)
        ->and($gridStart)->toBeLessThan($themeStart)
        ->and($themeStart)->toBeLessThan($footerStart);

    $details = substr($flyout, $detailsStart, $gridStart - $detailsStart);
    $grid = substr($flyout, $gridStart, $themeStart - $gridStart);
    $theme = substr($flyout, $themeStart, $footerStart - $themeStart);

    // Export stays in the header above the tabs; Save/Cancel stay in the sticky footer.
    expect(strpos($flyout, "attemptExport('ipuz')"))->toBeLessThan($detailsStart);

    expect($details)->toContain('x-show="settingsTab === \'details\'"')
        ->toContain('wire:model.live.debounce.750ms="title"')
        ->toContain('wire:model.live.debounce.750ms="author"')
        ->toContain('wire:model.live.debounce.750ms="copyright"')
        ->toContain('wire:model.live.debounce.750ms="notes"')
        ->toContain('wire:model.live="allowEmbed"')
        ->toContain('wire:model.live.debounce.300ms="tagSearch"')
        ->not->toContain('wire:model.live.debounce.750ms="minAnswerLength"')
        ->not->toContain('wire:model.live.debounce.750ms="secretTheme"');

    expect($grid)->toContain('x-show="settingsTab === \'grid\'"')
        ->toContain('wire:model.live.debounce.750ms="minAnswerLength"')
        ->toContain('data-test="symmetry-toggle"')
        ->toContain('Layout')
        ->toContain('Default Colors')
        ->toContain('wire:click="resizeGrid"')
        ->not->toContain('wire:model.live.debounce.750ms="title"');

    expect($theme)->toContain('x-show="settingsTab === \'theme\'"')
        ->toContain('wire:model.live.debounce.750ms="secretTheme"')
        ->toContain('Meta Answer')
        ->toContain('wire:model.live.debounce.750ms="metaAnswerPrompt"')
        ->not->toContain('wire:click="resizeGrid"');
});

test('settings tabs flag the tab whose fields failed validation', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('minAnswerLength', 0)
        ->call('saveMetadata')
        ->assertHasErrors(['minAnswerLength'])
        ->html();

    expect($html)->toContain('data-test="settings-tab-error-grid"')
        ->not->toContain('data-test="settings-tab-error-details"')
        ->not->toContain('data-test="settings-tab-error-theme"');
});

test('settings render in a permanent flush sidebar column down the left of the editor on desktop', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['title' => 'Sidebar Puzzle']);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    // One sidebar, desktop only, in the first column of the root grid across
    // both rows. It is always open: no collapsed strip, no overlay panel and
    // no client-side open state. Negative margins pull it out through main's
    // gutters so it butts up to the page edge and the header, and it takes
    // the page colour rather than a card surface.
    preg_match('/<aside[^>]*data-test="settings-pane"[^>]*>/', $html, $aside);
    expect(substr_count($html, 'data-test="settings-pane"'))->toBe(1)
        ->and($aside[0])->toContain('hidden')
        ->toContain('lg:flex')
        ->toContain('min-h-0')
        ->toContain('lg:col-start-1')
        ->toContain('lg:row-start-1')
        ->toContain('lg:row-span-2')
        ->toContain('lg:-ms-8')
        ->toContain('lg:-mt-8')
        ->toContain('lg:-mb-8')
        // The hook app.css uses to trim the upward pull under the guest banner.
        ->toContain('data-settings-pane')
        ->toContain('border-e')
        ->not->toContain('bg-surface')
        ->not->toContain('rounded')
        ->not->toContain('w-10')
        ->not->toContain('absolute')
        ->not->toContain('settingsPaneOpen');

    expect($html)->not->toContain('data-test="settings-pane-panel"')
        ->not->toContain('data-test="settings-pane-open"')
        ->not->toContain('data-test="settings-pane-close"')
        ->not->toContain('settingsPaneOpen');

    // The same export menu, tabs and Save as the flyout sit inside, plus a
    // fourth Suggestions tab whose panel holds the suggestions pane (its
    // own Words / Clue library tabs make six tabs in all).
    $pane = editorSettingsPaneHtml($html);
    expect($pane)->toContain("attemptExport('ipuz')")
        ->toContain('id="pane-settings-panel-details"')
        ->toContain('id="pane-settings-panel-grid"')
        ->toContain('id="pane-settings-panel-theme"')
        ->toContain('id="pane-settings-tab-suggestions"')
        ->toContain('id="pane-settings-panel-suggestions"')
        ->toContain('data-suggestions-pane')
        ->toContain('x-init="mountSuggestionsPane($el)"')
        ->toContain('showSuggestionsPane()')
        ->not->toContain('back to grid')
        ->toContain('wire:model.live.debounce.750ms="title"')
        ->toContain('wire:click="resizeGrid"')
        ->toContain('wire:click="saveMetadata"')
        ->and(substr_count($pane, 'role="tab"'))->toBe(6);

    // The tab is labelled Ideas; the panel's idle heading matches.
    preg_match('/<button[^>]*id="pane-settings-tab-suggestions"[^>]*>\s*Ideas/s', $pane, $ideasTab);
    expect($ideasTab)->not->toBeEmpty()
        ->and($pane)->not->toContain('>Suggestions<');

    // The flyout keeps its three tabs; mobile suggestions stay in the sheet.
    expect($html)->not->toContain('id="modal-settings-tab-suggestions"')
        ->not->toContain('id="modal-settings-panel-suggestions"');

    // Only the sidebar's form switches to Suggestions when the grid asks.
    expect(substr_count($html, 'x-on:editor-reveal-suggestions.window="settingsTab = \'suggestions\'"'))->toBe(1)
        ->and($pane)->toContain('x-on:editor-reveal-suggestions.window')
        ->and(editorSettingsFlyoutHtml($html))->not->toContain('x-on:editor-reveal-suggestions.window');

    // The root is a two-column grid from `lg`: the sidebar comes first so it
    // fills the narrow first track, then the toolbar and the workspace stack
    // in the flexible second one.
    preg_match('/<div[^>]*x-data="crosswordGrid\(.*?class="([^"]*)"/s', $html, $root);
    expect($root)->not->toBeEmpty()
        ->and($root[1])->toContain('lg:grid')
        ->toContain('lg:grid-cols-[18rem_minmax(0,1fr)]')
        ->toContain('lg:grid-rows-[auto_minmax(0,1fr)]');

    preg_match('/<div[^>]*data-test="editor-workspace"[^>]*>/', $html, $workspace);
    expect($workspace)->not->toBeEmpty()
        ->and($workspace[0])->toContain('max-lg:contents')
        ->toContain('lg:flex')
        ->toContain('lg:min-h-0')
        ->and(strpos($html, 'data-test="settings-pane"'))->toBeLessThan(strpos($html, 'data-suggestions-pane'))
        ->and(strpos($html, 'data-suggestions-pane'))->toBeLessThan(strpos($html, 'data-test="editor-tools-row"'))
        ->and(strpos($html, 'data-test="editor-tools-row"'))->toBeLessThan(strpos($html, 'data-test="editor-workspace"'))
        ->and(strpos($html, 'data-test="editor-workspace"'))->toBeLessThan(strpos($html, 'lg:max-h-[calc(100dvh-8rem)]'))
        ->and(strpos($html, 'data-test="editor-workspace"'))->toBeLessThan(strpos($html, 'x-ref="suggestionsSheet"'));

    // The flyout is the mobile surface only.
    preg_match('/<ui-modal[^>]*data-test="settings-flyout"[^>]*>\s*<dialog[^>]*>/s', $html, $dialog);
    expect($dialog)->not->toBeEmpty()
        ->and($dialog[0])->toContain('lg:hidden');
});

test('the settings form renders once per surface with distinct tab and panel ids', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    expect(substr_count($html, 'data-test="settings-form"'))->toBe(2)
        ->and(substr_count($html, 'role="tablist"'))->toBe(2)
        ->and(substr_count($html, 'data-test="symmetry-toggle"'))->toBe(2);

    foreach (['details', 'grid', 'theme'] as $tab) {
        foreach (['pane', 'modal'] as $surface) {
            expect(substr_count($html, sprintf('id="%s-settings-tab-%s"', $surface, $tab)))->toBe(1)
                ->and(substr_count($html, sprintf('aria-controls="%s-settings-panel-%s"', $surface, $tab)))->toBe(1)
                ->and(substr_count($html, sprintf('id="%s-settings-panel-%s"', $surface, $tab)))->toBe(1)
                ->and(substr_count($html, sprintf('aria-labelledby="%s-settings-tab-%s"', $surface, $tab)))->toBe(1);
        }
    }

    // No unprefixed ids survive, so the two copies can never collide.
    expect($html)->not->toContain('id="settings-tab-')
        ->not->toContain('id="settings-panel-');

    // The sidebar's tabs are a full-bleed segmented row of equal-width
    // buttons with hairlines between them and a filled active state; the
    // flyout keeps underline tabs packed to the start.
    preg_match_all('/<div[^>]*data-test="settings-tabs"[^>]*>/', $html, $tabBars);
    expect($tabBars[0])->toHaveCount(2);
    [$paneTabs, $flyoutTabs] = strpos($tabBars[0][0], 'divide-x') !== false
        ? [$tabBars[0][0], $tabBars[0][1]]
        : [$tabBars[0][1], $tabBars[0][0]];
    expect($paneTabs)->toContain('divide-x')
        ->toContain('-mx-4')
        ->and($flyoutTabs)->not->toContain('divide-x')
        ->not->toContain('-mx-4');

    $paneForm = editorSettingsFormHtml($html, 'pane');
    $flyoutForm = editorSettingsFormHtml($html, 'modal');
    expect(substr_count($paneForm, 'flex min-w-0 flex-1 items-center justify-center'))->toBe(4)
        ->and($paneForm)->toContain("'bg-zinc-800 text-white dark:bg-zinc-200 dark:text-zinc-900'")
        ->and($flyoutForm)->not->toContain('flex-1 items-center justify-center')
        ->and(substr_count($flyoutForm, 'border-b-2 px-3 py-2 text-sm font-medium'))->toBe(3)
        ->and($flyoutForm)->toContain("'border-zinc-800 dark:border-zinc-200'");

    // The form is a container, so its grids size to the surface, not the viewport.
    expect(substr_count($html, 'grid-cols-3 gap-2 @sm:grid-cols-4 @lg:grid-cols-5'))->toBe(2)
        ->and(substr_count($html, 'grid-cols-1 gap-2 @sm:grid-cols-2'))->toBe(2);
});

test('settings explanations render as info tooltips beside their labels instead of descriptions', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    $explanations = [
        'When off, solvers can no longer copy the embed code.',
        'Categorize your puzzle so solvers can find it more easily.',
        'Shortest allowed word length in the grid.',
        'Placing or removing a block does the same to its mirror cell',
        'How the grid and clues are arranged on screen.',
        'Default colors for the whole grid.',
        'Existing content will be preserved where dimensions overlap.',
        'Only used by AI Autofill to pick the best fill.',
        'Add a meta answer prompt that solvers see after completing the puzzle.',
        'Add one or more correct answers. Matching is case-insensitive.',
        'When enabled, solvers get instant feedback after submitting their answer.',
    ];

    // Each explanation lives in a toggleable tooltip, once per surface.
    foreach (['pane', 'modal'] as $surface) {
        $form = editorSettingsFormHtml($html, $surface);

        // Flux's field classes mention [data-flux-description] in selectors,
        // so only a real attribute on an element counts.
        expect(substr_count($form, 'data-test="info-tooltip"'))->toBe(count($explanations))
            ->and($form)->not->toMatch('/<[a-z-]+[^>]*\sdata-flux-description[\s>]/');

        // The icon's click must not bubble to the Flux label, which would
        // forward it to the control and flip a switch or checkbox.
        expect(substr_count($form, 'x-on:click.stop'))->toBeGreaterThanOrEqual(count($explanations));

        foreach ($explanations as $explanation) {
            expect(substr_count($form, $explanation))->toBe(1);
            // A toggleable Flux tooltip renders as a ui-dropdown.
            expect($form)->toMatch('/<ui-dropdown[^>]*data-flux-tooltip[^>]*>(?:(?!<\/ui-dropdown>).)*'.preg_quote($explanation, '/').'/s');
        }
    }
});

test('the toolbar has no progress meters and its publish button carries the completion ring', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSeeInOrder(['Auto-fill grid', 'Saving...', 'Saved', 'Publish'])
        ->assertDontSee('Cells with a letter')
        ->assertDontSee('Clues with text')
        ->html();

    expect($html)->not->toContain('data-test="editor-progress"')
        ->not->toContain('data-test="editor-progress-cells"')
        ->not->toContain('data-test="editor-progress-clues"')
        ->not->toContain('cellsFillPercent')
        ->not->toContain('cluesFillPercent');

    // The save status sits directly before the publish button, outside the tools row.
    preg_match('/data-test="editor-save-status".*?data-test="editor-toolbar-publish"/s', $html, $saveStatus);
    expect($saveStatus)->not->toBeEmpty()
        ->and($saveStatus[0])->not->toContain('data-test="editor-tools-row"');

    // The toolbar button carries the pooled ring, like the mobile one.
    preg_match('/<div[^>]*data-test="editor-toolbar-publish".*?data-test="editor-publish-button".*?<\/ui-tooltip>/s', $html, $toolbarButton);
    expect($toolbarButton)->not->toBeEmpty()
        ->and($toolbarButton[0])->toContain('data-test="editor-publish-progress"')
        ->toContain(':stroke-dashoffset="100 - totalFillPercent"')
        ->toContain("x-text=\"totalFillPercent + '% complete'\"")
        ->and($html)->toContain('x-bind:class="isReadyToPublish ? \'btn-amber-solid\' : \'btn-amber-outline\'"');
});

test('on mobile the publish button sits beside the clue tabs and carries a pooled completion ring', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    // The toolbar publish button is desktop only.
    preg_match('/<div[^>]*data-test="editor-toolbar-publish"[^>]*>/', $html, $toolbarPublish);
    expect($toolbarPublish)->not->toBeEmpty()
        ->and($toolbarPublish[0])->toContain('hidden lg:contents');

    // The mobile tab row holds Across, Down, then Publish with the ring.
    preg_match('/<div[^>]*data-test="editor-mobile-clue-tabs"[^>]*>.*?data-test="editor-publish-button-mobile".*?<\/flux:tooltip>|<div[^>]*data-test="editor-mobile-clue-tabs"[^>]*>/s', $html, $tabs);
    $tabsStart = strpos($html, 'data-test="editor-mobile-clue-tabs"');
    $mobileButton = strpos($html, 'data-test="editor-publish-button-mobile"');
    $ring = strpos($html, 'data-test="editor-publish-progress"', $tabsStart);
    expect($tabsStart)->not->toBeFalse()
        ->and(strpos($html, '>Across<', $tabsStart))->toBeLessThan(strpos($html, '>Down<', $tabsStart))
        ->and(strpos($html, '>Down<', $tabsStart))->toBeLessThan($mobileButton)
        ->and($ring)->toBeGreaterThan($tabsStart)
        ->and(substr_count($html, 'data-test="editor-publish-button-mobile"'))->toBe(1)
        ->and(substr_count($html, 'data-test="editor-publish-button"'))->toBe(1)
        // Both buttons carry the ring: one in the toolbar, one beside the tabs.
        ->and(substr_count($html, 'data-test="editor-publish-progress"'))->toBe(2);

    $ringHtml = substr($html, $ring, 1200);
    expect($ringHtml)->toContain(':stroke-dashoffset="100 - totalFillPercent"')
        ->toContain(":class=\"isReadyToPublish ? '' : totalFillColorClass\"")
        ->toContain('x-show="totalFillPercent > 0"')
        ->toContain("x-text=\"totalFillPercent + '% complete'\"");
});

test('a published puzzle shows Unpublish beside the mobile clue tabs', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->published()->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    $tabsStart = strpos($html, 'data-test="editor-mobile-clue-tabs"');
    $mobile = substr($html, $tabsStart, strpos($html, 'data-test="editor-publish-button-mobile"') - $tabsStart + 2000);

    expect($mobile)->toContain('Unpublish')
        ->toContain('data-test="editor-publish-progress"')
        ->toContain('btn-muted-outline');
});

test('an unlocked freestyle grid keeps the toolbar publish button on every width', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->freestyle()->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('freestyleLocked', false)
        ->html();

    preg_match('/<div[^>]*data-test="editor-toolbar-publish"[^>]*>/', $html, $toolbarPublish);

    // No clue tabs render yet, so there is no mobile copy to hand off to.
    expect($toolbarPublish)->not->toBeEmpty()
        ->and($toolbarPublish[0])->not->toContain('hidden')
        ->and($html)->not->toContain('data-test="editor-mobile-clue-tabs"')
        ->and($html)->not->toContain('data-test="editor-publish-button-mobile"');
});

test('clue numbers share a fixed-width column so the clue inputs align', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 15, 'height' => 15]);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    // Two desktop panels plus the Across and Down mobile lists, every number
    // in the same fixed, right-aligned slot regardless of its digit count.
    $sized = '<span class="mt-px w-6 shrink-0 text-right text-xs font-bold tabular-nums text-zinc-600" x-text="clue.displayNumber"></span>';
    expect(substr_count($html, $sized))->toBe(4)
        ->and($html)->not->toContain('<span class="mt-px text-xs font-bold text-zinc-600" x-text="clue.displayNumber">');
});

test('the editor root only stretches to full height on desktop', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 5, 'height' => 5]);

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    preg_match('/<div[^>]*x-data="crosswordGrid\(.*?class="([^"]*)"/s', $html, $root);

    // Below `lg` the page scrolls and the grid sits directly above the clue
    // list; a full-height root would spread them apart on small grids.
    expect($root)->not->toBeEmpty()
        ->and($root[1])->toContain('lg:h-full')
        ->and(preg_split('/\s+/', $root[1]))->not->toContain('h-full');
});

test('the cell context menu offers Place block and Remove block', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])->html();

    expect($html)->toContain("'Remove block' : 'Place block'")
        ->not->toContain('Make white')
        ->not->toContain('Make black');
});
