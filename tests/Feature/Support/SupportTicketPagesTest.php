<?php

use App\Models\SupportTicket;
use App\Models\User;
use Livewire\Livewire;

test('authenticated user can view support ticket list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('support.index'))
        ->assertOk();
});

test('guest is redirected from support pages', function () {
    $this->get(route('support.index'))->assertRedirect(route('login'));
    $this->get(route('support.create'))->assertRedirect(route('login'));
});

test('user only sees their own tickets', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $myTicket = SupportTicket::factory()->create(['user_id' => $user->id, 'subject' => 'My Ticket']);
    $otherTicket = SupportTicket::factory()->create(['user_id' => $otherUser->id, 'subject' => 'Other Ticket']);

    $this->actingAs($user);

    Livewire::test('pages::support.index')
        ->assertSee('My Ticket')
        ->assertDontSee('Other Ticket');
});

test('user can submit a support ticket', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::support.create')
        ->set('subject', 'Grid is not loading')
        ->set('category', 'bug_report')
        ->set('description', 'When I open a puzzle the grid does not render correctly on mobile devices.')
        ->call('submit')
        ->assertRedirect();

    $this->assertDatabaseHas('support_tickets', [
        'user_id' => $user->id,
        'subject' => 'Grid is not loading',
        'category' => 'bug_report',
        'status' => 'open',
        'priority' => 'normal',
    ]);
});

test('ticket submission validates required fields', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::support.create')
        ->set('subject', '')
        ->set('description', '')
        ->call('submit')
        ->assertHasErrors(['subject', 'description']);
});

test('ticket submission validates minimum description length', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::support.create')
        ->set('subject', 'Valid subject here')
        ->set('description', 'Too short')
        ->call('submit')
        ->assertHasErrors(['description']);
});

test('ticket submission validates category is valid', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::support.create')
        ->set('subject', 'Valid subject here')
        ->set('description', 'This is a long enough description for validation to pass.')
        ->set('category', 'invalid_category')
        ->call('submit')
        ->assertHasErrors(['category']);
});

test('user can view their own ticket', function () {
    $user = User::factory()->create();
    $ticket = SupportTicket::factory()->create([
        'user_id' => $user->id,
        'subject' => 'My Detailed Ticket',
    ]);

    $this->actingAs($user)
        ->get(route('support.show', $ticket))
        ->assertOk()
        ->assertSee('My Detailed Ticket');
});

test('user cannot view another users ticket', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ticket = SupportTicket::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->get(route('support.show', $ticket))
        ->assertForbidden();
});

test('user can add a response to their open ticket', function () {
    $user = User::factory()->create();
    $ticket = SupportTicket::factory()->open()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::support.show', ['ticket' => $ticket])
        ->set('responseBody', 'Here is some additional information about my issue.')
        ->call('addResponse');

    $this->assertDatabaseHas('ticket_responses', [
        'support_ticket_id' => $ticket->id,
        'user_id' => $user->id,
        'is_admin_response' => false,
    ]);
});

test('user cannot add response to a closed ticket', function () {
    $user = User::factory()->create();
    $ticket = SupportTicket::factory()->closed()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::support.show', ['ticket' => $ticket])
        ->assertDontSee('Add a Response')
        ->assertSee('This ticket has been closed.');
});

test('status filter shows only matching tickets', function () {
    $user = User::factory()->create();
    SupportTicket::factory()->open()->create(['user_id' => $user->id, 'subject' => 'Open Ticket']);
    SupportTicket::factory()->closed()->create(['user_id' => $user->id, 'subject' => 'Closed Ticket']);
    $this->actingAs($user);

    Livewire::test('pages::support.index')
        ->set('statusFilter', 'open')
        ->assertSee('Open Ticket')
        ->assertDontSee('Closed Ticket');
});
