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

test('bulk schedule publish action exists on contest list', function () {
    Livewire::test(ListContests::class)
        ->assertTableBulkActionExists('schedule_publish');
});

test('bulk schedule publish sets publish_at on draft contests', function () {
    $drafts = Contest::factory()->count(2)->draft()->create();
    $publishAt = now()->addDays(3)->startOfMinute();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('schedule_publish', $drafts, [
            'publish_at' => $publishAt->toDateTimeString(),
        ])
        ->assertNotified('Publish dates scheduled');

    foreach ($drafts as $draft) {
        expect($draft->fresh()->publish_at->toDateTimeString())
            ->toBe($publishAt->toDateTimeString());
    }
});

test('bulk schedule publish skips non-draft contests', function () {
    $draft = Contest::factory()->draft()->create();
    $active = Contest::factory()->active()->create();

    $publishAt = now()->addDays(3)->startOfMinute();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('schedule_publish', [$draft, $active], [
            'publish_at' => $publishAt->toDateTimeString(),
        ])
        ->assertNotified('Publish dates scheduled');

    expect($draft->fresh()->publish_at->toDateTimeString())
        ->toBe($publishAt->toDateTimeString())
        ->and($active->fresh()->publish_at)->toBeNull();
});

test('bulk schedule publish warns when no drafts selected', function () {
    $active = Contest::factory()->active()->create();
    $publishAt = now()->addDays(3)->startOfMinute();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('schedule_publish', [$active], [
            'publish_at' => $publishAt->toDateTimeString(),
        ])
        ->assertNotified('No draft contests selected');

    expect($active->fresh()->publish_at)->toBeNull();
});

test('bulk schedule publish requires publish_at date', function () {
    $draft = Contest::factory()->draft()->create();

    Livewire::test(ListContests::class)
        ->mountTableBulkAction('schedule_publish', [$draft])
        ->setTableBulkActionData(['publish_at' => null])
        ->callMountedTableBulkAction()
        ->assertHasActionErrors(['publish_at' => 'required']);
});

test('bulk schedule publish handles single draft contest', function () {
    $draft = Contest::factory()->draft()->create();
    $publishAt = now()->addWeek()->startOfMinute();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('schedule_publish', [$draft], [
            'publish_at' => $publishAt->toDateTimeString(),
        ])
        ->assertNotified('Publish dates scheduled');

    expect($draft->fresh()->publish_at->toDateTimeString())
        ->toBe($publishAt->toDateTimeString());
});

test('bulk schedule publish overwrites existing publish_at', function () {
    $draft = Contest::factory()->scheduledPublish(now()->addDay())->create();
    $newPublishAt = now()->addDays(5)->startOfMinute();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('schedule_publish', [$draft], [
            'publish_at' => $newPublishAt->toDateTimeString(),
        ])
        ->assertNotified('Publish dates scheduled');

    expect($draft->fresh()->publish_at->toDateTimeString())
        ->toBe($newPublishAt->toDateTimeString());
});

test('bulk schedule publish does not change contest status', function () {
    $draft = Contest::factory()->draft()->create();
    $publishAt = now()->addDays(3)->startOfMinute();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('schedule_publish', [$draft], [
            'publish_at' => $publishAt->toDateTimeString(),
        ]);

    expect($draft->fresh()->status)->toBe('draft');
});
