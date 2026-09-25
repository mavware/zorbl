<?php

use App\Filament\Pages\LogViewer;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);
});

test('the log viewer sits in the ungrouped sidebar section between Dashboard and Pulse', function () {
    $this->get('/admin/dashboard')
        ->assertSuccessful()
        ->assertSeeInOrder(['Dashboard', 'Log Viewer', 'Pulse']);

    Filament::setCurrentPanel('admin');

    $ungrouped = collect(Filament::getNavigation())
        ->first(fn (NavigationGroup $group): bool => blank($group->getLabel()));

    $labels = collect($ungrouped?->getItems() ?? [])->map->getLabel()->values();

    expect($ungrouped)->not->toBeNull()
        ->and($labels->first())->toBe('Dashboard')
        ->and($labels->search('Log Viewer'))->toBeInt()
        ->and($labels->search('Pulse'))->toBe($labels->search('Log Viewer') + 1);

    expect(collect(Filament::getNavigation())->map->getLabel()->filter()->all())
        ->not->toContain('Logs');
});

test('the log viewer page still answers on the plugin route', function () {
    $this->get(LogViewer::getUrl())->assertSuccessful();
});
