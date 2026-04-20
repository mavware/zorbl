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
    $drafts = Contest::factory()->draft()->count(3)->create();
    $publishAt = now()->addDays(3)->startOfMinute();

    Livewire::test(ListContests::class)
        ->selectTableRecords($drafts->pluck('id')->all())
        ->callAction(TestAction::make('schedulePublish')->bulk()->table(), [
            'publish_at' => $publishAt->toDateTimeString(),
        ])
        ->assertNotified();

    foreach ($drafts as $draft) {
        expect($draft->fresh()->publish_at->toDateTimeString())
            ->toBe($publishAt->toDateTimeString());
    }
});

test('bulk-schedule skips non-draft contests', function () {
    $draft = Contest::factory()->draft()->create();
    $active = Contest::factory()->active()->create();
    $publishAt = now()->addDays(3)->startOfMinute();

    Livewire::test(ListContests::class)
        ->selectTableRecords([$draft->id, $active->id])
        ->callAction(TestAction::make('schedulePublish')->bulk()->table(), [
            'publish_at' => $publishAt->toDateTimeString(),
        ])
        ->assertNotified();

    expect($draft->fresh()->publish_at->toDateTimeString())
        ->toBe($publishAt->toDateTimeString());
    expect($active->fresh()->publish_at)->toBeNull();
});

test('bulk-schedule warns when no draft contests selected', function () {
    $active = Contest::factory()->active()->create();
    $publishAt = now()->addDays(3)->startOfMinute();

    Livewire::test(ListContests::class)
        ->selectTableRecords([$active->id])
        ->callAction(TestAction::make('schedulePublish')->bulk()->table(), [
            'publish_at' => $publishAt->toDateTimeString(),
        ])
        ->assertNotified();

    expect($active->fresh()->publish_at)->toBeNull();
});

test('bulk-schedule requires publish_at date', function () {
    $draft = Contest::factory()->draft()->create();

    Livewire::test(ListContests::class)
        ->selectTableRecords([$draft->id])
        ->callAction(TestAction::make('schedulePublish')->bulk()->table(), [
            'publish_at' => null,
        ])
        ->assertHasActionErrors(['publish_at' => 'required']);
});

test('publish_at column is visible in contest list', function () {
    $contest = Contest::factory()->scheduledPublish(now()->addDay())->create();

    Livewire::test(ListContests::class)
        ->assertCanSeeTableRecords([$contest])
        ->assertSuccessful();
});

test('non-admin cannot access bulk-schedule action', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/contests')
        ->assertForbidden();
});
