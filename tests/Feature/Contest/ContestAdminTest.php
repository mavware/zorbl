<?php

use App\Filament\Resources\Contests\Pages\CreateContest;
use App\Filament\Resources\Contests\Pages\EditContest;
use App\Filament\Resources\Contests\Pages\ListContests;
use App\Models\Contest;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('admin can view contest list', function () {
    Contest::factory()->active()->create(['title' => 'Test Contest']);

    Livewire::test(ListContests::class)
        ->assertCanSeeTableRecords(Contest::all())
        ->assertSuccessful();
});

test('admin can create a contest', function () {
    Livewire::test(CreateContest::class)
        ->fillForm([
            'title' => 'New Contest',
            'slug' => 'new-contest',
            'meta_answer' => 'ANSWER',
            'status' => 'draft',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(8),
        ])
        ->call('create')
        ->assertNotified();

    $this->assertDatabaseHas('contests', [
        'title' => 'New Contest',
        'slug' => 'new-contest',
    ]);
});

test('admin can edit a contest', function () {
    $contest = Contest::factory()->create(['user_id' => $this->admin->id]);

    Livewire::test(EditContest::class, ['record' => $contest->id])
        ->fillForm([
            'title' => 'Updated Contest',
        ])
        ->call('save')
        ->assertNotified();

    expect($contest->fresh()->title)->toBe('Updated Contest');
});
