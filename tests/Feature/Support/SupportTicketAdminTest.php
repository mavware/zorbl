<?php

use App\Filament\Resources\SupportTickets\Pages\EditSupportTicket;
use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTickets\RelationManagers\ResponsesRelationManager;
use App\Models\SupportTicket;
use App\Models\TicketResponse;
use App\Models\User;
use App\Notifications\SupportTicketReplied;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function makeSupportAdmin(): User
{
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    return $admin;
}

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

test('admin can open and edit a ticket that is not assigned to them', function () {
    $admin = makeSupportAdmin();
    $ticket = SupportTicket::factory()->open()->create();
    expect($ticket->assigned_to)->toBeNull();

    $this->actingAs($admin);

    Livewire::test(EditSupportTicket::class, ['record' => $ticket->id])
        ->assertSuccessful()
        ->assertSeeLivewire(ResponsesRelationManager::class)
        ->fillForm(['status' => 'resolved'])
        ->call('save')
        ->assertNotified();

    expect($ticket->fresh()->status)->toBe('resolved');
});

test('conversation panel shows the ticket description and existing responses', function () {
    $admin = makeSupportAdmin();
    $ticket = SupportTicket::factory()->create();
    $userReply = TicketResponse::factory()->for($ticket)->for($ticket->user)->create(['body' => 'Still broken for me']);
    $staffReply = TicketResponse::factory()->for($ticket)->for($admin)->adminResponse()->create(['body' => 'Looking into it']);
    $otherTicketReply = TicketResponse::factory()->create();

    $this->actingAs($admin);

    Livewire::test(ResponsesRelationManager::class, [
        'ownerRecord' => $ticket,
        'pageClass' => EditSupportTicket::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$userReply, $staffReply])
        ->assertCanNotSeeTableRecords([$otherTicketReply])
        ->assertSee('Still broken for me')
        ->assertSee('Looking into it');
});

test('admin reply is stored as a staff response, updates status, and notifies the submitter', function () {
    Notification::fake();

    $admin = makeSupportAdmin();
    $ticket = SupportTicket::factory()->open()->create();

    $this->actingAs($admin);

    Livewire::test(ResponsesRelationManager::class, [
        'ownerRecord' => $ticket,
        'pageClass' => EditSupportTicket::class,
    ])
        ->callAction(TestAction::make('reply')->table(), [
            'body' => 'Thanks for reporting this, we have a fix on the way.',
            'status' => 'in_progress',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Reply sent');

    $this->assertDatabaseHas('ticket_responses', [
        'support_ticket_id' => $ticket->id,
        'user_id' => $admin->id,
        'body' => 'Thanks for reporting this, we have a fix on the way.',
        'is_admin_response' => true,
    ]);

    expect($ticket->fresh()->status)->toBe('in_progress');

    Notification::assertSentTo($ticket->user, SupportTicketReplied::class, function (SupportTicketReplied $notification) use ($ticket) {
        $payload = $notification->toArray($ticket->user);

        return $notification->ticket->is($ticket)
            && $payload['type'] === 'support.replied'
            && $payload['url'] === route('support.show', $ticket)
            && $payload['body'] === 'Thanks for reporting this, we have a fix on the way.';
    });
});

test('admin reply that closes the ticket sets closed_at', function () {
    Notification::fake();

    $admin = makeSupportAdmin();
    $ticket = SupportTicket::factory()->inProgress()->create();

    $this->actingAs($admin);

    Livewire::test(ResponsesRelationManager::class, [
        'ownerRecord' => $ticket,
        'pageClass' => EditSupportTicket::class,
    ])
        ->callAction(TestAction::make('reply')->table(), [
            'body' => 'This has been fixed. Closing the ticket.',
            'status' => 'closed',
        ])
        ->assertHasNoFormErrors();

    $fresh = $ticket->fresh();
    expect($fresh->status)->toBe('closed')
        ->and($fresh->closed_at)->not->toBeNull();
});

test('admin reply requires a message of at least five characters', function () {
    $admin = makeSupportAdmin();
    $ticket = SupportTicket::factory()->open()->create();

    $this->actingAs($admin);

    Livewire::test(ResponsesRelationManager::class, [
        'ownerRecord' => $ticket,
        'pageClass' => EditSupportTicket::class,
    ])
        ->callAction(TestAction::make('reply')->table(), [
            'body' => 'ok',
            'status' => 'in_progress',
        ])
        ->assertHasFormErrors(['body']);

    $this->assertDatabaseCount('ticket_responses', 0);
});

test('admin can edit their own response but not the submitter response', function () {
    $admin = makeSupportAdmin();
    $ticket = SupportTicket::factory()->create();
    $ownReply = TicketResponse::factory()->for($ticket)->for($admin)->adminResponse()->create(['body' => 'Original wording']);
    $userReply = TicketResponse::factory()->for($ticket)->for($ticket->user)->create();

    $this->actingAs($admin);

    Livewire::test(ResponsesRelationManager::class, [
        'ownerRecord' => $ticket,
        'pageClass' => EditSupportTicket::class,
    ])
        ->assertActionVisible(TestAction::make('edit')->table($ownReply))
        ->assertActionHidden(TestAction::make('edit')->table($userReply))
        ->callAction(TestAction::make('edit')->table($ownReply), [
            'body' => 'Corrected wording',
        ])
        ->assertHasNoFormErrors();

    expect($ownReply->fresh()->body)->toBe('Corrected wording');
});
