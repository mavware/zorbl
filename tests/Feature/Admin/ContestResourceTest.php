<?php

use App\Filament\Resources\Contests\Pages\CreateContest;
use App\Filament\Resources\Contests\Pages\EditContest;
use App\Filament\Resources\Contests\Pages\ListContests;
use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Crossword;
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

test('create contest requires meta_answer', function () {
    Livewire::test(CreateContest::class)
        ->fillForm([
            'title' => 'No Meta',
            'slug' => 'no-meta',
            'status' => 'draft',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(8)->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['meta_answer' => 'required'])
        ->assertNotNotified();
});

test('create contest requires starts_at', function () {
    Livewire::test(CreateContest::class)
        ->fillForm([
            'title' => 'No Start',
            'slug' => 'no-start',
            'meta_answer' => 'ANSWER',
            'status' => 'draft',
            'starts_at' => null,
            'ends_at' => now()->addDays(8)->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['starts_at' => 'required'])
        ->assertNotNotified();
});

test('create contest requires ends_at', function () {
    Livewire::test(CreateContest::class)
        ->fillForm([
            'title' => 'No End',
            'slug' => 'no-end',
            'meta_answer' => 'ANSWER',
            'status' => 'draft',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['ends_at' => 'required'])
        ->assertNotNotified();
});

test('create contest requires status', function () {
    Livewire::test(CreateContest::class)
        ->fillForm([
            'title' => 'No Status',
            'slug' => 'no-status',
            'meta_answer' => 'ANSWER',
            'status' => null,
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(8)->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['status' => 'required'])
        ->assertNotNotified();
});

test('admin can toggle featured contest', function () {
    $contest = Contest::factory()->create(['is_featured' => false]);

    Livewire::test(EditContest::class, ['record' => $contest->id])
        ->assertFormSet(['is_featured' => false])
        ->fillForm(['is_featured' => true])
        ->call('save')
        ->assertNotified();

    expect($contest->fresh()->is_featured)->toBeTrue();
});

test('admin can update meta_answer', function () {
    $contest = Contest::factory()->create(['meta_answer' => 'OLD']);

    Livewire::test(EditContest::class, ['record' => $contest->id])
        ->fillForm(['meta_answer' => 'NEW'])
        ->call('save')
        ->assertNotified();

    expect($contest->fresh()->meta_answer)->toBe('NEW');
});

test('admin can update max_meta_attempts', function () {
    $contest = Contest::factory()->create(['max_meta_attempts' => 0]);

    Livewire::test(EditContest::class, ['record' => $contest->id])
        ->fillForm(['max_meta_attempts' => 10])
        ->call('save')
        ->assertNotified();

    expect($contest->fresh()->max_meta_attempts)->toBe(10);
});

test('edit contest rejects slug taken by another contest', function () {
    Contest::factory()->create(['slug' => 'taken']);
    $contest = Contest::factory()->create(['slug' => 'mine']);

    Livewire::test(EditContest::class, ['record' => $contest->id])
        ->fillForm(['slug' => 'taken'])
        ->call('save')
        ->assertHasFormErrors(['slug' => 'unique'])
        ->assertNotNotified();
});

test('non-admin cannot access create contest page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/contests/create')
        ->assertForbidden();
});

test('non-admin cannot access edit contest page', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->create();

    $this->actingAs($user)
        ->get("/admin/contests/{$contest->id}/edit")
        ->assertForbidden();
});

test('admin can create contest with crosswords', function () {
    $crosswords = Crossword::factory()->count(2)->published()->create();

    Livewire::test(CreateContest::class)
        ->fillForm([
            'title' => 'Puzzle Contest',
            'slug' => 'puzzle-contest',
            'meta_answer' => 'FINAL',
            'status' => 'draft',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(8)->toDateTimeString(),
            'crosswords' => $crosswords->pluck('id')->toArray(),
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    $contest = Contest::where('slug', 'puzzle-contest')->first();
    expect($contest->crosswords)->toHaveCount(2);
});

test('admin can add crosswords to existing contest', function () {
    $contest = Contest::factory()->create();
    $crosswords = Crossword::factory()->count(3)->published()->create();

    Livewire::test(EditContest::class, ['record' => $contest->id])
        ->fillForm([
            'crosswords' => $crosswords->pluck('id')->toArray(),
        ])
        ->call('save')
        ->assertNotified();

    expect($contest->fresh()->crosswords)->toHaveCount(3);
});

test('table displays entries count', function () {
    $contest = Contest::factory()->create();
    ContestEntry::factory()->count(5)->create(['contest_id' => $contest->id]);

    Livewire::test(ListContests::class)
        ->assertCanSeeTableRecords([$contest])
        ->assertTableColumnStateSet('entries_count', 5, $contest);
});

test('table displays crosswords count', function () {
    $contest = Contest::factory()->create();
    $crosswords = Crossword::factory()->count(3)->published()->create();
    $contest->crosswords()->attach($crosswords);

    Livewire::test(ListContests::class)
        ->assertCanSeeTableRecords([$contest])
        ->assertTableColumnStateSet('crosswords_count', 3, $contest);
});

test('table displays featured icon', function () {
    $featured = Contest::factory()->featured()->create();
    $regular = Contest::factory()->create(['is_featured' => false]);

    Livewire::test(ListContests::class)
        ->assertCanSeeTableRecords([$featured, $regular])
        ->assertTableColumnStateSet('is_featured', true, $featured)
        ->assertTableColumnStateSet('is_featured', false, $regular);
});

test('contest list can be sorted by title', function () {
    Contest::factory()->create(['title' => 'Alpha']);
    Contest::factory()->create(['title' => 'Zeta']);

    Livewire::test(ListContests::class)
        ->sortTable('title')
        ->assertCanSeeTableRecords(Contest::orderBy('title')->get());
});

test('deleting a contest cascades to entries', function () {
    $contest = Contest::factory()->create();
    ContestEntry::factory()->count(3)->create(['contest_id' => $contest->id]);

    Livewire::test(EditContest::class, ['record' => $contest->id])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    $this->assertModelMissing($contest);
    expect(ContestEntry::where('contest_id', $contest->id)->count())->toBe(0);
});
