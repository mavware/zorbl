<?php

use App\Models\SupportTicket;
use App\Models\User;
use App\Policies\SupportTicketPolicy;

beforeEach(function () {
    $this->policy = new SupportTicketPolicy;

    $this->owner = new User;
    $this->owner->id = 1;

    $this->assignee = new User;
    $this->assignee->id = 2;

    $this->stranger = new User;
    $this->stranger->id = 3;
});

function makeTicket(int $ownerId, ?int $assignedTo = null, string $status = 'open'): SupportTicket
{
    $ticket = new SupportTicket;
    $ticket->user_id = $ownerId;
    $ticket->assigned_to = $assignedTo;
    $ticket->status = $status;

    return $ticket;
}

test('viewAny allows any authenticated user', function () {
    expect($this->policy->viewAny($this->owner))->toBeTrue();
});

test('view allows the ticket owner', function () {
    $ticket = makeTicket(1);

    expect($this->policy->view($this->owner, $ticket))->toBeTrue();
});

test('view allows the assigned admin', function () {
    $ticket = makeTicket(1, 2);

    expect($this->policy->view($this->assignee, $ticket))->toBeTrue();
});

test('view denies unrelated users', function () {
    $ticket = makeTicket(1, 2);

    expect($this->policy->view($this->stranger, $ticket))->toBeFalse();
});

test('view denies when no one is assigned and user is not owner', function () {
    $ticket = makeTicket(1);

    expect($this->policy->view($this->stranger, $ticket))->toBeFalse();
});

test('create allows any authenticated user', function () {
    expect($this->policy->create($this->owner))->toBeTrue();
});

test('respond allows the ticket owner on open ticket', function () {
    $ticket = makeTicket(1);

    expect($this->policy->respond($this->owner, $ticket))->toBeTrue();
});

test('respond allows the assigned admin on open ticket', function () {
    $ticket = makeTicket(1, 2);

    expect($this->policy->respond($this->assignee, $ticket))->toBeTrue();
});

test('respond denies unrelated users', function () {
    $ticket = makeTicket(1, 2);

    expect($this->policy->respond($this->stranger, $ticket))->toBeFalse();
});

test('respond denies on closed ticket even for owner', function () {
    $ticket = makeTicket(1, null, 'closed');

    expect($this->policy->respond($this->owner, $ticket))->toBeFalse();
});

test('respond denies on closed ticket even for assigned admin', function () {
    $ticket = makeTicket(1, 2, 'closed');

    expect($this->policy->respond($this->assignee, $ticket))->toBeFalse();
});

test('respond allows on in_progress ticket for owner', function () {
    $ticket = makeTicket(1, 2, 'in_progress');

    expect($this->policy->respond($this->owner, $ticket))->toBeTrue();
});

test('respond allows on resolved ticket for owner', function () {
    $ticket = makeTicket(1, 2, 'resolved');

    expect($this->policy->respond($this->owner, $ticket))->toBeTrue();
});
