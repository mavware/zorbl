<?php

use App\Filament\Resources\ClueEntries\Pages\ListClueEntries;
use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\User;
use App\Services\ClueHarvester;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('saving a clue entry stores its quality issues', function () {
    $entry = ClueEntry::factory()->create(['answer' => 'CAT', 'clue' => 'Cat nap spot']);

    expect($entry->fresh()->quality_issues)->toHaveCount(1)
        ->and($entry->fresh()->quality_issues[0]['code'])->toBe('answer_in_clue');
});

test('a clean clue entry stores no quality issues', function () {
    $entry = ClueEntry::factory()->create(['answer' => 'CAT', 'clue' => 'Feline pet']);

    expect($entry->fresh()->quality_issues)->toBeNull();
});

test('editing a clue rechecks its quality', function () {
    $entry = ClueEntry::factory()->create(['answer' => 'CAT', 'clue' => 'Cat nap spot']);

    $entry->update(['clue' => 'Feline pet']);

    expect($entry->fresh()->quality_issues)->toBeNull();
});

test('clues harvested from a published puzzle store their quality issues', function () {
    $crossword = Crossword::factory()->create([
        'width' => 3,
        'height' => 1,
        'grid' => [[1, 0, 0]],
        'solution' => [['C', 'A', 'T']],
        'clues_across' => [['number' => 1, 'clue' => 'Goes with 5-Down']],
        'clues_down' => [],
    ]);

    app(ClueHarvester::class)->harvest($crossword);

    $entry = ClueEntry::where('answer', 'CAT')->sole();
    expect(array_column($entry->quality_issues, 'code'))->toBe(['cross_reference']);
});

test('the editor checks clue quality against the current answers', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $issues = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('checkClueQuality', [
            ['direction' => 'across', 'number' => 1, 'clue' => 'Cat nap spot', 'answer' => 'CAT'],
            ['direction' => 'down', 'number' => 2, 'clue' => 'Feline pet', 'answer' => null],
            ['direction' => 'sideways', 'number' => 3, 'clue' => 'TODO', 'answer' => null],
        ])
        ->effects['returns'][0];

    expect(array_keys($issues))->toBe(['across-1'])
        ->and($issues['across-1'][0]['code'])->toBe('answer_in_clue');
});

test('the clue library marks clues with quality issues', function () {
    ClueEntry::factory()->standalone()->create([
        'answer' => 'CAT',
        'clue' => 'Cat nap spot',
        'status' => ClueEntry::STATUS_APPROVED,
    ]);

    $this->get(route('clues.index'))
        ->assertSuccessful()
        ->assertSee('data-test="clue-quality-chip"', false)
        ->assertSee('Clue contains the answer');
});

test('the clue library does not mark clean clues', function () {
    ClueEntry::factory()->standalone()->create([
        'answer' => 'CAT',
        'clue' => 'Feline pet',
        'status' => ClueEntry::STATUS_APPROVED,
    ]);

    $this->get(route('clues.index'))
        ->assertSuccessful()
        ->assertDontSee('data-test="clue-quality-chip"', false);
});

test('admins can filter pending clues to those with quality issues', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $flagged = ClueEntry::factory()->create(['answer' => 'CAT', 'clue' => 'Cat nap spot', 'status' => ClueEntry::STATUS_PENDING]);
    $clean = ClueEntry::factory()->create(['answer' => 'CAT', 'clue' => 'Feline pet', 'status' => ClueEntry::STATUS_PENDING]);

    Livewire::test(ListClueEntries::class)
        ->assertTableColumnStateSet('quality_issues', '1 issue', $flagged)
        ->filterTable('has_quality_issues')
        ->assertCanSeeTableRecords([$flagged])
        ->assertCanNotSeeTableRecords([$clean]);
});

test('the editor does not flag clues that reference other clues in the puzzle', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $issues = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('checkClueQuality', [
            ['direction' => 'across', 'number' => 1, 'clue' => 'Goes with 22 Across', 'answer' => 'OREO'],
        ])
        ->effects['returns'][0];

    expect($issues)->toBe([]);
});

test('short and long library clues are not stored as quality issues', function () {
    $short = ClueEntry::factory()->create(['answer' => 'NAY', 'clue' => 'No']);
    $long = ClueEntry::factory()->create(['answer' => 'NAY', 'clue' => str_repeat('word ', 30)]);

    expect($short->fresh()->quality_issues)->toBeNull()
        ->and($long->fresh()->quality_issues)->toBeNull();
});
