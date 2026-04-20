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

test('admin can bulk-schedule publish date on draft contests', function () {
    $contests = Contest::factory()->draft()->count(3)->create();
    $publishAt = now()->addDays(5)->startOfMinute();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('schedule_publish', $contests, data: [
            'publish_at' => $publishAt->toDateTimeString(),
        ])
        ->assertNotified('Publish date scheduled');

    foreach ($contests as $contest) {
        expect($contest->fresh()->publish_at->toDateTimeString())
            ->toBe($publishAt->toDateTimeString());
    }
});

test('bulk-schedule only updates draft contests', function () {
    $draft = Contest::factory()->draft()->create();
    $active = Contest::factory()->active()->create();
    $ended = Contest::factory()->ended()->create();
    $publishAt = now()->addDays(3)->startOfMinute();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('schedule_publish', [$draft, $active, $ended], data: [
            'publish_at' => $publishAt->toDateTimeString(),
        ])
        ->assertNotified('Publish date scheduled');

    expect($draft->fresh()->publish_at->toDateTimeString())
        ->toBe($publishAt->toDateTimeString())
        ->and($active->fresh()->publish_at)->toBeNull()
        ->and($ended->fresh()->publish_at)->toBeNull();
});

test('bulk-schedule warns when no draft contests selected', function () {
    $active = Contest::factory()->active()->create();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('schedule_publish', [$active], data: [
            'publish_at' => now()->addDays(3)->toDateTimeString(),
        ])
        ->assertNotified('No draft contests selected');

    expect($active->fresh()->publish_at)->toBeNull();
});

test('bulk-schedule requires a publish date', function () {
    $contest = Contest::factory()->draft()->create();

    Livewire::test(ListContests::class)
        ->callTableBulkAction('schedule_publish', [$contest], data: [
            'publish_at' => null,
        ])
        ->assertHasTableBulkActionErrors(['publish_at' => 'required']);

    expect($contest->fresh()->publish_at)->toBeNull();
});

test('non-admin cannot access the contests admin page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/contests')
        ->assertForbidden();
});
