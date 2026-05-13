<?php

use App\Actions\DeleteAccount;
use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

test('account deletion revokes API tokens', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');

    expect($user->tokens()->count())->toBe(1);

    app(DeleteAccount::class)($user);

    expect($user->tokens()->count())->toBe(0);
    unset($token);
});

test('account deletion removes database notifications', function () {
    $user = User::factory()->create();

    DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['message' => 'hi'],
    ]);

    expect($user->notifications()->count())->toBe(1);

    app(DeleteAccount::class)($user);

    expect(DatabaseNotification::query()->where('notifiable_id', $user->id)->count())->toBe(0);
});

test('account deletion removes the user record', function () {
    $user = User::factory()->create();

    app(DeleteAccount::class)($user);

    expect(User::query()->find($user->id))->toBeNull();
});

test('account deletion cascades user-owned content', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();
    PuzzleAttempt::factory()->for($user)->create();

    app(DeleteAccount::class)($user);

    expect(Crossword::query()->find($crossword->id))->toBeNull();
    expect(PuzzleAttempt::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('delete-user-modal calls the DeleteAccount action', function () {
    $user = User::factory()->create();
    $user->createToken('persistent');

    $this->actingAs($user);

    Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect(User::query()->find($user->id))->toBeNull();
    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(0);
});
