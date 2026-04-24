<?php

use App\Filament\Resources\SupportTickets\Pages\EditSupportTicket;
use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Models\SupportTicket;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('admin can view ticket list in admin panel', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $ticket = SupportTicket::factory()->create(['subject' => 'Test Admin Ticket']);

    $this->actingAs($admin);

    Livewire::test(ListSupportTickets::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$ticket]);
});

test('non-admin cannot access admin ticket list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/support-tickets')
        ->assertForbidden();
});

test('admin can update ticket status', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $ticket = SupportTicket::factory()->open()->create(['assigned_to' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test(EditSupportTicket::class, ['record' => $ticket->id])
        ->fillForm([
            'status' => 'in_progress',
        ])
        ->call('save')
        ->assertNotified();

    expect($ticket->fresh()->status)->toBe('in_progress');
});

test('admin can assign ticket', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $ticket = SupportTicket::factory()->create(['assigned_to' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test(EditSupportTicket::class, ['record' => $ticket->id])
        ->fillForm([
            'assigned_to' => $admin->id,
        ])
        ->call('save')
        ->assertNotified();

    expect($ticket->fresh()->assigned_to)->toBe($admin->id);
});

test('admin can change ticket priority', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $ticket = SupportTicket::factory()->create(['assigned_to' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test(EditSupportTicket::class, ['record' => $ticket->id])
        ->fillForm([
            'priority' => 'urgent',
        ])
        ->call('save')
        ->assertNotified();

    expect($ticket->fresh()->priority)->toBe('urgent');
});

test('closing ticket sets closed_at timestamp', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $ticket = SupportTicket::factory()->open()->create(['assigned_to' => $admin->id]);
    expect($ticket->closed_at)->toBeNull();

    $this->actingAs($admin);

    Livewire::test(EditSupportTicket::class, ['record' => $ticket->id])
        ->fillForm([
            'status' => 'closed',
        ])
        ->call('save')
        ->assertNotified();

    expect($ticket->fresh()->closed_at)->not->toBeNull();
});
