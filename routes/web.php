<?php

use App\Http\Controllers\Api\EmbedController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\SitemapController;
use App\Models\Crossword;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('robots.txt', function () {
    // Keep public marketing/puzzle/help pages crawlable; disallow the
    // authenticated app surface (guests are 302'd to login there, so crawling
    // it just wastes crawl budget) and settings/auth endpoints.
    $disallow = [
        '/crosswords', '/solving', '/dashboard', '/settings', '/support',
        '/favorites', '/leaderboard',
        '/roadmap', '/contests', '/impersonate',
    ];

    $lines = ['User-agent: *'];
    foreach ($disallow as $path) {
        $lines[] = 'Disallow: '.$path;
    }
    $lines[] = '';
    $lines[] = 'Sitemap: '.route('sitemap');

    return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
})->name('robots');

// Embed routes (public, no auth)
Route::options('/api/embed/{crossword}', [EmbedController::class, 'preflight']);
Route::get('/api/embed/{crossword}', [EmbedController::class, 'show'])->name('api.embed.show');
Route::get('/embed/{crossword}', function (Crossword $crossword) {
    abort_unless($crossword->is_published, 404);

    return view('embed.solver', ['crossword' => $crossword]);
})->name('embed.solver');

// Google OAuth — throttled per IP so a flood of invalid callbacks can't
// drown the Socialite HTTP requests we make against Google.
Route::middleware(['guest', 'throttle:oauth-callback'])->group(function () {
    Route::get('auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

// Help center (public, no auth)
Route::livewire('help', 'pages::help.index')->name('help.index');
Route::livewire('help/{article:slug}', 'pages::help.show')->name('help.show');

// Legal pages (public, no auth)
Route::livewire('terms', 'pages::legal.terms')->name('legal.terms');
Route::livewire('privacy', 'pages::legal.privacy')->name('legal.privacy');
Route::livewire('cookies', 'pages::legal.cookies')->name('legal.cookies');
Route::livewire('dmca', 'pages::legal.dmca')->name('legal.dmca');

// Public tools (no auth required)
Route::livewire('tools/convert', 'pages::tools.convert')->name('tools.convert');

// Public puzzle browsing (no auth required)
Route::livewire('puzzles', 'pages::puzzles.index')->name('puzzles.index');
Route::livewire('puzzles/daily', 'pages::puzzles.daily-history')->name('puzzles.daily-history');
Route::get('puzzles/{crossword}/og.png', [OgImageController::class, 'crossword'])->name('puzzles.og');
Route::livewire('puzzles/{crossword}', 'pages::puzzles.solve')->name('puzzles.solve');

// Public constructor directory + profiles (no auth required). These are a key
// SEO surface: each profile is a unique, content-rich page of a constructor's
// published puzzles. Logged-in users get the app layout; guests the public one.
Route::livewire('constructors', 'pages::constructors.index')->name('constructors.index');
Route::livewire('constructors/{constructor}', 'pages::constructors.show')->name('constructors.show');

// Public reference libraries (no auth required). Guests get the public chrome;
// write actions (adding/reporting clues) are gated behind auth in the pages.
Route::livewire('clues', 'pages::clues.index')->name('clues.index');
Route::livewire('words', 'pages::words.index')->name('words.index');
Route::livewire('words/{word:word}', 'pages::words.show')->name('words.show');

Route::middleware('auth')->group(function () {
    Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
    Route::post('impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');
});

// Static crosswords/* routes must be registered before the crosswords/{crossword}
// wildcard below, otherwise the wildcard swallows them (e.g. "analytics" would be
// treated as a {crossword} and 404 on the model binding).
Route::middleware(['auth', 'verified', 'not-anonymous'])->group(function () {
    // Analytics now live on the Build page; the name survives for old links
    // (e.g. the constructor weekly digest email).
    Route::redirect('crosswords/analytics', '/crosswords')->name('crosswords.analytics');
});

// Builder surface + Build home — anonymous "guest builder" users are allowed in
// (they land here as their dashboard/home and see the guest sign-up banner).
// CrosswordPolicy::update prevents them from opening anyone else's puzzle.
Route::middleware(['auth'])->group(function () {
    // The dashboard's sections now live on the Build and Solve pages; keep the
    // route name alive for old links and post-login redirects. Guests use the
    // Build page (crosswords.index) as their home.
    Route::redirect('dashboard', '/crosswords')->name('dashboard');

    Route::livewire('crosswords', 'pages::crosswords.index')->name('crosswords.index');
    Route::livewire('crosswords/{crossword}', 'pages::crosswords.editor')->name('crosswords.editor');
    Route::livewire('crosswords/{crossword}/solve', 'pages::crosswords.solver')->name('crosswords.solver');
});

Route::middleware(['auth', 'verified', 'not-anonymous'])->group(function () {
    Route::livewire('solving', 'pages::crosswords.solving')->name('crosswords.solving');
    Route::livewire('solving/stats', 'pages::crosswords.stats')->name('crosswords.stats');

    Route::livewire('favorites', 'pages::favorites.index')->name('favorites.index');

    Route::livewire('leaderboard', 'pages::leaderboard')->name('leaderboard');

    Route::livewire('roadmap', 'pages::roadmap.index')->name('roadmap.index');

    Route::livewire('contests', 'pages::contests.index')->name('contests.index');
    Route::livewire('contests/{contest:slug}', 'pages::contests.show')->name('contests.show');
    Route::livewire('contests/{contest:slug}/leaderboard', 'pages::contests.leaderboard')->name('contests.leaderboard');

    Route::livewire('support', 'pages::support.index')->name('support.index');
    Route::livewire('support/create', 'pages::support.create')->name('support.create');
    Route::livewire('support/{ticket}', 'pages::support.show')->name('support.show');
});

require __DIR__.'/settings.php';
