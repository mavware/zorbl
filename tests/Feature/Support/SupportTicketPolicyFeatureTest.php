<?php

use App\Models\SupportTicket;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function makeAdmin(): User
{
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    return $admin;
}

test('ticket owner can view their ticket', function () {
    $user = User::factory()->create();
    $ticket = SupportTicket::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('support.show', $ticket))
        ->assertOk();
});

test('assigned admin can view the ticket', function () {
    $owner = User::factory()->create();
    $admin = makeAdmin();
    $ticket = SupportTicket::factory()->assignedTo($admin)->create(['user_id' => $owner->id]);

    $this->actingAs($admin)
        ->get(route('support.show', $ticket))
        ->assertOk();
});

test('unrelated user cannot view the ticket', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $ticket = SupportTicket::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger)
        ->get(route('support.show', $ticket))
        ->assertForbidden();
});

test('assigned admin can respond to the ticket', function () {
    $owner = User::factory()->create();
    $admin = makeAdmin();
    $ticket = SupportTicket::factory()->open()->assignedTo($admin)->create(['user_id' => $owner->id]);

    $this->actingAs($admin);

    Livewire::test('pages::support.show', ['ticket' => $ticket])
        ->set('responseBody', 'I am looking into your issue right now.')
        ->call('addResponse');

    $this->assertDatabaseHas('ticket_responses', [
        'support_ticket_id' => $ticket->id,
        'user_id' => $admin->id,
        'is_admin_response' => true,
    ]);
});

test('ticket owner response is not marked as admin', function () {
    $owner = User::factory()->create();
    $admin = makeAdmin();
    $ticket = SupportTicket::factory()->open()->assignedTo($admin)->create(['user_id' => $owner->id]);

    $this->actingAs($owner);

    Livewire::test('pages::support.show', ['ticket' => $ticket])
        ->set('responseBody', 'Here is some more info about my issue.')
        ->call('addResponse');

    $this->assertDatabaseHas('ticket_responses', [
        'support_ticket_id' => $ticket->id,
        'user_id' => $owner->id,
        'is_admin_response' => false,
    ]);
});

test('assigned admin cannot respond to closed ticket', function () {
    $owner = User::factory()->create();
    $admin = makeAdmin();
    $ticket = SupportTicket::factory()->closed()->assignedTo($admin)->create(['user_id' => $owner->id]);

    $this->actingAs($admin);

    Livewire::test('pages::support.show', ['ticket' => $ticket])
        ->set('responseBody', 'Trying to respond to a closed ticket.')
        ->call('addResponse')
        ->assertForbidden();
});

test('unrelated user cannot respond to ticket', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $ticket = SupportTicket::factory()->open()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger)
        ->get(route('support.show', $ticket))
        ->assertForbidden();
});
