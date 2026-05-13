<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;

test('account export route requires authentication', function () {
    $this->get(route('account.export'))->assertRedirect(route('login'));
});

test('authenticated user can download a JSON export of their data', function () {
    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'bio' => 'Pioneer.',
    ]);

    Crossword::factory()->for($user)->count(2)->create(['title' => 'My Puzzle']);
    PuzzleAttempt::factory()->for($user)->create();

    $response = $this->actingAs($user)->get(route('account.export'));

    $response->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertHeader('content-disposition', 'attachment; filename='.str(config('app.name'))->slug().'-data-export-'.now()->format('Y-m-d').'.json');

    $payload = json_decode($response->streamedContent(), true);

    expect($payload)
        ->toHaveKey('exported_at')
        ->toHaveKey('format_version', 1)
        ->and($payload['profile']['name'])->toBe('Ada Lovelace')
        ->and($payload['profile']['email'])->toBe('ada@example.test')
        ->and($payload['profile']['bio'])->toBe('Pioneer.')
        ->and($payload['crosswords'])->toHaveCount(2)
        ->and($payload['puzzle_attempts'])->toHaveCount(1);
});

test('export does not include other users data', function () {
    $self = User::factory()->create();
    $other = User::factory()->create();

    Crossword::factory()->for($self)->create();
    Crossword::factory()->for($other)->count(3)->create();

    $response = $this->actingAs($self)->get(route('account.export'));

    $payload = json_decode($response->streamedContent(), true);

    expect($payload['crosswords'])->toHaveCount(1)
        ->and($payload['crosswords'][0]['user_id'])->toBe($self->id);
});
