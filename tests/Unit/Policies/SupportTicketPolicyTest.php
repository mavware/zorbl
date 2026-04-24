<?php

use App\Models\SupportTicket;
use App\Models\User;
use App\Policies\SupportTicketPolicy;

beforeEach(function () {
    $this->policy = new SupportTicketPolicy;

    $this->owner = new User;
    $this->owner->id = 1;

    $this->admin = Mockery::mock(User::class)->makePartial();
    $this->admin->id = 2;
    $this->admin->shouldReceive('hasRole')->with('Admin')->andReturn(true);

    $this->otherUser = Mockery::mock(User::class)->makePartial();
    $this->otherUser->id = 3;
    $this->otherUser->shouldReceive('hasRole')->with('Admin')->andReturn(false);
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
    expect($this->policy->view($this->owner, makeTicket(1)))->toBeTrue();
});

test('view allows the assigned admin', function () {
    expect($this->policy->view($this->admin, makeTicket(1, 2)))->toBeTrue();
});

test('view denies admin who is not assigned', function () {
    expect($this->policy->view($this->admin, makeTicket(1, 99)))->toBeFalse();
});

test('view denies non-owner non-admin', function () {
    expect($this->policy->view($this->otherUser, makeTicket(1)))->toBeFalse();
});

test('create allows any authenticated user', function () {
    expect($this->policy->create($this->owner))->toBeTrue();
});

test('update allows the ticket owner', function () {
    expect($this->policy->update($this->owner, makeTicket(1)))->toBeTrue();
});

test('update allows the assigned admin', function () {
    expect($this->policy->update($this->admin, makeTicket(1, 2)))->toBeTrue();
});

test('update denies admin who is not assigned', function () {
    expect($this->policy->update($this->admin, makeTicket(1, 99)))->toBeFalse();
});

test('update denies non-owner non-admin', function () {
    expect($this->policy->update($this->otherUser, makeTicket(1)))->toBeFalse();
});

test('respond allows the ticket owner on open ticket', function () {
    expect($this->policy->respond($this->owner, makeTicket(1, null, 'open')))->toBeTrue();
});

test('respond denies everyone on closed ticket', function () {
    expect($this->policy->respond($this->owner, makeTicket(1, null, 'closed')))->toBeFalse();
    expect($this->policy->respond($this->admin, makeTicket(1, 2, 'closed')))->toBeFalse();
});

test('respond allows the assigned admin on open ticket', function () {
    expect($this->policy->respond($this->admin, makeTicket(1, 2, 'in_progress')))->toBeTrue();
});

test('respond denies non-owner non-admin', function () {
    expect($this->policy->respond($this->otherUser, makeTicket(1, null, 'open')))->toBeFalse();
});

test('delete allows admin', function () {
    expect($this->policy->delete($this->admin, makeTicket(1)))->toBeTrue();
});

test('delete denies non-admin', function () {
    expect($this->policy->delete($this->otherUser, makeTicket(1)))->toBeFalse();
});
