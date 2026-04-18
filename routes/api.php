<?php

use App\Http\Controllers\Api\V1\AchievementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClueEntryController;
use App\Http\Controllers\Api\V1\ConstructorController;
use App\Http\Controllers\Api\V1\ContestController;
use App\Http\Controllers\Api\V1\ContestEntryController;
use App\Http\Controllers\Api\V1\CrosswordController;
use App\Http\Controllers\Api\V1\CrosswordLikeController;
use App\Http\Controllers\Api\V1\FavoriteListController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PuzzleAttemptController;
use App\Http\Controllers\Api\V1\PuzzleCommentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/ (configured in bootstrap/app.php).
|
*/

// --- Authentication ---
Route::post('/tokens', [AuthController::class, 'store'])->middleware('throttle:5,1');

// --- Public endpoints (no auth required) ---
Route::get('/crosswords', [CrosswordController::class, 'index'])->name('api.v1.crosswords.index');
Route::get('/crosswords/{crossword}', [CrosswordController::class, 'show'])->name('api.v1.crosswords.show');
Route::get('/crosswords/{crossword}/comments', [PuzzleCommentController::class, 'index']);

Route::get('/constructors/{user}', [ConstructorController::class, 'show']);
Route::get('/constructors/{user}/crosswords', [ConstructorController::class, 'crosswords']);

Route::get('/contests', [ContestController::class, 'index']);
Route::get('/contests/{contest:slug}', [ContestController::class, 'show']);
Route::get('/contests/{contest:slug}/leaderboard', [ContestController::class, 'leaderboard']);

Route::get('/clues', [ClueEntryController::class, 'index']);

// --- Authenticated endpoints ---
Route::middleware('auth:sanctum')->group(function () {
    // Token management
    Route::delete('/tokens', [AuthController::class, 'destroy']);

    // Current user
    Route::get('/me', [MeController::class, 'show']);
    Route::patch('/me', [MeController::class, 'update']);
    Route::get('/me/stats', [MeController::class, 'stats']);
    Route::get('/me/achievements', [AchievementController::class, 'index']);
    Route::get('/me/attempts', [PuzzleAttemptController::class, 'index']);

    // Crossword interactions
    Route::get('/crosswords/{crossword}/attempt', [PuzzleAttemptController::class, 'show']);
    Route::put('/crosswords/{crossword}/attempt', [PuzzleAttemptController::class, 'upsert']);
    Route::post('/crosswords/{crossword}/like', [CrosswordLikeController::class, 'store']);
    Route::delete('/crosswords/{crossword}/like', [CrosswordLikeController::class, 'destroy']);
    Route::post('/crosswords/{crossword}/comments', [PuzzleCommentController::class, 'store']);
    Route::delete('/comments/{comment}', [PuzzleCommentController::class, 'destroy']);

    // Contests
    Route::post('/contests/{contest:slug}/register', [ContestEntryController::class, 'store']);
    Route::get('/contests/{contest:slug}/entry', [ContestEntryController::class, 'show']);
    Route::post('/contests/{contest:slug}/meta', [ContestEntryController::class, 'submitMeta']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    // Favorites
    Route::get('/favorites', [FavoriteListController::class, 'index']);
    Route::post('/favorites', [FavoriteListController::class, 'store']);
    Route::delete('/favorites/{favoriteList}', [FavoriteListController::class, 'destroy']);
    Route::post('/favorites/{favoriteList}/crosswords', [FavoriteListController::class, 'addCrossword']);
    Route::delete('/favorites/{favoriteList}/crosswords/{crossword}', [FavoriteListController::class, 'removeCrossword']);
});
