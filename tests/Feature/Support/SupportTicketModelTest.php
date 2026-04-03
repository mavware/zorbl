<?php

use App\Models\SupportTicket;
use App\Models\TicketResponse;
use App\Models\User;

test('support ticket belongs to a user', function () {
    $user = User::factory()->create();
    $ticket = SupportTicket::factory()->create(['user_id' => $user->id]);

    expect($ticket->user->id)->toBe($user->id);
});

test('support ticket can have responses', function () {
    $ticket = SupportTicket::factory()->create();
    TicketResponse::factory()->count(3)->create(['support_ticket_id' => $ticket->id]);

    expect($ticket->responses)->toHaveCount(3);
});

test('support ticket can be assigned to a user', function () {
    $admin = User::factory()->create();
    $ticket = SupportTicket::factory()->assignedTo($admin)->create();

    expect($ticket->assignee->id)->toBe($admin->id);
});

test('ticket response belongs to a ticket and user', function () {
    $user = User::factory()->create();
    $ticket = SupportTicket::factory()->create();
    $response = TicketResponse::factory()->create([
        'support_ticket_id' => $ticket->id,
        'user_id' => $user->id,
    ]);

    expect($response->supportTicket->id)->toBe($ticket->id)
        ->and($response->user->id)->toBe($user->id);
});

test('deleting a ticket cascades to responses', function () {
    $ticket = SupportTicket::factory()->create();
    TicketResponse::factory()->count(3)->create(['support_ticket_id' => $ticket->id]);

    expect(TicketResponse::where('support_ticket_id', $ticket->id)->count())->toBe(3);

    $ticket->delete();

    expect(TicketResponse::where('support_ticket_id', $ticket->id)->count())->toBe(0);
});

test('factory state methods produce correct values', function () {
    $open = SupportTicket::factory()->open()->create();
    expect($open->status)->toBe('open');

    $closed = SupportTicket::factory()->closed()->create();
    expect($closed->status)->toBe('closed')
        ->and($closed->closed_at)->not->toBeNull();

    $bug = SupportTicket::factory()->bugReport()->create();
    expect($bug->category)->toBe('bug_report');

    $urgent = SupportTicket::factory()->urgent()->create();
    expect($urgent->priority)->toBe('urgent');

    $adminResponse = TicketResponse::factory()->adminResponse()->create();
    expect($adminResponse->is_admin_response)->toBeTrue();
});

test('user has support tickets relationship', function () {
    $user = User::factory()->create();
    SupportTicket::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->supportTickets)->toHaveCount(2);
});

test('user has assigned tickets relationship', function () {
    $admin = User::factory()->create();
    SupportTicket::factory()->count(2)->assignedTo($admin)->create();

    expect($admin->assignedTickets)->toHaveCount(2);
});
