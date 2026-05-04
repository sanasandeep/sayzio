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
use App\Modules\Api\Controllers\DashboardController;
use App\Modules\Api\Controllers\NotificationController;
use App\Modules\Api\Controllers\OnboardingController;
use App\Modules\Api\Controllers\OnboardingSlideController;
use App\Modules\Api\Controllers\OtpController;
use App\Modules\Api\Controllers\PlanController;
use App\Modules\Api\Controllers\WalletController;
use App\Modules\Api\Controllers\ProfileController;
use App\Modules\Api\Controllers\ProjectController;
use App\Modules\Api\Controllers\SocialAuthController;
use App\Modules\Api\Controllers\SubscriberController;
use App\Modules\Api\Controllers\WorkspaceController;
use App\Modules\Api\Controllers\BacklinkController;
use App\Modules\Api\Controllers\PropertyController;
use App\Modules\Api\Controllers\DomainController;
use App\Modules\Api\Controllers\SplashPageController;
use App\Modules\Api\Controllers\QrCodeController;
use App\Modules\Api\Controllers\SocialAccountController;
use App\Modules\Api\Controllers\IntegrationController;
use App\Modules\Api\Controllers\CalendarController;
use App\Modules\Api\Controllers\VaultController;
use App\Modules\Api\Controllers\VerificationController;
use App\Modules\Api\Controllers\BillingController;
use App\Modules\Api\Controllers\RevenueCatBillingController;
use App\Modules\Api\Controllers\DialerController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/health', fn () => response()->json([
        'data' => ['status' => 'ok', 'time' => now()->toIso8601String()],
    ]));

    // ── Auth (public) ───────────────────────────────────────────────
    // All public auth routes use the identifier-aware named limiters
    // declared in App\Providers\AppServiceProvider so the limit follows
    // (account + IP), not just IP. See that file for the per-bucket
    // budgets.
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('/auth/login',    [AuthController::class, 'login'])->middleware('throttle:auth-credentials');

    // OTP-based mobile auth
    Route::post('/auth/otp/send',     [OtpController::class, 'send'])->middleware('throttle:otp-send');
    Route::post('/auth/otp/verify',   [OtpController::class, 'verify'])->middleware('throttle:otp-verify');
    Route::post('/auth/otp/register', [OtpController::class, 'register'])->middleware('throttle:auth-register');

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

    // Best-effort page-visit tracking from in-app biolink viewers (mobile).
    // Mirrors the web's RedirectController::track() call on every biolink
    // page load so visits via the app are counted in creator analytics.
    Route::middleware(['api.optional_auth', 'throttle:120,1'])
        ->post('/biolinks/{alias}/visit', [BiolinkController::class, 'visit']);

    // Slide-view event ping for mobile slides viewer.
    Route::middleware(['api.optional_auth', 'throttle:240,1'])
        ->post('/biolinks/{alias}/slides/view', [BiolinkController::class, 'slideView']);

    // Best-effort block tap tracking from in-app biolink viewers (mobile).
    // Mirrors the web's redirect.block click counter so taps via the app
    // show up in the creator's analytics.
    Route::middleware(['api.optional_auth', 'throttle:120,1'])
        ->post('/biolinks/{alias}/blocks/{blockId}/tap', [BiolinkController::class, 'tap'])
        ->whereNumber('blockId');

    // Task #1094 — live "limits" snapshot (countdowns + remaining counts)
    // for every block on a biolink. Polled by the public-page JS and the
    // mobile editor preview so badges stay current without a page reload.
    // optional_auth so the visibility gate inside publicLimits can
    // identify followers/subscribers/owner viewers; anonymous requests
    // still work for fully-public biolinks.
    Route::middleware(['api.optional_auth', 'throttle:120,1'])
        ->get('/biolinks/{alias}/blocks/limits', [BiolinkBlockController::class, 'publicLimits']);

    // Native poll vote — mirrors the in-page poll UI but persists the vote
    // server-side so the mobile app no longer has to bounce out to the web
    // form to record it. Optional auth lets logged-in viewers be deduped by
    // user_id; anonymous viewers fall back to an ip+ua fingerprint.
    Route::middleware(['api.optional_auth', 'throttle:30,1'])
        ->post('/biolinks/{alias}/blocks/{blockId}/poll-vote', [BiolinkController::class, 'pollVote'])
        ->whereNumber('blockId');

    // Aggregated tallies for a poll block. Surfaced to viewers right
    // after they vote (and to viewers who already voted) so they can
    // see how their pick compares. Visibility-gated like the page.
    Route::middleware(['api.optional_auth', 'throttle:60,1'])
        ->get('/biolinks/{alias}/blocks/{blockId}/poll-results', [BiolinkController::class, 'pollResults'])
        ->whereNumber('blockId');

    // Native RSVP submission from a biolink RSVP block. The block resolves
    // its event link server-side via settings.event_link_id so the mobile
    // client only needs the biolink alias + block id (matching how the
    // poll-vote endpoint is shaped).
    Route::middleware(['api.optional_auth', 'throttle:10,1'])
        ->post('/biolinks/{alias}/blocks/{blockId}/rsvp', [BiolinkController::class, 'rsvpSubmit'])
        ->whereNumber('blockId');

    // Public read-only catalog
    Route::get('/plans', [PlanController::class, 'index']);

    // Auto-pixel interstitial fire beacon — public, anonymous visitors.
    // The interstitial loads the configured pixel scripts then POSTs here
    // to record one row in `link_pixel_fires` so the dashboard can show
    // retargeting impact. Throttled hard since it's an unauthenticated
    // public endpoint.
    Route::middleware('throttle:120,1')
        ->post('/links/{alias}/pixel-fire', [\App\Modules\Api\Controllers\LinkPixelFireController::class, 'store']);

    // Mobile splash slider — admin-managed onboarding slides.
    Route::get('/onboarding/slides', [OnboardingSlideController::class, 'index']);

    // ── Authenticated ───────────────────────────────────────────────
    Route::middleware(['auth:sanctum', \App\Modules\Api\Middleware\TouchSessionToken::class])->group(function () {
        Route::get('/auth/me',     [AuthController::class, 'me']);
        Route::post('/auth/logout',[AuthController::class, 'logout']);

        // Devices & sessions (task #1111).
        Route::get   ('/auth/sessions',                [\App\Modules\Api\Controllers\SessionsController::class, 'index']);
        Route::delete('/auth/sessions/others',         [\App\Modules\Api\Controllers\SessionsController::class, 'destroyOthers']);
        Route::delete('/auth/sessions/{id}',           [\App\Modules\Api\Controllers\SessionsController::class, 'destroy'])
            ->where('id', '[A-Za-z0-9:_-]+');

        Route::get('/profile',   [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);

        // Wallet & coins (mobile parity).
        Route::get ('/wallet',              [WalletController::class, 'balance']);
        Route::get ('/wallet/transactions', [WalletController::class, 'transactions']);
        Route::get ('/wallet/packages',     [WalletController::class, 'packages']);
        Route::post('/wallet/purchase',     [WalletController::class, 'purchase']);

        // AI credits (mobile parity for Mind/Persona/Companion/Coach).
        Route::get ('/ai/credits',              [\App\Modules\Api\Controllers\AiCreditsController::class, 'balance']);
        Route::get ('/ai/credits/transactions', [\App\Modules\Api\Controllers\AiCreditsController::class, 'transactions']);
        Route::get ('/ai/credits/packs',        [\App\Modules\Api\Controllers\AiCreditsController::class, 'packs']);
        Route::post('/ai/credits/purchase',     [\App\Modules\Api\Controllers\AiCreditsController::class, 'purchase']);

        // AI Mind picker defaults (Persona / Coach mobile parity).
        Route::get   ('/ai/minds',                  [\App\Modules\Api\Controllers\AiMindPickerController::class, 'minds']);
        Route::get   ('/ai/{feature}/defaults',     [\App\Modules\Api\Controllers\AiMindPickerController::class, 'getDefaults'])->whereIn('feature', ['persona', 'coach']);
        Route::put   ('/ai/{feature}/defaults',     [\App\Modules\Api\Controllers\AiMindPickerController::class, 'saveDefaults'])->whereIn('feature', ['persona', 'coach']);
        Route::delete('/ai/{feature}/defaults',     [\App\Modules\Api\Controllers\AiMindPickerController::class, 'clearDefaults'])->whereIn('feature', ['persona', 'coach']);

        // Voice Assistant (mobile parity for the floating mic). Same
        // orchestrator as the web — STT/LLM/TTS each charge their own
        // ledger row (voice_stt / voice_llm / voice_tts). Throttled to
        // match the web limit so abuse can't drain the user's credits.
        Route::get ('/ai/voice/capabilities', [\App\Modules\Api\Controllers\VoiceAssistantController::class, 'capabilities']);
        Route::post('/ai/voice/turn',         [\App\Modules\Api\Controllers\VoiceAssistantController::class, 'turn'])->middleware('throttle:30,1');
        // Wake-word check: short audio clip in, {matched, transcript} out.
        // Heavily throttled — a misbehaving foreground listener could
        // otherwise hammer Whisper and rack up upstream API costs even
        // though we never bill the user's credit ledger for this.
        Route::post('/ai/voice/wake-check',   [\App\Modules\Api\Controllers\VoiceAssistantController::class, 'wakeCheck'])->middleware('throttle:60,1');

        // Ask Coach (mobile parity for the data-aware self-support chatbot).
        Route::get   ('/ai/ask-coach/threads',                       [\App\Modules\Api\Controllers\AskCoachController::class, 'threads']);
        Route::post  ('/ai/ask-coach/threads',                       [\App\Modules\Api\Controllers\AskCoachController::class, 'createThread']);
        Route::get   ('/ai/ask-coach/threads/{thread}',              [\App\Modules\Api\Controllers\AskCoachController::class, 'messages'])->whereNumber('thread');
        Route::post  ('/ai/ask-coach/threads/{thread}/send',         [\App\Modules\Api\Controllers\AskCoachController::class, 'send'])->whereNumber('thread')->middleware('throttle:30,1');
        Route::delete('/ai/ask-coach/threads/{thread}',              [\App\Modules\Api\Controllers\AskCoachController::class, 'destroy'])->whereNumber('thread');
        Route::post  ('/ai/ask-coach/messages/{message}/feedback',   [\App\Modules\Api\Controllers\AskCoachController::class, 'feedback'])->whereNumber('message')->middleware('throttle:30,1');

        // Onboarding
        Route::get('/onboarding',          [OnboardingController::class, 'status']);
        Route::post('/onboarding/complete',[OnboardingController::class, 'complete']);

        // Links
        Route::get('/links',         [LinkController::class, 'index']);
        Route::post('/links',        [LinkController::class, 'store']);
        // A/B test endpoints — registered BEFORE the `/links/{id}` show
        // route so the literal segments (`ab`) win over the integer id
        // matcher and we don't accidentally treat "ab" as a link id.
        Route::get ('/links/ab',                       [LinkController::class, 'indexAb']);
        Route::post('/links/ab',                       [LinkController::class, 'storeAb']);
        Route::get ('/links/{id}/ab',                  [LinkController::class, 'showAb'])->whereNumber('id');
        Route::post('/links/{id}/ab/declare-winner',   [LinkController::class, 'declareAbWinner'])->whereNumber('id');
        Route::get('/links/{id}',    [LinkController::class, 'show'])->whereNumber('id');
        Route::patch('/links/{id}',  [LinkController::class, 'update'])->whereNumber('id');
        Route::delete('/links/{id}', [LinkController::class, 'destroy'])->whereNumber('id');
        Route::get   ('/links/{id}/analytics', [LinkController::class, 'analytics'])->whereNumber('id');
        Route::get   ('/links/{id}/analytics/blocks/{blockId}', [LinkController::class, 'blockAnalytics'])->whereNumber('id')->whereNumber('blockId');
        Route::post  ('/links/{id}/reset',     [LinkController::class, 'reset'])->whereNumber('id');

        // Smart links — geo / device / language / time / AB routing.
        // POST /links/smart creates a new short link with rules attached.
        // GET / PUT /links/{id}/rules manage rules on any owned link.
        Route::post  ('/links/smart',          [LinkController::class, 'storeSmart']);
        Route::get   ('/links/{id}/rules',     [LinkController::class, 'getRules'])->whereNumber('id');
        Route::put   ('/links/{id}/rules',     [LinkController::class, 'putRules'])->whereNumber('id');

        // Card templates (mobile parity for the web card-template gallery).
        Route::get ('/links/{id}/card-templates',         [\App\Modules\Api\Controllers\CardTemplateController::class, 'index'])->whereNumber('id');
        Route::post('/links/{id}/card-templates/apply',   [\App\Modules\Api\Controllers\CardTemplateController::class, 'apply'])->whereNumber('id');

        // Biolink blocks (authoring)
        Route::get   ('/links/{id}/blocks',                 [BiolinkBlockController::class, 'index'])->whereNumber('id');
        Route::post  ('/links/{id}/blocks',                 [BiolinkBlockController::class, 'store'])->whereNumber('id');
        Route::patch ('/links/{id}/blocks/{blockId}',       [BiolinkBlockController::class, 'update'])->whereNumber('id')->whereNumber('blockId');
        Route::delete('/links/{id}/blocks/{blockId}',       [BiolinkBlockController::class, 'destroy'])->whereNumber('id')->whereNumber('blockId');
        Route::post  ('/links/{id}/blocks/reorder',         [BiolinkBlockController::class, 'reorder'])->whereNumber('id');

        // Biolink themes (saved looks + scheduled application). Mobile
        // creators can save the current look, schedule it for a date
        // range, and cancel/end early. Public viewers always see the
        // currently-active theme via the existing /biolinks/{alias} show.
        Route::get   ('/links/{id}/themes',                              [\App\Modules\Api\Controllers\BiolinkThemeApiController::class, 'index'])->whereNumber('id');
        Route::post  ('/links/{id}/themes',                              [\App\Modules\Api\Controllers\BiolinkThemeApiController::class, 'storeTheme'])->whereNumber('id');
        Route::delete('/links/{id}/themes/{themeId}',                    [\App\Modules\Api\Controllers\BiolinkThemeApiController::class, 'destroyTheme'])->whereNumber('id')->whereNumber('themeId');
        Route::post  ('/links/{id}/themes/schedules',                    [\App\Modules\Api\Controllers\BiolinkThemeApiController::class, 'storeSchedule'])->whereNumber('id');
        Route::patch ('/links/{id}/themes/schedules/{scheduleId}',       [\App\Modules\Api\Controllers\BiolinkThemeApiController::class, 'updateSchedule'])->whereNumber('id')->whereNumber('scheduleId');
        Route::post  ('/links/{id}/themes/schedules/{scheduleId}/cancel',[\App\Modules\Api\Controllers\BiolinkThemeApiController::class, 'cancelSchedule'])->whereNumber('id')->whereNumber('scheduleId');

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

        // Dashboard summary (mobile home tab)
        Route::get ('/dashboard',                      [DashboardController::class, 'index']);

        // Notifications
        Route::get ('/notifications',                  [NotificationController::class, 'index']);
        Route::post('/notifications/read-all',         [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read',        [NotificationController::class, 'markRead'])->whereNumber('id');
        Route::get ('/me/notification-preferences',    [NotificationController::class, 'preferences']);
        Route::put ('/me/notification-preferences',    [NotificationController::class, 'updatePreferences']);

        // Recent-logins history + "This wasn't me" revoke (mobile parity
        // for the suspicious-login email pipeline).
        Route::get ('/security/logins',                [\App\Modules\Api\Controllers\SecurityController::class, 'logins']);
        Route::post('/security/logins/{id}/revoke',    [\App\Modules\Api\Controllers\SecurityController::class, 'revoke'])->whereNumber('id');

        // Projects
        Route::get   ('/projects',        [ProjectController::class, 'index']);
        Route::post  ('/projects',        [ProjectController::class, 'store']);
        Route::patch ('/projects/{id}',   [ProjectController::class, 'update'])->whereNumber('id');
        Route::delete('/projects/{id}',   [ProjectController::class, 'destroy'])->whereNumber('id');

        // Resume / Portfolio (single resume per user — the controller
        // resolves it from the bearer token, so the URL never carries a
        // resume id and members of foreign workspaces can't reach it).
        Route::get   ('/resume',                 [\App\Modules\Api\Controllers\ResumeController::class, 'show']);
        Route::put   ('/resume/header',          [\App\Modules\Api\Controllers\ResumeController::class, 'updateHeader']);
        Route::post  ('/resume/header/photo',    [\App\Modules\Api\Controllers\ResumeController::class, 'uploadHeaderPhoto']);
        Route::delete('/resume/header/photo',    [\App\Modules\Api\Controllers\ResumeController::class, 'removeHeaderPhoto']);
        Route::put   ('/resume/summary',         [\App\Modules\Api\Controllers\ResumeController::class, 'updateSummary']);
        Route::put   ('/resume/template',        [\App\Modules\Api\Controllers\ResumeController::class, 'updateTemplate']);
        Route::put   ('/resume/color-theme',     [\App\Modules\Api\Controllers\ResumeController::class, 'updateColorTheme']);
        Route::post  ('/resume/items',           [\App\Modules\Api\Controllers\ResumeController::class, 'storeItem']);
        Route::put   ('/resume/items/{item}',    [\App\Modules\Api\Controllers\ResumeController::class, 'updateItem'])->whereNumber('item');
        Route::delete('/resume/items/{item}',    [\App\Modules\Api\Controllers\ResumeController::class, 'destroyItem'])->whereNumber('item');
        Route::post  ('/resume/items/reorder',   [\App\Modules\Api\Controllers\ResumeController::class, 'reorderItems']);
        Route::put   ('/resume/publishing',      [\App\Modules\Api\Controllers\ResumeController::class, 'updatePublishing']);
        Route::put   ('/resume/public-pdf',      [\App\Modules\Api\Controllers\ResumeController::class, 'updatePublicPdf']);
        Route::post  ('/resume/share/revoke',    [\App\Modules\Api\Controllers\ResumeController::class, 'revokeShare']);
        Route::get   ('/resume/views',           [\App\Modules\Api\Controllers\ResumeController::class, 'views']);

        // Version management — see web routes for the full surface area.
        Route::get   ('/resume/versions',                       [\App\Modules\Api\Controllers\ResumeController::class, 'versionsIndex']);
        Route::post  ('/resume/versions',                       [\App\Modules\Api\Controllers\ResumeController::class, 'versionStore']);
        Route::put   ('/resume/versions/{version}',             [\App\Modules\Api\Controllers\ResumeController::class, 'versionRename'])->whereNumber('version');
        Route::delete('/resume/versions/{version}',             [\App\Modules\Api\Controllers\ResumeController::class, 'versionDestroy'])->whereNumber('version');
        Route::post  ('/resume/versions/{version}/duplicate',   [\App\Modules\Api\Controllers\ResumeController::class, 'versionDuplicate'])->whereNumber('version');
        Route::post  ('/resume/versions/{version}/default',     [\App\Modules\Api\Controllers\ResumeController::class, 'versionSetDefault'])->whereNumber('version');

        // Posts (creator feed)
        Route::get   ('/posts',            [CreatorPostController::class, 'index']);
        Route::post  ('/posts',            [CreatorPostController::class, 'store']);
        Route::patch ('/posts/{id}',       [CreatorPostController::class, 'update'])->whereNumber('id');
        Route::delete('/posts/{id}',       [CreatorPostController::class, 'destroy'])->whereNumber('id');
        Route::post  ('/posts/{id}/pin',   [CreatorPostController::class, 'pin'])->whereNumber('id');
        Route::post  ('/posts/{id}/unpin', [CreatorPostController::class, 'unpin'])->whereNumber('id');

        // Contacts
        Route::get   ('/contacts',                  [ContactController::class, 'index']);
        Route::post  ('/contacts',                  [ContactController::class, 'store'])->middleware('throttle:120,1');
        Route::post  ('/contacts/validate',         [ContactController::class, 'validateCandidate'])->middleware('throttle:120,1');
        Route::post  ('/contacts/bulk',             [ContactController::class, 'bulkImport']);
        Route::get   ('/contacts/{id}',             [ContactController::class, 'show'])->whereNumber('id');
        Route::patch ('/contacts/{id}',             [ContactController::class, 'update'])->whereNumber('id');
        Route::post  ('/contacts/{id}/merge',       [ContactController::class, 'merge'])->whereNumber('id')->middleware('throttle:60,1');
        Route::delete('/contacts/{id}',             [ContactController::class, 'destroy'])->whereNumber('id');

        // Forms (read-only for mobile; full CRUD lives on web)
        Route::get('/forms',                              [FormController::class, 'index']);
        Route::get('/forms/{id}',                         [FormController::class, 'show'])->whereNumber('id');
        Route::get('/forms/{id}/submissions',             [FormController::class, 'submissions'])->whereNumber('id');
        Route::get('/forms/{id}/submissions.csv',         [FormController::class, 'exportSubmissions'])->whereNumber('id');

        // Inbox (DM threads on owned biolinks)
        Route::get   ('/inbox/threads',                   [InboxController::class, 'threads']);
        Route::get   ('/inbox/conversations',             [InboxController::class, 'conversations']);
        Route::get   ('/inbox/conversations/{id}',        [InboxController::class, 'show'])->whereNumber('id');
        Route::post  ('/inbox/conversations/{id}/reply',  [InboxController::class, 'reply'])->whereNumber('id');
        Route::patch ('/inbox/conversations/{id}/status', [InboxController::class, 'setStatus'])->whereNumber('id');
        Route::post  ('/inbox/conversations/{id}/assign', [InboxController::class, 'assign'])->whereNumber('id');
        Route::delete('/inbox/conversations/{id}',        [InboxController::class, 'destroy'])->whereNumber('id');
        Route::get   ('/inbox/teammates',                 [InboxController::class, 'teammates']);

        // Workspaces
        Route::get('/workspaces',                 [WorkspaceController::class, 'index']);
        Route::get('/workspaces/{id}/members',    [WorkspaceController::class, 'members'])->whereNumber('id');

        // Workspace tracking pixels (Meta / TikTok / Google Ads). Used by
        // the browser extension Settings → Tracking pixels panel so the
        // IDs follow the user across devices instead of living only in
        // browser.storage.
        Route::get('/workspace/pixels', [\App\Modules\Api\Controllers\WorkspacePixelsController::class, 'show']);
        Route::put('/workspace/pixels', [\App\Modules\Api\Controllers\WorkspacePixelsController::class, 'update']);

        // Backlink radar (browser extension): the creator's known
        // properties feed + persistence for matches the extension
        // discovers while the creator browses.
        Route::get   ('/me/properties',         [PropertyController::class, 'show']);
        Route::get   ('/backlinks',             [BacklinkController::class, 'index']);
        Route::get   ('/backlinks/export.csv',  [BacklinkController::class, 'export']);
        Route::post  ('/backlinks',             [BacklinkController::class, 'store'])->middleware('throttle:120,1');
        Route::delete('/backlinks/{id}',        [BacklinkController::class, 'destroy'])->whereNumber('id');

        // Thank-you templates used by the radar's "Thank composer".
        // Stored per workspace so they sync across browsers / reinstalls.
        Route::get('/me/thank-templates', [\App\Modules\Api\Controllers\ThankTemplateController::class, 'show']);
        Route::put('/me/thank-templates', [\App\Modules\Api\Controllers\ThankTemplateController::class, 'update']);

        // Queued thank-yous (the "Pending thanks" panel). Synced per
        // workspace alongside the templates so the queue follows the
        // creator across browsers / reinstalls.
        Route::get('/me/pending-thanks', [\App\Modules\Api\Controllers\PendingThankController::class, 'show']);
        Route::put('/me/pending-thanks', [\App\Modules\Api\Controllers\PendingThankController::class, 'update']);

        // Custom domains
        Route::get   ('/domains',        [DomainController::class, 'index']);
        Route::post  ('/domains',        [DomainController::class, 'store']);
        Route::delete('/domains/{id}',   [DomainController::class, 'destroy'])->whereNumber('id');

        // Splash pages
        Route::get   ('/splash-pages',        [SplashPageController::class, 'index']);
        Route::post  ('/splash-pages',        [SplashPageController::class, 'store']);
        Route::get   ('/splash-pages/{id}',   [SplashPageController::class, 'show'])->whereNumber('id');
        Route::patch ('/splash-pages/{id}',   [SplashPageController::class, 'update'])->whereNumber('id');
        Route::delete('/splash-pages/{id}',   [SplashPageController::class, 'destroy'])->whereNumber('id');

        // QR codes
        Route::get   ('/qr-codes',       [QrCodeController::class, 'index']);
        Route::post  ('/qr-codes',       [QrCodeController::class, 'store']);
        Route::get   ('/qr-codes/{id}',  [QrCodeController::class, 'show'])->whereNumber('id');
        Route::delete('/qr-codes/{id}',  [QrCodeController::class, 'destroy'])->whereNumber('id');

        // Social accounts + social proof
        Route::get   ('/social/connections',                 [SocialAccountController::class, 'connections']);
        Route::post  ('/social/connections',                 [SocialAccountController::class, 'connect']);
        Route::post  ('/social/connections/{id}/refresh',    [SocialAccountController::class, 'refresh'])->whereNumber('id');
        Route::delete('/social/connections/{id}',            [SocialAccountController::class, 'disconnect'])->whereNumber('id');
        Route::get   ('/social/proofs',                      [SocialAccountController::class, 'socialProofs']);
        Route::post  ('/social/proofs',                      [SocialAccountController::class, 'storeProof']);
        Route::patch ('/social/proofs/{id}',                 [SocialAccountController::class, 'updateProof'])->whereNumber('id');
        Route::delete('/social/proofs/{id}',                 [SocialAccountController::class, 'destroyProof'])->whereNumber('id');

        // Integrations
        Route::get   ('/integrations',        [IntegrationController::class, 'index']);
        Route::delete('/integrations/{id}',   [IntegrationController::class, 'destroy'])->whereNumber('id');

        // Calendar accounts + RSVP responses
        Route::get   ('/calendar/accounts',        [CalendarController::class, 'accounts']);
        Route::delete('/calendar/accounts/{id}',   [CalendarController::class, 'disconnectAccount'])->whereNumber('id');
        Route::get   ('/links/{id}/rsvps',         [CalendarController::class, 'rsvps'])->whereNumber('id');

        // Vault (read-only on mobile; secret reveal stays on web)
        Route::get   ('/vault/clients',     [VaultController::class, 'clients']);
        Route::get   ('/vault/credentials', [VaultController::class, 'credentials']);

        // Verification (creator badge)
        Route::get   ('/verifications',     [VerificationController::class, 'index']);
        Route::post  ('/verifications',     [VerificationController::class, 'store']);

        // Billing
        Route::get   ('/billing/subscription',     [BillingController::class, 'subscription']);
        Route::get   ('/billing/invoices',         [BillingController::class, 'invoices']);
        Route::get   ('/billing/invoices/{id}',    [BillingController::class, 'showInvoice'])->whereNumber('id');
        Route::post  ('/billing/invoices',         [BillingController::class, 'storeInvoice']);
        Route::patch ('/billing/invoices/{id}',    [BillingController::class, 'updateInvoice'])->whereNumber('id');
        Route::delete('/billing/invoices/{id}',    [BillingController::class, 'destroyInvoice'])->whereNumber('id');
        Route::post  ('/billing/invoices/{id}/send', [BillingController::class, 'sendInvoice'])->whereNumber('id')->middleware('throttle:30,1');

        // Client portals
        Route::get   ('/client-portals',           [\App\Modules\Api\Controllers\ClientPortalController::class, 'index']);
        Route::post  ('/client-portals',           [\App\Modules\Api\Controllers\ClientPortalController::class, 'store']);
        Route::get   ('/client-portals/{id}',      [\App\Modules\Api\Controllers\ClientPortalController::class, 'show'])->whereNumber('id');
        Route::patch ('/client-portals/{id}',      [\App\Modules\Api\Controllers\ClientPortalController::class, 'update'])->whereNumber('id');
        Route::delete('/client-portals/{id}',      [\App\Modules\Api\Controllers\ClientPortalController::class, 'destroy'])->whereNumber('id');
        Route::post  ('/client-portals/{id}/links', [\App\Modules\Api\Controllers\ClientPortalController::class, 'sendLink'])->whereNumber('id')->middleware('throttle:30,1');

        // Team / staff (active workspace)
        Route::get   ('/team',                     [\App\Modules\Api\Controllers\TeamController::class, 'index']);
        Route::post  ('/team/invite',              [\App\Modules\Api\Controllers\TeamController::class, 'invite'])->middleware('throttle:30,1');
        Route::delete('/team/invites/{invite}',    [\App\Modules\Api\Controllers\TeamController::class, 'revokeInvite'])->whereNumber('invite');
        Route::delete('/team/members/{member}',    [\App\Modules\Api\Controllers\TeamController::class, 'removeMember'])->whereNumber('member');

        // Plan + addon catalogue priced for the signed-in user, plus
        // the RevenueCat receipt-verification hook used by the mobile
        // app after a successful Purchases.purchasePackage / restore.
        Route::get ('/billing/plans',                [RevenueCatBillingController::class, 'plans']);
        Route::post('/billing/revenuecat/activate',  [RevenueCatBillingController::class, 'activate'])
            ->middleware('throttle:30,1');

        // Dialer
        Route::post  ('/dialer/lookup',  [DialerController::class, 'lookup'])->middleware('throttle:60,1');
        Route::get   ('/dialer/history', [DialerController::class, 'history']);
    });
});
