<?php

use Illuminate\Support\Facades\Route;
use App\Modules\User\Controllers\AuthController;
use App\Modules\User\Controllers\DashboardController;
use App\Modules\User\Controllers\ProfileController;
use App\Modules\User\Controllers\ProjectController;
use App\Modules\User\Controllers\LinkController;
use App\Modules\User\Controllers\FormController;
use App\Modules\User\Controllers\PixelController;
use App\Modules\User\Controllers\FileLinkController;
use App\Modules\User\Controllers\IcsLinkController;
use App\Modules\User\Controllers\VcfLinkController;
use App\Modules\User\Controllers\QrCodeController;
use App\Modules\User\Controllers\BiolinkBlockController;
use App\Modules\User\Controllers\CalendarAccountController;
use App\Modules\User\Controllers\RsvpController;
use App\Modules\User\Controllers\UserFileController;
use App\Modules\User\Controllers\PlanManagementController;
use App\Modules\User\Controllers\SubscriberController;
use App\Modules\User\Controllers\InboxController;
use App\Modules\User\Controllers\ContactController;
use App\Modules\User\Controllers\GoogleContactsAccountController;
use App\Modules\User\Controllers\DialerController;
use App\Modules\User\Controllers\VerificationController;
use App\Modules\User\Middleware\CheckPlanLimit;
use App\Modules\User\Middleware\SuperAdmin;

Route::get('/', [\App\Modules\Common\Controllers\HomeController::class, 'index'])->name('home');

Route::prefix('user')->name('user.')->group(function () {
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->name('register.submit');
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('demo-login', [AuthController::class, 'demoLogin'])->name('demo.login');
    Route::post('send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:5,1')->name('otp.send');
    Route::post('resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:5,1')->name('otp.resend');
    Route::get('verify-otp', [AuthController::class, 'showOtpVerify'])->name('otp.verify.form');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('otp.verify');

    // "Sign in with <provider>" — resolves the social identity to its
    // linked account and signs the visitor in. Available pre-auth.
    Route::get('social-oauth/{provider}/login', [\App\Modules\User\Controllers\SocialOAuthController::class, 'loginConnect'])
        ->name('social-oauth.login');

    // OAuth provider callback. Lives outside the auth middleware so that
    // login-mode flows (where the visitor isn't logged in yet) can
    // complete. Connect/merge modes inside the handler check Auth::check()
    // themselves and bounce back to login when needed.
    Route::get('social-oauth/{provider}/callback', [\App\Modules\User\Controllers\SocialOAuthController::class, 'callback'])
        ->name('social-oauth.callback');

    // Public referral-code availability/validity check (used by signup form).
    Route::get('referrals/check', [\App\Modules\User\Controllers\ReferralController::class, 'check'])
        ->middleware('throttle:60,1')
        ->name('referrals.check');

    // Public, signed one-click unsubscribe target linked from the
    // broken-social-connection email. Must live outside the auth
    // middleware so users can opt out from any device or inbox client.
    Route::get('social-accounts/broken-emails/unsubscribe/{user}', [\App\Modules\User\Controllers\SocialAccountController::class, 'unsubscribeBrokenEmails'])
        ->name('social-accounts.broken-emails.unsubscribe');

    Route::get('verify-email', [AuthController::class, 'showVerifyEmail'])->middleware('auth')->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['auth', 'signed'])->name('verification.verify');
    Route::post('verify-email/resend', [AuthController::class, 'resendVerification'])->middleware(['auth', 'throttle:6,1'])->name('verification.send');

    Route::middleware('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ===== Social: followers, posts, notifications (dashboard) =====
        Route::get('followers', [\App\Modules\User\Controllers\FollowController::class, 'followers'])->name('followers.index');
        Route::get('following', [\App\Modules\User\Controllers\FollowController::class, 'following'])->name('following.index');
        Route::get('posts',  [\App\Modules\User\Controllers\CreatorPostController::class, 'index'])->name('posts.index');
        Route::post('posts', [\App\Modules\User\Controllers\CreatorPostController::class, 'store'])->name('posts.store');
        Route::post('posts/{post}/pin', [\App\Modules\User\Controllers\CreatorPostController::class, 'pin'])->name('posts.pin');
        Route::post('posts/{post}/unpin', [\App\Modules\User\Controllers\CreatorPostController::class, 'unpin'])->name('posts.unpin');
        Route::delete('posts/{post}', [\App\Modules\User\Controllers\CreatorPostController::class, 'destroy'])->name('posts.destroy');
        Route::get('notifications',  [\App\Modules\User\Controllers\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read', [\App\Modules\User\Controllers\NotificationController::class, 'markRead'])->name('notifications.read');

        Route::get('links/{link}/visitors', [\App\Modules\User\Controllers\VisitorAnalyticsController::class, 'index'])->name('links.visitors');
        Route::get('links/{link}/followers', [\App\Modules\User\Controllers\LinkController::class, 'followers'])->name('links.followers');
        Route::get('links/{link}/followers/export', [\App\Modules\User\Controllers\LinkController::class, 'followersExport'])->name('links.followers.export');
        Route::get('links/{link}/followers/{follower}', [\App\Modules\User\Controllers\LinkController::class, 'followerHistory'])->name('links.followers.history');

        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'edit'])->name('edit');
            Route::put('/', [ProfileController::class, 'update'])->name('update');
            Route::post('/digest/sample', [ProfileController::class, 'sendSample'])->name('digest.sample');
            Route::get('/digest/preview', [ProfileController::class, 'digestPreview'])->name('digest.preview');
        });

        Route::resource('projects', ProjectController::class)->except(['store']);
        Route::post('projects', [ProjectController::class, 'store'])->middleware(CheckPlanLimit::class . ':projects')->name('projects.store');

        // ---- Forms ----
        Route::get('forms', [FormController::class, 'index'])->name('forms.index');
        Route::get('forms/create', [FormController::class, 'create'])->name('forms.create');
        Route::post('forms', [FormController::class, 'store'])->name('forms.store');
        Route::get('forms/{form}', [FormController::class, 'show'])->name('forms.show');
        Route::delete('forms/{form}', [FormController::class, 'destroy'])->name('forms.destroy');
        Route::post('forms/{form}/toggle-active', [FormController::class, 'toggleActive'])->name('forms.toggle-active');
        Route::get('forms/{form}/builder', [FormController::class, 'builder'])->name('forms.builder');
        Route::put('forms/{form}/builder', [FormController::class, 'updateBuilder'])->name('forms.builder.update');
        Route::get('forms/{form}/design', [FormController::class, 'design'])->name('forms.design');
        Route::put('forms/{form}/design', [FormController::class, 'updateDesign'])->name('forms.design.update');
        Route::get('forms/{form}/notifications', [FormController::class, 'notifications'])->name('forms.notifications');
        Route::put('forms/{form}/notifications', [FormController::class, 'updateNotifications'])->name('forms.notifications.update');
        Route::get('forms/{form}/embed', [FormController::class, 'embed'])->name('forms.embed');
        Route::get('forms/{form}/submissions', [FormController::class, 'submissions'])->name('forms.submissions');
        Route::get('forms/{form}/submissions/export', [FormController::class, 'exportSubmissions'])->name('forms.submissions.export');
        Route::get('forms/{form}/submissions/{submission}', [FormController::class, 'showSubmission'])->name('forms.submissions.show');
        Route::post('forms/{form}/submissions/{submission}/star', [FormController::class, 'toggleSubmissionStar'])->name('forms.submissions.star');
        Route::delete('forms/{form}/submissions/{submission}', [FormController::class, 'destroySubmission'])->name('forms.submissions.destroy');

        Route::resource('links', LinkController::class)->except(['store']);
        Route::post('links/choose-type', [LinkController::class, 'chooseType'])->name('links.choose-type');
        Route::get('links-url/create', [LinkController::class, 'createUrl'])->name('links.url.create');
        Route::get('links-biolink/create', [LinkController::class, 'createBiolink'])->name('links.biolink.create');
        Route::post('links', [LinkController::class, 'store'])->middleware(CheckPlanLimit::class . ':links')->name('links.store');
        Route::post('links/{link}/toggle-active', [LinkController::class, 'toggleActive'])->name('links.toggle-active');
        Route::post('links/{link}/duplicate', [LinkController::class, 'duplicate'])->middleware(CheckPlanLimit::class . ':links')->name('links.duplicate');
        Route::post('links/{link}/coach-action', [LinkController::class, 'coachAction'])->name('links.coach-action');
        Route::post('links/{link}/performance-coach/settings', [LinkController::class, 'updatePerformanceCoachSettings'])->name('links.performance-coach.settings');
        Route::post('links/coach-undo', [LinkController::class, 'coachUndo'])->name('links.coach-undo');
        Route::delete('links/{link}/stats', [LinkController::class, 'resetStats'])->name('links.reset-stats');
        Route::put('links/{link}/alias', [LinkController::class, 'updateAlias'])->name('links.update-alias');

        // Additional (alternative) aliases per link — same page served, no redirect.
        Route::post('links/{link}/aliases', [\App\Modules\User\Controllers\LinkAliasController::class, 'store'])->name('links.aliases.store');
        Route::delete('links/{link}/aliases/{alias}', [\App\Modules\User\Controllers\LinkAliasController::class, 'destroy'])->name('links.aliases.destroy');
        Route::post('links/{link}/aliases/{alias}/promote', [\App\Modules\User\Controllers\LinkAliasController::class, 'promote'])->name('links.aliases.promote');

        Route::get('links-file/create', [FileLinkController::class, 'create'])->name('links.file.create');
        Route::post('links-file', [FileLinkController::class, 'store'])->middleware(CheckPlanLimit::class . ':links')->name('links.file.store');
        Route::get('links-ics/create', [IcsLinkController::class, 'create'])->name('links.ics.create');
        Route::post('links-ics', [IcsLinkController::class, 'store'])->middleware(CheckPlanLimit::class . ':links')->name('links.ics.store');
        Route::get('links-ics/{link}/edit', [IcsLinkController::class, 'edit'])->name('links.ics.edit');
        Route::put('links-ics/{link}', [IcsLinkController::class, 'update'])->name('links.ics.update');
        Route::get('links-vcf/create', [VcfLinkController::class, 'create'])->name('links.vcf.create');
        Route::post('links-vcf', [VcfLinkController::class, 'store'])->middleware(CheckPlanLimit::class . ':links')->name('links.vcf.store');
        Route::get('links-vcf/{link}/edit', [VcfLinkController::class, 'edit'])->name('links.vcf.edit');
        Route::put('links-vcf/{link}', [VcfLinkController::class, 'update'])->name('links.vcf.update');

        Route::get('links/{link}/blocks', [BiolinkBlockController::class, 'editor'])->name('links.blocks.editor');
        Route::get('links/{link}/settings', [BiolinkBlockController::class, 'settings'])->name('links.blocks.settings');
        Route::get('links/{link}/settings/appearance', [BiolinkBlockController::class, 'settingsAppearance'])->name('links.settings.appearance');
        Route::get('links/{link}/settings/layout', [BiolinkBlockController::class, 'settingsLayout'])->name('links.settings.layout');
        Route::get('links/{link}/settings/block-theme', [BiolinkBlockController::class, 'settingsBlockTheme'])->name('links.settings.block-theme');
        Route::get('links/{link}/settings/advanced', [BiolinkBlockController::class, 'settingsAdvanced'])->name('links.settings.advanced');
        Route::post('links/{link}/blocks', [BiolinkBlockController::class, 'store'])->name('links.blocks.store');
        Route::put('links/{link}/blocks/{block}', [BiolinkBlockController::class, 'update'])->name('links.blocks.update');
        Route::get('links/{link}/blocks/{block}/edit-form', [BiolinkBlockController::class, 'editForm'])->name('links.blocks.editForm');
        Route::delete('links/{link}/blocks/{block}', [BiolinkBlockController::class, 'destroy'])->name('links.blocks.destroy');
        Route::post('links/{link}/blocks/reorder', [BiolinkBlockController::class, 'reorder'])->name('links.blocks.reorder');
        Route::post('links/{link}/blocks/{block}/toggle', [BiolinkBlockController::class, 'toggleActive'])->name('links.blocks.toggle');
        Route::post('links/{link}/blocks/{block}/move', [BiolinkBlockController::class, 'moveBlock'])->name('links.blocks.move');
        Route::post('links/{link}/page-settings', [BiolinkBlockController::class, 'updatePageSettings'])->name('links.page-settings');

        // Plan upgrade prompt destination (used by template lock badges).
        Route::get('upgrade', function () {
            return redirect()->route('user.dashboard')->with('info', 'Upgrade your plan to unlock premium templates and features. Contact support to change plans.');
        })->name('upgrade');

        // Page & card templates (admin-curated presets)
        Route::get('links/{link}/templates', [\App\Modules\User\Controllers\LinkTemplateController::class, 'picker'])->name('links.templates.picker');
        Route::post('links/{link}/templates/apply-page', [\App\Modules\User\Controllers\LinkTemplateController::class, 'applyPage'])->name('links.templates.apply-page');
        Route::get('links/{link}/templates/cards', [\App\Modules\User\Controllers\LinkTemplateController::class, 'cardGallery'])->name('links.templates.cards');
        Route::post('links/{link}/templates/apply-card', [\App\Modules\User\Controllers\LinkTemplateController::class, 'applyCard'])->name('links.templates.apply-card');

        // Standalone splash pages — reusable across multiple links
        Route::resource('splash-pages', \App\Modules\User\Controllers\SplashPageController::class);
        Route::get('splash-pages/{splash_page}/preview', [\App\Modules\User\Controllers\SplashPageController::class, 'preview'])->name('splash-pages.preview');

        // Reusable third-party integration configurations (payment / sms / email)
        // ---- Connected social accounts (follower count source for biolink Follow buttons) ----
        Route::get('social-accounts',                          [\App\Modules\User\Controllers\SocialAccountController::class, 'index'])->name('social-accounts.index');
        Route::post('social-accounts',                         [\App\Modules\User\Controllers\SocialAccountController::class, 'store'])->name('social-accounts.store');
        Route::post('social-accounts/{connection}/refresh',    [\App\Modules\User\Controllers\SocialAccountController::class, 'refresh'])->name('social-accounts.refresh');
        Route::delete('social-accounts/{connection}',          [\App\Modules\User\Controllers\SocialAccountController::class, 'destroy'])->name('social-accounts.destroy');
        Route::post('social-accounts/broken-emails/preference', [\App\Modules\User\Controllers\SocialAccountController::class, 'updateBrokenEmailPreference'])->name('social-accounts.broken-emails.preference');

        // OAuth connect / callback for providers that need a per-user token.
        // Each provider activates only when its CLIENT_ID + CLIENT_SECRET env
        // vars are set; otherwise the UI falls back to manual token paste.
        Route::get('social-oauth/{provider}/connect',  [\App\Modules\User\Controllers\SocialOAuthController::class, 'connect'])->name('social-oauth.connect');
        Route::get('social-oauth/{provider}/merge',    [\App\Modules\User\Controllers\SocialOAuthController::class, 'mergeConnect'])->name('social-oauth.merge');

        // Linked identifiers (multi-identity account settings).
        Route::prefix('identifiers')->name('identifiers.')->group(function () {
            Route::get('/',                                [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'index'])->name('index');
            Route::post('start',                           [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'start'])->middleware('throttle:5,1')->name('start');
            Route::post('confirm',                         [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'confirm'])->middleware('throttle:10,1')->name('confirm');
            Route::delete('{identifier}',                  [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'destroy'])->name('destroy');
            Route::post('{identifier}/promote',            [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'promote'])->name('promote');
        });

        // Account merge flow.
        Route::prefix('merge')->name('merge.')->group(function () {
            Route::get('/',           [\App\Modules\User\Controllers\AccountMergeController::class, 'start'])->name('start');
            Route::post('challenge',  [\App\Modules\User\Controllers\AccountMergeController::class, 'challenge'])->middleware('throttle:5,1')->name('challenge');
            Route::get('preview',     [\App\Modules\User\Controllers\AccountMergeController::class, 'preview'])->name('preview');
            Route::post('confirm',    [\App\Modules\User\Controllers\AccountMergeController::class, 'confirm'])->name('confirm');
            Route::post('cancel',     [\App\Modules\User\Controllers\AccountMergeController::class, 'cancel'])->name('cancel');
        });

        Route::get('integrations',                       [\App\Modules\User\Controllers\IntegrationConfigController::class, 'index'])->name('integrations.index');
        Route::get('integrations/{kind}/create',         [\App\Modules\User\Controllers\IntegrationConfigController::class, 'create'])->name('integrations.create')->where('kind', 'payment|sms|email');
        Route::post('integrations/{kind}',               [\App\Modules\User\Controllers\IntegrationConfigController::class, 'store'])->name('integrations.store')->where('kind', 'payment|sms|email');
        Route::get('integrations/{integrationConfig}/edit',         [\App\Modules\User\Controllers\IntegrationConfigController::class, 'edit'])->name('integrations.edit');
        Route::put('integrations/{integrationConfig}',              [\App\Modules\User\Controllers\IntegrationConfigController::class, 'update'])->name('integrations.update');
        Route::delete('integrations/{integrationConfig}',           [\App\Modules\User\Controllers\IntegrationConfigController::class, 'destroy'])->name('integrations.destroy');
        Route::post('integrations/{integrationConfig}/set-default', [\App\Modules\User\Controllers\IntegrationConfigController::class, 'setDefault'])->name('integrations.set-default');
        Route::post('integrations/{integrationConfig}/toggle',      [\App\Modules\User\Controllers\IntegrationConfigController::class, 'toggleActive'])->name('integrations.toggle');

        // Splash attachment for a specific link (picker UI)
        Route::get('links/{link}/splash',  [LinkController::class, 'splashSettings'])->name('links.splash');
        Route::post('links/{link}/splash', [LinkController::class, 'updateSplash'])->name('links.splash.update');

        Route::get('links/{link}/heatmap', [LinkController::class, 'heatmap'])->name('links.heatmap');
        Route::get('links/{link}/heatmap/live', [LinkController::class, 'heatmapLive'])->name('links.heatmap.live');
        Route::get('links/{link}/heatmap/live/stream', [LinkController::class, 'heatmapLiveStream'])->name('links.heatmap.live.stream');
        Route::get('links/{link}/clicks/partial', [LinkController::class, 'recentClicksPartial'])->name('links.clicks.partial');
        Route::get('links/{link}/clicks/export', [LinkController::class, 'exportClicks'])->name('links.clicks.export');
        Route::get('links/{link}/qrcode', [QrCodeController::class, 'show'])->name('links.qrcode');
        Route::post('links/{link}/qrcode', [QrCodeController::class, 'generate'])->name('links.qrcode.download');
        Route::get('links/{link}/qrcode/preview', [QrCodeController::class, 'preview'])->name('links.qrcode.preview');

        Route::get('qrcode', [QrCodeController::class, 'standalone'])->name('qrcode');

        // ===== Contacts & Dialer =====
        Route::get('contacts',                              [ContactController::class, 'index'])->name('contacts.index');
        Route::get('contacts/create',                       [ContactController::class, 'create'])->name('contacts.create');
        Route::post('contacts',                             [ContactController::class, 'store'])->middleware(CheckPlanLimit::class . ':contacts_max')->name('contacts.store');
        Route::get('contacts/import',                       [ContactController::class, 'importForm'])->name('contacts.import');
        Route::post('contacts/import',                      [ContactController::class, 'import'])->middleware(CheckPlanLimit::class . ':contacts_max')->name('contacts.import.store');
        Route::get('contacts/{contact}',                    [ContactController::class, 'show'])->name('contacts.show');
        Route::get('contacts/{contact}/edit',               [ContactController::class, 'edit'])->name('contacts.edit');
        Route::put('contacts/{contact}',                    [ContactController::class, 'update'])->name('contacts.update');
        Route::delete('contacts/{contact}',                 [ContactController::class, 'destroy'])->name('contacts.destroy');
        Route::post('contacts/{contact}/biolink/detach',    [ContactController::class, 'detachBiolink'])->name('contacts.biolink.detach');
        Route::post('contacts/{contact}/biolink/attach',    [ContactController::class, 'attachBiolink'])->name('contacts.biolink.attach');
        Route::post('contacts/{contact}/biolink/sms',       [ContactController::class, 'smsBiolink'])->name('contacts.biolink.sms');

        // Google Contacts OAuth + sync.
        Route::get('contacts/google/connect',               [GoogleContactsAccountController::class, 'connect'])->middleware(CheckPlanLimit::class . ':contacts_google_sync')->name('contacts.google.connect');
        Route::get('contacts/google/callback',              [GoogleContactsAccountController::class, 'callback'])->name('contacts.google.callback');
        Route::post('contacts/google/{account}/sync',       [GoogleContactsAccountController::class, 'syncNow'])->name('contacts.google.sync');
        Route::delete('contacts/google/{account}',          [GoogleContactsAccountController::class, 'destroy'])->name('contacts.google.destroy');

        // Dialer.
        Route::get('dialer',                                [DialerController::class, 'index'])->name('dialer.index');
        Route::get('dialer/profile',                        [DialerController::class, 'profile'])->name('dialer.profile');

        // ===== Events calendar (month / week / day / list views) =====
        Route::get('events',                                [CalendarAccountController::class, 'events'])->name('events.index');
        Route::get('events/feed',                           [CalendarAccountController::class, 'eventsFeed'])->name('events.feed');

        // ===== Calendar accounts (Google / Microsoft / CalDAV sync) =====
        Route::get('calendar',                              [CalendarAccountController::class, 'index'])->name('calendar.index');
        Route::get('calendar/connect/{provider}',           [CalendarAccountController::class, 'connect'])->name('calendar.connect')->where('provider', 'google|microsoft|caldav');
        Route::get('calendar/callback/{provider}',          [CalendarAccountController::class, 'callback'])->name('calendar.callback')->where('provider', 'google|microsoft|caldav');
        Route::post('calendar/{account}/sync',              [CalendarAccountController::class, 'syncNow'])->name('calendar.sync');
        Route::put('calendar/{account}',                    [CalendarAccountController::class, 'update'])->name('calendar.update');
        Route::delete('calendar/{account}',                 [CalendarAccountController::class, 'destroy'])->name('calendar.destroy');

        // ===== RSVPs (guest list on Event Invite links) =====
        Route::get('links/{link}/rsvps',                    [RsvpController::class, 'index'])->name('links.rsvps.index');
        Route::get('links/{link}/rsvps/export',             [RsvpController::class, 'export'])->name('links.rsvps.export');
        Route::delete('links/{link}/rsvps/{rsvp}',          [RsvpController::class, 'destroy'])->name('links.rsvps.destroy');
        Route::post('qrcode', [QrCodeController::class, 'generateStandalone'])->name('qrcode.download');
        Route::get('qrcode/preview', [QrCodeController::class, 'previewStandalone'])->name('qrcode.preview');

        // QR Studio (full builder + library)
        Route::get   ('qr-codes',                    [QrCodeController::class, 'index'])    ->name('qr-codes.index');
        Route::get   ('qr-codes/create',             [QrCodeController::class, 'create'])   ->name('qr-codes.create');
        Route::post  ('qr-codes',                    [QrCodeController::class, 'store'])    ->name('qr-codes.store');
        Route::get   ('qr-codes/{qrCode}/edit',      [QrCodeController::class, 'edit'])     ->name('qr-codes.edit');
        Route::put   ('qr-codes/{qrCode}',           [QrCodeController::class, 'update'])   ->name('qr-codes.update');
        Route::delete('qr-codes/{qrCode}',           [QrCodeController::class, 'destroy'])  ->name('qr-codes.destroy');
        Route::post  ('qr-codes/{qrCode}/duplicate', [QrCodeController::class, 'duplicate'])->name('qr-codes.duplicate');
        Route::post  ('qr-codes/resolve',            [QrCodeController::class, 'resolvePayload'])->name('qr-codes.resolve');

        Route::resource('pixels', PixelController::class)->except(['show', 'store']);
        Route::post('pixels', [PixelController::class, 'store'])->middleware(CheckPlanLimit::class . ':pixels')->name('pixels.store');

        Route::prefix('domains')->name('domains.')->middleware(CheckPlanLimit::class . ':custom_domains')->group(function () {
            Route::get('/', [\App\Modules\User\Controllers\DomainController::class, 'index'])->name('index');
            Route::post('/', [\App\Modules\User\Controllers\DomainController::class, 'store'])->name('store');
            Route::post('{domain}/verify', [\App\Modules\User\Controllers\DomainController::class, 'verify'])->name('verify');
            Route::delete('{domain}', [\App\Modules\User\Controllers\DomainController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('social-proofs')->name('social-proofs.')->group(function () {
            Route::get('/',                            [\App\Modules\User\Controllers\SocialProofController::class, 'index'])->name('index');
            Route::get('create',                       [\App\Modules\User\Controllers\SocialProofController::class, 'create'])->name('create');
            Route::post('/',                           [\App\Modules\User\Controllers\SocialProofController::class, 'store'])->name('store');
            Route::get('{socialProof}/edit',           [\App\Modules\User\Controllers\SocialProofController::class, 'edit'])->name('edit');
            Route::put('{socialProof}',                [\App\Modules\User\Controllers\SocialProofController::class, 'update'])->name('update');
            Route::post('{socialProof}/toggle',        [\App\Modules\User\Controllers\SocialProofController::class, 'toggleActive'])->name('toggle');
            Route::delete('{socialProof}',             [\App\Modules\User\Controllers\SocialProofController::class, 'destroy'])->name('destroy');
            Route::post('{socialProof}/items',         [\App\Modules\User\Controllers\SocialProofController::class, 'storeItem'])->name('items.store');
            Route::delete('{socialProof}/items/{item}',[\App\Modules\User\Controllers\SocialProofController::class, 'destroyItem'])->name('items.destroy');
        });

        Route::prefix('files')->name('files.')->group(function () {
            Route::get('/', [UserFileController::class, 'index'])->name('index');
            Route::post('upload', [UserFileController::class, 'upload'])->name('upload');
            Route::post('import-url', [UserFileController::class, 'importUrl'])->name('import-url');
            Route::delete('{file}', [UserFileController::class, 'destroy'])->name('destroy');
            Route::get('quota', [UserFileController::class, 'quota'])->name('quota');
        });

        Route::prefix('inbox')->name('inbox.')->group(function () {
            Route::get('/', [InboxController::class, 'index'])->name('index');
            Route::get('export', [InboxController::class, 'exportFiltered'])->name('export');
            Route::get('spam-settings', [InboxController::class, 'settings'])->name('spam-settings');
            Route::post('spam-settings', [InboxController::class, 'updateSettings'])->name('spam-settings.update');
            Route::post('spam-settings/import', [InboxController::class, 'importTrustedCsv'])->name('spam-settings.import');
            Route::post('spam-settings/disable-keyword', [InboxController::class, 'disableKeyword'])->name('spam-settings.disable-keyword');
            Route::post('spam-settings/enable-default-keyword', [InboxController::class, 'enableDefaultKeyword'])->name('spam-settings.enable-default-keyword');
            Route::post('bulk', [InboxController::class, 'bulk'])->name('bulk');

            // Account-level forwarding rules: send new inbox messages to
            // an email address or webhook URL with optional source filter.
            Route::prefix('forwards')->name('forwards.')->group(function () {
                Route::get('/',                          [\App\Modules\User\Controllers\InboxForwardController::class, 'index'])->name('index');
                Route::post('/',                         [\App\Modules\User\Controllers\InboxForwardController::class, 'store'])->name('store');
                Route::put('{forward}',                  [\App\Modules\User\Controllers\InboxForwardController::class, 'update'])->name('update');
                Route::post('{forward}/toggle',          [\App\Modules\User\Controllers\InboxForwardController::class, 'toggle'])->name('toggle');
                Route::post('{forward}/test',            [\App\Modules\User\Controllers\InboxForwardController::class, 'test'])->middleware('throttle:10,1')->name('test');
                Route::delete('{forward}',               [\App\Modules\User\Controllers\InboxForwardController::class, 'destroy'])->name('destroy');
                Route::post('deliveries/{delivery}/retry', [\App\Modules\User\Controllers\InboxForwardController::class, 'retry'])->name('deliveries.retry');
            });

            Route::get('{type}/{id}', [InboxController::class, 'show'])
                ->where('type', 'form_submission|subscriber')->whereNumber('id')->name('show');
            Route::post('{type}/{id}', [InboxController::class, 'update'])
                ->where('type', 'form_submission|subscriber')->whereNumber('id')->name('update');
            Route::post('{type}/{id}/reply', [InboxController::class, 'reply'])
                ->where('type', 'form_submission|subscriber')->whereNumber('id')->name('reply');
        });

        Route::prefix('subscribers')->name('subscribers.')->group(function () {
            Route::get('/', [SubscriberController::class, 'index'])->name('index');
            Route::get('settings', [SubscriberController::class, 'settings'])->name('settings');
            Route::post('settings', [SubscriberController::class, 'updateSettings'])->name('settings.update');
            Route::get('compose', [SubscriberController::class, 'compose'])->name('compose');
            Route::post('send', [SubscriberController::class, 'send'])->name('send');
            Route::get('export', [SubscriberController::class, 'export'])->name('export');
            Route::get('messages', [SubscriberController::class, 'messageHistory'])->name('messages');
            Route::post('{subscriber}/toggle', [SubscriberController::class, 'toggleStatus'])->name('toggle');
            Route::delete('{subscriber}', [SubscriberController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('referrals')->name('referrals.')->group(function () {
            Route::get('/', [\App\Modules\User\Controllers\ReferralController::class, 'index'])->name('index');
            Route::put('code', [\App\Modules\User\Controllers\ReferralController::class, 'updateCode'])->name('code.update');
        });

        Route::prefix('verification')->name('verification.')->group(function () {
            Route::get('/', [VerificationController::class, 'index'])->name('index');
            Route::get('request', [VerificationController::class, 'create'])->name('request');
            Route::post('request', [VerificationController::class, 'store'])->name('store');
            Route::post('blocks/{block}/toggle', [VerificationController::class, 'toggleBlock'])->name('block.toggle');
        });

        Route::middleware(SuperAdmin::class)->group(function () {
            Route::resource('plans', PlanManagementController::class)->except(['show']);
            Route::get('verification-admin', [VerificationController::class, 'adminIndex'])->name('verification.admin');
            Route::get('verification-admin/{verificationRequest}', [VerificationController::class, 'adminReview'])->name('verification.admin.review');
            Route::post('verification-admin/{verificationRequest}/approve', [VerificationController::class, 'adminApprove'])->name('verification.admin.approve');
            Route::post('verification-admin/{verificationRequest}/reject', [VerificationController::class, 'adminReject'])->name('verification.admin.reject');
        });
    });
});
