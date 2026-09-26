<?php

use App\Filament\Resources\ClueEntries\Pages\ListClueEntries;
use App\Models\ClueEntry;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('admin can load the clue entries list with its status tabs', function () {
    $pending = ClueEntry::factory()->create(['status' => ClueEntry::STATUS_PENDING]);
    $approved = ClueEntry::factory()->create(['status' => ClueEntry::STATUS_APPROVED]);

    Livewire::test(ListClueEntries::class)
        ->assertSuccessful()
        ->assertSee('Pending review')
        ->assertSee('Approved')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$approved]);
});

test('switching tabs filters clue entries by status', function () {
    $pending = ClueEntry::factory()->create(['status' => ClueEntry::STATUS_PENDING]);
    $approved = ClueEntry::factory()->create(['status' => ClueEntry::STATUS_APPROVED]);

    Livewire::test(ListClueEntries::class)
        ->set('activeTab', 'approved')
        ->assertCanSeeTableRecords([$approved])
        ->assertCanNotSeeTableRecords([$pending])
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$pending, $approved]);
});

test('the pending clue table shows 50 entries per page by default', function () {
    ClueEntry::factory()->count(51)->create(['status' => ClueEntry::STATUS_PENDING]);

    $component = Livewire::test(ListClueEntries::class)
        ->assertCountTableRecords(51)
        ->assertSet('tableRecordsPerPage', 50);

    expect($component->instance()->getTableRecords())->toHaveCount(50);
});

test('admin can delete a pending clue written by another user from its row', function () {
    $pending = ClueEntry::factory()->create(['status' => ClueEntry::STATUS_PENDING]);

    Livewire::test(ListClueEntries::class)
        ->assertActionVisible(TestAction::make(DeleteAction::class)->table($pending))
        ->callAction(TestAction::make(DeleteAction::class)->table($pending))
        ->assertNotified();

    $this->assertModelMissing($pending);
});

test('non-admin users still cannot delete clues written by others', function () {
    $clue = ClueEntry::factory()->create();

    expect(User::factory()->create()->can('delete', $clue))->toBeFalse()
        ->and($clue->user->can('delete', $clue))->toBeTrue();
});

test('admin can bulk delete pending clues written by other users', function () {
    $pending = ClueEntry::factory()->count(2)->create(['status' => ClueEntry::STATUS_PENDING]);

    Livewire::test(ListClueEntries::class)
        ->selectTableRecords($pending)
        ->assertActionVisible(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified();

    $pending->each(fn (ClueEntry $clue) => $this->assertModelMissing($clue));
});
