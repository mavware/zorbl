<?php

use App\Enums\WebhookEvent;
use App\Jobs\DispatchWebhooks;
use App\Models\Crossword;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

test('webhook job delivers to subscribed endpoints', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $owner = User::factory()->create();
    $endpoint = WebhookEndpoint::factory()->for($owner)->forEvents([WebhookEvent::PuzzleCompleted])->create();

    $job = new DispatchWebhooks(WebhookEvent::PuzzleCompleted, $owner->id, [
        'puzzle_id' => 1,
        'puzzle_title' => 'Test Puzzle',
    ]);

    $job->handle();

    expect($endpoint->deliveries()->count())->toBe(1);

    $delivery = $endpoint->deliveries()->first();
    expect($delivery->success)->toBeTrue()
        ->and($delivery->response_code)->toBe(200)
        ->and($delivery->event)->toBe('puzzle.completed');
});

test('webhook job skips endpoints not subscribed to the event', function () {
    Http::fake();

    $owner = User::factory()->create();
    WebhookEndpoint::factory()->for($owner)->forEvents([WebhookEvent::PuzzleLiked])->create();

    $job = new DispatchWebhooks(WebhookEvent::PuzzleCompleted, $owner->id, ['puzzle_id' => 1]);
    $job->handle();

    Http::assertNothingSent();
});

test('webhook job skips inactive endpoints', function () {
    Http::fake();

    $owner = User::factory()->create();
    WebhookEndpoint::factory()->for($owner)->inactive()->forEvents([WebhookEvent::PuzzleCompleted])->create();

    $job = new DispatchWebhooks(WebhookEvent::PuzzleCompleted, $owner->id, ['puzzle_id' => 1]);
    $job->handle();

    Http::assertNothingSent();
});

test('webhook job records failed deliveries', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $owner = User::factory()->create();
    $endpoint = WebhookEndpoint::factory()->for($owner)->forEvents([WebhookEvent::PuzzleCompleted])->create();

    $job = new DispatchWebhooks(WebhookEvent::PuzzleCompleted, $owner->id, ['puzzle_id' => 1]);
    $job->handle();

    $delivery = $endpoint->deliveries()->first();
    expect($delivery->success)->toBeFalse()
        ->and($delivery->response_code)->toBe(500);
});

test('webhook job sends correct signature header', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $owner = User::factory()->create();
    $endpoint = WebhookEndpoint::factory()->for($owner)->forEvents([WebhookEvent::PuzzleCompleted])->create();

    $job = new DispatchWebhooks(WebhookEvent::PuzzleCompleted, $owner->id, ['puzzle_id' => 1]);
    $job->handle();

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Webhook-Signature')
            && $request->hasHeader('X-Webhook-Event');
    });
});

test('webhook job updates last_triggered_at on endpoint', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $owner = User::factory()->create();
    $endpoint = WebhookEndpoint::factory()->for($owner)->forEvents([WebhookEvent::PuzzleCompleted])->create();

    expect($endpoint->last_triggered_at)->toBeNull();

    $job = new DispatchWebhooks(WebhookEvent::PuzzleCompleted, $owner->id, ['puzzle_id' => 1]);
    $job->handle();

    $endpoint->refresh();
    expect($endpoint->last_triggered_at)->not->toBeNull();
});

test('puzzle completion dispatches webhook job', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $crossword = Crossword::factory()->for($owner)->published()->withBlocks()->withSolution()->create();
    WebhookEndpoint::factory()->for($owner)->forEvents([WebhookEvent::PuzzleCompleted])->create();

    $solver = User::factory()->create();
    Sanctum::actingAs($solver);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => $crossword->solution,
        'is_completed' => true,
        'solve_time_seconds' => 120,
    ]);

    Queue::assertPushed(DispatchWebhooks::class, function ($job) {
        return $job->event === WebhookEvent::PuzzleCompleted;
    });
});

test('new puzzle attempt dispatches attempt started webhook', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $crossword = Crossword::factory()->for($owner)->published()->withBlocks()->withSolution()->create();
    WebhookEndpoint::factory()->for($owner)->forEvents([WebhookEvent::PuzzleAttemptStarted])->create();

    $solver = User::factory()->create();
    Sanctum::actingAs($solver);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
        'is_completed' => false,
    ]);

    Queue::assertPushed(DispatchWebhooks::class, function ($job) {
        return $job->event === WebhookEvent::PuzzleAttemptStarted;
    });
});

test('webhook job delivers to multiple endpoints', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $owner = User::factory()->create();
    WebhookEndpoint::factory()->count(3)->for($owner)->forEvents([WebhookEvent::PuzzleCompleted])->create();

    $job = new DispatchWebhooks(WebhookEvent::PuzzleCompleted, $owner->id, ['puzzle_id' => 1]);
    $job->handle();

    Http::assertSentCount(3);
});

test('webhook job handles connection errors gracefully', function () {
    Http::fake(['*' => fn () => throw new Exception('Connection refused')]);

    $owner = User::factory()->create();
    $endpoint = WebhookEndpoint::factory()->for($owner)->forEvents([WebhookEvent::PuzzleCompleted])->create();

    $job = new DispatchWebhooks(WebhookEvent::PuzzleCompleted, $owner->id, ['puzzle_id' => 1]);
    $job->handle();

    $delivery = $endpoint->deliveries()->first();
    expect($delivery->success)->toBeFalse()
        ->and($delivery->response_code)->toBeNull()
        ->and($delivery->response_body)->toContain('Connection refused');
});
