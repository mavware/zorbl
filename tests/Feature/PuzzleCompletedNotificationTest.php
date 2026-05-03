<?php

use App\Models\Crossword;
use App\Models\User;
use App\Notifications\PuzzleCompleted;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

test('constructor is notified when another user completes their puzzle via solver', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'width' => 3,
        'height' => 3,
    ]);

    $progress = Crossword::emptySolution(3, 3);

    Livewire::actingAs($solver)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', $progress, true, 120);

    Notification::assertSentTo($constructor, PuzzleCompleted::class, function ($notification) use ($crossword, $solver) {
        return $notification->crossword->id === $crossword->id
            && $notification->solver->id === $solver->id
            && $notification->solveTimeSeconds === 120;
    });
});

test('constructor is not notified when solving their own puzzle', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->create([
        'width' => 3,
        'height' => 3,
    ]);

    $progress = Crossword::emptySolution(3, 3);

    Livewire::actingAs($constructor)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', $progress, true, 60);

    Notification::assertNotSentTo($constructor, PuzzleCompleted::class);
});

test('constructor is not notified on progress save without completion', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'width' => 3,
        'height' => 3,
    ]);

    $progress = Crossword::emptySolution(3, 3);
    $progress[0][0] = 'A';

    Livewire::actingAs($solver)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', $progress, false, 30);

    Notification::assertNotSentTo($constructor, PuzzleCompleted::class);
});

test('constructor is not notified twice for same completion', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'width' => 3,
        'height' => 3,
    ]);

    $progress = Crossword::emptySolution(3, 3);

    $component = Livewire::actingAs($solver)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', $progress, true, 120);

    $component->call('saveProgress', $progress, true, 125);

    Notification::assertSentToTimes($constructor, PuzzleCompleted::class, 1);
});

test('constructor is notified when puzzle completed via API', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'width' => 3,
        'height' => 3,
    ]);

    Sanctum::actingAs($solver);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(3, 3),
        'is_completed' => true,
        'solve_time_seconds' => 90,
    ])->assertSuccessful();

    Notification::assertSentTo($constructor, PuzzleCompleted::class, function ($notification) use ($crossword, $solver) {
        return $notification->crossword->id === $crossword->id
            && $notification->solver->id === $solver->id
            && $notification->solveTimeSeconds === 90;
    });
});

test('constructor is not notified when completing own puzzle via API', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->create([
        'width' => 3,
        'height' => 3,
    ]);

    Sanctum::actingAs($constructor);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(3, 3),
        'is_completed' => true,
        'solve_time_seconds' => 60,
    ])->assertSuccessful();

    Notification::assertNotSentTo($constructor, PuzzleCompleted::class);
});

test('notification payload includes correct data', function () {
    $constructor = User::factory()->create(['name' => 'Alice']);
    $solver = User::factory()->create(['name' => 'Bob']);
    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Sunday Fun',
    ]);

    $notification = new PuzzleCompleted($crossword, $solver, 185);
    $data = $notification->toArray($constructor);

    expect($data['type'])->toBe('puzzle.completed')
        ->and($data['title'])->toContain('Bob')
        ->and($data['title'])->toContain('Sunday Fun')
        ->and($data['title'])->toContain('3:05')
        ->and($data['crossword_id'])->toBe($crossword->id)
        ->and($data['solver_id'])->toBe($solver->id)
        ->and($data['url'])->toBe(route('crosswords.solver', $crossword));
});

test('notification payload omits time when null', function () {
    $constructor = User::factory()->create(['name' => 'Alice']);
    $solver = User::factory()->create(['name' => 'Bob']);
    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Quick One',
    ]);

    $notification = new PuzzleCompleted($crossword, $solver, null);
    $data = $notification->toArray($constructor);

    expect($data['title'])->not->toContain('in ')
        ->and($data['title'])->toContain('Bob')
        ->and($data['title'])->toContain('Quick One');
});
