<?php

use App\Filament\Resources\ClueReports\Pages\CreateClueReport;
use App\Filament\Resources\ClueReports\Pages\EditClueReport;
use App\Filament\Resources\ClueReports\Pages\ListClueReports;
use App\Models\ClueEntry;
use App\Models\ClueReport;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('admin can view clue reports list', function () {
    $clueEntry = ClueEntry::create([
        'answer' => 'TEST',
        'clue' => 'A trial or experiment',
        'user_id' => $this->admin->id,
    ]);

    $report = ClueReport::create([
        'clue_entry_id' => $clueEntry->id,
        'user_id' => $this->admin->id,
        'reason' => 'Inaccurate clue',
    ]);

    Livewire::test(ListClueReports::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$report]);
});

test('admin can create a clue report', function () {
    $clueEntry = ClueEntry::create([
        'answer' => 'WORD',
        'clue' => 'A unit of language',
        'user_id' => $this->admin->id,
    ]);

    $reporter = User::factory()->create();

    Livewire::test(CreateClueReport::class)
        ->fillForm([
            'clue_entry_id' => $clueEntry->id,
            'user_id' => $reporter->id,
            'reason' => 'Offensive content',
        ])
        ->call('create')
        ->assertNotified();

    $this->assertDatabaseHas('clue_reports', [
        'clue_entry_id' => $clueEntry->id,
        'user_id' => $reporter->id,
        'reason' => 'Offensive content',
    ]);
});

test('creating a clue report requires reason', function () {
    $clueEntry = ClueEntry::create([
        'answer' => 'WORD',
        'clue' => 'A unit of language',
        'user_id' => $this->admin->id,
    ]);

    Livewire::test(CreateClueReport::class)
        ->fillForm([
            'clue_entry_id' => $clueEntry->id,
            'user_id' => $this->admin->id,
            'reason' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['reason' => 'required'])
        ->assertNotNotified();
});

test('admin can edit a clue report', function () {
    $clueEntry = ClueEntry::create([
        'answer' => 'EDIT',
        'clue' => 'To revise',
        'user_id' => $this->admin->id,
    ]);

    $report = ClueReport::create([
        'clue_entry_id' => $clueEntry->id,
        'user_id' => $this->admin->id,
        'reason' => 'Original reason',
    ]);

    Livewire::test(EditClueReport::class, ['record' => $report->id])
        ->fillForm([
            'reason' => 'Updated reason',
        ])
        ->call('save')
        ->assertNotified();

    expect($report->fresh()->reason)->toBe('Updated reason');
});

test('admin can delete a clue report', function () {
    $clueEntry = ClueEntry::create([
        'answer' => 'DELETE',
        'clue' => 'To remove',
        'user_id' => $this->admin->id,
    ]);

    $report = ClueReport::create([
        'clue_entry_id' => $clueEntry->id,
        'user_id' => $this->admin->id,
        'reason' => 'To be deleted',
    ]);

    Livewire::test(EditClueReport::class, ['record' => $report->id])
        ->callAction('delete')
        ->assertNotified();

    $this->assertDatabaseMissing('clue_reports', ['id' => $report->id]);
});

test('non-admin cannot access clue reports admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/clue-reports')
        ->assertForbidden();
});
