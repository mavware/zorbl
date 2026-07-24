<?php

use App\Filament\Resources\AnonymousUsers\Pages\ListAnonymousUsers;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Http\Controllers\ImpersonationController;
use App\Models\Crossword;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

function anonymousUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'name' => 'Guest',
        'is_anonymous' => true,
        'anonymous_token' => (string) Str::uuid(),
        'anonymous_created_at' => now(),
        'email' => null,
        'password' => null,
    ], $attributes));
}

test('non-admin cannot access the anonymous user list', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/anonymous-users')
        ->assertForbidden();
});

test('admin can view anonymous users', function () {
    $guests = collect([anonymousUser(), anonymousUser(), anonymousUser()]);

    Livewire::test(ListAnonymousUsers::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($guests);
});

test('the anonymous user list excludes registered users', function () {
    $registered = User::factory()->create();

    Livewire::test(ListAnonymousUsers::class)
        ->assertCanNotSeeTableRecords([$registered]);
});

test('the main user list still excludes anonymous users', function () {
    $guest = anonymousUser();

    Livewire::test(ListUsers::class)
        ->assertCanNotSeeTableRecords([$guest]);
});

test('the has crosswords filter only shows guests with crosswords', function () {
    $withCrossword = anonymousUser();
    Crossword::factory()->for($withCrossword)->create();
    $withoutCrossword = anonymousUser();

    Livewire::test(ListAnonymousUsers::class)
        ->filterTable('has_crosswords')
        ->assertCanSeeTableRecords([$withCrossword])
        ->assertCanNotSeeTableRecords([$withoutCrossword]);
});

test('the list shows the id, token, and stored session user agent', function () {
    $guest = anonymousUser(['anonymous_token' => 'tok_abcdef123456']);

    DB::table('sessions')->insert([
        'id' => 'sess-'.$guest->id,
        'user_id' => $guest->id,
        'ip_address' => '203.0.113.7',
        'user_agent' => 'Mozilla/5.0 TestAgent',
        'payload' => 'x',
        'last_activity' => now()->timestamp,
    ]);

    Livewire::test(ListAnonymousUsers::class)
        ->assertCanSeeTableRecords([$guest])
        ->assertSee((string) $guest->id)
        ->assertSee('203.0.113.7')
        ->assertSee('Mozilla/5.0 TestAgent');
});

test('a guest with no stored session shows placeholders', function () {
    $guest = anonymousUser();

    Livewire::test(ListAnonymousUsers::class)
        ->assertCanSeeTableRecords([$guest])
        ->assertSee('Never');
});

test('an admin can impersonate an anonymous user', function () {
    $guest = anonymousUser();

    Livewire::test(ListAnonymousUsers::class)
        ->callAction(TestAction::make('impersonate')->table($guest));

    expect(auth()->id())->toBe($guest->id)
        ->and(session(ImpersonationController::SESSION_KEY))->toBe($this->admin->id);
});
