<?php

use App\Modules\Api\Controllers\AuthController;
use App\Modules\Api\Controllers\BiolinkBlockController;
use App\Modules\Api\Controllers\BiolinkController;
use App\Modules\Api\Controllers\ContactController;
use App\Modules\Api\Controllers\CreatorPostController;
use App\Modules\Api\Controllers\DiscoveryController;
use App\Modules\Api\Controllers\FeedController;
use App\Modules\Api\Controllers\FollowController;
use App\Modules\Api\Controllers\FormController;
use App\Modules\Api\Controllers\InboxController;
use App\Modules\Api\Controllers\LinkController;
use App\Modules\Api\Controllers\NfcWriteController;
use App\Modules\Api\Controllers\NotificationController;
use App\Modules\Api\Controllers\OnboardingController;
use App\Modules\Api\Controllers\OtpController;
use App\Modules\Api\Controllers\PlanController;
use App\Modules\Api\Controllers\ProfileController;
use App\Modules\Api\Controllers\ProjectController;
use App\Modules\Api\Controllers\SocialAuthController;
use App\Modules\Api\Controllers\SubscriberController;
use App\Modules\Api\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/health', fn () => response()->json([
        'data' => ['status' => 'ok', 'time' => now()->toIso8601String()],
    ]));

    // ── Auth (public) ───────────────────────────────────────────────
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/auth/login',    [AuthController::class, 'login'])->middleware('throttle:10,1');

    // OTP-based mobile auth
    Route::post('/auth/otp/send',     [OtpController::class, 'send'])->middleware('throttle:10,1');
    Route::post('/auth/otp/verify',   [OtpController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('/auth/otp/register', [OtpController::class, 'register'])->middleware('throttle:5,1');

    // Native social sign-in (Apple / Google / etc.)
    Route::post('/auth/social', [SocialAuthController::class, 'exchange'])->middleware('throttle:20,1');

    // Demo login (non-prod). Mirrors the web "Try as Demo" button.
    Route::post('/auth/demo', [OtpController::class, 'demo'])->middleware('throttle:20,1');

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

    // Public read-only catalog
    Route::get('/plans', [PlanController::class, 'index']);

    // ── Authenticated ───────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me',     [AuthController::class, 'me']);
        Route::post('/auth/logout',[AuthController::class, 'logout']);

        Route::get('/profile',   [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);

        // Onboarding
        Route::get('/onboarding',          [OnboardingController::class, 'status']);
        Route::post('/onboarding/complete',[OnboardingController::class, 'complete']);

        // Links
        Route::get('/links',         [LinkController::class, 'index']);
        Route::post('/links',        [LinkController::class, 'store']);
        Route::get('/links/{id}',    [LinkController::class, 'show'])->whereNumber('id');
        Route::patch('/links/{id}',  [LinkController::class, 'update'])->whereNumber('id');
        Route::delete('/links/{id}', [LinkController::class, 'destroy'])->whereNumber('id');

        // Biolink blocks (authoring)
        Route::get   ('/links/{id}/blocks',                 [BiolinkBlockController::class, 'index'])->whereNumber('id');
        Route::post  ('/links/{id}/blocks',                 [BiolinkBlockController::class, 'store'])->whereNumber('id');
        Route::patch ('/links/{id}/blocks/{blockId}',       [BiolinkBlockController::class, 'update'])->whereNumber('id')->whereNumber('blockId');
        Route::delete('/links/{id}/blocks/{blockId}',       [BiolinkBlockController::class, 'destroy'])->whereNumber('id')->whereNumber('blockId');
        Route::post  ('/links/{id}/blocks/reorder',         [BiolinkBlockController::class, 'reorder'])->whereNumber('id');

        // NFC writes (per link + global summary)
        Route::get   ('/links/{id}/nfc-writes',     [NfcWriteController::class, 'index'])->whereNumber('id');
        Route::post  ('/links/{id}/nfc-writes',     [NfcWriteController::class, 'store'])->whereNumber('id')->middleware('throttle:60,1');
        Route::delete('/links/{id}/nfc-writes/{writeId}', [NfcWriteController::class, 'destroy'])->whereNumber('id')->whereNumber('writeId');
        Route::get   ('/nfc-writes/summary',        [NfcWriteController::class, 'summary']);

        // Follows
        Route::post  ('/follows/{userId}',   [FollowController::class, 'follow'])->whereNumber('userId');
        Route::delete('/follows/{userId}',   [FollowController::class, 'unfollow'])->whereNumber('userId');
        Route::get   ('/follows/following',  [FollowController::class, 'following']);
        Route::get   ('/follows/followers',  [FollowController::class, 'followers']);

        // Subscribers
        Route::get   ('/subscribers',        [SubscriberController::class, 'index']);
        Route::delete('/subscribers/{id}',   [SubscriberController::class, 'destroy'])->whereNumber('id');

        // Notifications
        Route::get ('/notifications',                  [NotificationController::class, 'index']);
        Route::post('/notifications/read-all',         [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read',        [NotificationController::class, 'markRead'])->whereNumber('id');

        // Projects
        Route::get   ('/projects',        [ProjectController::class, 'index']);
        Route::post  ('/projects',        [ProjectController::class, 'store']);
        Route::patch ('/projects/{id}',   [ProjectController::class, 'update'])->whereNumber('id');
        Route::delete('/projects/{id}',   [ProjectController::class, 'destroy'])->whereNumber('id');

        // Posts (creator feed)
        Route::get   ('/posts',           [CreatorPostController::class, 'index']);
        Route::post  ('/posts',           [CreatorPostController::class, 'store']);
        Route::patch ('/posts/{id}',      [CreatorPostController::class, 'update'])->whereNumber('id');
        Route::delete('/posts/{id}',      [CreatorPostController::class, 'destroy'])->whereNumber('id');

        // Contacts
        Route::get   ('/contacts',        [ContactController::class, 'index']);
        Route::post  ('/contacts',        [ContactController::class, 'store']);
        Route::patch ('/contacts/{id}',   [ContactController::class, 'update'])->whereNumber('id');
        Route::delete('/contacts/{id}',   [ContactController::class, 'destroy'])->whereNumber('id');

        // Forms (read-only for mobile; full CRUD lives on web)
        Route::get('/forms',                        [FormController::class, 'index']);
        Route::get('/forms/{id}',                   [FormController::class, 'show'])->whereNumber('id');
        Route::get('/forms/{id}/submissions',       [FormController::class, 'submissions'])->whereNumber('id');

        // Inbox (DM threads on owned biolinks)
        Route::get('/inbox/threads', [InboxController::class, 'threads']);

        // Workspaces
        Route::get('/workspaces',                 [WorkspaceController::class, 'index']);
        Route::get('/workspaces/{id}/members',    [WorkspaceController::class, 'members'])->whereNumber('id');
    });
});
