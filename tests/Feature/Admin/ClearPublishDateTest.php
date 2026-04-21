<?php

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

test('clear publish date action exists on contest list', function () {
    Livewire::test(ListContests::class)
        ->assertTableBulkActionExists('clearPublishDate');
});

test('admin can clear publish date on scheduled contests', function () {
    $scheduled = Contest::factory()
        ->count(2)
        ->scheduledPublish(now()->addDays(3))
        ->create();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('clearPublishDate', $scheduled)
        ->assertNotified('Cleared publish date on 2 contest(s)');

    foreach ($scheduled as $contest) {
        expect($contest->fresh()->publish_at)->toBeNull();
    }
});

test('clear publish date skips contests without a publish date', function () {
    $scheduled = Contest::factory()->scheduledPublish(now()->addDay())->create();
    $unscheduled = Contest::factory()->draft()->create();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('clearPublishDate', [$scheduled, $unscheduled])
        ->assertNotified('Cleared publish date on 1 contest(s)');

    expect($scheduled->fresh()->publish_at)->toBeNull()
        ->and($unscheduled->fresh()->publish_at)->toBeNull();
});

test('clear publish date warns when no scheduled contests selected', function () {
    $unscheduled = Contest::factory()->draft()->create();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('clearPublishDate', [$unscheduled])
        ->assertNotified('No scheduled contests selected');

    expect($unscheduled->fresh()->publish_at)->toBeNull();
});

test('clear publish date handles single contest', function () {
    $scheduled = Contest::factory()->scheduledPublish(now()->addWeek())->create();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('clearPublishDate', [$scheduled])
        ->assertNotified('Cleared publish date on 1 contest(s)');

    expect($scheduled->fresh()->publish_at)->toBeNull();
});

test('clear publish date does not change contest status', function () {
    $scheduled = Contest::factory()->scheduledPublish(now()->addDay())->create();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('clearPublishDate', [$scheduled]);

    expect($scheduled->fresh()->status)->toBe('draft');
});
