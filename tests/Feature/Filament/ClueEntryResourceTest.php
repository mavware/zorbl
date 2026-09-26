<?php

use App\Filament\Resources\ClueEntries\Pages\ListClueEntries;
use App\Models\ClueEntry;
use App\Models\User;
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
