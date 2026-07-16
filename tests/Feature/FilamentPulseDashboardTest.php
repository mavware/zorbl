<?php

use App\Filament\Widgets\Pulse\CacheWidget;
use App\Filament\Widgets\Pulse\ExceptionsWidget;
use App\Filament\Widgets\Pulse\PeriodSelectorWidget;
use App\Filament\Widgets\Pulse\QueuesWidget;
use App\Filament\Widgets\Pulse\ServersWidget;
use App\Filament\Widgets\Pulse\SlowJobsWidget;
use App\Filament\Widgets\Pulse\SlowOutgoingRequestsWidget;
use App\Filament\Widgets\Pulse\SlowQueriesWidget;
use App\Filament\Widgets\Pulse\SlowRequestsWidget;
use App\Filament\Widgets\Pulse\UsageWidget;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
});

test('admins can view the pulse dashboard page with every pulse widget', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $response = $this->actingAs($admin)
        ->get('/admin/pulse')
        ->assertOk()
        ->assertSee('pulse-widgets');

    foreach ([
        PeriodSelectorWidget::class,
        ServersWidget::class,
        UsageWidget::class,
        QueuesWidget::class,
        CacheWidget::class,
        SlowQueriesWidget::class,
        ExceptionsWidget::class,
        SlowRequestsWidget::class,
        SlowJobsWidget::class,
        SlowOutgoingRequestsWidget::class,
    ] as $widget) {
        $response->assertSeeLivewire($widget);
    }
});

test('non-admin users cannot view the pulse dashboard page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/pulse')
        ->assertForbidden();
});
