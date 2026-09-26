<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\PulseDashboard;
use App\Services\WordExporter;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            // Nightwatch can't be embedded, so link out to it beside Pulse.
            ->navigationItems([
                NavigationItem::make('Nightwatch')
                    ->url(fn (): string => config('services.nightwatch.dashboard_url'), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedEye)
                    ->sort(2),
                // Links to a redirect so the export disk is only resolved on click, not on every render.
                NavigationItem::make('Word List JSON')
                    ->url(fn (): string => route('filament.admin.word-list'), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->sort(3),
            ])
            // Redirects to the manifest indexing the per-length JSON shards written by words:export-json.
            ->authenticatedRoutes(function (): void {
                Route::get('word-list', fn (WordExporter $exporter): RedirectResponse => redirect()->away($exporter->manifestUrl()))
                    ->name('word-list');
            })
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => PulseDashboard::renderAssets(),
                scopes: [PulseDashboard::class, Dashboard::class],
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_LOGO_AFTER,
                fn (): string => view('filament.components.panel-switcher')->render(),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn (): string => view('filament.components.panel-switcher')->render(),
            )
            ->usermenuitems([
                Action::make('Dashboard')
                    ->icon('heroicon-o-home')
                    ->url('/dashboard'),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
