<?php

use App\Filament\Resources\Contests\Pages\CreateContest;
use App\Filament\Resources\Contests\Pages\EditContest;
use App\Filament\Resources\Contests\Pages\ListContests;
use App\Models\Contest;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('admin can view contest list', function () {
    $contests = Contest::factory()->count(3)->create();

    Livewire::test(ListContests::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($contests);
});

test('non-admin cannot access contest list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/contests')
        ->assertForbidden();
});

test('admin can search contests by title', function () {
    $target = Contest::factory()->create(['title' => 'Unique Contest Title']);
    Contest::factory()->count(3)->create();

    Livewire::test(ListContests::class)
        ->searchTable('Unique Contest Title')
        ->assertCanSeeTableRecords([$target])
        ->assertCountTableRecords(1);
});

test('admin can filter contests by status', function () {
    $active = Contest::factory()->active()->create();
    $draft = Contest::factory()->draft()->create();

    Livewire::test(ListContests::class)
        ->filterTable('status', 'active')
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$draft]);
});

test('admin can create a contest', function () {
    Livewire::test(CreateContest::class)
        ->fillForm([
            'title' => 'My New Contest',
            'slug' => 'my-new-contest',
            'description' => 'A test contest description',
            'rules' => 'Some contest rules',
            'meta_answer' => 'ANSWER',
            'meta_hint' => 'Think carefully',
            'status' => 'draft',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(8)->toDateTimeString(),
            'max_meta_attempts' => 5,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    $this->assertDatabaseHas(Contest::class, [
        'title' => 'My New Contest',
        'slug' => 'my-new-contest',
        'user_id' => $this->admin->id,
    ]);
});

test('create contest sets user_id to authenticated admin', function () {
    Livewire::test(CreateContest::class)
        ->fillForm([
            'title' => 'Admin Contest',
            'slug' => 'admin-contest',
            'meta_answer' => 'SECRET',
            'status' => 'draft',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(8)->toDateTimeString(),
        ])
        ->call('create')
        ->assertNotified();

    expect(Contest::where('slug', 'admin-contest')->first()->user_id)->toBe($this->admin->id);
});

test('create contest requires title', function () {
    Livewire::test(CreateContest::class)
        ->fillForm([
            'title' => null,
            'slug' => 'some-slug',
            'meta_answer' => 'ANSWER',
            'status' => 'draft',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(8)->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['title' => 'required'])
        ->assertNotNotified();
});

test('create contest requires unique slug', function () {
    Contest::factory()->create(['slug' => 'taken-slug']);

    Livewire::test(CreateContest::class)
        ->fillForm([
            'title' => 'Another Contest',
            'slug' => 'taken-slug',
            'meta_answer' => 'ANSWER',
            'status' => 'draft',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(8)->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique'])
        ->assertNotNotified();
});

test('create contest requires ends_at after starts_at', function () {
    Livewire::test(CreateContest::class)
        ->fillForm([
            'title' => 'Bad Dates Contest',
            'slug' => 'bad-dates',
            'meta_answer' => 'ANSWER',
            'status' => 'draft',
            'starts_at' => now()->addDays(8)->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['ends_at' => 'after'])
        ->assertNotNotified();
});

test('admin can edit a contest', function () {
    $contest = Contest::factory()->draft()->create(['title' => 'Original Title']);

    Livewire::test(EditContest::class, ['record' => $contest->id])
        ->assertFormSet([
            'title' => 'Original Title',
        ])
        ->fillForm([
            'title' => 'Updated Title',
        ])
        ->call('save')
        ->assertNotified();

    expect($contest->fresh()->title)->toBe('Updated Title');
});

test('admin can change contest status', function () {
    $contest = Contest::factory()->draft()->create();

    Livewire::test(EditContest::class, ['record' => $contest->id])
        ->fillForm([
            'status' => 'active',
        ])
        ->call('save')
        ->assertNotified();

    expect($contest->fresh()->status)->toBe('active');
});

test('admin can delete a contest', function () {
    $contest = Contest::factory()->create();

    Livewire::test(EditContest::class, ['record' => $contest->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    $this->assertModelMissing($contest);
});

test('edit contest allows reusing own slug', function () {
    $contest = Contest::factory()->create(['slug' => 'my-slug']);

    Livewire::test(EditContest::class, ['record' => $contest->id])
        ->fillForm([
            'title' => 'New Title Same Slug',
            'slug' => 'my-slug',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();
});
