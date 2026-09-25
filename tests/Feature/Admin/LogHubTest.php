<?php

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Microcode\FilamentLogHub\Models\LogEntry;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeLogHubAdmin(): User
{
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    return $admin;
}

test('log messages are stored in the database through the stack channel', function () {
    config(['log-hub.dedupe_seconds' => 0]);

    expect(config('logging.channels.stack.channels'))->toContain('log-hub');

    Log::warning('Disk almost full', ['free_mb' => 120]);

    $entry = LogEntry::query()->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->level_name)->toBe('warning')
        ->and($entry->message)->toBe('Disk almost full')
        ->and($entry->context)->toBe(['free_mb' => 120]);
});

test('the log hub sits in the ungrouped sidebar section directly above Pulse', function () {
    $this->actingAs(makeLogHubAdmin());

    $this->get('/admin/dashboard')
        ->assertSuccessful()
        ->assertSeeInOrder(['Dashboard', 'Log Hub', 'Pulse'])
        ->assertDontSee('Integrations');

    Filament::setCurrentPanel('admin');

    $ungrouped = collect(Filament::getNavigation())
        ->first(fn (NavigationGroup $group): bool => blank($group->getLabel()));

    $labels = collect($ungrouped?->getItems() ?? [])->map->getLabel()->values();

    expect($ungrouped)->not->toBeNull()
        ->and($labels->search('Log Hub'))->toBeInt()
        ->and($labels->search('Pulse'))->toBe($labels->search('Log Hub') + 1);
});

test('admins can open the log hub page and see stored entries', function () {
    config(['log-hub.dedupe_seconds' => 0]);
    Log::error('Payment webhook rejected');

    $this->actingAs(makeLogHubAdmin())
        ->get('/admin/log-hub')
        ->assertSuccessful()
        ->assertSee('Payment webhook rejected');
});

test('non-admins cannot open the log hub page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/log-hub')
        ->assertForbidden();
});

test('expired log entries are purged daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'log-hub:purge'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * *');
});
