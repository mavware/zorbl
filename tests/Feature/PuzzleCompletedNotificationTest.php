<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use App\Notifications\PuzzleCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('sends notification to constructor when someone completes their puzzle via API', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->published()->create();

    Sanctum::actingAs($solver);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
        'is_completed' => true,
        'solve_time_seconds' => 300,
    ])->assertCreated();

    Notification::assertSentTo($constructor, PuzzleCompleted::class, function ($notification) use ($crossword, $solver) {
        return $notification->crossword->id === $crossword->id
            && $notification->solver->id === $solver->id
            && $notification->solveTimeSeconds === 300;
    });
});

it('does not notify constructor when solving your own puzzle via API', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->published()->create();

    Sanctum::actingAs($constructor);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
        'is_completed' => true,
        'solve_time_seconds' => 300,
    ])->assertCreated();

    Notification::assertNotSentTo($constructor, PuzzleCompleted::class);
});

it('does not send duplicate notification when puzzle was already completed via API', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->published()->create();

    PuzzleAttempt::factory()->for($solver)->for($crossword)->completed()->create();

    Sanctum::actingAs($solver);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
        'is_completed' => true,
        'solve_time_seconds' => 300,
    ])->assertSuccessful();

    Notification::assertNotSentTo($constructor, PuzzleCompleted::class);
});

it('does not send notification for non-completion saves via API', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->published()->create();

    Sanctum::actingAs($solver);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
        'is_completed' => false,
    ])->assertCreated();

    Notification::assertNotSentTo($constructor, PuzzleCompleted::class);
});

it('includes solve time in notification body', function () {
    $constructor = User::factory()->create();
    $solver = User::factory()->create(['name' => 'Jane Solver']);
    $crossword = Crossword::factory()->for($constructor)->published()->create(['title' => 'Test Puzzle']);

    $notification = new PuzzleCompleted($crossword, $solver, 185);
    $data = $notification->toArray($constructor);

    expect($data['type'])->toBe('puzzle.completed')
        ->and($data['title'])->toContain('Jane Solver')
        ->and($data['title'])->toContain('Test Puzzle')
        ->and($data['body'])->toContain('3:05')
        ->and($data['crossword_id'])->toBe($crossword->id)
        ->and($data['solver_id'])->toBe($solver->id);
});

it('handles null solve time gracefully', function () {
    $constructor = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->published()->create();

    $notification = new PuzzleCompleted($crossword, $solver);
    $data = $notification->toArray($constructor);

    expect($data['body'])->toBeNull();
});

it('formats hours in solve time', function () {
    $constructor = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->published()->create();

    $notification = new PuzzleCompleted($crossword, $solver, 3661);
    $data = $notification->toArray($constructor);

    expect($data['body'])->toContain('1:01:01');
});
