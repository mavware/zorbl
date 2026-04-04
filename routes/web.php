<?php

use App\Http\Controllers\Api\EmbedController;
use App\Models\Crossword;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Embed routes (public, no auth)
Route::options('/api/embed/{crossword}', [EmbedController::class, 'preflight']);
Route::get('/api/embed/{crossword}', [EmbedController::class, 'show'])->name('api.embed.show');
Route::get('/embed/{crossword}', function (Crossword $crossword) {
    abort_unless($crossword->is_published, 404);

    return view('embed.solver', ['crossword' => $crossword]);
})->name('embed.solver');

// Public puzzle browsing (no auth required)
Route::livewire('puzzles', 'pages::puzzles.index')->name('puzzles.index');
Route::livewire('puzzles/{crossword}', 'pages::puzzles.solve')->name('puzzles.solve');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('crosswords', 'pages::crosswords.index')->name('crosswords.index');
    Route::livewire('crosswords/analytics', 'pages::crosswords.analytics')->name('crosswords.analytics');
    Route::livewire('solving', 'pages::crosswords.solving')->name('crosswords.solving');
    Route::livewire('solving/stats', 'pages::crosswords.stats')->name('crosswords.stats');
    Route::livewire('crosswords/{crossword}', 'pages::crosswords.editor')->name('crosswords.editor');
    Route::livewire('crosswords/{crossword}/solve', 'pages::crosswords.solver')->name('crosswords.solver');

    Route::livewire('clues', 'pages::clues.index')->name('clues.index');

    Route::livewire('favorites', 'pages::favorites.index')->name('favorites.index');

    Route::livewire('constructors/{constructor}', 'pages::constructors.show')->name('constructors.show');

    Route::livewire('roadmap', 'pages::roadmap.index')->name('roadmap.index');

    Route::livewire('contests', 'pages::contests.index')->name('contests.index');
    Route::livewire('contests/{contest:slug}', 'pages::contests.show')->name('contests.show');
    Route::livewire('contests/{contest:slug}/leaderboard', 'pages::contests.leaderboard')->name('contests.leaderboard');

    Route::livewire('support', 'pages::support.index')->name('support.index');
    Route::livewire('support/create', 'pages::support.create')->name('support.create');
    Route::livewire('support/{ticket}', 'pages::support.show')->name('support.show');
});

require __DIR__.'/settings.php';
