<?php

use App\Models\Crossword;
use App\Models\User;
use App\Services\AnonymousUserManager;
use Illuminate\Support\Facades\Hash;

test('anonymous user is created with no email or password', function () {
    $manager = app(AnonymousUserManager::class);
    $user = $manager->create();

    expect($user->is_anonymous)->toBeTrue();
    expect($user->email)->toBeNull();
    expect($user->password)->toBeNull();
    expect($user->anonymous_token)->not->toBeNull();
});

test('AnonymousUserManager returns the same row for repeated requests with the same cookie', function () {
    $manager = app(AnonymousUserManager::class);
    $first = $manager->create();

    $request = request();
    $request->cookies->set(AnonymousUserManager::COOKIE_NAME, $first->anonymous_token);

    $second = $manager->getOrCreateForRequest($request);

    expect($second->id)->toBe($first->id);
});

test('anonymous user can open their own editor', function () {
    $anon = app(AnonymousUserManager::class)->create();
    $crossword = Crossword::factory()->for($anon)->create();

    $this->actingAs($anon)
        ->get(route('crosswords.editor', $crossword))
        ->assertOk();
});

test('anonymous user is sent to the build home from the dashboard', function () {
    $anon = app(AnonymousUserManager::class)->create();

    $this->actingAs($anon)
        ->get(route('dashboard'))
        ->assertRedirect(route('crosswords.index', absolute: false));
});

test('anonymous user can reach the build home and sees the guest banner', function () {
    $anon = app(AnonymousUserManager::class)->create();

    $this->actingAs($anon)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('building as a guest');
});

test('publishing is blocked for anonymous users via observer', function () {
    $anon = app(AnonymousUserManager::class)->create();
    $crossword = Crossword::factory()->for($anon)->create(['is_published' => false]);

    expect(fn () => $crossword->update(['is_published' => true]))
        ->toThrow(RuntimeException::class);
});

test('publish policy denies anonymous users', function () {
    $anon = app(AnonymousUserManager::class)->create();
    $crossword = Crossword::factory()->for($anon)->create();

    expect($anon->can('publish', $crossword))->toBeFalse();
});

test('publish policy allows real owners', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    expect($user->can('publish', $crossword))->toBeTrue();
});

test('register conversion upgrades the anonymous user in place', function () {
    $anon = app(AnonymousUserManager::class)->create();
    $crossword = Crossword::factory()->for($anon)->create();

    $this->withCookies([AnonymousUserManager::COOKIE_NAME => $anon->anonymous_token])
        ->post('/register', [
            'name' => 'Real Person',
            'email' => 'real@example.com',
            'password' => 'SuperSecret123!',
        ])
        ->assertRedirect();

    $fresh = $anon->fresh();
    expect($fresh->is_anonymous)->toBeFalse();
    expect($fresh->email)->toBe('real@example.com');
    expect($fresh->password)->not->toBeNull();
    expect($fresh->converted_at)->not->toBeNull();
    expect($fresh->anonymous_token)->toBeNull();
    expect($crossword->fresh()->user_id)->toBe($anon->id);
});

test('register collision with existing email is blocked and anon row preserved', function () {
    $existing = User::factory()->create(['email' => 'taken@example.com']);
    $anon = app(AnonymousUserManager::class)->create();
    Crossword::factory()->for($anon)->create();

    $this->withCookies([AnonymousUserManager::COOKIE_NAME => $anon->anonymous_token])
        ->post('/register', [
            'name' => 'Real Person',
            'email' => 'taken@example.com',
            'password' => 'SuperSecret123!',
        ])
        ->assertSessionHasErrors('email');

    expect($anon->fresh()->is_anonymous)->toBeTrue();
    expect($existing->fresh()->crosswords()->count())->toBe(0);
});

test('login with anon cookie merges the anon puzzle into the real account', function () {
    $real = User::factory()->create([
        'email' => 'real@example.com',
        'password' => Hash::make('password'),
    ]);
    $anon = app(AnonymousUserManager::class)->create();
    $crossword = Crossword::factory()->for($anon)->create();

    $this->withCookies([AnonymousUserManager::COOKIE_NAME => $anon->anonymous_token])
        ->post('/login', [
            'email' => 'real@example.com',
            'password' => 'password',
        ])
        ->assertRedirect();

    expect($crossword->fresh()->user_id)->toBe($real->id);
    expect(User::find($anon->id))->toBeNull();
});

test('prune command deletes old anonymous users and their puzzles', function () {
    $stale = app(AnonymousUserManager::class)->create();
    $stale->forceFill(['anonymous_created_at' => now()->subDays(31)])->save();
    $staleCrossword = Crossword::factory()->for($stale)->create();

    $fresh = app(AnonymousUserManager::class)->create();
    $freshCrossword = Crossword::factory()->for($fresh)->create();

    $real = User::factory()->create();

    $this->artisan('users:prune-anonymous')->assertExitCode(0);

    expect(User::find($stale->id))->toBeNull();
    expect(Crossword::find($staleCrossword->id))->toBeNull();
    expect(User::find($fresh->id))->not->toBeNull();
    expect(Crossword::find($freshCrossword->id))->not->toBeNull();
    expect(User::find($real->id))->not->toBeNull();
});

test('constructors query excludes anonymous users', function () {
    $anon = app(AnonymousUserManager::class)->create();
    $crossword = Crossword::factory()->for($anon)->create();
    // Bypass the observer to simulate a row that slipped through and got published.
    DB::table('crosswords')->where('id', $crossword->id)->update(['is_published' => true]);

    $real = User::factory()->create();
    Crossword::factory()->for($real)->published()->create();

    $constructors = User::where('is_anonymous', false)
        ->whereHas('crosswords', fn ($q) => $q->where('is_published', true))
        ->get();

    expect($constructors->pluck('id'))->toContain($real->id);
    expect($constructors->pluck('id'))->not->toContain($anon->id);
});

test('anonymous user planLimits caps puzzles at one', function () {
    $anon = app(AnonymousUserManager::class)->create();

    expect($anon->planLimits()->maxPuzzles())->toBe(1);
    expect($anon->planLimits()->canExportPdf())->toBeFalse();
    expect($anon->planLimits()->monthlyAiFills())->toBe(0);
});

test('register page is reachable by an anonymous user', function () {
    $anon = app(AnonymousUserManager::class)->create();

    $this->actingAs($anon)
        ->get('/register')
        ->assertOk()
        ->assertSee('Finish signing up');
});

test('register page bounces a real authenticated user', function () {
    $real = User::factory()->create();

    $this->actingAs($real)
        ->get('/register')
        ->assertRedirect();
});
