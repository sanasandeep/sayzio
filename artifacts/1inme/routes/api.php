<?php

use App\Modules\Api\Controllers\AuthController;
use App\Modules\Api\Controllers\BiolinkController;
use App\Modules\Api\Controllers\DiscoveryController;
use App\Modules\Api\Controllers\FeedController;
use App\Modules\Api\Controllers\FollowController;
use App\Modules\Api\Controllers\LinkController;
use App\Modules\Api\Controllers\ProfileController;
use App\Modules\Api\Controllers\SubscriberController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/health', fn () => response()->json([
        'data' => ['status' => 'ok', 'time' => now()->toIso8601String()],
    ]));

    // ── Auth (public) ───────────────────────────────────────────────
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/auth/login',    [AuthController::class, 'login'])->middleware('throttle:10,1');

    // ── Public, visibility-aware (optional bearer token) ────────────
    Route::middleware('api.optional_auth')->group(function () {
        Route::get('/biolinks/{alias}',            [BiolinkController::class, 'show']);
        Route::get('/discovery/creators',          [DiscoveryController::class, 'creators']);
        Route::get('/discovery/creators/{handle}', [DiscoveryController::class, 'creator']);
        Route::get('/feed',                        [FeedController::class, 'index']);
        Route::get('/creators/{handle}/feed',      [FeedController::class, 'byCreator']);
    });

    Route::post('/biolinks/{alias}/subscribe', [BiolinkController::class, 'subscribe'])
        ->middleware('throttle:10,1');

    // ── Authenticated ───────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me',     [AuthController::class, 'me']);
        Route::post('/auth/logout',[AuthController::class, 'logout']);

        Route::get('/profile',   [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);

        Route::get('/links',         [LinkController::class, 'index']);
        Route::post('/links',        [LinkController::class, 'store']);
        Route::get('/links/{id}',    [LinkController::class, 'show'])->whereNumber('id');
        Route::patch('/links/{id}',  [LinkController::class, 'update'])->whereNumber('id');
        Route::delete('/links/{id}', [LinkController::class, 'destroy'])->whereNumber('id');

        Route::post('/follows/{userId}',   [FollowController::class, 'follow'])->whereNumber('userId');
        Route::delete('/follows/{userId}', [FollowController::class, 'unfollow'])->whereNumber('userId');
        Route::get('/follows/following',   [FollowController::class, 'following']);
        Route::get('/follows/followers',   [FollowController::class, 'followers']);

        Route::get('/subscribers',         [SubscriberController::class, 'index']);
        Route::delete('/subscribers/{id}', [SubscriberController::class, 'destroy'])->whereNumber('id');
    });
});
