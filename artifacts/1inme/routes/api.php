<?php

use App\Modules\Api\Controllers\AccountMergeController;
use App\Modules\Api\Controllers\AdminAccessController;
use App\Modules\Api\Controllers\AuthController;
use App\Modules\Api\Controllers\BiolinkBlockController;
use App\Modules\Api\Controllers\BiolinkController;
use App\Modules\Api\Controllers\BiolinkWizardController;
use App\Modules\Api\Controllers\CardScanController;
use App\Modules\Api\Controllers\ConnectedAppController;
use App\Modules\Api\Controllers\ContactController;
use App\Modules\Api\Controllers\CreatorPostController;
use App\Modules\Api\Controllers\FeatureStateController;
use App\Modules\Api\Controllers\DiscoveryController;
use App\Modules\Api\Controllers\FeedController;
use App\Modules\Api\Controllers\FollowController;
use App\Modules\Api\Controllers\FormController;
use App\Modules\Api\Controllers\InboxController;
use App\Modules\Api\Controllers\LinkController;
use App\Modules\Api\Controllers\NfcWriteController;
use App\Modules\Api\Controllers\DashboardController;
use App\Modules\Api\Controllers\NotificationController;
use App\Modules\Api\Controllers\PushTokenController;
use App\Modules\Api\Controllers\ApiUsageController;
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
use App\Modules\Api\Controllers\AccountingController;
use App\Modules\Api\Controllers\BillingController;
use App\Modules\Api\Controllers\RevenueCatBillingController;
use App\Modules\Api\Controllers\DialerController;
use App\Modules\Api\Controllers\RestaurantController;
use App\Modules\Api\Controllers\StoreController;
use App\Modules\Api\Controllers\ServiceBookingController;
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

    // Public auth config — which login methods are available (email-only by
    // default; WhatsApp/mobile behind an admin toggle with allowed codes).
    Route::get('/auth/config', [OtpController::class, 'config']);

    // OTP-based mobile auth
    Route::post('/auth/otp/send',     [OtpController::class, 'send'])->middleware('throttle:otp-send');
    Route::post('/auth/otp/verify',   [OtpController::class, 'verify'])->middleware('throttle:otp-verify');
    Route::post('/auth/otp/register', [OtpController::class, 'register'])->middleware('throttle:auth-register');

    // Native social sign-in (Apple / Google / etc.)
    Route::post('/auth/social', [SocialAuthController::class, 'exchange'])->middleware('throttle:20,1');

    // Demo login (non-prod). Mirrors the web "Try as Demo" button.
    Route::post('/auth/demo', [OtpController::class, 'demo'])->middleware('throttle:20,1');

    // Cloud File OAuth landing (PUBLIC — the provider redirect carries no
    // bearer token). State is an APP_KEY-encrypted, short-lived token minted by
    // the authed POST /cloud-files/{provider}/connect; the handler exchanges
    // the code and bounces back to the app deep link. See CloudFilesApiController.
    Route::get('/cloud-files/oauth/callback', [\App\Modules\Api\Controllers\CloudFilesApiController::class, 'oauthCallback'])
        ->middleware('throttle:30,1');

    // ── Public, visibility-aware (optional bearer token) ────────────
    Route::middleware('api.optional_auth')->group(function () {
        Route::get('/biolinks/{alias}',            [BiolinkController::class, 'show']);

        // Restaurant menu (Task #1536) — public: fetch menu, place order,
        // poll guest order status. No auth, no online payment.
        Route::get('/restaurant/{alias}',              [RestaurantController::class, 'show']);
        Route::post('/restaurant/{alias}/quote',       [RestaurantController::class, 'quote'])->middleware('throttle:120,1');
        Route::post('/restaurant/{alias}/order',       [RestaurantController::class, 'placeOrder'])->middleware('throttle:30,1');
        Route::get('/restaurant/orders/{token}/status',[RestaurantController::class, 'orderStatus']);

        // Store menu (Task #3072) — public: fetch store, place order request,
        // poll guest order status. No auth, no online payment, no quote
        // endpoint (no tax/coupon means the total is just the line sum).
        Route::get('/store/{alias}',               [StoreController::class, 'show']);
        Route::post('/store/{alias}/order',        [StoreController::class, 'placeOrder'])->middleware('throttle:30,1');
        Route::get('/store/orders/{token}/status', [StoreController::class, 'orderStatus']);
        // Service booking (Task #3085) — public: fetch page, see free slots,
        // get an estimated quote, submit a booking request, poll status.
        // No online payment — every total is an estimate.
        Route::get ('/service-booking/{alias}',                [ServiceBookingController::class, 'show']);
        Route::post('/service-booking/{alias}/slots',          [ServiceBookingController::class, 'slots'])->middleware('throttle:120,1');
        Route::post('/service-booking/{alias}/quote',          [ServiceBookingController::class, 'quote'])->middleware('throttle:120,1');
        Route::post('/service-booking/{alias}/book',           [ServiceBookingController::class, 'book'])->middleware('throttle:20,1');
        Route::get ('/service-booking/bookings/{token}/status',[ServiceBookingController::class, 'bookingStatus']);

        // Public reviews feed + summary for a standalone Reviews page.
        Route::get('/reviews/{alias}',             [\App\Modules\Api\Controllers\ReviewApiController::class, 'index']);
        Route::get('/reviews/{alias}/summary',     [\App\Modules\Api\Controllers\ReviewApiController::class, 'summary']);
        Route::get('/discovery/creators',          [DiscoveryController::class, 'creators']);
        Route::get('/discovery/creators/{handle}', [DiscoveryController::class, 'creator']);
        Route::get('/feed',                        [FeedController::class, 'index']);
        Route::get('/creators/{handle}/feed',      [FeedController::class, 'byCreator']);

        // Creator Profile JSON API (Task #1207). Mirrors the /@handle web
        // surface so the Expo app can render the same page.
        Route::get('/creator-profile/{handle}',                          [\App\Modules\Api\Controllers\CreatorProfileApiController::class, 'show']);
        Route::get('/creator-profile/{handle}/posts',                    [\App\Modules\Api\Controllers\CreatorProfileApiController::class, 'feed']);
        Route::get('/creator-profile/{handle}/posts/{post}/comments',    [\App\Modules\Api\Controllers\CreatorProfileApiController::class, 'comments'])->whereNumber('post');
        Route::post('/creator-profile/{handle}/posts/{post}/react',      [\App\Modules\Api\Controllers\CreatorProfileApiController::class, 'react'])->whereNumber('post')->middleware('throttle:120,1');
        Route::post('/creator-profile/{handle}/posts/{post}/comment',    [\App\Modules\Api\Controllers\CreatorProfileApiController::class, 'comment'])->whereNumber('post')->middleware('throttle:60,1');

        // Standalone Paid Page (Task #1649). Resolved by link alias so the
        // Expo app can render the bold per-link themed design natively.
        // Reuses the handle-keyed react/comment endpoints above for the feed
        // interactions (the show response returns the creator handle).
        Route::get('/paid-page/{alias}',         [\App\Modules\Api\Controllers\CreatorProfileApiController::class, 'paidPageShow']);
        Route::get('/paid-page/{alias}/posts',   [\App\Modules\Api\Controllers\CreatorProfileApiController::class, 'paidPageFeed']);

        // Creator monetization (Task #1209). Public-facing per-creator
        // endpoints — listing tiers is unauthenticated; subscribing,
        // unlocking, and tipping require auth (Sanctum).
        Route::get ('/creators/{handle}/tiers',                  [\App\Modules\Api\Controllers\CreatorMonetizationApiController::class, 'tiers']);
        Route::post('/creators/{handle}/subscribe',              [\App\Modules\Api\Controllers\CreatorMonetizationApiController::class, 'subscribe'])->middleware('throttle:30,1');
        Route::post('/creators/{handle}/posts/{post}/unlock',    [\App\Modules\Api\Controllers\CreatorMonetizationApiController::class, 'unlockPost'])->whereNumber('post')->middleware('throttle:30,1');
        Route::post('/creators/{handle}/tip',                    [\App\Modules\Api\Controllers\CreatorMonetizationApiController::class, 'tip'])->middleware('throttle:30,1');
        Route::get ('/creators/{handle}/my-subscription',        [\App\Modules\Api\Controllers\CreatorMonetizationApiController::class, 'mySubscription']);
        Route::post('/creators/{handle}/my-subscription/cancel', [\App\Modules\Api\Controllers\CreatorMonetizationApiController::class, 'cancelSubscription']);
        Route::post('/creators/{handle}/my-subscription/resume', [\App\Modules\Api\Controllers\CreatorMonetizationApiController::class, 'resumeSubscription']);

        // All subscriptions the signed-in fan holds — backs the native
        // "manage subscription" screen (Task #3019) so a fan can review
        // and cancel/resume every creator subscription in-app.
        Route::get ('/me/subscriptions', [\App\Modules\Api\Controllers\CreatorMonetizationApiController::class, 'mySubscriptions']);

        // Owner-side dashboard endpoints (Sanctum-authenticated creator).
        Route::get('/me/creator/earnings',     [\App\Modules\Api\Controllers\CreatorMonetizationApiController::class, 'earnings']);
        Route::get('/me/creator/subscribers',  [\App\Modules\Api\Controllers\CreatorMonetizationApiController::class, 'ownerSubscribers']);
        Route::get('/me/creator/payments',     [\App\Modules\Api\Controllers\CreatorMonetizationApiController::class, 'ownerPayments']);
        Route::get('/me/creator/tiers',        [\App\Modules\Api\Controllers\CreatorMonetizationApiController::class, 'ownerTiers']);

        // Unified creator Stats home (mobile parity for web /user/stats).
        Route::get('/stats', [\App\Modules\Api\Controllers\CreatorStatsApiController::class, 'index']);

        // In-page Product storefront (Task #1763). The cart lives in the app
        // (no session on the Sanctum path) and is posted as line items.
        Route::post('/store/{alias}/buy',      [\App\Modules\Api\Controllers\BiolinkStoreApiController::class, 'buy'])->middleware('throttle:30,1');
        Route::post('/store/{alias}/checkout', [\App\Modules\Api\Controllers\BiolinkStoreApiController::class, 'checkout'])->middleware('throttle:30,1');
        Route::get ('/store/orders/{order}',   [\App\Modules\Api\Controllers\BiolinkStoreApiController::class, 'order'])->whereNumber('order');
        Route::get ('/me/creator/orders',                [\App\Modules\Api\Controllers\BiolinkStoreApiController::class, 'ownerOrders']);
        Route::post('/me/creator/orders/{order}/fulfill',[\App\Modules\Api\Controllers\BiolinkStoreApiController::class, 'fulfillOrder'])->whereNumber('order');
    });

    Route::post('/biolinks/{alias}/subscribe', [BiolinkController::class, 'subscribe'])
        ->middleware('throttle:10,1');

    // Public, no-login review submission (honeypot + SpamChecker inside).
    // optional_auth so a bearer token is honoured when the page is
    // visibility-gated (registered/followers/subscribers).
    Route::post('/reviews/{alias}', [\App\Modules\Api\Controllers\ReviewApiController::class, 'submit'])
        ->middleware(['api.optional_auth', 'throttle:10,1']);

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

    // Public marketing content for the mobile "Info" screens. Mirrors the
    // admin-editable web /about page (incl. the EEFind parent-company block)
    // so mobile copy stays in sync without an app rebuild.
    Route::middleware('throttle:60,1')
        ->get('/site/about', [\App\Modules\Api\Controllers\SiteContentController::class, 'about']);

    // Public brand contact details (address, support email, phone, hours,
    // social links, map) mirroring the admin-editable web /contact card so both
    // the mobile Contact screen and the marketing site's Contact page stay in
    // sync with admin edits without an app rebuild or redeploy, and always serve
    // the correct brand defaults (never stale placeholders) when unedited.
    Route::middleware('throttle:60,1')
        ->get('/site/contact', [\App\Modules\Api\Controllers\SiteContentController::class, 'contact']);

    // Auto-pixel interstitial fire beacon — public, anonymous visitors.
    // The interstitial loads the configured pixel scripts then POSTs here
    // to record one row in `link_pixel_fires` so the dashboard can show
    // retargeting impact. Throttled hard since it's an unauthenticated
    // public endpoint.
    Route::middleware('throttle:120,1')
        ->post('/links/{alias}/pixel-fire', [\App\Modules\Api\Controllers\LinkPixelFireController::class, 'store']);

    // Mobile splash slider — admin-managed onboarding slides.
    Route::get('/onboarding/slides', [OnboardingSlideController::class, 'index']);

    // Multi-channel quick-contact (Call back / WhatsApp call / Email) — mobile
    // parity for the web standalone widget. Reuses the SAME contract +
    // QuickContactService as POST /assistant/quick-contact (web), so a request
    // lands in the admin Contact Inbox and triggers an admin email. NOT
    // login-gated (mirrors the always-anonymous web widget), but optional_auth
    // is applied so a signed-in caller's name/email default in. Throttled to
    // match the web route's throttle:10,1.
    Route::middleware(['api.optional_auth', 'throttle:10,1'])
        ->post('/assistant/quick-contact', [\App\Modules\Common\Controllers\SiteAssistantController::class, 'quickContact']);

    // ── Authenticated ───────────────────────────────────────────────
    Route::middleware(['auth:sanctum', \App\Modules\Api\Middleware\TouchSessionToken::class, \App\Modules\Api\Middleware\MeterApiUsage::class])->group(function () {
        Route::get('/auth/me',     [AuthController::class, 'me']);
        Route::post('/auth/logout',[AuthController::class, 'logout']);

        // Post-sign-up email verification reminder (mobile parity with the
        // web banner). Send a 6-digit code, then confirm it to stamp
        // email_verified_at. Throttled like the login OTP buckets.
        Route::post('/auth/email-verify/send',    [AuthController::class, 'sendEmailVerifyCode'])->middleware('throttle:otp-send');
        Route::post('/auth/email-verify/confirm', [AuthController::class, 'confirmEmailVerifyCode'])->middleware('throttle:otp-verify');

        // ── Paid DMs (Task #1210) ───────────────────────────────
        // Mobile-facing wrappers around the same controller methods
        // the web modal uses. Mounted under /api/v1 (Sanctum Bearer,
        // CSRF-exempt by design) so native clients don't have to deal
        // with cookie-based session auth or CSRF headers. The web
        // surface keeps using the cookie-authed /viewer/dm/* routes.
        Route::get ('/dm/profile/{handle}/access',          [\App\Modules\Common\Controllers\ProfileDirectMessageController::class, 'access']);
        Route::get ('/dm/profile/{handle}/thread',          [\App\Modules\Common\Controllers\ProfileDirectMessageController::class, 'thread']);
        Route::post('/dm/profile/{handle}/send',            [\App\Modules\Common\Controllers\ProfileDirectMessageController::class, 'send'])->middleware('throttle:30,1');
        Route::post('/dm/attachments/{attachment}/unlock',  [\App\Modules\Common\Controllers\ProfileDirectMessageController::class, 'unlockAttachment'])->whereNumber('attachment')->middleware('throttle:30,1');
        Route::post('/dm/threads/{conversation}/tip',       [\App\Modules\Common\Controllers\ProfileDirectMessageController::class, 'tip'])->whereNumber('conversation')->middleware('throttle:20,1');

        // Devices & sessions (task #1111).
        Route::get   ('/auth/sessions',                [\App\Modules\Api\Controllers\SessionsController::class, 'index']);
        Route::delete('/auth/sessions/others',         [\App\Modules\Api\Controllers\SessionsController::class, 'destroyOthers']);
        Route::delete('/auth/sessions/{id}',           [\App\Modules\Api\Controllers\SessionsController::class, 'destroy'])
            ->where('id', '[A-Za-z0-9:_-]+');

        Route::get('/profile',   [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);

        // ── Account merge (Task #2174) ───────────────────────────────
        // Stateless Bearer-token mirror of the web /user/merge flow so a
        // mobile user can swallow a duplicate account they also control,
        // without hopping out to the browser. challenge → verify →
        // preview → confirm; the proven secondary id rides between steps
        // in an encrypted "merge token" instead of a session.
        Route::prefix('account/merge')->group(function () {
            Route::post('/challenge', [AccountMergeController::class, 'challenge'])->middleware('throttle:otp-send');
            Route::post('/verify',    [AccountMergeController::class, 'verify'])->middleware('throttle:otp-verify');
            Route::post('/preview',   [AccountMergeController::class, 'preview']);
            Route::post('/confirm',   [AccountMergeController::class, 'confirm'])->middleware('throttle:10,1');
        });

        // ── Reviews moderation (owner-scoped) ────────────────────────
        // Bearer-token parity for the web /user/.../reviews/* moderation
        // actions so creators can approve / hide / pin / reply / delete
        // their own native reviews from the mobile "Manage reviews"
        // screen. Every action is scoped to the authenticated owner.
        Route::get   ('/me/reviews',                  [\App\Modules\Api\Controllers\ReviewApiController::class, 'mine']);
        Route::post  ('/me/reviews/{review}/approve', [\App\Modules\Api\Controllers\ReviewApiController::class, 'approve'])->whereNumber('review');
        Route::post  ('/me/reviews/{review}/hide',    [\App\Modules\Api\Controllers\ReviewApiController::class, 'hide'])->whereNumber('review');
        Route::post  ('/me/reviews/{review}/pin',     [\App\Modules\Api\Controllers\ReviewApiController::class, 'pin'])->whereNumber('review');
        Route::post  ('/me/reviews/{review}/reply',   [\App\Modules\Api\Controllers\ReviewApiController::class, 'reply'])->whereNumber('review');
        Route::delete('/me/reviews/{review}',         [\App\Modules\Api\Controllers\ReviewApiController::class, 'destroy'])->whereNumber('review');

        // ── Roadmap triage (owner-scoped) ────────────────────────────
        // Bearer-token parity for the web /user/links/{link}/roadmap
        // triage dashboard so a creator can manage their biolink's public
        // roadmap submissions from mobile: list items + counts, change an
        // item's status/fields, delete an idea, and merge a duplicate into
        // another. Ownership is the same `links.edit` gate as the web — the
        // link must belong to the caller and be a biolink-family page.
        Route::get   ('/links/{link}/roadmap',                  [\App\Modules\Api\Controllers\RoadmapTriageController::class, 'index'])->whereNumber('link');
        Route::patch ('/links/{link}/roadmap/items/{item}',     [\App\Modules\Api\Controllers\RoadmapTriageController::class, 'update'])->whereNumber('link')->whereNumber('item');
        Route::delete('/links/{link}/roadmap/items/{item}',     [\App\Modules\Api\Controllers\RoadmapTriageController::class, 'destroy'])->whereNumber('link')->whereNumber('item');
        Route::post  ('/links/{link}/roadmap/items/{item}/merge',[\App\Modules\Api\Controllers\RoadmapTriageController::class, 'merge'])->whereNumber('link')->whereNumber('item');

        // ── Email history (self-scoped) ──────────────────────────────
        // Bearer-token parity for the web /user/emails screen: the user's own
        // transactional emails plus a throttled, allow-listed resend (invoices /
        // receipts / verification) to their own address.
        Route::get ('/me/emails',                  [\App\Modules\Api\Controllers\EmailHistoryController::class, 'index']);
        Route::post('/me/emails/{emailLog}/resend', [\App\Modules\Api\Controllers\EmailHistoryController::class, 'resend'])->whereNumber('emailLog')->middleware('throttle:5,1');

        // Creator Payouts + Adult-content (Task #1208) ───────────────
        // Mobile parity for the "Earnings & Payouts" dashboard. The
        // hosted-onboarding URL is returned to the app to open in an
        // in-app browser; webhooks + return parsing remain server-side.
        Route::get   ('/payouts',                       [\App\Modules\Api\Controllers\CreatorPayoutsApiController::class, 'index']);
        Route::post  ('/payouts/{provider}/connect',    [\App\Modules\Api\Controllers\CreatorPayoutsApiController::class, 'connect']);
        Route::post  ('/payouts/{connection}/sync',     [\App\Modules\Api\Controllers\CreatorPayoutsApiController::class, 'sync'])->whereNumber('connection');
        Route::post  ('/payouts/{connection}/default',  [\App\Modules\Api\Controllers\CreatorPayoutsApiController::class, 'setDefault'])->whereNumber('connection');
        Route::delete('/payouts/{connection}',          [\App\Modules\Api\Controllers\CreatorPayoutsApiController::class, 'destroy'])->whereNumber('connection');

        Route::get   ('/adult-content', [\App\Modules\Api\Controllers\CreatorPayoutsApiController::class, 'adultShow']);
        Route::post  ('/adult-content', [\App\Modules\Api\Controllers\CreatorPayoutsApiController::class, 'adultUpdate']);

        // ── Cloud File Library (Google Drive / Dropbox / OneDrive) ──
        // Web parity for connect/browse/library/attach. Workspace
        // permissions (files.view / files.create / files.delete) are enforced
        // inside the controller, mirroring the web `workspace.can:*`
        // middleware. The OAuth landing is a separate PUBLIC route (below,
        // outside auth:sanctum) because the provider redirect carries no
        // bearer token.
        Route::get   ('/cloud-files/connections',            [\App\Modules\Api\Controllers\CloudFilesApiController::class, 'connections']);
        Route::post  ('/cloud-files/{provider}/connect',     [\App\Modules\Api\Controllers\CloudFilesApiController::class, 'connect']);
        Route::delete('/cloud-files/connections/{connection}',[\App\Modules\Api\Controllers\CloudFilesApiController::class, 'disconnect'])->whereNumber('connection');
        Route::get   ('/cloud-files/picker/{connection}',    [\App\Modules\Api\Controllers\CloudFilesApiController::class, 'browse'])->whereNumber('connection');
        Route::get   ('/cloud-files',                         [\App\Modules\Api\Controllers\CloudFilesApiController::class, 'library']);
        Route::post  ('/cloud-files',                         [\App\Modules\Api\Controllers\CloudFilesApiController::class, 'store']);
        Route::delete('/cloud-files/{cloudFile}',            [\App\Modules\Api\Controllers\CloudFilesApiController::class, 'destroy'])->whereNumber('cloudFile');
        Route::get   ('/cloud-files/attachments',            [\App\Modules\Api\Controllers\CloudFilesApiController::class, 'attachments']);
        Route::post  ('/cloud-files/attach',                 [\App\Modules\Api\Controllers\CloudFilesApiController::class, 'attach']);
        Route::delete('/cloud-files/attach/{attachment}',    [\App\Modules\Api\Controllers\CloudFilesApiController::class, 'detach'])->whereNumber('attachment');

        // Mail / SMTP settings (super-admin parity). Status + a live
        // "send test email" plus full editing of the transport, all mirroring
        // the web admin page and gated behind `settings.manage`.
        Route::get ('/admin/mail-settings',      [\App\Modules\Api\Controllers\MailSettingsController::class, 'status']);
        Route::put ('/admin/mail-settings',      [\App\Modules\Api\Controllers\MailSettingsController::class, 'update']);
        Route::post('/admin/mail-settings/test', [\App\Modules\Api\Controllers\MailSettingsController::class, 'sendTest'])->middleware('throttle:10,1');

        // Email templates (super-admin parity): list every templated email by
        // category, edit/reset a subject/body override and preview it with
        // sample data through the same central pipeline as real sends. Gated
        // behind `settings.manage`.
        Route::get   ('/admin/email-templates',                [\App\Modules\Api\Controllers\EmailTemplateController::class, 'index']);
        Route::get   ('/admin/email-templates/{key}',          [\App\Modules\Api\Controllers\EmailTemplateController::class, 'show'])->where('key', '[A-Za-z0-9._-]+');
        Route::put   ('/admin/email-templates/{key}',          [\App\Modules\Api\Controllers\EmailTemplateController::class, 'update'])->where('key', '[A-Za-z0-9._-]+');
        Route::delete('/admin/email-templates/{key}',          [\App\Modules\Api\Controllers\EmailTemplateController::class, 'reset'])->where('key', '[A-Za-z0-9._-]+');
        Route::post  ('/admin/email-templates/{key}/preview',  [\App\Modules\Api\Controllers\EmailTemplateController::class, 'preview'])->where('key', '[A-Za-z0-9._-]+');

        // Email activity log (super-admin parity): browse/search/filter every
        // outbound email and resend any one (throttled). Gated behind
        // `settings.manage`.
        Route::get ('/admin/email-logs',                  [\App\Modules\Api\Controllers\EmailLogController::class, 'index']);
        Route::get ('/admin/email-logs/{emailLog}',       [\App\Modules\Api\Controllers\EmailLogController::class, 'show'])->whereNumber('emailLog');
        Route::post('/admin/email-logs/{emailLog}/resend', [\App\Modules\Api\Controllers\EmailLogController::class, 'resend'])->whereNumber('emailLog')->middleware('throttle:30,1');

        // Schema-column repair (super-admin parity). Read-only drift report
        // plus the destructive-adjacent one-click repair, mirroring the web
        // dashboard banner and gated behind `settings.manage`. The repair
        // ALTERs the live schema, so it is rate-limited and writes an audit row.
        Route::get ('/admin/schema-health',        [\App\Modules\Api\Controllers\SchemaHealthController::class, 'status']);
        Route::get ('/admin/schema-health/audits', [\App\Modules\Api\Controllers\SchemaHealthController::class, 'audits']);
        Route::post('/admin/schema-health/repair', [\App\Modules\Api\Controllers\SchemaHealthController::class, 'repair'])->middleware('throttle:10,1');

        // Analytics-storage health parity (Task #2356): read analytics-history
        // growth + retention and set/clear the hard cap + growth-alert
        // threshold. Mirrors the web admin panel, gated behind `settings.manage`.
        Route::get('/admin/stats-storage', [\App\Modules\Api\Controllers\StatsStorageController::class, 'status']);
        Route::put('/admin/stats-storage', [\App\Modules\Api\Controllers\StatsStorageController::class, 'update']);

        // Cron jobs reference (super-admin parity). Read-only list of the
        // required scheduled jobs plus the single master crontab line, derived
        // live from routes/console.php via CronJobsInspector and mirroring the
        // web admin "Cron Jobs" page. Gated behind `settings.manage`.
        Route::get ('/admin/cron-jobs', [\App\Modules\Api\Controllers\CronJobsController::class, 'index']);

        // Admin dashboard switch, role / admin-access assignment and
        // impersonation (mobile parity for the back-office tooling). The
        // operator's authority comes from their email-linked back-office
        // Admin record, so a mobile user with an active admin account can
        // reach these with the same token — no re-login. Each action is
        // gated behind the same admin-guard permission the web routes use.
        Route::get   ('/admin/context',                       [AdminAccessController::class, 'context']);
        Route::get   ('/admin/users',                         [AdminAccessController::class, 'users']);
        Route::get   ('/admin/users/{user}/roles',            [AdminAccessController::class, 'userRoles'])->whereNumber('user');
        Route::put   ('/admin/users/{user}/roles',            [AdminAccessController::class, 'updateRoles'])->whereNumber('user');
        Route::post  ('/admin/users/{user}/admin-access',     [AdminAccessController::class, 'grantAdminAccess'])->whereNumber('user');
        Route::delete('/admin/users/{user}/admin-access',     [AdminAccessController::class, 'revokeAdminAccess'])->whereNumber('user');
        Route::post  ('/admin/users/{user}/impersonate',      [AdminAccessController::class, 'impersonate'])->whereNumber('user')->middleware('throttle:20,1');

        // Protected accounts (mobile parity for the web back-office page).
        // The canonical never-delete/suspend list: staff with `users.view`
        // read it; only a super-admin may add/remove entries, and the
        // hard-locked seeds (super-admin + demo) can never be removed.
        Route::get   ('/admin/protected-accounts',      [\App\Modules\Api\Controllers\ProtectedAccountController::class, 'index']);
        Route::post  ('/admin/protected-accounts',      [\App\Modules\Api\Controllers\ProtectedAccountController::class, 'store']);
        Route::delete('/admin/protected-accounts/{protectedAccount}', [\App\Modules\Api\Controllers\ProtectedAccountController::class, 'destroy'])->whereNumber('protectedAccount');

        // Plan editor (mobile parity for the back-office plan management).
        // Lists plans WITH the admin-only `is_internal` flag, accepts it on
        // create/update, and mirrors the web deep-copy Duplicate action.
        // Gated behind the same admin-guard `plans.view` / `plans.manage`
        // permissions as the web routes.
        Route::get ('/admin/plans',                       [\App\Modules\Api\Controllers\AdminPlanController::class, 'index']);
        Route::post('/admin/plans',                       [\App\Modules\Api\Controllers\AdminPlanController::class, 'store']);
        Route::put ('/admin/plans/{plan}',                [\App\Modules\Api\Controllers\AdminPlanController::class, 'update'])->whereNumber('plan');
        Route::post('/admin/plans/{plan}/duplicate',      [\App\Modules\Api\Controllers\AdminPlanController::class, 'duplicate'])->whereNumber('plan');

        // Banned names / reserved handles (mobile parity for the back-office
        // page). Gated inside the controller behind the same admin-guard
        // `settings.manage` permission the web routes use.
        Route::get   ('/admin/banned-names',                 [\App\Modules\Api\Controllers\AdminBannedNameController::class, 'index']);
        Route::post  ('/admin/banned-names',                 [\App\Modules\Api\Controllers\AdminBannedNameController::class, 'store']);
        Route::post  ('/admin/banned-names/bulk',            [\App\Modules\Api\Controllers\AdminBannedNameController::class, 'bulkStore']);
        Route::post  ('/admin/banned-names/restore-defaults',[\App\Modules\Api\Controllers\AdminBannedNameController::class, 'restoreDefaults']);
        Route::get   ('/admin/banned-names/export',          [\App\Modules\Api\Controllers\AdminBannedNameController::class, 'export']);
        Route::get   ('/admin/banned-names/{id}/conflicts',  [\App\Modules\Api\Controllers\AdminBannedNameController::class, 'conflicts'])->whereNumber('id');
        Route::post  ('/admin/banned-names/{id}/force-rename',[\App\Modules\Api\Controllers\AdminBannedNameController::class, 'toggleForceRename'])->whereNumber('id');
        Route::delete('/admin/banned-names/{id}',            [\App\Modules\Api\Controllers\AdminBannedNameController::class, 'destroy'])->whereNumber('id');

        // Wallet & coins (mobile parity).
        Route::get ('/wallet',              [WalletController::class, 'balance']);
        Route::get ('/wallet/transactions', [WalletController::class, 'transactions']);
        Route::get ('/wallet/packages',     [WalletController::class, 'packages']);
        Route::post('/wallet/purchase',     [WalletController::class, 'purchase']);

        // AI Mind picker defaults (Persona / Coach mobile parity).
        Route::get   ('/ai/minds',                  [\App\Modules\Api\Controllers\AiMindPickerController::class, 'minds']);
        Route::get   ('/ai/{feature}/defaults',     [\App\Modules\Api\Controllers\AiMindPickerController::class, 'getDefaults'])->whereIn('feature', ['persona', 'coach']);
        Route::put   ('/ai/{feature}/defaults',     [\App\Modules\Api\Controllers\AiMindPickerController::class, 'saveDefaults'])->whereIn('feature', ['persona', 'coach']);
        Route::delete('/ai/{feature}/defaults',     [\App\Modules\Api\Controllers\AiMindPickerController::class, 'clearDefaults'])->whereIn('feature', ['persona', 'coach']);

        // AI resource sharing (mobile parity for Task #2909 → Task #2923).
        // Read minds / personas shared WITH the acting user, and let owners
        // grant / revoke team + badge-group shares. Access is resolved live;
        // AI / coin costs are always charged to the acting user, not the owner.
        Route::get   ('/ai/shared',                       [\App\Modules\Api\Controllers\AiResourceShareController::class, 'shared']);
        Route::get   ('/ai/minds/{mind}/shares',          [\App\Modules\Api\Controllers\AiResourceShareController::class, 'indexMind'])->whereNumber('mind');
        Route::post  ('/ai/minds/{mind}/shares',          [\App\Modules\Api\Controllers\AiResourceShareController::class, 'storeMind'])->whereNumber('mind');
        Route::delete('/ai/minds/{mind}/shares/{share}',  [\App\Modules\Api\Controllers\AiResourceShareController::class, 'destroyMind'])->whereNumber('mind')->whereNumber('share');
        Route::get   ('/ai/personas/{persona}/shares',         [\App\Modules\Api\Controllers\AiResourceShareController::class, 'indexPersona'])->whereNumber('persona');
        Route::post  ('/ai/personas/{persona}/shares',         [\App\Modules\Api\Controllers\AiResourceShareController::class, 'storePersona'])->whereNumber('persona');
        Route::delete('/ai/personas/{persona}/shares/{share}', [\App\Modules\Api\Controllers\AiResourceShareController::class, 'destroyPersona'])->whereNumber('persona')->whereNumber('share');

        // Voice Assistant (mobile parity for the floating mic). Same
        // orchestrator as the web — STT/LLM/TTS each charge their own
        // ledger row (voice_stt / voice_llm / voice_tts). Throttled to
        // match the web limit so abuse can't drain the user's credits.
        Route::get ('/ai/voice/capabilities', [\App\Modules\Api\Controllers\VoiceAssistantController::class, 'capabilities']);
        Route::post('/ai/voice/turn',         [\App\Modules\Api\Controllers\VoiceAssistantController::class, 'turn'])->middleware('throttle:30,1');
        Route::post('/ai/voice/transcribe',   [\App\Modules\Api\Controllers\VoiceAssistantController::class, 'transcribe'])->middleware('throttle:30,1');
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

        // AI Marketing Strategist (Task #3060): REST parity for the web
        // digital-performer feature — generate an organic + paid plan from
        // the creator's own data, chat-refine (streamed, metered), and
        // one-click apply suggestions.
        Route::get   ('/ai/marketing-strategist',                          [\App\Modules\Api\Controllers\MarketingStrategistController::class, 'index']);
        Route::post  ('/ai/marketing-strategist/estimate',                 [\App\Modules\Api\Controllers\MarketingStrategistController::class, 'estimate']);
        Route::post  ('/ai/marketing-strategist',                          [\App\Modules\Api\Controllers\MarketingStrategistController::class, 'store'])->middleware('throttle:20,1');
        Route::get   ('/ai/marketing-strategist/{strategy}',               [\App\Modules\Api\Controllers\MarketingStrategistController::class, 'show'])->whereNumber('strategy');
        Route::get   ('/ai/marketing-strategist/{strategy}/export',        [\App\Modules\Api\Controllers\MarketingStrategistController::class, 'export'])->whereNumber('strategy');
        Route::delete('/ai/marketing-strategist/{strategy}',               [\App\Modules\Api\Controllers\MarketingStrategistController::class, 'destroy'])->whereNumber('strategy');
        Route::post  ('/ai/marketing-strategist/{strategy}/chat',          [\App\Modules\Api\Controllers\MarketingStrategistController::class, 'chat'])->whereNumber('strategy')->middleware('throttle:30,1');
        Route::post  ('/ai/marketing-strategist/suggestions/{suggestion}/apply',   [\App\Modules\Api\Controllers\MarketingStrategistController::class, 'applySuggestion'])->whereNumber('suggestion');
        Route::post  ('/ai/marketing-strategist/suggestions/{suggestion}/dismiss', [\App\Modules\Api\Controllers\MarketingStrategistController::class, 'dismissSuggestion'])->whereNumber('suggestion');

        // AI Staff (Task #3523) — mobile parity is deliberately narrow:
        // list/enable staff + read/apply/dismiss suggestions. Drafting,
        // chat, and chase-generation stay web-only for now.
        Route::get   ('/ai/staff',                                  [\App\Modules\Api\Controllers\AiStaffController::class, 'index']);
        Route::patch ('/ai/staff/{staff}',                          [\App\Modules\Api\Controllers\AiStaffController::class, 'update'])->whereNumber('staff');
        Route::get   ('/ai/staff/suggestions',                      [\App\Modules\Api\Controllers\AiStaffController::class, 'suggestions']);
        Route::post  ('/ai/staff/suggestions/{suggestion}/apply',   [\App\Modules\Api\Controllers\AiStaffController::class, 'applySuggestion'])->whereNumber('suggestion');
        Route::post  ('/ai/staff/suggestions/{suggestion}/dismiss', [\App\Modules\Api\Controllers\AiStaffController::class, 'dismissSuggestion'])->whereNumber('suggestion');

        // Onboarding
        Route::get('/onboarding',          [OnboardingController::class, 'status']);
        Route::post('/onboarding/complete',[OnboardingController::class, 'complete']);

        // Links
        Route::get('/links',         [LinkController::class, 'index']);
        Route::post('/links',        [LinkController::class, 'store']);
        // CSV export of the caller's link list (web parity for
        // user.links.export). Literal `export.csv` is registered before the
        // whereNumber-guarded `/links/{id}` show route below.
        Route::get('/links/export.csv', [LinkController::class, 'export']);
        // Live Custom URL availability check (mobile parity for the web
        // user.links.check-alias). Literal `check-alias` wins over the
        // whereNumber-guarded `/links/{id}` route below.
        Route::get('/links/check-alias', [LinkController::class, 'checkAlias']);
        // Guided Link-in-Bio wizard (mobile parity for web user.links.wizard.*).
        // Stateless: the client drives the steps and submits all answers to
        // /generate. Literal `wizard` segments win over `/links/{id}` (which is
        // whereNumber-guarded), so ordering here is purely cosmetic.
        Route::get ('/links/wizard/taxonomy',  [BiolinkWizardController::class, 'taxonomy']);
        Route::get ('/links/wizard/starting-designs', [BiolinkWizardController::class, 'startingDesigns']);
        Route::get ('/links/wizard/questions', [BiolinkWizardController::class, 'questions']);
        Route::get ('/links/wizard/alias-availability', [BiolinkWizardController::class, 'checkAlias']);
        Route::post('/links/wizard/image',     [BiolinkWizardController::class, 'uploadImage']);
        Route::post('/links/wizard/generate',  [BiolinkWizardController::class, 'generate']);
        Route::post('/links/wizard/ai-generate', [BiolinkWizardController::class, 'aiGenerate']);
        Route::get ('/links/wizard/resources',   [BiolinkWizardController::class, 'resources']);
        // Scan a business card / brochure (mobile parity for the web-only
        // user.card-scans.* flow). Upload runs the shared AI extraction
        // service; review/save persists a Contact and/or seeds a wizard draft.
        Route::post('/card-scans',             [CardScanController::class, 'store'])->middleware('throttle:20,1');
        Route::get ('/card-scans/{scan}',      [CardScanController::class, 'show'])->whereNumber('scan');
        Route::post('/card-scans/{scan}/save', [CardScanController::class, 'save'])->whereNumber('scan');
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

        // ── Link Insurance (Task #2602) ──────────────────────────────
        // Bearer-token parity for the web /user/insurance + per-link
        // /user/links/{link}/insurance surfaces: workspace dashboard,
        // per-link settings, update + replace backups, restore primary,
        // and an on-demand probe. The probe/failover/restore engine is
        // reused verbatim from LinkHealthChecker — no new logic. The
        // signed-email one-click restore/promote-next routes stay web-only.
        Route::get ('/insurance',                    [\App\Modules\Api\Controllers\LinkInsuranceController::class, 'dashboard']);
        Route::get ('/links/{id}/insurance',         [\App\Modules\Api\Controllers\LinkInsuranceController::class, 'show'])->whereNumber('id');
        Route::put ('/links/{id}/insurance',         [\App\Modules\Api\Controllers\LinkInsuranceController::class, 'update'])->whereNumber('id');
        Route::post('/links/{id}/insurance/restore', [\App\Modules\Api\Controllers\LinkInsuranceController::class, 'restore'])->whereNumber('id');
        Route::post('/links/{id}/insurance/probe',   [\App\Modules\Api\Controllers\LinkInsuranceController::class, 'probe'])->whereNumber('id')->middleware('throttle:30,1');

        // ── Delivery Projects (Task #3564) ───────────────────────────
        // Bearer-token parity for the web /user/delivery-projects surfaces:
        // list projects, view a project with tasks + workspace members, and
        // task CRUD (add/update/delete). Reads need `tasks.view`, mutations
        // need `tasks.edit`, resolved across accessible workspaces (the
        // stateless Sanctum path never runs SetActiveWorkspace). The public
        // share_token page and warranty reminders stay web/command-side.
        Route::get   ('/delivery-projects',              [\App\Modules\Api\Controllers\DeliveryProjectController::class, 'index']);
        Route::get   ('/delivery-projects/{id}',         [\App\Modules\Api\Controllers\DeliveryProjectController::class, 'show'])->whereNumber('id');
        Route::post  ('/delivery-projects/{id}/tasks',   [\App\Modules\Api\Controllers\DeliveryProjectController::class, 'storeTask'])->whereNumber('id');
        Route::post  ('/delivery-projects/{id}/tasks/reorder', [\App\Modules\Api\Controllers\DeliveryProjectController::class, 'reorderTasks'])->whereNumber('id');
        Route::patch ('/delivery-projects/tasks/{task}', [\App\Modules\Api\Controllers\DeliveryProjectController::class, 'updateTask'])->whereNumber('task');
        Route::delete('/delivery-projects/tasks/{task}', [\App\Modules\Api\Controllers\DeliveryProjectController::class, 'destroyTask'])->whereNumber('task');
        Route::get   ('/delivery-projects/{id}/comments', [\App\Modules\Api\Controllers\DeliveryProjectController::class, 'comments'])->whereNumber('id');
        Route::post  ('/delivery-projects/{id}/comments', [\App\Modules\Api\Controllers\DeliveryProjectController::class, 'storeComment'])->whereNumber('id');

        // Full-page AI chat link editor (links.type = ai_chat). Mirrors
        // the web user.links.ai-chat.{editor,save} routes, reusing the AI
        // Companion infra via the shared AiChatPageManager.
        Route::get ('/links/{id}/ai-chat', [\App\Modules\Api\Controllers\AiChatController::class, 'show'])->whereNumber('id');
        Route::put ('/links/{id}/ai-chat', [\App\Modules\Api\Controllers\AiChatController::class, 'save'])->whereNumber('id');

        // Conversational flow editor (links.type = conversational). Mirrors
        // the web user.links.conversational.{editor,save} routes, reusing
        // the shared flow validation/persistence helpers.
        Route::get ('/links/{id}/conversational', [\App\Modules\Api\Controllers\ConversationFlowController::class, 'show'])->whereNumber('id');
        Route::put ('/links/{id}/conversational', [\App\Modules\Api\Controllers\ConversationFlowController::class, 'save'])->whereNumber('id');
        Route::get   ('/links/{id}/analytics', [LinkController::class, 'analytics'])->whereNumber('id');
        Route::get   ('/links/{id}/analytics/blocks/{blockId}', [LinkController::class, 'blockAnalytics'])->whereNumber('id')->whereNumber('blockId');

        // Geographic click heatmap (parity with web /links/{link}/heatmap).
        // `heatmap` returns aggregated coordinate points for the window;
        // `heatmap/live` is the pollable "recent points since X" feed mobile
        // uses in place of the web's SSE live stream.
        Route::get   ('/links/{id}/heatmap',      [LinkController::class, 'heatmap'])->whereNumber('id');
        Route::get   ('/links/{id}/heatmap/live', [LinkController::class, 'heatmapLive'])->whereNumber('id');

        Route::post  ('/links/{id}/reset',     [LinkController::class, 'reset'])->whereNumber('id');

        // Per-biolink visitor rate-limit override (used by VisitorRateLimiter).
        Route::get   ('/links/{id}/rate-limit', [LinkController::class, 'rateLimit'])->whereNumber('id');
        Route::patch ('/links/{id}/rate-limit', [LinkController::class, 'rateLimit'])->whereNumber('id');

        // Smart links — geo / device / language / time / AB routing.
        // POST /links/smart creates a new short link with rules attached.
        // GET / PUT /links/{id}/rules manage rules on any owned link.
        Route::post  ('/links/smart',          [LinkController::class, 'storeSmart']);
        Route::get   ('/links/{id}/rules',     [LinkController::class, 'getRules'])->whereNumber('id');
        Route::put   ('/links/{id}/rules',     [LinkController::class, 'putRules'])->whereNumber('id');

        // Card templates (mobile parity for the web card-template gallery).
        Route::get ('/links/{id}/card-templates',         [\App\Modules\Api\Controllers\CardTemplateController::class, 'index'])->whereNumber('id');
        Route::post('/links/{id}/card-templates/apply',   [\App\Modules\Api\Controllers\CardTemplateController::class, 'apply'])->whereNumber('id');

        // Page templates (mobile parity for the web full-page template picker).
        // Applying replaces the link's blocks, so apply honours an overwrite
        // confirmation guard (409 when the link already has blocks).
        Route::get ('/links/{id}/page-templates',         [\App\Modules\Api\Controllers\PageTemplateController::class, 'index'])->whereNumber('id');
        // Full block tree for a single page template (no DB writes) so the
        // mobile editor can render a true visual preview with the native
        // block renderer before the user commits to replacing their page.
        Route::get ('/links/{id}/page-templates/{template}', [\App\Modules\Api\Controllers\PageTemplateController::class, 'show'])->whereNumber('id')->whereNumber('template');
        Route::post('/links/{id}/page-templates/apply',   [\App\Modules\Api\Controllers\PageTemplateController::class, 'apply'])->whereNumber('id');

        // Biolink blocks (authoring)
        // Block-type palette catalog (mobile parity for the web editor
        // palette). User-scoped — categories + picker-visible types with a
        // per-user `locked` flag. Declared before the {id}/blocks routes
        // since it carries no link id.
        Route::get   ('/block-catalog',                     [BiolinkBlockController::class, 'catalog']);
        Route::get   ('/links/{id}/blocks',                 [BiolinkBlockController::class, 'index'])->whereNumber('id');
        Route::post  ('/links/{id}/blocks',                 [BiolinkBlockController::class, 'store'])->whereNumber('id');
        Route::patch ('/links/{id}/blocks/{blockId}',       [BiolinkBlockController::class, 'update'])->whereNumber('id')->whereNumber('blockId');
        Route::delete('/links/{id}/blocks/{blockId}',       [BiolinkBlockController::class, 'destroy'])->whereNumber('id')->whereNumber('blockId');
        Route::post  ('/links/{id}/blocks/reorder',         [BiolinkBlockController::class, 'reorder'])->whereNumber('id');

        // "Build my Link in Bio with AI" (mobile parity for the web
        // links/{link}/ai-builder flow). Prompt + optional images/links →
        // a full page assembled by the shared AiBiolinkBuilderService, which
        // replaces the biolink's blocks. Honors the same plan/AI-credit gating,
        // auto-refund-on-parse-failure, and On-Brand AI `use_brand_kit` opt-in
        // as web. Throttles mirror the web routes.
        Route::get ('/links/{id}/ai-builder',          [\App\Modules\Api\Controllers\AiBiolinkBuilderController::class, 'intake'])->whereNumber('id');
        Route::post('/links/{id}/ai-builder/estimate', [\App\Modules\Api\Controllers\AiBiolinkBuilderController::class, 'estimate'])->whereNumber('id')->middleware('throttle:30,1');
        Route::post('/links/{id}/ai-builder/generate', [\App\Modules\Api\Controllers\AiBiolinkBuilderController::class, 'generate'])->whereNumber('id')->middleware('throttle:10,1');

        // Competitor Biolink Teardown (mobile parity for the web
        // links-teardown flow). Paste a competitor URL, get an AI-scored
        // teardown (strengths/weaknesses/missing elements/CTA quality), then
        // optionally hand off to the AI biolink builder to build a better
        // version. Every write lands on the caller's own account.
        Route::get ('/links-teardown',               [\App\Modules\Api\Controllers\CompetitorTeardownController::class, 'index']);
        Route::post('/links-teardown',               [\App\Modules\Api\Controllers\CompetitorTeardownController::class, 'store'])->middleware('throttle:10,1');
        Route::get ('/links-teardown/{id}',          [\App\Modules\Api\Controllers\CompetitorTeardownController::class, 'show'])->whereNumber('id');
        Route::post('/links-teardown/{id}/build',    [\App\Modules\Api\Controllers\CompetitorTeardownController::class, 'build'])->whereNumber('id')->middleware('throttle:10,1');

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

        // Task #3525 — dashboard widget catalog/presets + AI designer parity.
        Route::get ('/dashboard/layout',                [\App\Modules\Api\Controllers\DashboardLayoutController::class, 'show']);
        Route::post('/dashboard/layout/preset',          [\App\Modules\Api\Controllers\DashboardLayoutController::class, 'applyPreset']);
        Route::post('/dashboard/ai/estimate',            [\App\Modules\Api\Controllers\DashboardLayoutController::class, 'estimate'])->middleware('throttle:30,1');
        Route::post('/dashboard/ai/generate',            [\App\Modules\Api\Controllers\DashboardLayoutController::class, 'generate'])->middleware('throttle:10,1');

        // Notifications
        Route::get ('/notifications',                  [NotificationController::class, 'index']);
        Route::post('/notifications/read-all',         [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read',        [NotificationController::class, 'markRead'])->whereNumber('id');
        Route::delete('/notifications/{id}',           [NotificationController::class, 'destroy'])->whereNumber('id');
        Route::post('/notifications/{id}/restore',      [NotificationController::class, 'restore'])->whereNumber('id');
        Route::get ('/notifications/dismissed',         [NotificationController::class, 'dismissed']);
        Route::get ('/me/notification-preferences',    [NotificationController::class, 'preferences']);
        Route::put ('/me/notification-preferences',    [NotificationController::class, 'updatePreferences']);
        // Account-level one-way WhatsApp payment alerts (new subscriber, tip,
        // PPV/unlock, paid form) — gated on a verified WhatsApp number.
        Route::get ('/me/whatsapp-payment-alerts',     [NotificationController::class, 'whatsappPaymentAlerts']);
        Route::put ('/me/whatsapp-payment-alerts',     [NotificationController::class, 'updateWhatsappPaymentAlerts']);

        // Add + verify a WhatsApp number from mobile (parity with the web
        // onboarding WhatsApp connect step). Unblocks the alert toggles above,
        // which require a verified number. Stateless: verify carries the number
        // back alongside the code. Throttled like the other OTP buckets.
        Route::post('/me/whatsapp/send',   [\App\Modules\Api\Controllers\WhatsappController::class, 'send'])->middleware('throttle:otp-send');
        Route::post('/me/whatsapp/verify', [\App\Modules\Api\Controllers\WhatsappController::class, 'verify'])->middleware('throttle:otp-verify');

        // View the connected WhatsApp number (masked) and disconnect it from the
        // app — mirrors the web Account Settings linked-identifier remove path.
        Route::get   ('/me/whatsapp', [\App\Modules\Api\Controllers\WhatsappController::class, 'status']);
        Route::delete('/me/whatsapp', [\App\Modules\Api\Controllers\WhatsappController::class, 'disconnect']);

        // Linked identifiers — the full Account Settings "Linked identifiers"
        // surface (mobile parity for the web user.identifiers.* routes). List
        // every verified email/phone/social, add + verify a new email/phone,
        // remove a non-primary one, and promote any verified email/phone to
        // primary. Remove + promote reuse AccountMergeService guards, so
        // promoting unblocks removing a primary number (incl. WhatsApp).
        // Stateless add: verify carries kind+value back alongside the code.
        Route::get   ('/me/identifiers',                       [\App\Modules\Api\Controllers\IdentifierController::class, 'index']);
        Route::post  ('/me/identifiers/send',                  [\App\Modules\Api\Controllers\IdentifierController::class, 'send'])->middleware('throttle:otp-send');
        Route::post  ('/me/identifiers/verify',                [\App\Modules\Api\Controllers\IdentifierController::class, 'verify'])->middleware('throttle:otp-verify');
        Route::delete('/me/identifiers/{identifier}',          [\App\Modules\Api\Controllers\IdentifierController::class, 'destroy'])->whereNumber('identifier');
        Route::post  ('/me/identifiers/{identifier}/promote',  [\App\Modules\Api\Controllers\IdentifierController::class, 'promote'])->whereNumber('identifier');

        // Expo push-token registration (1inme-mobile push delivery).
        Route::post  ('/me/push-tokens',               [PushTokenController::class, 'store']);
        Route::delete('/me/push-tokens',               [PushTokenController::class, 'destroy']);

        // Developer API-usage summary (mobile mirror of the web meter).
        Route::get ('/me/api-usage',                   [ApiUsageController::class, 'show']);

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
        Route::get   ('/contacts/follow-ups',       [ContactController::class, 'followUps']);
        Route::get   ('/contacts/follow-ups/count', [ContactController::class, 'followUpsCount']);
        Route::post  ('/contacts',                  [ContactController::class, 'store'])->middleware('throttle:120,1');
        Route::post  ('/contacts/validate',         [ContactController::class, 'validateCandidate'])->middleware('throttle:120,1');
        Route::post  ('/contacts/bulk',             [ContactController::class, 'bulkImport']);
        Route::get   ('/contacts/{id}',             [ContactController::class, 'show'])->whereNumber('id');
        Route::patch ('/contacts/{id}',             [ContactController::class, 'update'])->whereNumber('id');
        Route::post  ('/contacts/{id}/manual-profile', [ContactController::class, 'updateManualProfile'])->whereNumber('id');
        Route::post  ('/contacts/{id}/merge',       [ContactController::class, 'merge'])->whereNumber('id')->middleware('throttle:60,1');
        Route::post  ('/contacts/{id}/sms-biolink', [ContactController::class, 'smsBiolink'])->whereNumber('id')->middleware('throttle:30,1');
        Route::post  ('/contacts/{id}/follow-up',   [ContactController::class, 'setFollowUp'])->whereNumber('id');
        Route::delete('/contacts/{id}/follow-up',   [ContactController::class, 'clearFollowUp'])->whereNumber('id');
        Route::delete('/contacts/{id}',             [ContactController::class, 'destroy'])->whereNumber('id');

        // Contacts — Google Contacts sync
        Route::get   ('/contacts/google/status',     [ContactController::class, 'googleStatus']);
        Route::post  ('/contacts/google/sync',       [ContactController::class, 'googleSync'])->middleware('throttle:12,1');
        Route::patch ('/contacts/google',            [ContactController::class, 'googleUpdate']);
        Route::delete('/contacts/google',            [ContactController::class, 'googleDisconnect']);

        // Contacts — bulk import preview/commit (area 4)
        Route::post  ('/contacts/import/parse',                  [ContactController::class, 'importParse'])->middleware('throttle:30,1');
        Route::get   ('/contacts/import/preview/{token}',        [ContactController::class, 'importPreview']);
        Route::patch ('/contacts/import/preview/{token}/rows/{index}', [ContactController::class, 'importRowUpdate'])->whereNumber('index');
        Route::delete('/contacts/import/preview/{token}/rows/{index}', [ContactController::class, 'importRowSkip'])->whereNumber('index');
        Route::delete('/contacts/import/preview/{token}',        [ContactController::class, 'importCancel']);
        Route::post  ('/contacts/import/preview/{token}/confirm',[ContactController::class, 'importConfirm']);
        Route::get   ('/contacts/import/status/{id}',            [ContactController::class, 'importStatus'])->whereNumber('id');

        // Connected Apps (CRM two-way sync + GA4 forwarding). Mirrors the
        // web /user/connected-apps area; OAuth is delegated to the shared
        // stateless web callback (finishes to the sayzio:// deep link).
        Route::get   ('/connected-apps',                    [ConnectedAppController::class, 'index']);
        Route::post  ('/connected-apps/{provider}/connect-url', [ConnectedAppController::class, 'connectUrl'])->middleware('throttle:30,1');
        Route::post  ('/connected-apps/google-analytics',   [ConnectedAppController::class, 'saveGa']);
        Route::patch ('/connected-apps/{id}',               [ConnectedAppController::class, 'update'])->whereNumber('id');
        Route::post  ('/connected-apps/{id}/sync',          [ConnectedAppController::class, 'syncNow'])->whereNumber('id')->middleware('throttle:12,1');
        Route::delete('/connected-apps/{id}',               [ConnectedAppController::class, 'destroy'])->whereNumber('id');

        // App-wide "Coming soon" feature states (mobile parity): list every
        // catalogue feature with its resolved state, and record "notify me"
        // interest (deduped per user).
        Route::get ('/feature-states',              [FeatureStateController::class, 'index']);
        Route::post('/feature-states/{key}/notify', [FeatureStateController::class, 'notify']);

        // Forms (mobile can list + create-on-the-spot from the block
        // editor; richer editing lives on web).
        Route::get ('/forms',                             [FormController::class, 'index']);
        Route::post('/forms',                             [FormController::class, 'store']);
        Route::get('/forms/{id}',                         [FormController::class, 'show'])->whereNumber('id');
        Route::get('/forms/{id}/analytics',               [FormController::class, 'analytics'])->whereNumber('id');
        Route::get('/forms/{id}/payment',                 [FormController::class, 'payment'])->whereNumber('id');
        Route::put('/forms/{id}/payment',                 [FormController::class, 'updatePayment'])->whereNumber('id');
        // One-way WhatsApp alert for new submissions — gated on a verified number.
        Route::get('/forms/{id}/whatsapp-alert',          [FormController::class, 'whatsappAlert'])->whereNumber('id');
        Route::put('/forms/{id}/whatsapp-alert',          [FormController::class, 'updateWhatsappAlert'])->whereNumber('id');
        Route::get('/forms/{id}/submissions',             [FormController::class, 'submissions'])->whereNumber('id');
        Route::get('/forms/{id}/submissions.csv',         [FormController::class, 'exportSubmissions'])->whereNumber('id');
        Route::post('/forms/{id}/submissions/{submissionId}/refund', [FormController::class, 'refundSubmission'])->whereNumber('id')->whereNumber('submissionId');

        // Biolink AI Companions: list + persona lookup + create-on-the-spot
        // for the block editor's "AI" picker (richer editing lives on web).
        Route::get ('/ai-companions',                     [\App\Modules\Api\Controllers\AiCompanionController::class, 'index']);
        Route::get ('/ai-companions/personas',            [\App\Modules\Api\Controllers\AiCompanionController::class, 'personas']);
        Route::post('/ai-companions/personas',            [\App\Modules\Api\Controllers\AiCompanionController::class, 'storePersona']);
        Route::patch('/ai-companions/personas/{persona}', [\App\Modules\Api\Controllers\AiCompanionController::class, 'updatePersona'])->whereNumber('persona');
        Route::post('/ai-companions',                     [\App\Modules\Api\Controllers\AiCompanionController::class, 'store']);

        // Inbox (DM threads on owned biolinks)
        Route::get   ('/inbox/threads',                   [InboxController::class, 'threads']);
        Route::get   ('/inbox/conversations',             [InboxController::class, 'conversations']);
        Route::get   ('/inbox/conversations/{id}',        [InboxController::class, 'show'])->whereNumber('id');
        Route::post  ('/inbox/conversations/{id}/reply',  [InboxController::class, 'reply'])->whereNumber('id');
        Route::patch ('/inbox/conversations/{id}/status', [InboxController::class, 'setStatus'])->whereNumber('id');
        Route::post  ('/inbox/conversations/{id}/assign', [InboxController::class, 'assign'])->whereNumber('id');
        Route::delete('/inbox/conversations/{id}',        [InboxController::class, 'destroy'])->whereNumber('id');
        Route::get   ('/inbox/teammates',                 [InboxController::class, 'teammates']);

        // Inbox spam settings (mobile parity for the Spam Settings page).
        Route::get   ('/inbox/spam-settings',                 [\App\Modules\Api\Controllers\InboxSettingsController::class, 'show']);
        Route::put   ('/inbox/spam-settings',                 [\App\Modules\Api\Controllers\InboxSettingsController::class, 'update']);
        Route::post  ('/inbox/spam-settings/disable-keyword', [\App\Modules\Api\Controllers\InboxSettingsController::class, 'disableKeyword']);
        Route::post  ('/inbox/spam-settings/enable-keyword',  [\App\Modules\Api\Controllers\InboxSettingsController::class, 'enableDefaultKeyword']);
        Route::post  ('/inbox/spam-settings/import-trusted',  [\App\Modules\Api\Controllers\InboxSettingsController::class, 'importTrustedCsv']);

        // Inbox forwarding rules (mobile parity for the Forwarding page).
        Route::get   ('/inbox/forwards',                  [\App\Modules\Api\Controllers\InboxForwardController::class, 'index']);
        Route::post  ('/inbox/forwards',                  [\App\Modules\Api\Controllers\InboxForwardController::class, 'store']);
        Route::put   ('/inbox/forwards/{id}',             [\App\Modules\Api\Controllers\InboxForwardController::class, 'update'])->whereNumber('id');
        Route::post  ('/inbox/forwards/{id}/toggle',      [\App\Modules\Api\Controllers\InboxForwardController::class, 'toggle'])->whereNumber('id');
        Route::delete('/inbox/forwards/{id}',             [\App\Modules\Api\Controllers\InboxForwardController::class, 'destroy'])->whereNumber('id');
        Route::post  ('/inbox/forwards/{id}/test',        [\App\Modules\Api\Controllers\InboxForwardController::class, 'test'])->whereNumber('id');
        Route::post  ('/inbox/forward-deliveries/{id}/retry', [\App\Modules\Api\Controllers\InboxForwardController::class, 'retry'])->whereNumber('id');

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
        Route::get   ('/domains',             [DomainController::class, 'index']);
        Route::get   ('/domains/available',   [DomainController::class, 'available']);
        Route::post  ('/domains',             [DomainController::class, 'store']);
        Route::post  ('/domains/{id}/primary',[DomainController::class, 'makePrimary'])->whereNumber('id');
        Route::delete('/domains/{id}',        [DomainController::class, 'destroy'])->whereNumber('id');

        // Splash pages
        Route::get   ('/splash-pages',        [SplashPageController::class, 'index']);
        Route::post  ('/splash-pages',        [SplashPageController::class, 'store']);
        Route::get   ('/splash-pages/{id}',   [SplashPageController::class, 'show'])->whereNumber('id');
        Route::patch ('/splash-pages/{id}',   [SplashPageController::class, 'update'])->whereNumber('id');
        Route::delete('/splash-pages/{id}',   [SplashPageController::class, 'destroy'])->whereNumber('id');

        // QR codes
        Route::get   ('/qr-codes',          [QrCodeController::class, 'index']);
        Route::get   ('/qr-codes/catalog',  [QrCodeController::class, 'catalog']);
        // AI Artistic QR (Task #2690) — mobile parity for the web QR Studio
        // generateArt flow: availability/cost/balance probe + gated generation
        // (feature enabled + plan access + coin charge with auto-refund inside
        // QrArtService). Must precede the numeric /qr-codes/{id} routes.
        Route::get   ('/qr-codes/art-availability', [QrCodeController::class, 'artAvailability']);
        Route::post  ('/qr-codes/generate-art',     [QrCodeController::class, 'generateArt'])->middleware('throttle:20,1');
        Route::post  ('/qr-codes',          [QrCodeController::class, 'store']);
        Route::post  ('/qr-codes/bulk',     [QrCodeController::class, 'bulk']);
        Route::get   ('/qr-codes/{id}',     [QrCodeController::class, 'show'])->whereNumber('id');
        Route::put   ('/qr-codes/{id}',     [QrCodeController::class, 'update'])->whereNumber('id');
        Route::patch ('/qr-codes/{id}',     [QrCodeController::class, 'update'])->whereNumber('id');
        Route::delete('/qr-codes/{id}',     [QrCodeController::class, 'destroy'])->whereNumber('id');

        // AI Brand Kit (Task #2666) — mobile parity for the web
        // /user/brand-kits flow: list + apply targets, an upfront credit
        // estimate, AI generation (gated by the per-plan max_brand_kits cap,
        // charged + auto-refunded inside AiBrandKitService), delete, and
        // apply-to-biolink / apply-to-qr. Throttles mirror the web routes.
        Route::get   ('/brand-kits',          [\App\Modules\Api\Controllers\BrandKitController::class, 'index']);
        // Brand Consistency Score (Task #2664) — audit of the caller's biolinks
        // vs their default kit; mobile turns findings into one-tap "Apply fix".
        Route::get   ('/brand-kits/consistency', [\App\Modules\Api\Controllers\BrandKitController::class, 'consistency']);
        Route::post  ('/brand-kits/estimate', [\App\Modules\Api\Controllers\BrandKitController::class, 'estimate'])->middleware('throttle:30,1');
        Route::post  ('/brand-kits/generate', [\App\Modules\Api\Controllers\BrandKitController::class, 'generate'])->middleware('throttle:10,1');
        Route::delete('/brand-kits/{brandKit}', [\App\Modules\Api\Controllers\BrandKitController::class, 'destroy'])->whereNumber('brandKit');
        Route::post  ('/brand-kits/{brandKit}/apply/biolink/{link}', [\App\Modules\Api\Controllers\BrandKitController::class, 'applyToBiolink'])->whereNumber('brandKit')->whereNumber('link');
        Route::post  ('/brand-kits/{brandKit}/apply/qr/{qrCode}',    [\App\Modules\Api\Controllers\BrandKitController::class, 'applyToQr'])->whereNumber('brandKit')->whereNumber('qrCode');

        // Restaurant menu (Task #1536) — owner orders dashboard parity.
        Route::get ('/restaurant/links/{link}/orders',                [RestaurantController::class, 'ownerOrders'])->whereNumber('link');
        Route::get ('/restaurant/links/{link}/orders/poll',           [RestaurantController::class, 'ownerPoll'])->whereNumber('link');
        Route::post('/restaurant/links/{link}/orders/{order}/status', [RestaurantController::class, 'updateOrderStatus'])->whereNumber('link')->whereNumber('order');

        // Restaurant menu builder (Task #1689) — native mobile editor parity
        // with the web RestaurantMenuController editor.
        Route::get   ('/restaurant/links/{link}/menu',                          [RestaurantController::class, 'ownerMenu'])->whereNumber('link');
        Route::post  ('/restaurant/links/{link}/menu/settings',                 [RestaurantController::class, 'saveMenuSettings'])->whereNumber('link');
        Route::post  ('/restaurant/links/{link}/menu/photo',                    [RestaurantController::class, 'uploadItemPhoto'])->whereNumber('link');
        Route::post  ('/restaurant/links/{link}/menu/categories',               [RestaurantController::class, 'storeCategory'])->whereNumber('link');
        Route::put   ('/restaurant/links/{link}/menu/categories/{category}',    [RestaurantController::class, 'updateCategory'])->whereNumber('link')->whereNumber('category');
        Route::delete('/restaurant/links/{link}/menu/categories/{category}',    [RestaurantController::class, 'destroyCategory'])->whereNumber('link')->whereNumber('category');
        Route::post  ('/restaurant/links/{link}/menu/items',                    [RestaurantController::class, 'storeItem'])->whereNumber('link');
        Route::put   ('/restaurant/links/{link}/menu/items/{item}',             [RestaurantController::class, 'updateItem'])->whereNumber('link')->whereNumber('item');
        Route::delete('/restaurant/links/{link}/menu/items/{item}',             [RestaurantController::class, 'destroyItem'])->whereNumber('link')->whereNumber('item');
        Route::post  ('/restaurant/links/{link}/menu/tables',                   [RestaurantController::class, 'storeTable'])->whereNumber('link');
        Route::delete('/restaurant/links/{link}/menu/tables/{table}',           [RestaurantController::class, 'destroyTable'])->whereNumber('link')->whereNumber('table');

        // Restaurant menu coupons (Task #3070) — owner CRUD parity with the
        // web editor so phone owners can set up discount codes too.
        Route::post  ('/restaurant/links/{link}/menu/coupons',                  [RestaurantController::class, 'storeCoupon'])->whereNumber('link');
        Route::put   ('/restaurant/links/{link}/menu/coupons/{coupon}',         [RestaurantController::class, 'updateCoupon'])->whereNumber('link')->whereNumber('coupon');
        Route::delete('/restaurant/links/{link}/menu/coupons/{coupon}',         [RestaurantController::class, 'destroyCoupon'])->whereNumber('link')->whereNumber('coupon');

        // Store menu (Task #3072) — owner orders dashboard parity.
        Route::get ('/store/links/{link}/orders',                [StoreController::class, 'ownerOrders'])->whereNumber('link');
        Route::get ('/store/links/{link}/orders/poll',           [StoreController::class, 'ownerPoll'])->whereNumber('link');
        Route::post('/store/links/{link}/orders/{order}/status', [StoreController::class, 'updateOrderStatus'])->whereNumber('link')->whereNumber('order');

        // Store menu builder (Task #3072) — native mobile editor parity with
        // the web StoreMenuController editor. No tables, no tax/coupon.
        Route::get   ('/store/links/{link}/menu',                       [StoreController::class, 'ownerMenu'])->whereNumber('link');
        Route::post  ('/store/links/{link}/menu/settings',             [StoreController::class, 'saveMenuSettings'])->whereNumber('link');
        Route::post  ('/store/links/{link}/menu/photo',                [StoreController::class, 'uploadProductPhoto'])->whereNumber('link');
        Route::post  ('/store/links/{link}/menu/categories',           [StoreController::class, 'storeCategory'])->whereNumber('link');
        Route::put   ('/store/links/{link}/menu/categories/{category}',[StoreController::class, 'updateCategory'])->whereNumber('link')->whereNumber('category');
        Route::delete('/store/links/{link}/menu/categories/{category}',[StoreController::class, 'destroyCategory'])->whereNumber('link')->whereNumber('category');
        Route::post  ('/store/links/{link}/menu/products',             [StoreController::class, 'storeProduct'])->whereNumber('link');
        Route::put   ('/store/links/{link}/menu/products/{product}',   [StoreController::class, 'updateProduct'])->whereNumber('link')->whereNumber('product');
        Route::delete('/store/links/{link}/menu/products/{product}',   [StoreController::class, 'destroyProduct'])->whereNumber('link')->whereNumber('product');
        // Service booking (Task #3085) — owner bookings dashboard parity with
        // the web ServiceBookingController.
        Route::get ('/service-booking/links/{link}/bookings',                  [ServiceBookingController::class, 'ownerBookings'])->whereNumber('link');
        Route::get ('/service-booking/links/{link}/bookings/poll',             [ServiceBookingController::class, 'ownerPoll'])->whereNumber('link');
        Route::post('/service-booking/links/{link}/bookings/{booking}/status', [ServiceBookingController::class, 'updateBookingStatus'])->whereNumber('link')->whereNumber('booking');

        // Service booking builder — native mobile editor parity with the web
        // ServiceBookingController editor (settings, catalog, availability).
        Route::get   ('/service-booking/links/{link}/config',                        [ServiceBookingController::class, 'ownerConfig'])->whereNumber('link');
        Route::post  ('/service-booking/links/{link}/config/settings',               [ServiceBookingController::class, 'saveSettings'])->whereNumber('link');
        Route::post  ('/service-booking/links/{link}/config/photo',                  [ServiceBookingController::class, 'uploadServicePhoto'])->whereNumber('link');
        Route::post  ('/service-booking/links/{link}/config/categories',             [ServiceBookingController::class, 'storeCategory'])->whereNumber('link');
        Route::put   ('/service-booking/links/{link}/config/categories/{category}',  [ServiceBookingController::class, 'updateCategory'])->whereNumber('link')->whereNumber('category');
        Route::delete('/service-booking/links/{link}/config/categories/{category}',  [ServiceBookingController::class, 'destroyCategory'])->whereNumber('link')->whereNumber('category');
        Route::post  ('/service-booking/links/{link}/config/services',               [ServiceBookingController::class, 'storeService'])->whereNumber('link');
        Route::put   ('/service-booking/links/{link}/config/services/{service}',     [ServiceBookingController::class, 'updateService'])->whereNumber('link')->whereNumber('service');
        Route::delete('/service-booking/links/{link}/config/services/{service}',     [ServiceBookingController::class, 'destroyService'])->whereNumber('link')->whereNumber('service');
        Route::post  ('/service-booking/links/{link}/config/availability',           [ServiceBookingController::class, 'storeAvailability'])->whereNumber('link');
        Route::put   ('/service-booking/links/{link}/config/availability/{rule}',    [ServiceBookingController::class, 'updateAvailability'])->whereNumber('link')->whereNumber('rule');
        Route::delete('/service-booking/links/{link}/config/availability/{rule}',    [ServiceBookingController::class, 'destroyAvailability'])->whereNumber('link')->whereNumber('rule');
        Route::post  ('/service-booking/links/{link}/config/blocked-dates',          [ServiceBookingController::class, 'storeBlockedDate'])->whereNumber('link');
        Route::delete('/service-booking/links/{link}/config/blocked-dates/{blockedDate}', [ServiceBookingController::class, 'destroyBlockedDate'])->whereNumber('link')->whereNumber('blockedDate');

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

        // Followable calendars: owned + followed list, public calendar view,
        // follow toggle, "My Calendar" agenda feed, today's reminders.
        Route::get   ('/calendars',                       [\App\Modules\Api\Controllers\MyCalendarController::class, 'index']);
        Route::get   ('/my-calendar',                     [\App\Modules\Api\Controllers\MyCalendarController::class, 'feed']);
        Route::get   ('/my-calendar/export',              [\App\Modules\Api\Controllers\MyCalendarController::class, 'export']);
        Route::get   ('/my-calendar/today',               [\App\Modules\Api\Controllers\MyCalendarController::class, 'today']);
        Route::get   ('/calendars/{calendar}',            [\App\Modules\Api\Controllers\MyCalendarController::class, 'show'])->whereNumber('calendar');
        Route::post  ('/calendars/{calendar}/follow',     [\App\Modules\Api\Controllers\MyCalendarController::class, 'toggleFollow'])->whereNumber('calendar');
        // Owner-only calendar management (create/edit calendar + event CRUD).
        Route::post  ('/calendars',                                  [\App\Modules\Api\Controllers\MyCalendarController::class, 'store']);
        Route::patch ('/calendars/{calendar}',                       [\App\Modules\Api\Controllers\MyCalendarController::class, 'update'])->whereNumber('calendar');
        Route::post  ('/calendars/{calendar}/events',                [\App\Modules\Api\Controllers\MyCalendarController::class, 'storeEvent'])->whereNumber('calendar');
        Route::patch ('/calendars/{calendar}/events/{event}',        [\App\Modules\Api\Controllers\MyCalendarController::class, 'updateEvent'])->whereNumber('calendar')->whereNumber('event');
        Route::delete('/calendars/{calendar}/events/{event}',        [\App\Modules\Api\Controllers\MyCalendarController::class, 'destroyEvent'])->whereNumber('calendar')->whereNumber('event');

        // Vault (read-only on mobile; secret reveal stays on web)
        Route::get   ('/vault/clients',     [VaultController::class, 'clients']);
        Route::get   ('/vault/credentials', [VaultController::class, 'credentials']);

        // Verification (creator badge)
        Route::get   ('/verifications',     [VerificationController::class, 'index']);
        Route::post  ('/verifications',     [VerificationController::class, 'store']);

        // Billing
        Route::get   ('/billing/subscription',     [BillingController::class, 'subscription']);
        Route::get   ('/billing/downgrade',          [BillingController::class, 'downgradeOptions']);
        Route::post  ('/billing/downgrade/schedule', [BillingController::class, 'scheduleDowngrade']);
        Route::post  ('/billing/downgrade/cancel',   [BillingController::class, 'cancelDowngrade']);
        Route::post  ('/billing/upgrade',            [BillingController::class, 'upgrade']);
        Route::post  ('/billing/cancel',             [BillingController::class, 'cancel']);
        Route::post  ('/billing/resume',             [BillingController::class, 'resume']);
        Route::get   ('/billing/invoices',         [BillingController::class, 'invoices']);
        Route::get   ('/billing/invoices/{id}',    [BillingController::class, 'showInvoice'])->whereNumber('id');
        Route::post  ('/billing/invoices',         [BillingController::class, 'storeInvoice']);
        Route::post  ('/billing/receipts',         [BillingController::class, 'storeReceipt']);
        Route::patch ('/billing/invoices/{id}',    [BillingController::class, 'updateInvoice'])->whereNumber('id');
        Route::delete('/billing/invoices/{id}',    [BillingController::class, 'destroyInvoice'])->whereNumber('id');
        Route::post  ('/billing/invoices/{id}/send', [BillingController::class, 'sendInvoice'])->whereNumber('id')->middleware('throttle:30,1');
        Route::post  ('/billing/invoices/{id}/mark-paid', [BillingController::class, 'markPaidInvoice'])->whereNumber('id');
        Route::post  ('/billing/invoices/{id}/refund',    [BillingController::class, 'refundInvoice'])->whereNumber('id');
        Route::get   ('/billing/invoices/{id}/receipt',   [BillingController::class, 'invoiceReceipt'])->whereNumber('id');
        // Credit notes (mobile parity for the web billing dashboard's
        // "Credit notes" table). Read-only; minted server-side on refund.
        Route::get   ('/billing/credit-notes',            [BillingController::class, 'creditNotes']);

        // Accounting suite: billing companies, tax rules, catalog, expenses,
        // recurring invoices, ledger (all user-scoped; mirrors web).
        Route::get   ('/billing/companies',          [AccountingController::class, 'companies']);
        Route::post  ('/billing/companies',          [AccountingController::class, 'storeCompany']);
        Route::patch ('/billing/companies/{id}',     [AccountingController::class, 'updateCompany'])->whereNumber('id');
        Route::delete('/billing/companies/{id}',     [AccountingController::class, 'destroyCompany'])->whereNumber('id');

        // Per-company outbound mail (SMTP) + client-facing email template
        // editor — mobile parity for the web company SMTP section and the
        // invoice/receipt template editor. Owner-scoped to the creator's own
        // companies; the SMTP password is never returned, only masked.
        Route::get ('/billing/companies/{id}/smtp',        [\App\Modules\Api\Controllers\CompanyMailController::class, 'smtpShow'])->whereNumber('id');
        Route::put ('/billing/companies/{id}/smtp',        [\App\Modules\Api\Controllers\CompanyMailController::class, 'smtpUpdate'])->whereNumber('id');
        Route::post('/billing/companies/{id}/smtp/verify', [\App\Modules\Api\Controllers\CompanyMailController::class, 'smtpVerify'])->whereNumber('id')->middleware('throttle:10,1');
        Route::post('/billing/companies/{id}/smtp/test',   [\App\Modules\Api\Controllers\CompanyMailController::class, 'smtpTest'])->whereNumber('id')->middleware('throttle:10,1');

        Route::get   ('/billing/companies/{id}/emails',                 [\App\Modules\Api\Controllers\CompanyMailController::class, 'templatesIndex'])->whereNumber('id');
        Route::get   ('/billing/companies/{id}/emails/{key}',           [\App\Modules\Api\Controllers\CompanyMailController::class, 'templateShow'])->whereNumber('id')->where('key', '[A-Za-z0-9._-]+');
        Route::put   ('/billing/companies/{id}/emails/{key}',           [\App\Modules\Api\Controllers\CompanyMailController::class, 'templateUpdate'])->whereNumber('id')->where('key', '[A-Za-z0-9._-]+');
        Route::delete('/billing/companies/{id}/emails/{key}',           [\App\Modules\Api\Controllers\CompanyMailController::class, 'templateReset'])->whereNumber('id')->where('key', '[A-Za-z0-9._-]+');
        Route::post  ('/billing/companies/{id}/emails/{key}/preview',   [\App\Modules\Api\Controllers\CompanyMailController::class, 'templatePreview'])->whereNumber('id')->where('key', '[A-Za-z0-9._-]+');

        Route::get   ('/billing/tax-rules',          [AccountingController::class, 'taxRules']);
        Route::post  ('/billing/tax-rules',          [AccountingController::class, 'storeTaxRule']);
        Route::patch ('/billing/tax-rules/{id}',     [AccountingController::class, 'updateTaxRule'])->whereNumber('id');
        Route::delete('/billing/tax-rules/{id}',     [AccountingController::class, 'destroyTaxRule'])->whereNumber('id');

        Route::get   ('/billing/catalog',            [AccountingController::class, 'catalog']);
        Route::post  ('/billing/catalog/categories', [AccountingController::class, 'storeCategory']);
        Route::delete('/billing/catalog/categories/{id}', [AccountingController::class, 'destroyCategory'])->whereNumber('id');
        Route::post  ('/billing/catalog/items',      [AccountingController::class, 'storeItem']);
        Route::patch ('/billing/catalog/items/{id}', [AccountingController::class, 'updateItem'])->whereNumber('id');
        Route::delete('/billing/catalog/items/{id}', [AccountingController::class, 'destroyItem'])->whereNumber('id');

        Route::get   ('/billing/expenses',           [AccountingController::class, 'expenses']);
        Route::post  ('/billing/expenses',           [AccountingController::class, 'storeExpense']);
        Route::patch ('/billing/expenses/{id}',      [AccountingController::class, 'updateExpense'])->whereNumber('id');
        Route::delete('/billing/expenses/{id}',      [AccountingController::class, 'destroyExpense'])->whereNumber('id');

        Route::get   ('/billing/recurring',          [AccountingController::class, 'recurring']);
        Route::post  ('/billing/recurring',          [AccountingController::class, 'storeRecurring']);
        Route::patch ('/billing/recurring/{id}',     [AccountingController::class, 'updateRecurring'])->whereNumber('id');
        Route::delete('/billing/recurring/{id}',     [AccountingController::class, 'destroyRecurring'])->whereNumber('id');
        Route::post  ('/billing/recurring/{id}/run', [AccountingController::class, 'runRecurring'])->whereNumber('id');

        Route::get   ('/billing/ledger',             [AccountingController::class, 'ledger']);

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
        Route::post('/billing/currency',             [RevenueCatBillingController::class, 'setCurrency'])
            ->middleware('throttle:60,1');
        Route::post('/billing/revenuecat/activate',  [RevenueCatBillingController::class, 'activate'])
            ->middleware('throttle:30,1');

        // Dialer
        Route::get   ('/dialer/suggestions',        [DialerController::class, 'suggestions']);
        Route::get   ('/dialer/search',             [DialerController::class, 'search']);
        Route::post  ('/dialer/lookup',             [DialerController::class, 'lookup'])->middleware('throttle:60,1');
        Route::get   ('/dialer/profile',            [DialerController::class, 'profile']);
        Route::get   ('/dialer/history',            [DialerController::class, 'history']);
        Route::get   ('/dialer/channels',           [DialerController::class, 'channels']);
        Route::put   ('/dialer/channels',           [DialerController::class, 'updateChannels']);
        // Contact privacy (Task #3497) — what strangers may see via caller-ID / search.
        Route::get   ('/me/contact-privacy',        [DialerController::class, 'contactPrivacy']);
        Route::put   ('/me/contact-privacy',        [DialerController::class, 'updateContactPrivacy']);
        // Pollable live-sync cursor (favorites/flags/call-log across devices).
        Route::get   ('/dialer/live',               [DialerController::class, 'live']);
        // Speed-dial favorites.
        Route::get   ('/dialer/favorites',          [DialerController::class, 'favorites']);
        Route::post  ('/dialer/favorites',          [DialerController::class, 'addFavorite']);
        Route::post  ('/dialer/favorites/reorder',  [DialerController::class, 'reorderFavorites']);
        Route::delete('/dialer/favorites/{id}',     [DialerController::class, 'removeFavorite'])->whereNumber('id');
        // Per-user spam/block flags.
        Route::post  ('/dialer/flag',               [DialerController::class, 'flag']);
        // Call log (outcome/note/tag) + call-back reminders.
        Route::post  ('/dialer/log',                [DialerController::class, 'logCall']);
        Route::post  ('/dialer/callback',           [DialerController::class, 'setCallback']);
        Route::delete('/dialer/callback/{id}',      [DialerController::class, 'clearCallback'])->whereNumber('id');
    });
});
