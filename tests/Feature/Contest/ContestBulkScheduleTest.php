<?php

use App\Filament\Resources\Contests\Pages\ListContests;
use App\Models\Contest;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
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
        ->selectTableRecords($contests->pluck('id')->all())
        ->callAction(TestAction::make('schedulePublish')->bulk()->table(), [
            'publish_at' => $publishAt->toDateTimeString(),
        ])
        ->assertNotified('Scheduled 3 contest(s) for publishing');

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
        ->selectTableRecords([$draft->id, $active->id, $ended->id])
        ->callAction(TestAction::make('schedulePublish')->bulk()->table(), [
            'publish_at' => $publishAt->toDateTimeString(),
        ])
        ->assertNotified('Scheduled 1 contest(s) for publishing');

    expect($draft->fresh()->publish_at->toDateTimeString())
        ->toBe($publishAt->toDateTimeString())
        ->and($active->fresh()->publish_at)->toBeNull()
        ->and($ended->fresh()->publish_at)->toBeNull();
});

test('bulk-schedule warns when no draft contests selected', function () {
    $active = Contest::factory()->active()->create();

    Livewire::test(ListContests::class)
        ->selectTableRecords([$active->id])
        ->callAction(TestAction::make('schedulePublish')->bulk()->table(), [
            'publish_at' => now()->addDays(3)->toDateTimeString(),
        ])
        ->assertNotified('No draft contests selected');

    expect($active->fresh()->publish_at)->toBeNull();
});

test('bulk-schedule requires a publish date', function () {
    $contest = Contest::factory()->draft()->create();

    Livewire::test(ListContests::class)
        ->selectTableRecords([$contest->id])
        ->callAction(TestAction::make('schedulePublish')->bulk()->table(), [
            'publish_at' => null,
        ])
        ->assertHasActionErrors(['publish_at' => 'required']);

    expect($contest->fresh()->publish_at)->toBeNull();
});

test('non-admin cannot access the contests admin page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/contests')
        ->assertForbidden();
});
