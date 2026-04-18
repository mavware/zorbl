<?php

use App\Filament\Resources\SupportTickets\Pages\EditSupportTicket;
use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Models\SupportTicket;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('admin can view ticket list', function () {
    $tickets = SupportTicket::factory()->count(3)->create();

    Livewire::test(ListSupportTickets::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($tickets);
});

test('admin can search tickets by subject', function () {
    $target = SupportTicket::factory()->create(['subject' => 'Unique Broken Widget']);
    SupportTicket::factory()->count(3)->create();

    Livewire::test(ListSupportTickets::class)
        ->searchTable('Unique Broken Widget')
        ->assertCanSeeTableRecords([$target])
        ->assertCountTableRecords(1);
});

test('admin can search tickets by submitter name', function () {
    $submitter = User::factory()->create(['name' => 'Distinctive McName']);
    $target = SupportTicket::factory()->create(['user_id' => $submitter->id]);
    SupportTicket::factory()->count(2)->create();

    Livewire::test(ListSupportTickets::class)
        ->searchTable('Distinctive McName')
        ->assertCanSeeTableRecords([$target])
        ->assertCountTableRecords(1);
});

test('admin can filter tickets by status', function () {
    $open = SupportTicket::factory()->open()->create();
    $closed = SupportTicket::factory()->closed()->create();

    Livewire::test(ListSupportTickets::class)
        ->filterTable('status', 'open')
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$closed]);
});

test('admin can filter tickets by category', function () {
    $bug = SupportTicket::factory()->bugReport()->create();
    $feature = SupportTicket::factory()->featureRequest()->create();

    Livewire::test(ListSupportTickets::class)
        ->filterTable('category', 'bug_report')
        ->assertCanSeeTableRecords([$bug])
        ->assertCanNotSeeTableRecords([$feature]);
});

test('admin can filter tickets by priority', function () {
    $urgent = SupportTicket::factory()->urgent()->create();
    $low = SupportTicket::factory()->lowPriority()->create();

    Livewire::test(ListSupportTickets::class)
        ->filterTable('priority', 'urgent')
        ->assertCanSeeTableRecords([$urgent])
        ->assertCanNotSeeTableRecords([$low]);
});

test('admin can edit ticket and see form populated', function () {
    $ticket = SupportTicket::factory()->create([
        'subject' => 'Something broke',
        'category' => 'bug_report',
        'priority' => 'high',
    ]);

    Livewire::test(EditSupportTicket::class, ['record' => $ticket->id])
        ->assertFormSet([
            'subject' => 'Something broke',
            'category' => 'bug_report',
            'priority' => 'high',
        ])
        ->assertSuccessful();
});

test('closing a ticket stamps closed_at', function () {
    $ticket = SupportTicket::factory()->open()->create();

    Livewire::test(EditSupportTicket::class, ['record' => $ticket->id])
        ->fillForm(['status' => 'closed'])
        ->call('save')
        ->assertNotified();

    expect($ticket->fresh()->closed_at)->not->toBeNull();
});

test('reopening a closed ticket clears closed_at', function () {
    $ticket = SupportTicket::factory()->closed()->create();
    expect($ticket->closed_at)->not->toBeNull();

    Livewire::test(EditSupportTicket::class, ['record' => $ticket->id])
        ->fillForm(['status' => 'open'])
        ->call('save')
        ->assertNotified();

    expect($ticket->fresh())
        ->status->toBe('open')
        ->closed_at->toBeNull();
});

test('resolving a ticket does not set closed_at', function () {
    $ticket = SupportTicket::factory()->open()->create();

    Livewire::test(EditSupportTicket::class, ['record' => $ticket->id])
        ->fillForm(['status' => 'resolved'])
        ->call('save')
        ->assertNotified();

    expect($ticket->fresh())
        ->status->toBe('resolved')
        ->closed_at->toBeNull();
});

test('edit form requires status', function () {
    $ticket = SupportTicket::factory()->create();

    Livewire::test(EditSupportTicket::class, ['record' => $ticket->id])
        ->fillForm(['status' => null])
        ->call('save')
        ->assertHasFormErrors(['status' => 'required']);
});

test('edit form requires category', function () {
    $ticket = SupportTicket::factory()->create();

    Livewire::test(EditSupportTicket::class, ['record' => $ticket->id])
        ->fillForm(['category' => null])
        ->call('save')
        ->assertHasFormErrors(['category' => 'required']);
});

test('edit form requires priority', function () {
    $ticket = SupportTicket::factory()->create();

    Livewire::test(EditSupportTicket::class, ['record' => $ticket->id])
        ->fillForm(['priority' => null])
        ->call('save')
        ->assertHasFormErrors(['priority' => 'required']);
});

test('admin can delete a ticket', function () {
    $ticket = SupportTicket::factory()->create();

    Livewire::test(EditSupportTicket::class, ['record' => $ticket->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    $this->assertModelMissing($ticket);
});

test('non-admin cannot view ticket list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/support-tickets')
        ->assertForbidden();
});
