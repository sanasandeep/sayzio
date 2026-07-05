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
use App\Modules\User\Controllers\BiolinkWizardController;
use App\Modules\User\Controllers\BulkBiolinkController;
use App\Modules\User\Controllers\CalendarAccountController;
use App\Modules\User\Controllers\RsvpController;
use App\Modules\User\Controllers\PollVoteController;
use App\Modules\User\Controllers\UserFileController;
use App\Modules\User\Controllers\PlanManagementController;
use App\Modules\User\Controllers\SubscriberController;
use App\Modules\User\Controllers\InboxController;
use App\Modules\User\Controllers\ContactController;
use App\Modules\User\Controllers\GoogleContactsAccountController;
use App\Modules\User\Controllers\DialerController;
use App\Modules\User\Controllers\VerificationController;
use App\Modules\User\Middleware\CheckPlanLimit;
use App\Modules\User\Controllers\RoleManagementController;
use App\Modules\User\Controllers\UserAccessController;

Route::get('/', [\App\Modules\Common\Controllers\HomeController::class, 'index'])->name('home');

// Public SVG placeholders generated for the biolink creation wizard.
// Referenced as the default avatar / cover image on pages built by the
// wizard, so this route must remain auth-free.
Route::get('/wizard-placeholders/{slug}.svg', [\App\Modules\User\Controllers\BiolinkWizardController::class, 'placeholder'])
    ->where('slug', '[a-z0-9_]+')
    ->name('wizard.placeholder');

// Public anonymous currency switcher used by the marketing pricing section
// (and any other public page that renders prices via PricingResolver).
// Persists the choice in the session — country-bound users still see their
// country's currency until they change it in profile settings.
Route::post('/pricing/switch-currency', [\App\Modules\User\Controllers\UpgradeController::class, 'switchCurrency'])
    ->name('upgrade.public.switch-currency');

// Payment-gateway webhook. Signed with config('billing.activation_secret').
// CSRF is disabled for /webhooks/billing/* in bootstrap/app.php. No session
// auth; the controller requires a valid HMAC signature to proceed.
Route::post('/webhooks/billing/activate', [\App\Modules\User\Controllers\UpgradeController::class, 'activate'])
    ->name('webhooks.billing.activate');

// ─── Public client/sponsor portal (magic-link, no auth) ─────────────
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('start/{token}', [\App\Modules\User\Controllers\PortalAuthController::class, 'start'])
        ->where('token', '[A-Za-z0-9]{32,}')
        ->middleware('throttle:30,1')
        ->name('start');
    Route::get('gone',   [\App\Modules\User\Controllers\PortalAuthController::class, 'gone'])->name('gone');
    Route::post('logout',[\App\Modules\User\Controllers\PortalAuthController::class, 'logout'])->name('logout');

    Route::middleware('portal.session')->group(function () {
        Route::get ('/',                      [\App\Modules\User\Controllers\PortalController::class, 'dashboard'])->name('dashboard');
        Route::get ('boards/{board}',         [\App\Modules\User\Controllers\PortalController::class, 'board'])->whereNumber('board')->name('board');
        Route::get ('files',                  [\App\Modules\User\Controllers\PortalController::class, 'files'])->name('files');
        Route::get ('files/{file}/download',  [\App\Modules\User\Controllers\PortalController::class, 'fileDownload'])->whereNumber('file')->name('files.download');
        Route::get ('drafts',                 [\App\Modules\User\Controllers\PortalController::class, 'drafts'])->name('drafts');
        Route::post('drafts/{share}/decide',  [\App\Modules\User\Controllers\PortalController::class, 'decideDraft'])->whereNumber('share')->name('drafts.decide');
        Route::get ('invoices',               [\App\Modules\User\Controllers\PortalController::class, 'invoices'])->name('invoices');
        Route::post('invoices/{invoice}/pay', [\App\Modules\User\Controllers\PortalController::class, 'payInvoice'])->whereNumber('invoice')->name('invoices.pay');
        Route::get ('reports/{link}',         [\App\Modules\User\Controllers\PortalController::class, 'report'])->whereNumber('link')->name('report');
        Route::get ('delivery-projects/{project}', [\App\Modules\User\Controllers\PortalController::class, 'deliveryProject'])->whereNumber('project')->name('delivery-project');
        Route::post('delivery-projects/{project}/comments', [\App\Modules\User\Controllers\PortalController::class, 'deliveryProjectComment'])->whereNumber('project')->name('delivery-project.comment');
    });
});

// Public, read-only share of a Delivery Project for anonymous buyers (e.g.
// restaurant/store order customers) who have no portal login. The unguessable
// share_token in the URL is the only authenticator; the page is view-only.
Route::get('dp/{token}', [\App\Modules\User\Controllers\DeliveryProjectController::class, 'share'])
    ->where('token', '[A-Za-z0-9]{16,}')
    ->middleware('throttle:60,1')
    ->name('delivery-project.share');

// Anonymous buyer posts a comment/question from the public share page.
Route::post('dp/{token}/comments', [\App\Modules\User\Controllers\DeliveryProjectController::class, 'shareComment'])
    ->where('token', '[A-Za-z0-9]{16,}')
    ->middleware('throttle:20,1')
    ->name('delivery-project.share.comment');

Route::prefix('user')->name('user.')->group(function () {
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    // Registration was previously unthrottled — easy spam-farm vector.
    // Now keyed on IP via the auth-register limiter (3/min, 20/hour).
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:auth-register')
        ->name('register.submit');
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    // Email + password sign-in (only honored when the admin has enabled it).
    Route::post('login', [AuthController::class, 'loginWithPassword'])
        ->middleware('throttle:auth-credentials')
        ->name('login.submit');
    Route::get('demo-login', fn () => redirect()->route('user.login'));
    Route::post('demo-login', [AuthController::class, 'demoLogin'])->middleware('throttle:5,1')->name('demo.login');
    // OTP send/verify now go through identifier-aware named limiters
    // defined in AppServiceProvider so a single attacker IP can't pin
    // a victim account behind a CGNAT carrier, and a botnet can't
    // bypass the per-IP cap by spraying across many IPs.
    Route::post('send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:otp-send')->name('otp.send');
    Route::post('resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:otp-send')->name('otp.resend');
    Route::get('verify-otp', [AuthController::class, 'showOtpVerify'])->name('otp.verify.form');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify')->name('otp.verify');

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

    // Public, signed "This wasn't me" link sent in suspicious-login
    // alert emails. Must live outside the auth middleware so the
    // legitimate (or attacker-blocked) user can click it from any
    // inbox without first signing in. The signed URL is the only
    // authenticator; the controller invalidates the offending session
    // and clears the password before responding.
    Route::get('security/logins/revoke/{token}', [\App\Modules\User\Controllers\SecurityController::class, 'revoke'])
        ->where('token', '[A-Za-z0-9]{32,}')
        ->middleware('throttle:30,10')
        ->name('security.logins.revoke');

    // Public, signed one-click unsubscribe target linked from the
    // weekly backlink-digest email. GET = footer click in a browser,
    // POST = inbox-provider one-click chip per RFC 8058 (CSRF exempted
    // for this URL group in bootstrap/app.php). The signed URL itself
    // is the authenticator; no session required.
    Route::match(['get', 'post'], 'notifications/backlink-digest/unsubscribe/{user}',
        [\App\Modules\User\Controllers\NotificationController::class, 'unsubscribeBacklinkDigest'])
        ->middleware('throttle:600,1')
        ->name('notifications.backlink-digest.unsubscribe');

    // Public, signed one-click unsubscribe target linked from the periodic
    // "verify your email" reminder email. Same rationale as the backlink
    // digest one above (inbox-provider POSTs cannot present a CSRF token;
    // the signed URL is the authenticator). CSRF exempted in bootstrap/app.php.
    Route::match(['get', 'post'], 'notifications/email-verification-reminder/unsubscribe/{user}',
        [\App\Modules\User\Controllers\NotificationController::class, 'unsubscribeEmailVerificationReminder'])
        ->middleware('throttle:600,1')
        ->name('notifications.email-verification-reminder.unsubscribe');

    Route::get('verify-email', [AuthController::class, 'showVerifyEmail'])->middleware('auth')->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['auth', 'signed'])->name('verification.verify');
    Route::post('verify-email/resend', [AuthController::class, 'resendVerification'])->middleware(['auth', 'throttle:6,1'])->name('verification.send');

    // In-app email verification by 6-digit code (powers the post-sign-up
    // reminder banner for users who skipped verification at registration).
    Route::post('verify-email/code/send', [AuthController::class, 'sendEmailVerifyCode'])->middleware(['auth', 'throttle:otp-send'])->name('verification.code.send');
    Route::post('verify-email/code/confirm', [AuthController::class, 'confirmEmailVerifyCode'])->middleware(['auth', 'throttle:otp-verify'])->name('verification.code.confirm');

    // One-click "renew my free Starter plan for another year". Drives the
    // free-window re-confirmation banner/email — reminders only, never a
    // lockout, so this just pushes the window out 12 months.
    Route::post('starter/renew-free-window', [AuthController::class, 'renewStarterFreeWindow'])
        ->middleware(['auth', 'throttle:6,1'])->name('starter.renew-free-window');

    // Signed GET variant for the reminder EMAIL's one-click CTA. An email link
    // is a GET with no guaranteed session, so it can't use the POST/auth route
    // above. The signature authenticates the user instead; the handler renews
    // the named user and bounces to the dashboard (or login if signed out).
    Route::get('starter/renew-free-window/{user}', [AuthController::class, 'renewStarterFreeWindowViaLink'])
        ->middleware(['signed', 'throttle:6,1'])->name('starter.renew-free-window.link');

    // ---- Workspace invite landing (public — no workspace context yet) ----
    Route::get('workspaces/invites/{token}', [\App\Modules\User\Controllers\AcceptInviteController::class, 'show'])
        ->name('workspaces.invite.show');
    // Public on purpose: controller stashes the token in session and redirects
    // unauthenticated visitors to the OTP signup flow, then auto-attaches the
    // invite when the new account is verified.
    Route::post('workspaces/invites/{token}/accept', [\App\Modules\User\Controllers\AcceptInviteController::class, 'accept'])
        ->name('workspaces.invite.accept');

    // ---- Workspace audit "this wasn't authorised" landing (signed link
    // from alert email; works without a session). The controller verifies
    // the signature via the `signed` middleware. POST is rate-limited so
    // the public surface can't be hammered.
    Route::get('workspaces/audit/events/{event}/report',
        [\App\Modules\User\Controllers\WorkspaceAuditController::class, 'reportShow'])
        ->middleware('signed')
        ->whereNumber('event')
        ->name('workspaces.audit.report.show');
    Route::post('workspaces/audit/events/{event}/report',
        [\App\Modules\User\Controllers\WorkspaceAuditController::class, 'reportStore'])
        ->middleware(['signed', 'throttle:6,60'])
        ->whereNumber('event')
        ->name('workspaces.audit.report.store');

    // ---- 2FA challenge (between OTP verify and full login) ----
    Route::get ('account/two-factor/challenge', [\App\Modules\User\Controllers\TwoFactorController::class, 'challenge'])
        ->name('account.two-factor.challenge');
    Route::post('account/two-factor/challenge', [\App\Modules\User\Controllers\TwoFactorController::class, 'verifyChallenge'])
        ->middleware('throttle:10,1')
        ->name('account.two-factor.challenge.verify');

    Route::middleware(['auth', 'workspace.scope', 'workspace.2fa', \App\Modules\User\Middleware\EnsureFeatureAvailable::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        // App-wide "Coming soon" feature preview + notify-me. The
        // EnsureFeatureAvailable middleware redirects any coming-soon feature
        // route here; these two routes are exempt inside that middleware.
        Route::get ('coming-soon/{feature}',        [\App\Modules\User\Controllers\ComingSoonController::class, 'show'])->name('coming-soon.show');
        Route::post('coming-soon/{feature}/notify', [\App\Modules\User\Controllers\ComingSoonController::class, 'notify'])->middleware('throttle:20,1')->name('coming-soon.notify');

        // Seamless switch from the user dashboard to the matching admin dashboard.
        Route::post('switch-to-admin', [\App\Modules\Common\Controllers\DashboardSwitchController::class, 'toAdmin'])->name('switch-to-admin');

        // One-click "Enable AI now" from the "AI is turned off" page: an
        // admin with a configured OpenAI key flips ai.enabled on without a
        // detour through the back-office settings screen.
        Route::post('ai/enable', [\App\Modules\Common\Controllers\DashboardSwitchController::class, 'enableAi'])->name('ai.enable');

        // Recent-logins history + the in-app revoke action mirroring
        // the email's "This wasn't me" button.
        Route::get('settings/security/logins', [\App\Modules\User\Controllers\SecurityController::class, 'logins'])
            ->name('security.logins');
        Route::post('security/logins/{loginEvent}/revoke', [\App\Modules\User\Controllers\SecurityController::class, 'revokeFromList'])
            ->name('security.logins.revoke-from-list');
        Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['onboarding.gate', 'contacts.sync-on-open'])->name('dashboard');

        // Task #3525 — "Customize dashboard": preset picker + AI designer.
        Route::post('dashboard/layout/preset', [\App\Modules\User\Controllers\DashboardLayoutController::class, 'applyPreset'])->name('dashboard.layout.preset');
        Route::post('dashboard/ai/estimate', [\App\Modules\User\Controllers\DashboardLayoutController::class, 'estimate'])->middleware('throttle:30,1')->name('dashboard.ai.estimate');
        Route::post('dashboard/ai/generate', [\App\Modules\User\Controllers\DashboardLayoutController::class, 'generate'])->middleware('throttle:10,1')->name('dashboard.ai.generate');

        // ---- Personal 2FA enrollment ----
        Route::get   ('settings/security',            [\App\Modules\User\Controllers\TwoFactorController::class, 'show'])
            ->name('account.two-factor.show');
        Route::post  ('account/two-factor',           [\App\Modules\User\Controllers\TwoFactorController::class, 'confirm'])
            ->name('account.two-factor.confirm');
        Route::delete('account/two-factor',           [\App\Modules\User\Controllers\TwoFactorController::class, 'disable'])
            ->name('account.two-factor.disable');
        Route::post  ('account/two-factor/recovery',  [\App\Modules\User\Controllers\TwoFactorController::class, 'regenerateRecoveryCodes'])
            ->name('account.two-factor.recovery-codes');
        Route::get   ('account/two-factor/required',  [\App\Modules\User\Controllers\TwoFactorController::class, 'required'])
            ->name('account.two-factor.required');

        // ---- Workspace security (owner-only) ----
        Route::put ('workspaces/security',           [\App\Modules\User\Controllers\WorkspaceSecurityController::class, 'update'])
            ->name('workspaces.security.update');
        Route::post('workspaces/security/remind',    [\App\Modules\User\Controllers\WorkspaceSecurityController::class, 'remindMembers'])
            ->name('workspaces.security.remind');

        // ---- Workspaces ----
        Route::post('workspaces',                              [\App\Modules\User\Controllers\WorkspaceController::class, 'store'])  ->name('workspaces.store');
        Route::post('workspaces/access-request',               [\App\Modules\User\Controllers\WorkspaceController::class, 'requestAccess'])
            ->middleware('throttle:6,60')
            ->name('workspaces.request-access');
        Route::post('workspaces/{workspace}/switch',           [\App\Modules\User\Controllers\WorkspaceController::class, 'switch']) ->name('workspaces.switch');
        Route::put ('workspaces/{workspace}',                  [\App\Modules\User\Controllers\WorkspaceController::class, 'update']) ->name('workspaces.update');
        Route::put ('workspaces/{workspace}/post-approval',    [\App\Modules\User\Controllers\WorkspaceController::class, 'updatePostApproval'])->name('workspaces.post-approval.update');
        Route::delete('workspaces/{workspace}',                [\App\Modules\User\Controllers\WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

        // ---- Sensitive-action audit log (owner / admin only). Append-only
        // ledger of high-risk actions on this workspace plus the per-action
        // alert preferences toggles.
        Route::get ('workspaces/audit',             [\App\Modules\User\Controllers\WorkspaceAuditController::class, 'index'])->name('workspaces.audit.index');
        Route::get ('workspaces/audit/preferences', [\App\Modules\User\Controllers\WorkspaceAuditController::class, 'preferences'])->name('workspaces.audit.preferences');
        Route::put ('workspaces/audit/preferences', [\App\Modules\User\Controllers\WorkspaceAuditController::class, 'updatePreferences'])->name('workspaces.audit.preferences.update');

        // ---- Team (members + invites) ----
        Route::get   ('team',                                  [\App\Modules\User\Controllers\TeamController::class, 'index'])  ->name('team.index');
        Route::post  ('team/invite',                           [\App\Modules\User\Controllers\TeamController::class, 'invite']) ->name('team.invite');
        Route::post  ('team/invites/{invite}/resend',          [\App\Modules\User\Controllers\TeamController::class, 'resend']) ->name('team.invites.resend');
        Route::delete('team/invites/{invite}',                 [\App\Modules\User\Controllers\TeamController::class, 'revoke']) ->name('team.invites.revoke');
        Route::put   ('team/members/{member}',                 [\App\Modules\User\Controllers\TeamController::class, 'updateMember'])->name('team.members.update');
        Route::delete('team/members/{member}',                 [\App\Modules\User\Controllers\TeamController::class, 'removeMember'])->name('team.members.remove');
        Route::post  ('team/members/{member}/suspend',         [\App\Modules\User\Controllers\TeamController::class, 'suspend'])    ->name('team.members.suspend');
        Route::post  ('team/members/{member}/reactivate',      [\App\Modules\User\Controllers\TeamController::class, 'reactivate']) ->name('team.members.reactivate');

        // ---- Roles & Permissions (Owner + Admin) ----
        Route::get   ('team/roles',                            [\App\Modules\User\Controllers\WorkspaceRolesController::class, 'index']) ->name('team.roles.index');
        Route::put   ('team/roles',                            [\App\Modules\User\Controllers\WorkspaceRolesController::class, 'update'])->name('team.roles.update');
        Route::post  ('team/roles/reset',                      [\App\Modules\User\Controllers\WorkspaceRolesController::class, 'reset']) ->name('team.roles.reset');

        // ---- Activity log (Owner + Admin) ----
        Route::get   ('workspaces/activity',                   [\App\Modules\User\Controllers\WorkspaceActivityController::class, 'index'])->name('workspaces.activity.index');
        Route::get   ('workspaces/activity/export',            [\App\Modules\User\Controllers\WorkspaceActivityController::class, 'export'])->name('workspaces.activity.export');

        // First-run onboarding — single page with personas (left) +
        // matching templates (right) + a live mini-preview drawer.
        Route::prefix('onboarding')->name('onboarding.')->group(function () {
            Route::get('/', [\App\Modules\User\Controllers\OnboardingController::class, 'index'])->name('index');
            // Legacy URLs from the old two-step flow — redirect to the new
            // single page so any bookmarked link, dashboard banner deep
            // link, or external doc keeps working.
            Route::get('persona',  fn() => redirect()->route('user.onboarding.index'))->name('persona');
            Route::get('template', fn() => redirect()->route('user.onboarding.index'))->name('template');

            // JSON/HTML helpers powering the split-panel UI.
            Route::get('templates', [\App\Modules\User\Controllers\OnboardingController::class, 'templatesJson'])->name('templates.list');
            Route::get('template/{id}/preview', [\App\Modules\User\Controllers\OnboardingController::class, 'templatePreview'])
                ->whereNumber('id')
                ->name('template.preview');

            Route::post('persona', [\App\Modules\User\Controllers\OnboardingController::class, 'savePersona'])->name('persona.save');
            Route::post('preview/remember', [\App\Modules\User\Controllers\OnboardingController::class, 'rememberPreview'])->name('preview.remember');
            Route::post('preview/dismiss',  [\App\Modules\User\Controllers\OnboardingController::class, 'dismissResume'])->name('preview.dismiss');
            Route::post('template',[\App\Modules\User\Controllers\OnboardingController::class, 'applyTemplate'])->name('template.apply');
            Route::post('go-to-dashboard', [\App\Modules\User\Controllers\OnboardingController::class, 'goToDashboard'])->name('go-to-dashboard');
            Route::post('dismiss-banner', [\App\Modules\User\Controllers\OnboardingController::class, 'dismissBanner'])->name('dismiss-banner');
            Route::post('dismiss-whatsapp-prompt', [\App\Modules\User\Controllers\OnboardingController::class, 'dismissWhatsappPrompt'])->name('dismiss-whatsapp-prompt');

            // Post-registration WhatsApp connect step + the shared inline
            // add/verify endpoints (also used by the dashboard nudge card).
            Route::get ('whatsapp',        [\App\Modules\User\Controllers\OnboardingController::class, 'whatsappStep'])->name('whatsapp');
            Route::post('whatsapp/send',   [\App\Modules\User\Controllers\OnboardingController::class, 'whatsappSend'])->middleware('throttle:5,1')->name('whatsapp.send');
            Route::post('whatsapp/verify', [\App\Modules\User\Controllers\OnboardingController::class, 'whatsappVerify'])->middleware('throttle:10,1')->name('whatsapp.verify');
            Route::post('whatsapp/skip',   [\App\Modules\User\Controllers\OnboardingController::class, 'whatsappSkip'])->name('whatsapp.skip');

            // Post-registration contact-privacy step (Task #3497) — no
            // forced default, one-time nudge, editable later from Settings.
            Route::get ('privacy',      [\App\Modules\User\Controllers\OnboardingController::class, 'privacyStep'])->name('privacy');
            Route::post('privacy',      [\App\Modules\User\Controllers\OnboardingController::class, 'privacySave'])->name('privacy.save');
            Route::post('privacy/skip', [\App\Modules\User\Controllers\OnboardingController::class, 'privacySkip'])->name('privacy.skip');
        });

        // ===== Social: followers, posts, notifications (dashboard) =====
        Route::get('followers', [\App\Modules\User\Controllers\FollowController::class, 'followers'])->middleware('workspace.can:followers.view')->name('followers.index');
        Route::get('following', [\App\Modules\User\Controllers\FollowController::class, 'following'])->middleware('workspace.can:followers.view')->name('following.index');
        Route::get('posts',  [\App\Modules\User\Controllers\CreatorPostController::class, 'index'])->middleware('workspace.can:posts.view')->name('posts.index');
        Route::post('posts', [\App\Modules\User\Controllers\CreatorPostController::class, 'store'])->middleware('workspace.can:posts.create')->name('posts.store');
        Route::post('posts/{post}/pin', [\App\Modules\User\Controllers\CreatorPostController::class, 'pin'])->middleware('workspace.can:posts.edit')->name('posts.pin');
        Route::post('posts/{post}/unpin', [\App\Modules\User\Controllers\CreatorPostController::class, 'unpin'])->middleware('workspace.can:posts.edit')->name('posts.unpin');
        Route::delete('posts/{post}', [\App\Modules\User\Controllers\CreatorPostController::class, 'destroy'])->middleware('workspace.can:posts.delete')->name('posts.destroy');
        // ---- Approval workflow (review queue actions) ----
        // Approver-side gating is in the controller (it depends on the
        // workspace's approver-roles setting, not a static role permission).
        Route::post('posts/{post}/approve',         [\App\Modules\User\Controllers\CreatorPostController::class, 'approve'])->name('posts.approve');
        Route::post('posts/{post}/request-changes', [\App\Modules\User\Controllers\CreatorPostController::class, 'requestChanges'])->name('posts.request-changes');
        Route::post('posts/{post}/reject',          [\App\Modules\User\Controllers\CreatorPostController::class, 'reject'])->name('posts.reject');
        Route::post('posts/{post}/resubmit',        [\App\Modules\User\Controllers\CreatorPostController::class, 'resubmit'])->middleware('workspace.can:posts.create')->name('posts.resubmit');
        Route::post('posts/{post}/comments',        [\App\Modules\User\Controllers\CreatorPostController::class, 'comment'])->name('posts.comments.store');

        // ---- Monetization (Task #1209): tiers, promos, earnings,
        // subscribers, ledger. Permission-gated under posts.view since
        // the dashboard sits next to "My Posts" in the sidebar and is
        // only meaningful for the workspace owner.
        Route::prefix('monetization')->name('monetization.')->middleware('workspace.can:posts.view')->group(function () {
            Route::get ('/',             [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'index'])->name('index');
            Route::get ('/earnings',     [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'earnings'])->name('earnings');
            Route::get ('/subscribers',  [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'subscribers'])->name('subscribers');
            Route::get ('/payments',     [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'payments'])->name('payments');
            Route::get ('/orders',       [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'orders'])->name('orders');
            Route::post('/orders/{order}/fulfill', [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'fulfillOrder'])->name('orders.fulfill');
            Route::post('/orders/{order}/refund',  [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'refundOrder'])->name('orders.refund');

            Route::get ('/tiers',                  [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'tiers'])->name('tiers');
            Route::post('/tiers',                  [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'storeTier'])->name('tiers.store');
            Route::put ('/tiers/{tier}',           [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'updateTier'])->name('tiers.update');
            Route::delete('/tiers/{tier}',         [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'destroyTier'])->name('tiers.destroy');

            Route::get ('/promos',                 [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'promos'])->name('promos');
            Route::post('/promos',                 [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'storePromo'])->name('promos.store');
            Route::put ('/promos/{promo}',         [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'updatePromo'])->name('promos.update');
            Route::delete('/promos/{promo}',       [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'destroyPromo'])->name('promos.destroy');
            Route::post('/promos/{promo}/toggle', [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'togglePromo'])->name('promos.toggle');

            Route::post('/refund',                 [\App\Modules\User\Controllers\CreatorMonetizationController::class, 'refund'])->name('refund');
        });
        // Notifications are scoped to the signed-in user (not the workspace
        // owner) — every team member has their own notification feed.
        Route::get('notifications',  [\App\Modules\User\Controllers\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read', [\App\Modules\User\Controllers\NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/{id}/read', [\App\Modules\User\Controllers\NotificationController::class, 'markOneRead'])->name('notifications.read-one')->whereNumber('id');
        Route::get('notifications/{id}/open', [\App\Modules\User\Controllers\NotificationController::class, 'open'])->name('notifications.open')->whereNumber('id');
        Route::delete('notifications/{id}', [\App\Modules\User\Controllers\NotificationController::class, 'destroy'])->name('notifications.destroy')->whereNumber('id');
        Route::post('notifications/{id}/restore', [\App\Modules\User\Controllers\NotificationController::class, 'restore'])->name('notifications.restore')->whereNumber('id');
        Route::get('settings/notifications', [\App\Modules\User\Controllers\NotificationController::class, 'preferences'])->name('notifications.preferences');
        Route::put('notifications/preferences', [\App\Modules\User\Controllers\NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');
        // Task #3497: contact-privacy prefs (hide phone/email/location/socials
        // from strangers via the dialer caller-ID + universal search).
        Route::prefix('settings/privacy')->name('settings.privacy.')->group(function () {
            Route::get('/', [\App\Modules\User\Controllers\ContactPrivacyController::class, 'show'])->name('show');
            Route::put('/', [\App\Modules\User\Controllers\ContactPrivacyController::class, 'update'])->name('update');
        });
        // On-demand "Send sample now" preview for the weekly backlink
        // digest. Always emails the signed-in user, never an arbitrary
        // recipient, and is rate-limited inside the controller.
        Route::post('notifications/backlink-digest/sample', [\App\Modules\User\Controllers\NotificationController::class, 'sendBacklinkDigestSample'])
            ->name('notifications.backlink-digest.sample');

        // Per-user blocklist of bot families that should not be recorded
        // at all. Gated under stats.view because the management screen
        // is reached from the link analytics bot-breakdown panel — only
        // members who can see analytics need (or can use) this control.
        Route::get   ('bot-blocks',                [\App\Modules\User\Controllers\BotBlockController::class, 'index'])->middleware('workspace.can:stats.view')->name('bot-blocks.index');
        Route::post  ('bot-blocks',                [\App\Modules\User\Controllers\BotBlockController::class, 'store'])->middleware('workspace.can:stats.view')->name('bot-blocks.store');
        Route::delete('bot-blocks/{family}',       [\App\Modules\User\Controllers\BotBlockController::class, 'destroy'])->middleware('workspace.can:stats.view')->where('family', '.+')->name('bot-blocks.destroy');

        Route::get('links/{link}/visitors', [\App\Modules\User\Controllers\VisitorAnalyticsController::class, 'index'])->middleware('workspace.can:stats.view')->name('links.visitors');
        Route::get('links/{link}/nfc-writes', [\App\Modules\User\Controllers\VisitorAnalyticsController::class, 'nfcHistory'])->middleware('workspace.can:stats.view')->name('links.nfc-writes');
        Route::get('links/{link}/followers', [\App\Modules\User\Controllers\LinkController::class, 'followers'])->middleware('workspace.can:followers.view')->name('links.followers');
        Route::get('links/{link}/followers/export', [\App\Modules\User\Controllers\LinkController::class, 'followersExport'])->middleware('workspace.can:followers.view')->name('links.followers.export');
        Route::get('links/{link}/followers/{follower}', [\App\Modules\User\Controllers\LinkController::class, 'followerHistory'])->middleware('workspace.can:followers.view')->name('links.followers.history');

        // Profile, billing, invoices, integrations, domains, splash pages,
        // pixels, files, calendar/contacts/dialer settings, verification,
        // social-accounts, identifiers, account merge, social proofs and
        // standalone QR studio are all account-level configuration. Members
        // need the workspace `settings` feature (Admin gets it; Editor and
        // Replier presets do not) to see or change anything here.
        // Devices & sessions (task #1111). Account-level so it sits
        // alongside the other settings views and inherits the same
        // workspace permission gate.
        Route::middleware('workspace.can:settings.view')->group(function () {
            Route::get   ('settings/security/devices',         [\App\Modules\User\Controllers\SessionManagerController::class, 'index'])->name('settings.sessions.index');
            Route::delete('settings/sessions/others',          [\App\Modules\User\Controllers\SessionManagerController::class, 'destroyOthers'])->name('settings.sessions.destroy-others');
            Route::delete('settings/sessions/{id}',            [\App\Modules\User\Controllers\SessionManagerController::class, 'destroy'])
                ->where('id', '[A-Za-z0-9:_\-]+')
                ->name('settings.sessions.destroy');

            // Developer API keys (task #1393). Gated behind the `api_access`
            // plan feature inside the controller.
            Route::get   ('settings/developer',  [\App\Modules\User\Controllers\ApiKeyController::class, 'index'])->name('api-keys.index');
            Route::post  ('api-keys',            [\App\Modules\User\Controllers\ApiKeyController::class, 'store'])->middleware('throttle:20,1')->name('api-keys.store');
            Route::delete('api-keys/{key}',      [\App\Modules\User\Controllers\ApiKeyController::class, 'destroy'])->whereNumber('key')->name('api-keys.destroy');

            // Email history: the user's own transactional emails + self-scoped,
            // throttled, allow-listed resend (invoices/receipts/verification).
            Route::get ('emails',                 [\App\Modules\User\Controllers\EmailHistoryController::class, 'index'])->name('emails.index');
            Route::post('emails/{emailLog}/resend', [\App\Modules\User\Controllers\EmailHistoryController::class, 'resend'])->whereNumber('emailLog')->name('emails.resend');
        });

        // Creator Profile editor (Task #1207). Lives next to the regular
        // Profile editor so the existing settings.view permission applies
        // — the public surface is at /@handle, this is just the editor.
        Route::prefix('settings/creator')->name('creator-profile.')->middleware('workspace.can:settings.view')->group(function () {
            Route::get('/',         [\App\Modules\User\Controllers\CreatorProfileController::class, 'edit'])->name('edit');
            Route::post('/',        [\App\Modules\User\Controllers\CreatorProfileController::class, 'update'])->name('update');
            Route::post('/handle',  [\App\Modules\User\Controllers\CreatorProfileController::class, 'claimHandle'])->name('handle.claim');
        });

        // Task #1211 — Unified Stats home (audience + content + engagement
        // + earnings) with CSV export. Sits next to "My Posts" so creators
        // can land here for the "how am I doing this week?" question
        // without paging through the deeper Monetization screens.
        Route::prefix('stats')->name('stats.')->middleware('workspace.can:posts.view')->group(function () {
            Route::get('/',       [\App\Modules\User\Controllers\CreatorStatsController::class, 'index'])->name('index');
            Route::get('export',  [\App\Modules\User\Controllers\CreatorStatsController::class, 'export'])->name('export');
        });

        // Task #1211 — on-demand "Send sample now" preview for the weekly
        // creator digest, mirroring the backlinks-digest sample pattern.
        Route::post('creator-digest/sample', function () {
            $user = \Illuminate\Support\Facades\Auth::user();
            $sent = app(\App\Modules\User\Services\CreatorDigestService::class)->send($user, true);
            return back()->with($sent ? 'success' : 'error',
                $sent ? "Sent a sample digest to {$user->email}." : "Couldn't send a sample digest right now.");
        })->middleware('workspace.can:posts.view')->name('creator-digest.sample');

        // ── Earnings & Payouts (Task #1208) ─────────────────────────
        // Workspace owner area: connect / manage payout providers and
        // toggle the 18+ adult-content flag on the profile. Owner-only
        // because payout connections are per-account financial data.
        Route::prefix('payouts')->name('payouts.')->middleware('workspace.owner')->group(function () {
            Route::get ('/',                                  [\App\Modules\User\Controllers\CreatorPayoutController::class, 'show'])->name('show');
            Route::get ('preview',                            [\App\Modules\User\Controllers\CreatorPayoutController::class, 'preview'])->name('preview');
            Route::get ('connect/{provider}',                 [\App\Modules\User\Controllers\CreatorPayoutController::class, 'connect'])->name('connect');
            Route::get ('return/{provider}',                  [\App\Modules\User\Controllers\CreatorPayoutController::class, 'returnFrom'])->name('return');
            Route::post('preview/{provider}/complete',        [\App\Modules\User\Controllers\CreatorPayoutController::class, 'previewComplete'])->name('preview-complete');
            Route::post('{connection}/sync',                  [\App\Modules\User\Controllers\CreatorPayoutController::class, 'sync'])->whereNumber('connection')->name('sync');
            Route::post('{connection}/default',               [\App\Modules\User\Controllers\CreatorPayoutController::class, 'setDefault'])->whereNumber('connection')->name('set-default');
            Route::delete('{connection}',                     [\App\Modules\User\Controllers\CreatorPayoutController::class, 'destroy'])->whereNumber('connection')->name('destroy');
        });

        Route::prefix('adult-content')->name('adult-content.')->middleware('workspace.owner')->group(function () {
            Route::get ('/',  [\App\Modules\User\Controllers\AdultContentController::class, 'show'])->name('show');
            Route::post('/',  [\App\Modules\User\Controllers\AdultContentController::class, 'update'])->name('update');
        });

        Route::prefix('settings/profile')->name('profile.')->middleware('workspace.can:settings.view')->group(function () {
            Route::get('/', [ProfileController::class, 'edit'])->name('edit');
            Route::put('/', [ProfileController::class, 'update'])->name('update');
            // Follower-digest preview & sample are gated under the dedicated
            // `digests` feature (Editor preset gets digests.view by design)
            // rather than the broader `settings` feature, so editors can
            // QA the digest without unlocking billing/integrations. We
            // strip the parent `settings.view` middleware and re-apply the
            // matching digests permission.
            //
            // `digest.sample` is intentionally `digests.view`, not
            // `digests.edit`: the action only ever emails the signed-in
            // user (controller hard-codes $user->email as the recipient),
            // so it's a QA/preview action — the same role that can view
            // the digest can fire one to themselves. `digests.edit` stays
            // reserved for bulk/all-followers digest actions (currently
            // only the scheduled console command, but kept distinct so a
            // future "send digest to all followers now" admin action has
            // a stricter gate available).
            Route::post('/digest/sample', [ProfileController::class, 'sendSample'])
                ->withoutMiddleware('workspace.can:settings.view')
                ->middleware('workspace.can:digests.view')
                ->name('digest.sample');
            Route::get('/digest/preview', [ProfileController::class, 'digestPreview'])
                ->withoutMiddleware('workspace.can:settings.view')
                ->middleware('workspace.can:digests.view')
                ->name('digest.preview');
        });

        Route::get('invoices/{invoice}/pdf', [\App\Modules\User\Controllers\InvoiceController::class, 'pdf'])->middleware('workspace.owner')->name('invoices.pdf');

        // ─── Client / Sponsor Portals ───────────────────────────
        // Owner-managed read-only branded portals shared with vault clients
        // via expirable magic links. Owner-only — settings.edit gates the
        // ability to create or modify, and the views rely on workspace
        // owner data anyway.
        Route::prefix('client-portals')->name('client-portals.')->middleware('workspace.can:settings.edit')->group(function () {
            Route::get   ('/',                                  [\App\Modules\User\Controllers\ClientPortalController::class, 'index'])->name('index');
            Route::get   ('create',                             [\App\Modules\User\Controllers\ClientPortalController::class, 'create'])->name('create');
            Route::post  ('/',                                  [\App\Modules\User\Controllers\ClientPortalController::class, 'store'])->name('store');
            Route::get   ('{clientPortal}/edit',                [\App\Modules\User\Controllers\ClientPortalController::class, 'edit'])->name('edit');
            Route::put   ('{clientPortal}',                     [\App\Modules\User\Controllers\ClientPortalController::class, 'update'])->name('update');
            Route::delete('{clientPortal}',                     [\App\Modules\User\Controllers\ClientPortalController::class, 'destroy'])->name('destroy');
            Route::post  ('{clientPortal}/shares',              [\App\Modules\User\Controllers\ClientPortalController::class, 'storeShare'])->name('shares.store');
            Route::delete('{clientPortal}/shares/{share}',      [\App\Modules\User\Controllers\ClientPortalController::class, 'destroyShare'])->whereNumber('share')->name('shares.destroy');
            Route::post  ('{clientPortal}/links',               [\App\Modules\User\Controllers\ClientPortalController::class, 'sendLink'])->middleware('throttle:20,10')->name('links.send');
            Route::post  ('{clientPortal}/links/{link}/revoke', [\App\Modules\User\Controllers\ClientPortalController::class, 'revokeLink'])->whereNumber('link')->name('links.revoke');
            Route::post  ('{clientPortal}/links/{link}/rotate', [\App\Modules\User\Controllers\ClientPortalController::class, 'rotateLink'])->whereNumber('link')->name('links.rotate');
        });

        // Projects are link-organisation buckets — gate under the links
        // feature so any role with links access can place links into one.
        Route::resource('projects', ProjectController::class)->except(['store', 'index', 'show'])->middleware('workspace.can:links.edit');
        Route::get('projects', [ProjectController::class, 'index'])->middleware('workspace.can:links.view')->name('projects.index');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->middleware('workspace.can:links.view')->name('projects.show');
        Route::post('projects', [ProjectController::class, 'store'])->middleware(['workspace.can:links.create', CheckPlanLimit::class . ':projects'])->name('projects.store');

        // ---- Resume / Portfolio ----
        // Personal to the signed-in user (one row per account); no
        // workspace permission gating because the resume isn't a
        // workspace artifact. The controller resolves the resume from
        // the authenticated user every call so a member of someone
        // else's workspace can never see/edit the owner's resume.
        Route::prefix('resume')->name('resume.')->group(function () {
            Route::get  ('/',                 [\App\Modules\User\Controllers\ResumeController::class, 'editor'])->name('editor');
            Route::get  ('data',              [\App\Modules\User\Controllers\ResumeController::class, 'show'])->name('show');
            Route::put  ('header',            [\App\Modules\User\Controllers\ResumeController::class, 'updateHeader'])->name('header.update');
            Route::post  ('header/photo',     [\App\Modules\User\Controllers\ResumeController::class, 'uploadHeaderPhoto'])->name('header.photo.upload');
            Route::delete('header/photo',     [\App\Modules\User\Controllers\ResumeController::class, 'removeHeaderPhoto'])->name('header.photo.destroy');
            Route::put  ('summary',           [\App\Modules\User\Controllers\ResumeController::class, 'updateSummary'])->name('summary.update');
            Route::put  ('template',          [\App\Modules\User\Controllers\ResumeController::class, 'updateTemplate'])->name('template.update');
            Route::put  ('color-theme',       [\App\Modules\User\Controllers\ResumeController::class, 'updateColorTheme'])->name('color-theme.update');
            Route::put  ('public-pdf',        [\App\Modules\User\Controllers\ResumeController::class, 'updatePublicPdf'])->name('public-pdf.update');

            Route::post  ('sections',         [\App\Modules\User\Controllers\ResumeController::class, 'addCustomSection'])->name('sections.store');
            Route::put   ('sections/{key}',   [\App\Modules\User\Controllers\ResumeController::class, 'updateCustomSection'])->where('key', '[a-z0-9_]+')->name('sections.update');
            Route::delete('sections/{key}',   [\App\Modules\User\Controllers\ResumeController::class, 'destroyCustomSection'])->where('key', '[a-z0-9_]+')->name('sections.destroy');

            Route::post  ('items',            [\App\Modules\User\Controllers\ResumeController::class, 'storeItem'])->name('items.store');
            Route::put   ('items/{item}',     [\App\Modules\User\Controllers\ResumeController::class, 'updateItem'])->whereNumber('item')->name('items.update');
            Route::delete('items/{item}',     [\App\Modules\User\Controllers\ResumeController::class, 'destroyItem'])->whereNumber('item')->name('items.destroy');
            Route::post  ('items/reorder',    [\App\Modules\User\Controllers\ResumeController::class, 'reorderItems'])->name('items.reorder');

            // Polished PDF export for the signed-in owner. Throttled so
            // headless rendering can't be weaponised against the worker.
            Route::get('download.pdf',        [\App\Modules\User\Controllers\ResumeController::class, 'download'])
                ->middleware('throttle:20,1')
                ->name('download');

            // ATS-friendliness scan. Read-only and lightweight, but
            // throttled so a flapping client can't loop on it.
            Route::post('ats-check',          [\App\Modules\User\Controllers\ResumeController::class, 'atsCheck'])
                ->middleware('throttle:30,1')
                ->name('ats-check');

            // Importers: file/PDF/DOCX, LinkedIn (URL + export PDF), bio link
            // pull-in, AI-assisted draft, and the merge endpoint that commits
            // the user's curated picks back into the resume.
            Route::prefix('import')->name('import.')->group(function () {
                Route::post('file',     [\App\Modules\User\Controllers\ResumeImportController::class, 'file'])->name('file');
                Route::post('linkedin', [\App\Modules\User\Controllers\ResumeImportController::class, 'linkedin'])->name('linkedin');
                Route::post('biolink',  [\App\Modules\User\Controllers\ResumeImportController::class, 'biolink'])->name('biolink');
                Route::post('ai',       [\App\Modules\User\Controllers\ResumeImportController::class, 'ai'])->middleware('throttle:10,1')->name('ai');
                Route::post('merge',    [\App\Modules\User\Controllers\ResumeImportController::class, 'merge'])->name('merge');
            });

            // Tailor-to-job: paste a JD, get an AI-rewritten draft of the
            // summary + experience bullets + suggested skill additions
            // with a per-change diff. `run` is the chargeable AI call;
            // `apply` commits the user's accepted picks for free.
            Route::prefix('tailor')->name('tailor.')->group(function () {
                Route::post('estimate', [\App\Modules\User\Controllers\ResumeTailorController::class, 'estimate'])->middleware('throttle:30,1')->name('estimate');
                Route::post('run',      [\App\Modules\User\Controllers\ResumeTailorController::class, 'run'])->middleware('throttle:10,1')->name('run');
                Route::post('apply',    [\App\Modules\User\Controllers\ResumeTailorController::class, 'apply'])->name('apply');
                Route::get ('history',  [\App\Modules\User\Controllers\ResumeTailorController::class, 'history'])->name('history');
            });

            // Cover-letter generator: paste a JD, pick a tone, and the
            // AI returns a structured letter (greeting + body + sign-off).
            // `store` is the chargeable AI call; `regenerate` is a smaller
            // per-section AI call. Inline edits / list / show / delete /
            // PDF download are free reads of already-generated content.
            Route::prefix('cover-letters')->name('cover-letters.')->group(function () {
                Route::post('estimate', [\App\Modules\User\Controllers\ResumeCoverLetterController::class, 'estimate'])->middleware('throttle:30,1')->name('estimate');
                Route::get ('/',        [\App\Modules\User\Controllers\ResumeCoverLetterController::class, 'index'])->name('index');
                Route::post('/',        [\App\Modules\User\Controllers\ResumeCoverLetterController::class, 'store'])->middleware('throttle:10,1')->name('store');
                Route::get ('{letter}',            [\App\Modules\User\Controllers\ResumeCoverLetterController::class, 'show'])->whereNumber('letter')->name('show');
                Route::patch('{letter}',           [\App\Modules\User\Controllers\ResumeCoverLetterController::class, 'update'])->whereNumber('letter')->name('update');
                Route::post('{letter}/regenerate', [\App\Modules\User\Controllers\ResumeCoverLetterController::class, 'regenerate'])->whereNumber('letter')->middleware('throttle:20,1')->name('regenerate');
                Route::delete('{letter}',          [\App\Modules\User\Controllers\ResumeCoverLetterController::class, 'destroy'])->whereNumber('letter')->name('destroy');
                Route::get('{letter}/download',    [\App\Modules\User\Controllers\ResumeCoverLetterController::class, 'download'])->whereNumber('letter')->middleware('throttle:30,1')->name('download');
            });

            // Publish & sharing — toggles the public /{handle}/resume URL,
            // visibility tier (public/registered/followers/subscribers/
            // password), the per-user noindex flag, and (when password
            // tier is selected) the hashed password + optional expiration.
            Route::put('publishing', [\App\Modules\User\Controllers\ResumeController::class, 'updatePublishing'])->name('publishing.update');
            // Revoke the active share without changing the URL. Bumps
            // share_revision so previously-unlocked visitors get re-prompted.
            Route::post('share/revoke', [\App\Modules\User\Controllers\ResumeController::class, 'revokeShare'])->name('share.revoke');
            // Paginated audit log (timestamp / country / referrer / handle).
            Route::get('views', [\App\Modules\User\Controllers\ResumeController::class, 'views'])->name('views');

            // ---- Version management ----
            // CRUD over named resume versions. The {version} binding is
            // numeric (a Resume row id); the controller/service verify
            // ownership before mutating so a foreign id 403s.
            Route::get   ('versions',                                [\App\Modules\User\Controllers\ResumeController::class, 'versionsIndex'])->name('versions.index');
            Route::post  ('versions',                                [\App\Modules\User\Controllers\ResumeController::class, 'versionStore'])->name('versions.store');
            Route::put   ('versions/{version}',                      [\App\Modules\User\Controllers\ResumeController::class, 'versionRename'])->whereNumber('version')->name('versions.rename');
            Route::delete('versions/{version}',                      [\App\Modules\User\Controllers\ResumeController::class, 'versionDestroy'])->whereNumber('version')->name('versions.destroy');
            Route::post  ('versions/{version}/duplicate',            [\App\Modules\User\Controllers\ResumeController::class, 'versionDuplicate'])->whereNumber('version')->name('versions.duplicate');
            Route::post  ('versions/{version}/default',              [\App\Modules\User\Controllers\ResumeController::class, 'versionSetDefault'])->whereNumber('version')->name('versions.default');
        });

        // ---- Forms ----
        // Forms feed inbox — gate read endpoints with `inbox.view` and
        // mutations with the matching create/edit/delete actions so a
        // view-only or reply-only member cannot reshape the form, while
        // submissions starring/destroy live under inbox.edit/inbox.delete.
        Route::get('forms', [FormController::class, 'index'])->middleware('workspace.can:inbox.view')->name('forms.index');
        Route::get('forms/create', [FormController::class, 'create'])->middleware('workspace.can:inbox.create')->name('forms.create');
        Route::post('forms', [FormController::class, 'store'])->middleware(['workspace.can:inbox.create', CheckPlanLimit::class . ':forms'])->name('forms.store');
        Route::get('forms/{form}', [FormController::class, 'show'])->middleware('workspace.can:inbox.view')->name('forms.show');
        Route::delete('forms/{form}', [FormController::class, 'destroy'])->middleware('workspace.can:inbox.delete')->name('forms.destroy');
        Route::post('forms/{form}/toggle-active', [FormController::class, 'toggleActive'])->middleware('workspace.can:inbox.edit')->name('forms.toggle-active');
        Route::get('forms/{form}/builder', [FormController::class, 'builder'])->middleware('workspace.can:inbox.view')->name('forms.builder');
        Route::put('forms/{form}/builder', [FormController::class, 'updateBuilder'])->middleware('workspace.can:inbox.edit')->name('forms.builder.update');
        Route::get('forms/{form}/design', [FormController::class, 'design'])->middleware('workspace.can:inbox.view')->name('forms.design');
        Route::put('forms/{form}/design', [FormController::class, 'updateDesign'])->middleware('workspace.can:inbox.edit')->name('forms.design.update');
        Route::get('forms/{form}/notifications', [FormController::class, 'notifications'])->middleware('workspace.can:inbox.view')->name('forms.notifications');
        Route::put('forms/{form}/notifications', [FormController::class, 'updateNotifications'])->middleware('workspace.can:inbox.edit')->name('forms.notifications.update');
        Route::get('forms/{form}/payment', [FormController::class, 'payment'])->middleware('workspace.can:inbox.view')->name('forms.payment');
        Route::put('forms/{form}/payment', [FormController::class, 'updatePayment'])->middleware('workspace.can:inbox.edit')->name('forms.payment.update');
        Route::get('forms/{form}/embed', [FormController::class, 'embed'])->middleware('workspace.can:inbox.view')->name('forms.embed');
        Route::put('forms/{form}/domain', [FormController::class, 'updateDomain'])->middleware('workspace.can:inbox.edit')->name('forms.domain.update');
        Route::get('forms/{form}/submissions', [FormController::class, 'submissions'])->middleware('workspace.can:inbox.view')->name('forms.submissions');
        Route::get('forms/{form}/submissions/export', [FormController::class, 'exportSubmissions'])->middleware('workspace.can:inbox.view')->name('forms.submissions.export');
        Route::get('forms/{form}/submissions/{submission}', [FormController::class, 'showSubmission'])->middleware('workspace.can:inbox.view')->name('forms.submissions.show');
        Route::post('forms/{form}/submissions/{submission}/star', [FormController::class, 'toggleSubmissionStar'])->middleware('workspace.can:inbox.edit')->name('forms.submissions.star');
        Route::delete('forms/{form}/submissions/{submission}', [FormController::class, 'destroySubmission'])->middleware('workspace.can:inbox.delete')->name('forms.submissions.destroy');
        Route::post('forms/{form}/submissions/{submission}/refund', [FormController::class, 'refundSubmission'])->middleware('workspace.can:inbox.delete')->name('forms.submissions.refund');
        Route::post('forms/{form}/submissions/erase-submitter', [FormController::class, 'eraseSubmitter'])->middleware('workspace.can:inbox.delete')->name('forms.submissions.erase-submitter');

        // Links: action-aware gating. Resource read endpoints (index/show/
        // create form/edit form) require `links.view`; mutations (store/update/
        // destroy/toggle/duplicate/aliases/blocks/page-settings) escalate to
        // links.create / links.edit / links.delete. This stops a viewer from
        // editing biolink blocks or destroying links.
        // Guided biolink creation wizard. Registered BEFORE the
        // `Route::resource('links', ...)` so `links/wizard` is matched as a
        // literal path and not as the `links/{link}` show-route.
        Route::get('links/wizard',          [BiolinkWizardController::class, 'index'])->middleware('workspace.can:links.create')->name('links.wizard');
        // POST so destructive draft-reset is CSRF-guarded — never allow a GET
        // to delete state (a third-party prefetch could trigger draft loss).
        Route::post('links/wizard/start',   [BiolinkWizardController::class, 'start'])->middleware('workspace.can:links.create')->name('links.wizard.start');
        Route::get('links/wizard/resume',   [BiolinkWizardController::class, 'resume'])->middleware('workspace.can:links.create')->name('links.wizard.resume');
        Route::post('links/wizard/step',    [BiolinkWizardController::class, 'save'])->middleware('workspace.can:links.create')->name('links.wizard.step');
        Route::post('links/wizard',         [BiolinkWizardController::class, 'save'])->middleware('workspace.can:links.create')->name('links.wizard.save');
        Route::patch('links/wizard/draft',  [BiolinkWizardController::class, 'draft'])->middleware('workspace.can:links.create')->name('links.wizard.draft');
        Route::post('links/wizard/finish',  [BiolinkWizardController::class, 'finish'])->middleware(['workspace.can:links.create', CheckPlanLimit::class . ':links'])->name('links.wizard.finish');
        Route::post('links/wizard/ai-draft', [BiolinkWizardController::class, 'finishAi'])->middleware(['workspace.can:links.create', CheckPlanLimit::class . ':links'])->name('links.wizard.ai-draft');

        // Live "Custom URL availability" probe for the Create Link page — must
        // sit BEFORE Route::resource('links', ...) so `links/check-alias` is
        // matched as a literal path and not as the `links/{link}` show-route.
        Route::get('links/check-alias', [LinkController::class, 'checkAlias'])->middleware('workspace.can:links.create')->name('links.check-alias');

        // Export the My Links list (honours the list filters) as CSV. Must sit
        // BEFORE Route::resource('links', ...) so `links/export` is matched as
        // a literal path and not as the `links/{link}` show-route.
        Route::get('links/export', [LinkController::class, 'export'])->middleware('workspace.can:links.view')->name('links.export');

        Route::resource('links', LinkController::class)->except(['store', 'update', 'destroy'])->middleware('workspace.can:links.view');
        Route::put('links/{link}',  [LinkController::class, 'update'])->middleware('workspace.can:links.edit')->name('links.update');
        Route::patch('links/{link}',[LinkController::class, 'update'])->middleware('workspace.can:links.edit');
        Route::delete('links/{link}', [LinkController::class, 'destroy'])->middleware('workspace.can:links.delete')->name('links.destroy');
        Route::post('links/choose-type', [LinkController::class, 'chooseType'])->middleware('workspace.can:links.create')->name('links.choose-type');
        Route::get('links-url/create', [LinkController::class, 'createUrl'])->middleware('workspace.can:links.create')->name('links.url.create');
        Route::get('links-url/bulk', [LinkController::class, 'bulkCreateUrl'])->middleware('workspace.can:links.create')->name('links.url.bulk');
        Route::post('links-url/bulk/preview', [LinkController::class, 'bulkPreviewUrl'])->middleware('workspace.can:links.create')->name('links.url.bulk.preview');
        Route::post('links-url/bulk', [LinkController::class, 'bulkStoreUrl'])->middleware('workspace.can:links.create')->name('links.url.bulk.store');
        Route::get('links-biolink/create', [LinkController::class, 'createBiolink'])->middleware('workspace.can:links.create')->name('links.biolink.create');
        Route::get('links-biolink/bulk', [BulkBiolinkController::class, 'create'])->middleware('workspace.can:links.create')->name('links.biolink.bulk');
        Route::post('links-biolink/bulk/preview', [BulkBiolinkController::class, 'preview'])->middleware('workspace.can:links.create')->name('links.biolink.bulk.preview');
        Route::post('links-biolink/bulk/sample', [BulkBiolinkController::class, 'sampleSheet'])->middleware('workspace.can:links.create')->name('links.biolink.bulk.sample');
        Route::post('links-biolink/bulk', [BulkBiolinkController::class, 'store'])->middleware('workspace.can:links.create')->name('links.biolink.bulk.store');
        Route::post('links', [LinkController::class, 'store'])->middleware(['workspace.can:links.create', CheckPlanLimit::class . ':links'])->name('links.store');
        Route::post('links/{link}/toggle-active', [LinkController::class, 'toggleActive'])->middleware('workspace.can:links.edit')->name('links.toggle-active');
        Route::post('links/{link}/duplicate', [LinkController::class, 'duplicate'])->middleware(['workspace.can:links.create', CheckPlanLimit::class . ':links'])->name('links.duplicate');
        // Cross-workspace move (owner-only — see LinkController::move).
        Route::post('links/{link}/move',  [LinkController::class, 'move'])->middleware('workspace.can:links.edit')->name('links.move');
        Route::post('links/move-bulk',    [LinkController::class, 'moveBulk'])->middleware('workspace.can:links.edit')->name('links.move-bulk');
        Route::post('links/{link}/coach-action', [LinkController::class, 'coachAction'])->middleware('workspace.can:links.edit')->name('links.coach-action');

        // Public-roadmap triage dashboard for a biolink
        Route::get   ('links/{link}/roadmap',                [\App\Modules\User\Controllers\RoadmapTriageController::class, 'index'])->middleware('workspace.can:links.edit')->name('roadmap.triage');
        Route::patch ('links/{link}/roadmap/items/{item}',   [\App\Modules\User\Controllers\RoadmapTriageController::class, 'update'])->middleware('workspace.can:links.edit')->name('roadmap.update');
        Route::delete('links/{link}/roadmap/items/{item}',   [\App\Modules\User\Controllers\RoadmapTriageController::class, 'destroy'])->middleware('workspace.can:links.edit')->name('roadmap.destroy');
        Route::post  ('links/{link}/roadmap/items/{item}/merge', [\App\Modules\User\Controllers\RoadmapTriageController::class, 'merge'])->middleware('workspace.can:links.edit')->name('roadmap.merge');
        Route::post('links/{link}/performance-coach/settings', [LinkController::class, 'updatePerformanceCoachSettings'])->middleware('workspace.can:links.edit')->name('links.performance-coach.settings');
        Route::post('links/coach-undo', [LinkController::class, 'coachUndo'])->middleware('workspace.can:links.edit')->name('links.coach-undo');
        Route::delete('links/{link}/stats', [LinkController::class, 'resetStats'])->middleware('workspace.can:links.delete')->name('links.reset-stats');
        Route::put('links/{link}/alias', [LinkController::class, 'updateAlias'])->middleware('workspace.can:links.edit')->name('links.update-alias');
        // Mint a fresh signed preview URL for the editor's device-preview iframe.
        // Called by the editor when the existing 24h URL is about to expire so
        // the iframe never falls into Laravel's "Invalid signature" page.
        Route::get('links/{link}/preview-url', [LinkController::class, 'previewUrl'])->middleware('workspace.can:links.view')->name('links.preview-url');

        // ---- Backlink radar (web dashboard view of the same data the
        // browser extension popup reads via the JSON API). ----
        Route::get   ('backlinks',                 [\App\Modules\User\Controllers\BacklinkController::class, 'index'])  ->middleware('workspace.can:links.view')->name('backlinks.index');
        Route::get   ('backlinks/export.csv',      [\App\Modules\User\Controllers\BacklinkController::class, 'export']) ->middleware('workspace.can:links.view')->name('backlinks.export');
        Route::delete('backlinks/{id}',            [\App\Modules\User\Controllers\BacklinkController::class, 'destroy'])->middleware('workspace.can:links.delete')->whereNumber('id')->name('backlinks.destroy');

        // Link Insurance — per-link backups + workspace dashboard. The
        // dashboard is mounted at /user/insurance so it doesn't collide
        // with the {link} param routes above.
        Route::get('insurance', [\App\Modules\User\Controllers\LinkInsuranceController::class, 'dashboard'])->middleware('workspace.can:links.view')->name('insurance.dashboard');
        Route::get('links/{link}/insurance', [\App\Modules\User\Controllers\LinkInsuranceController::class, 'settings'])->middleware('workspace.can:links.view')->name('links.insurance.settings');
        Route::post('links/{link}/insurance', [\App\Modules\User\Controllers\LinkInsuranceController::class, 'update'])->middleware('workspace.can:links.edit')->name('links.insurance.update');
        Route::post('links/{link}/insurance/restore', [\App\Modules\User\Controllers\LinkInsuranceController::class, 'restorePrimary'])->middleware('workspace.can:links.edit')->name('links.insurance.restore');
        // GET variant for the one-click "Restore now" link in the
        // failover email + in-app notification action button.
        // GET restore + promote use Laravel's signed-URL middleware
        // so a CSRF-style trick (logged-in user opens a malicious
        // page with <img src="/insurance/restore">) cannot mutate
        // state — only links generated by NotificationService /
        // LinkInsuranceAlertMail (which call URL::signedRoute) work.
        Route::get('links/{link}/insurance/restore', [\App\Modules\User\Controllers\LinkInsuranceController::class, 'restoreFromNotification'])->middleware(['signed', 'workspace.can:links.edit'])->name('links.insurance.restore-action');
        Route::get('links/{link}/insurance/promote-next', [\App\Modules\User\Controllers\LinkInsuranceController::class, 'promoteNext'])->middleware(['signed', 'workspace.can:links.edit'])->name('links.insurance.promote-next');
        Route::post('links/{link}/insurance/probe', [\App\Modules\User\Controllers\LinkInsuranceController::class, 'probeNow'])->middleware('workspace.can:links.edit')->name('links.insurance.probe');

        // Additional (alternative) aliases per link — same page served, no redirect.
        Route::post('links/{link}/aliases', [\App\Modules\User\Controllers\LinkAliasController::class, 'store'])->middleware('workspace.can:links.edit')->name('links.aliases.store');
        Route::delete('links/{link}/aliases/{alias}', [\App\Modules\User\Controllers\LinkAliasController::class, 'destroy'])->middleware('workspace.can:links.edit')->name('links.aliases.destroy');
        Route::post('links/{link}/aliases/{alias}/promote', [\App\Modules\User\Controllers\LinkAliasController::class, 'promote'])->middleware('workspace.can:links.edit')->name('links.aliases.promote');
        Route::put('links/{link}/aliases/{alias}/domain', [\App\Modules\User\Controllers\LinkAliasController::class, 'updateDomain'])->middleware('workspace.can:links.edit')->name('links.aliases.update-domain');

        Route::get('links-file/create', [FileLinkController::class, 'create'])->middleware('workspace.can:links.create')->name('links.file.create');
        Route::post('links-file', [FileLinkController::class, 'store'])->middleware(['workspace.can:links.create', CheckPlanLimit::class . ':links'])->name('links.file.store');
        Route::get('links-ics/create', [IcsLinkController::class, 'create'])->middleware('workspace.can:links.create')->name('links.ics.create');
        Route::post('links-ics', [IcsLinkController::class, 'store'])->middleware(['workspace.can:links.create', CheckPlanLimit::class . ':links', CheckPlanLimit::class . ':events'])->name('links.ics.store');
        Route::get('links-ics/{link}/edit', [IcsLinkController::class, 'edit'])->middleware('workspace.can:links.edit')->name('links.ics.edit');
        Route::put('links-ics/{link}', [IcsLinkController::class, 'update'])->middleware('workspace.can:links.edit')->name('links.ics.update');
        Route::get('links-vcf/create', [VcfLinkController::class, 'create'])->middleware('workspace.can:links.create')->name('links.vcf.create');
        Route::post('links-vcf', [VcfLinkController::class, 'store'])->middleware(['workspace.can:links.create', CheckPlanLimit::class . ':links'])->name('links.vcf.store');
        Route::get('links-vcf/{link}/edit', [VcfLinkController::class, 'edit'])->middleware('workspace.can:links.edit')->name('links.vcf.edit');
        Route::put('links-vcf/{link}', [VcfLinkController::class, 'update'])->middleware('workspace.can:links.edit')->name('links.vcf.update');

        // ── Standalone Reviews page: step-2 create, editor, moderation,
        //    custom questions, and 3rd-party provider connections. ──
        Route::get ('links-reviews/create', [LinkController::class, 'createReviews'])->middleware('workspace.can:links.create')->name('links.reviews.create');
        // Standalone Resume / Portfolio link: step-2 create only. The editor
        // and public page reuse the existing standalone resume builder.
        Route::get ('links-resume/create', [LinkController::class, 'createResume'])->middleware('workspace.can:links.create')->name('links.resume.create');
        // Standalone Paid Page link: step-2 create + design editor. Posts and
        // tiers reuse the existing per-creator dashboards.
        Route::get ('links-paid-page/create', [LinkController::class, 'createPaidPage'])->middleware('workspace.can:links.create')->name('links.paid-page.create');
        // Standalone Brand / Press Kit link: step-2 create + dedicated editor.
        // The editor is prefilled from the owner's saved AI Brand Kit.
        Route::get ('links-brand-kit/create', [LinkController::class, 'createBrandKit'])->middleware('workspace.can:links.create')->name('links.brand-kit.create');
        // Followable Calendar link type: step-2 create + dedicated event editor,
        // per-calendar settings, event CRUD, and the cross-calendar "My Calendar"
        // agenda (owned + followed). Distinct from the external CalendarAccount
        // Google-sync routes (calendar.* / events.*) above — these use the
        // `calendars` prefix and the `user.calendars.*` name space.
        Route::get   ('calendars/create',               [\App\Modules\User\Controllers\CalendarController::class, 'create'])->middleware('workspace.can:links.create')->name('calendars.create');
        Route::get   ('my-calendar',                     [\App\Modules\User\Controllers\CalendarController::class, 'myCalendar'])->name('calendars.mine');
        Route::get   ('my-calendar/export',              [\App\Modules\User\Controllers\CalendarController::class, 'myCalendarExport'])->name('calendars.mine.export');
        Route::post  ('my-calendar/feed/reset',          [\App\Modules\User\Controllers\CalendarController::class, 'regenerateMyCalendarFeed'])->name('calendars.mine.feed.reset');
        Route::get   ('calendars/{link}/editor',         [\App\Modules\User\Controllers\CalendarController::class, 'editor'])->whereNumber('link')->middleware('workspace.can:links.view')->name('calendars.editor');
        Route::post  ('calendars/{link}/settings',       [\App\Modules\User\Controllers\CalendarController::class, 'updateSettings'])->whereNumber('link')->middleware('workspace.can:links.edit')->name('calendars.settings');
        Route::post  ('calendars/{link}/sync',           [\App\Modules\User\Controllers\CalendarController::class, 'syncToGoogle'])->whereNumber('link')->middleware('workspace.can:links.edit')->name('calendars.sync');
        Route::post  ('calendars/{link}/events',         [\App\Modules\User\Controllers\CalendarController::class, 'storeEvent'])->whereNumber('link')->middleware('workspace.can:links.edit')->name('calendars.events.store');
        Route::put   ('calendars/{link}/events/{event}', [\App\Modules\User\Controllers\CalendarController::class, 'updateEvent'])->whereNumber('link')->whereNumber('event')->middleware('workspace.can:links.edit')->name('calendars.events.update');
        Route::delete('calendars/{link}/events/{event}', [\App\Modules\User\Controllers\CalendarController::class, 'destroyEvent'])->whereNumber('link')->whereNumber('event')->middleware('workspace.can:links.edit')->name('calendars.events.destroy');
        Route::get ('links/{link}/paid-page', [\App\Modules\User\Controllers\PaidPageController::class, 'editor'])->whereNumber('link')->middleware('workspace.can:links.view')->name('links.paid-page.editor');
        Route::post('links/{link}/paid-page', [\App\Modules\User\Controllers\PaidPageController::class, 'update'])->whereNumber('link')->middleware('workspace.can:links.edit')->name('links.paid-page.update');
        Route::get ('links/{link}/brand-kit', [\App\Modules\User\Controllers\BrandKitPageController::class, 'editor'])->whereNumber('link')->middleware('workspace.can:links.view')->name('links.brand-kit.editor');
        Route::post('links/{link}/brand-kit', [\App\Modules\User\Controllers\BrandKitPageController::class, 'update'])->whereNumber('link')->middleware('workspace.can:links.edit')->name('links.brand-kit.update');
        Route::get ('links/{link}/reviews', [\App\Modules\User\Controllers\ReviewsController::class, 'editor'])->whereNumber('link')->middleware('workspace.can:links.view')->name('links.reviews.editor');
        Route::post('links/{link}/reviews/settings', [\App\Modules\User\Controllers\ReviewsController::class, 'updateSettings'])->whereNumber('link')->middleware('workspace.can:links.edit')->name('links.reviews.settings');
        Route::post('links/{link}/reviews/questions', [\App\Modules\User\Controllers\ReviewsController::class, 'storeQuestion'])->whereNumber('link')->middleware('workspace.can:links.edit')->name('links.reviews.questions.store');
        Route::delete('reviews/questions/{question}', [\App\Modules\User\Controllers\ReviewsController::class, 'destroyQuestion'])->whereNumber('question')->middleware('workspace.can:links.edit')->name('links.reviews.questions.destroy');
        // Moderation actions (creator-scoped reviews).
        Route::post  ('reviews/{review}/approve', [\App\Modules\User\Controllers\ReviewsController::class, 'approve'])->whereNumber('review')->middleware('workspace.can:links.edit')->name('links.reviews.approve');
        Route::post  ('reviews/{review}/hide',    [\App\Modules\User\Controllers\ReviewsController::class, 'hide'])->whereNumber('review')->middleware('workspace.can:links.edit')->name('links.reviews.hide');
        Route::post  ('reviews/{review}/pin',     [\App\Modules\User\Controllers\ReviewsController::class, 'pin'])->whereNumber('review')->middleware('workspace.can:links.edit')->name('links.reviews.pin');
        Route::post  ('reviews/{review}/reply',   [\App\Modules\User\Controllers\ReviewsController::class, 'reply'])->whereNumber('review')->middleware('workspace.can:links.edit')->name('links.reviews.reply');
        Route::delete('reviews/{review}',         [\App\Modules\User\Controllers\ReviewsController::class, 'destroy'])->whereNumber('review')->middleware('workspace.can:links.edit')->name('links.reviews.destroy');
        // 3rd-party provider connections.
        Route::post  ('reviews/providers/{provider}/connect', [\App\Modules\User\Controllers\ReviewsController::class, 'connectProvider'])->middleware('workspace.can:links.edit')->name('links.reviews.providers.connect');
        Route::post  ('reviews/providers/{providerConn}/refresh', [\App\Modules\User\Controllers\ReviewsController::class, 'refreshProvider'])->whereNumber('providerConn')->middleware('workspace.can:links.edit')->name('links.reviews.providers.refresh');
        Route::delete('reviews/providers/{providerConn}', [\App\Modules\User\Controllers\ReviewsController::class, 'disconnectProvider'])->whereNumber('providerConn')->middleware('workspace.can:links.edit')->name('links.reviews.providers.disconnect');

        // AI Biolink Page Builder — describe a page, AI assembles it from
        // real supported block types, then opens the standard editor.
        Route::get('links/{link}/ai-builder', [\App\Modules\User\Controllers\AiBiolinkBuilderController::class, 'intake'])->middleware('workspace.can:links.view')->name('links.ai-builder');
        Route::post('links/{link}/ai-builder/estimate', [\App\Modules\User\Controllers\AiBiolinkBuilderController::class, 'estimate'])->middleware(['workspace.can:links.edit', 'throttle:30,1'])->name('links.ai-builder.estimate');
        Route::post('links/{link}/ai-builder/generate', [\App\Modules\User\Controllers\AiBiolinkBuilderController::class, 'generate'])->middleware(['workspace.can:links.edit', 'throttle:10,1'])->name('links.ai-builder.generate');

        // Competitor Biolink Teardown — paste a competitor URL, get an
        // AI-scored teardown (strengths/weaknesses/missing elements/CTA
        // quality), then hand off to the AI biolink builder to build a
        // better version.
        Route::get('links-teardown/create', [\App\Modules\User\Controllers\CompetitorTeardownController::class, 'create'])->middleware('workspace.can:links.create')->name('links.teardown.create');
        Route::post('links-teardown', [\App\Modules\User\Controllers\CompetitorTeardownController::class, 'store'])->middleware(['workspace.can:links.create', 'throttle:10,1'])->name('links.teardown.store');
        Route::get('links-teardown/{teardown}', [\App\Modules\User\Controllers\CompetitorTeardownController::class, 'show'])->whereNumber('teardown')->middleware('workspace.can:links.view')->name('links.teardown.show');
        Route::post('links-teardown/{teardown}/build', [\App\Modules\User\Controllers\CompetitorTeardownController::class, 'build'])->whereNumber('teardown')->middleware(['workspace.can:links.create', 'throttle:10,1', CheckPlanLimit::class . ':links'])->name('links.teardown.build');

        // AI Brand Kit — persistent per-creator brand identity (palette, fonts,
        // voice, taglines, bio, recommended block theme). Plan-gated via the
        // max_brand_kits quantity cap; can be applied to a biolink or QR code.
        Route::get   ('brand-kits',                              [\App\Modules\User\Controllers\BrandKitController::class, 'index'])->middleware('workspace.can:links.view')->name('brand-kits.index');
        Route::post  ('brand-kits/estimate',                     [\App\Modules\User\Controllers\BrandKitController::class, 'estimate'])->middleware(['workspace.can:links.create', 'throttle:30,1'])->name('brand-kits.estimate');
        Route::post  ('brand-kits/generate',                     [\App\Modules\User\Controllers\BrandKitController::class, 'generate'])->middleware(['workspace.can:links.create', 'throttle:10,1'])->name('brand-kits.generate');
        Route::post  ('brand-kits/defaults',                     [\App\Modules\User\Controllers\BrandKitController::class, 'saveDefaults'])->middleware('workspace.can:links.view')->name('brand-kits.defaults.save');
        Route::delete('brand-kits/defaults',                     [\App\Modules\User\Controllers\BrandKitController::class, 'clearDefaults'])->middleware('workspace.can:links.view')->name('brand-kits.defaults.clear');
        Route::delete('brand-kits/{brandKit}',                   [\App\Modules\User\Controllers\BrandKitController::class, 'destroy'])->middleware('workspace.can:links.create')->name('brand-kits.destroy');
        Route::post  ('brand-kits/{brandKit}/apply/biolink/{link}', [\App\Modules\User\Controllers\BrandKitController::class, 'applyToBiolink'])->middleware('workspace.can:links.edit')->name('brand-kits.apply.biolink');
        Route::post  ('brand-kits/{brandKit}/apply/qr/{qrCode}',    [\App\Modules\User\Controllers\BrandKitController::class, 'applyToQr'])->middleware('workspace.can:links.edit')->name('brand-kits.apply.qr');

        // Biolink blocks live under a link — same gating as the parent link.
        Route::get('links/{link}/blocks', [BiolinkBlockController::class, 'editor'])->middleware('workspace.can:links.view')->name('links.blocks.editor');
        Route::get('links/{link}/settings', [BiolinkBlockController::class, 'settings'])->middleware('workspace.can:links.view')->name('links.blocks.settings');
        Route::get('links/{link}/settings/appearance', [BiolinkBlockController::class, 'settingsAppearance'])->middleware('workspace.can:links.view')->name('links.settings.appearance');
        Route::get('links/{link}/settings/layout', [BiolinkBlockController::class, 'settingsLayout'])->middleware('workspace.can:links.view')->name('links.settings.layout');
        Route::get('links/{link}/settings/block-theme', [BiolinkBlockController::class, 'settingsBlockTheme'])->middleware('workspace.can:links.view')->name('links.settings.block-theme');
        Route::get('links/{link}/settings/advanced', [BiolinkBlockController::class, 'settingsAdvanced'])->middleware('workspace.can:links.view')->name('links.settings.advanced');
        Route::get('links/{link}/settings/embed', [BiolinkBlockController::class, 'settingsEmbed'])->middleware('workspace.can:links.view')->name('links.settings.embed');

        // ── Scheduled biolink themes (save current look + schedule it for a window) ──
        Route::get   ('links/{link}/themes',                          [\App\Modules\User\Controllers\BiolinkThemeController::class, 'settingsIndex'])->middleware('workspace.can:links.view')->name('links.themes.settings');
        Route::get   ('links/{link}/themes.json',                     [\App\Modules\User\Controllers\BiolinkThemeController::class, 'jsonIndex'])->middleware('workspace.can:links.view')->name('links.themes.json');
        Route::post  ('links/{link}/themes',                          [\App\Modules\User\Controllers\BiolinkThemeController::class, 'storeTheme'])->middleware('workspace.can:links.edit')->name('links.themes.store');
        Route::delete('links/{link}/themes/{theme}',                  [\App\Modules\User\Controllers\BiolinkThemeController::class, 'destroyTheme'])->middleware('workspace.can:links.edit')->name('links.themes.destroy');
        Route::post  ('links/{link}/themes/schedules',                [\App\Modules\User\Controllers\BiolinkThemeController::class, 'storeSchedule'])->middleware('workspace.can:links.edit')->name('links.themes.schedules.store');
        Route::patch ('links/{link}/themes/schedules/{schedule}',     [\App\Modules\User\Controllers\BiolinkThemeController::class, 'updateSchedule'])->middleware('workspace.can:links.edit')->name('links.themes.schedules.update');
        Route::post  ('links/{link}/themes/schedules/{schedule}/cancel', [\App\Modules\User\Controllers\BiolinkThemeController::class, 'cancelSchedule'])->middleware('workspace.can:links.edit')->name('links.themes.schedules.cancel');
        Route::post('links/{link}/blocks', [BiolinkBlockController::class, 'store'])->middleware('workspace.can:links.edit')->name('links.blocks.store');
        Route::put('links/{link}/blocks/{block}', [BiolinkBlockController::class, 'update'])->middleware('workspace.can:links.edit')->name('links.blocks.update');
        Route::get('links/{link}/blocks/{block}/edit-form', [BiolinkBlockController::class, 'editForm'])->middleware('workspace.can:links.view')->name('links.blocks.editForm');
        Route::delete('links/{link}/blocks/{block}', [BiolinkBlockController::class, 'destroy'])->middleware('workspace.can:links.edit')->name('links.blocks.destroy');
        Route::delete('links/{link}/blocks', [BiolinkBlockController::class, 'bulkDestroy'])->middleware('workspace.can:links.edit')->name('links.blocks.bulkDestroy');
        Route::post('links/{link}/blocks/reorder', [BiolinkBlockController::class, 'reorder'])->middleware('workspace.can:links.edit')->name('links.blocks.reorder');
        Route::post('links/{link}/blocks/{block}/toggle', [BiolinkBlockController::class, 'toggleActive'])->middleware('workspace.can:links.edit')->name('links.blocks.toggle');
        Route::post('links/{link}/blocks/{block}/move', [BiolinkBlockController::class, 'moveBlock'])->middleware('workspace.can:links.edit')->name('links.blocks.move');
        Route::post('links/{link}/blocks/{block}/apply-variant-to-all', [BiolinkBlockController::class, 'applyVariantToAll'])->middleware('workspace.can:links.edit')->name('links.blocks.applyVariantToAll');
        Route::post('links/{link}/blocks/{block}/apply-variant', [BiolinkBlockController::class, 'applyVariant'])->middleware('workspace.can:links.edit')->name('links.blocks.applyVariant');
        Route::post('links/{link}/blocks/{block}/restore-custom-style', [BiolinkBlockController::class, 'restoreCustomStyle'])->middleware('workspace.can:links.edit')->name('links.blocks.restoreCustomStyle');
        Route::post('links/{link}/blocks/{block}/reset-style', [BiolinkBlockController::class, 'resetStyle'])->middleware('workspace.can:links.edit')->name('links.blocks.resetStyle');
        Route::get('links/{link}/blocks/{block}/variant-previews', [BiolinkBlockController::class, 'variantPreviews'])->middleware('workspace.can:links.view')->name('links.blocks.variantPreviews');
        Route::post('links/{link}/page-settings', [BiolinkBlockController::class, 'updatePageSettings'])->middleware('workspace.can:links.edit')->name('links.page-settings');

        // Biolink layout A/B tests — start, stop, fetch live results.
        Route::post('links/{link}/experiment/start',  [\App\Modules\User\Controllers\BiolinkExperimentController::class, 'start'])->middleware('workspace.can:links.edit')->name('links.experiment.start');
        Route::post('links/{link}/experiment/stop',   [\App\Modules\User\Controllers\BiolinkExperimentController::class, 'stop'])->middleware('workspace.can:links.edit')->name('links.experiment.stop');
        Route::get ('links/{link}/experiment.json',   [\App\Modules\User\Controllers\BiolinkExperimentController::class, 'results'])->middleware('workspace.can:links.view')->name('links.experiment.results');

        // Adaptive Biolink (Task #3531) — per-segment bandit block ordering.
        // Mutually exclusive with the manual A/B test above.
        Route::post('links/{link}/experiment/adaptive/enable',  [\App\Modules\User\Controllers\BiolinkExperimentController::class, 'enableAdaptive'])->middleware('workspace.can:links.edit')->name('links.experiment.adaptive.enable');
        Route::post('links/{link}/experiment/adaptive/disable', [\App\Modules\User\Controllers\BiolinkExperimentController::class, 'disableAdaptive'])->middleware('workspace.can:links.edit')->name('links.experiment.adaptive.disable');
        Route::get ('links/{link}/experiment/adaptive/results.json', [\App\Modules\User\Controllers\BiolinkExperimentController::class, 'adaptiveResults'])->middleware('workspace.can:links.view')->name('links.experiment.adaptive.results');

        // Custom fonts (.woff/.woff2/.ttf/.otf) — surface in the "My Fonts"
        // section pinned at the top of every font picker. Lives at the user
        // level (not per-link) so a single upload powers every page.
        Route::get('custom-fonts',                [\App\Modules\User\Controllers\CustomFontController::class, 'index'])->name('custom-fonts.index');
        Route::post('custom-fonts',               [\App\Modules\User\Controllers\CustomFontController::class, 'store'])->name('custom-fonts.store');
        Route::delete('custom-fonts/{font}',      [\App\Modules\User\Controllers\CustomFontController::class, 'destroy'])->name('custom-fonts.destroy');
        Route::post('links/{link}/preview-draft', [BiolinkBlockController::class, 'previewDraft'])->middleware('workspace.can:links.edit')->name('links.preview-draft');

        // ── Conversational Biolink (chat-style guided flow) ─────────────
        Route::get   ('links/{link}/conversational',           [\App\Modules\User\Controllers\ConversationFlowController::class, 'editor'])->middleware('workspace.can:links.view')->name('links.conversational.editor');
        Route::post  ('links/{link}/conversational/toggle',    [\App\Modules\User\Controllers\ConversationFlowController::class, 'toggleMode'])->middleware('workspace.can:links.edit')->name('links.conversational.toggle');
        Route::post  ('links/{link}/conversational',           [\App\Modules\User\Controllers\ConversationFlowController::class, 'save'])->middleware('workspace.can:links.edit')->name('links.conversational.save');
        Route::get   ('links/{link}/conversational/analytics', [\App\Modules\User\Controllers\ConversationFlowController::class, 'analyticsPage'])->middleware('workspace.can:links.view')->name('links.conversational.analytics');
        Route::get   ('links/{link}/conversational/analytics.json', [\App\Modules\User\Controllers\ConversationFlowController::class, 'analytics'])->middleware('workspace.can:links.view')->name('links.conversational.analytics.json');

        // ── Slides Biolink (full-screen swipeable deck) ────────────────
        Route::get ('links/{link}/slides',        [\App\Modules\User\Controllers\SlideDeckController::class, 'editor'])->middleware('workspace.can:links.view')->name('links.slides.editor');
        Route::post('links/{link}/slides/toggle', [\App\Modules\User\Controllers\SlideDeckController::class, 'toggleMode'])->middleware('workspace.can:links.edit')->name('links.slides.toggle');
        Route::post('links/{link}/slides',        [\App\Modules\User\Controllers\SlideDeckController::class, 'save'])->middleware('workspace.can:links.edit')->name('links.slides.save');
        Route::get ('links/{link}/slides/analytics',      [\App\Modules\User\Controllers\SlideDeckController::class, 'analyticsPage'])->middleware('workspace.can:links.view')->name('links.slides.analytics');
        Route::get ('links/{link}/slides/analytics.json', [\App\Modules\User\Controllers\SlideDeckController::class, 'analytics'])->middleware('workspace.can:links.view')->name('links.slides.analytics.json');
        Route::get ('links/{link}/slides/analytics.csv',  [\App\Modules\User\Controllers\SlideDeckController::class, 'exportCsv'])->middleware('workspace.can:links.view')->name('links.slides.analytics.csv');

        // ── Full-page AI Chat (links.type = ai_chat) ───────────────────
        Route::get ('links/{link}/ai-chat', [\App\Modules\User\Controllers\AiChatController::class, 'editor'])->middleware('workspace.can:links.view')->name('links.ai-chat.editor');
        Route::post('links/{link}/ai-chat', [\App\Modules\User\Controllers\AiChatController::class, 'save'])->middleware('workspace.can:links.edit')->name('links.ai-chat.save');

        // ── Restaurant Menu (links.type = restaurant_menu) ─────────────
        Route::get ('links/{link}/restaurant',          [\App\Modules\User\Controllers\RestaurantMenuController::class, 'editor'])->middleware('workspace.can:links.view')->name('links.restaurant.editor');
        Route::post('links/{link}/restaurant/settings', [\App\Modules\User\Controllers\RestaurantMenuController::class, 'saveSettings'])->middleware('workspace.can:links.edit')->name('links.restaurant.settings');
        // Categories
        Route::post  ('links/{link}/restaurant/categories',                 [\App\Modules\User\Controllers\RestaurantMenuController::class, 'storeCategory'])->middleware('workspace.can:links.edit')->name('links.restaurant.categories.store');
        Route::put   ('links/{link}/restaurant/categories/{category}',      [\App\Modules\User\Controllers\RestaurantMenuController::class, 'updateCategory'])->middleware('workspace.can:links.edit')->name('links.restaurant.categories.update');
        Route::delete('links/{link}/restaurant/categories/{category}',      [\App\Modules\User\Controllers\RestaurantMenuController::class, 'destroyCategory'])->middleware('workspace.can:links.edit')->name('links.restaurant.categories.destroy');
        Route::post  ('links/{link}/restaurant/categories/reorder',         [\App\Modules\User\Controllers\RestaurantMenuController::class, 'reorderCategories'])->middleware('workspace.can:links.edit')->name('links.restaurant.categories.reorder');
        // Items
        Route::post  ('links/{link}/restaurant/items',         [\App\Modules\User\Controllers\RestaurantMenuController::class, 'storeItem'])->middleware('workspace.can:links.edit')->name('links.restaurant.items.store');
        Route::put   ('links/{link}/restaurant/items/{item}',  [\App\Modules\User\Controllers\RestaurantMenuController::class, 'updateItem'])->middleware('workspace.can:links.edit')->name('links.restaurant.items.update');
        Route::delete('links/{link}/restaurant/items/{item}',  [\App\Modules\User\Controllers\RestaurantMenuController::class, 'destroyItem'])->middleware('workspace.can:links.edit')->name('links.restaurant.items.destroy');
        Route::post  ('links/{link}/restaurant/items/reorder', [\App\Modules\User\Controllers\RestaurantMenuController::class, 'reorderItems'])->middleware('workspace.can:links.edit')->name('links.restaurant.items.reorder');
        // Coupons (owner-configurable discount codes)
        Route::post  ('links/{link}/restaurant/coupons',           [\App\Modules\User\Controllers\RestaurantMenuController::class, 'storeCoupon'])->middleware('workspace.can:links.edit')->name('links.restaurant.coupons.store');
        Route::put   ('links/{link}/restaurant/coupons/{coupon}',  [\App\Modules\User\Controllers\RestaurantMenuController::class, 'updateCoupon'])->middleware('workspace.can:links.edit')->name('links.restaurant.coupons.update');
        Route::delete('links/{link}/restaurant/coupons/{coupon}',  [\App\Modules\User\Controllers\RestaurantMenuController::class, 'destroyCoupon'])->middleware('workspace.can:links.edit')->name('links.restaurant.coupons.destroy');
        // Tables + per-table printable QR
        Route::post  ('links/{link}/restaurant/tables',             [\App\Modules\User\Controllers\RestaurantMenuController::class, 'storeTable'])->middleware('workspace.can:links.edit')->name('links.restaurant.tables.store');
        Route::delete('links/{link}/restaurant/tables/{table}',     [\App\Modules\User\Controllers\RestaurantMenuController::class, 'destroyTable'])->middleware('workspace.can:links.edit')->name('links.restaurant.tables.destroy');
        Route::get   ('links/{link}/restaurant/tables/{table}/qr',  [\App\Modules\User\Controllers\RestaurantMenuController::class, 'tableQr'])->middleware('workspace.can:links.view')->name('links.restaurant.tables.qr');
        Route::get   ('links/{link}/restaurant/tables-qr-sheet',     [\App\Modules\User\Controllers\RestaurantMenuController::class, 'tablesQrSheet'])->middleware('workspace.can:links.view')->name('links.restaurant.tables.qr-sheet');
        // Orders dashboard + near-real-time polling + status workflow
        Route::get ('links/{link}/restaurant/orders',                   [\App\Modules\User\Controllers\RestaurantMenuController::class, 'orders'])->middleware('workspace.can:links.view')->name('links.restaurant.orders');
        Route::get ('links/{link}/restaurant/orders/poll',              [\App\Modules\User\Controllers\RestaurantMenuController::class, 'pollOrders'])->middleware('workspace.can:links.view')->name('links.restaurant.orders.poll');
        Route::post('links/{link}/restaurant/orders/{order}/status',    [\App\Modules\User\Controllers\RestaurantMenuController::class, 'updateOrderStatus'])->middleware('workspace.can:links.edit')->name('links.restaurant.orders.status');

        // ── Store Menu (links.type = store_menu) ───────────────────────
        // Mirrors restaurant, minus coupons/tables/tax. Single store QR.
        Route::get ('links/{link}/store',          [\App\Modules\User\Controllers\StoreMenuController::class, 'editor'])->middleware('workspace.can:links.view')->name('links.store.editor');
        Route::post('links/{link}/store/settings', [\App\Modules\User\Controllers\StoreMenuController::class, 'saveSettings'])->middleware('workspace.can:links.edit')->name('links.store.settings');

        Route::post  ('links/{link}/store/categories',                 [\App\Modules\User\Controllers\StoreMenuController::class, 'storeCategory'])->middleware('workspace.can:links.edit')->name('links.store.categories.store');
        Route::put   ('links/{link}/store/categories/{category}',      [\App\Modules\User\Controllers\StoreMenuController::class, 'updateCategory'])->middleware('workspace.can:links.edit')->name('links.store.categories.update');
        Route::delete('links/{link}/store/categories/{category}',      [\App\Modules\User\Controllers\StoreMenuController::class, 'destroyCategory'])->middleware('workspace.can:links.edit')->name('links.store.categories.destroy');
        Route::post  ('links/{link}/store/categories/reorder',         [\App\Modules\User\Controllers\StoreMenuController::class, 'reorderCategories'])->middleware('workspace.can:links.edit')->name('links.store.categories.reorder');

        Route::post  ('links/{link}/store/products',            [\App\Modules\User\Controllers\StoreMenuController::class, 'storeProduct'])->middleware('workspace.can:links.edit')->name('links.store.products.store');
        Route::put   ('links/{link}/store/products/{product}',  [\App\Modules\User\Controllers\StoreMenuController::class, 'updateProduct'])->middleware('workspace.can:links.edit')->name('links.store.products.update');
        Route::delete('links/{link}/store/products/{product}',  [\App\Modules\User\Controllers\StoreMenuController::class, 'destroyProduct'])->middleware('workspace.can:links.edit')->name('links.store.products.destroy');
        Route::post  ('links/{link}/store/products/reorder',    [\App\Modules\User\Controllers\StoreMenuController::class, 'reorderProducts'])->middleware('workspace.can:links.edit')->name('links.store.products.reorder');

        Route::get   ('links/{link}/store/qr',  [\App\Modules\User\Controllers\StoreMenuController::class, 'storeQr'])->middleware('workspace.can:links.view')->name('links.store.qr');

        Route::get ('links/{link}/store/orders',                [\App\Modules\User\Controllers\StoreMenuController::class, 'orders'])->middleware('workspace.can:links.view')->name('links.store.orders');
        Route::get ('links/{link}/store/orders/poll',           [\App\Modules\User\Controllers\StoreMenuController::class, 'pollOrders'])->middleware('workspace.can:links.view')->name('links.store.orders.poll');
        Route::post('links/{link}/store/orders/{order}/status', [\App\Modules\User\Controllers\StoreMenuController::class, 'updateOrderStatus'])->middleware('workspace.can:links.edit')->name('links.store.orders.status');
        // ── Service Booking (links.type = service_booking) ─────────────
        Route::get ('links/{link}/service-booking',          [\App\Modules\User\Controllers\ServiceBookingController::class, 'editor'])->middleware('workspace.can:links.view')->name('links.service-booking.editor');
        Route::post('links/{link}/service-booking/settings', [\App\Modules\User\Controllers\ServiceBookingController::class, 'saveSettings'])->middleware('workspace.can:links.edit')->name('links.service-booking.settings');
        // Categories
        Route::post  ('links/{link}/service-booking/categories',            [\App\Modules\User\Controllers\ServiceBookingController::class, 'storeCategory'])->middleware('workspace.can:links.edit')->name('links.service-booking.categories.store');
        Route::put   ('links/{link}/service-booking/categories/{category}', [\App\Modules\User\Controllers\ServiceBookingController::class, 'updateCategory'])->middleware('workspace.can:links.edit')->name('links.service-booking.categories.update');
        Route::delete('links/{link}/service-booking/categories/{category}', [\App\Modules\User\Controllers\ServiceBookingController::class, 'destroyCategory'])->middleware('workspace.can:links.edit')->name('links.service-booking.categories.destroy');
        Route::post  ('links/{link}/service-booking/categories/reorder',    [\App\Modules\User\Controllers\ServiceBookingController::class, 'reorderCategories'])->middleware('workspace.can:links.edit')->name('links.service-booking.categories.reorder');
        // Services
        Route::post  ('links/{link}/service-booking/services',            [\App\Modules\User\Controllers\ServiceBookingController::class, 'storeService'])->middleware('workspace.can:links.edit')->name('links.service-booking.services.store');
        Route::put   ('links/{link}/service-booking/services/{service}',  [\App\Modules\User\Controllers\ServiceBookingController::class, 'updateService'])->middleware('workspace.can:links.edit')->name('links.service-booking.services.update');
        Route::delete('links/{link}/service-booking/services/{service}',  [\App\Modules\User\Controllers\ServiceBookingController::class, 'destroyService'])->middleware('workspace.can:links.edit')->name('links.service-booking.services.destroy');
        Route::post  ('links/{link}/service-booking/services/reorder',    [\App\Modules\User\Controllers\ServiceBookingController::class, 'reorderServices'])->middleware('workspace.can:links.edit')->name('links.service-booking.services.reorder');
        // Weekly availability rules
        Route::post  ('links/{link}/service-booking/availability',         [\App\Modules\User\Controllers\ServiceBookingController::class, 'storeAvailability'])->middleware('workspace.can:links.edit')->name('links.service-booking.availability.store');
        Route::put   ('links/{link}/service-booking/availability/{rule}',  [\App\Modules\User\Controllers\ServiceBookingController::class, 'updateAvailability'])->middleware('workspace.can:links.edit')->name('links.service-booking.availability.update');
        Route::delete('links/{link}/service-booking/availability/{rule}',  [\App\Modules\User\Controllers\ServiceBookingController::class, 'destroyAvailability'])->middleware('workspace.can:links.edit')->name('links.service-booking.availability.destroy');
        // Blocked dates
        Route::post  ('links/{link}/service-booking/blocked-dates',                 [\App\Modules\User\Controllers\ServiceBookingController::class, 'storeBlockedDate'])->middleware('workspace.can:links.edit')->name('links.service-booking.blocked-dates.store');
        Route::delete('links/{link}/service-booking/blocked-dates/{blockedDate}',   [\App\Modules\User\Controllers\ServiceBookingController::class, 'destroyBlockedDate'])->middleware('workspace.can:links.edit')->name('links.service-booking.blocked-dates.destroy');
        // Bookings dashboard + near-real-time polling + status workflow
        Route::get ('links/{link}/service-booking/bookings',                 [\App\Modules\User\Controllers\ServiceBookingController::class, 'bookings'])->middleware('workspace.can:links.view')->name('links.service-booking.bookings');
        Route::get ('links/{link}/service-booking/bookings/poll',            [\App\Modules\User\Controllers\ServiceBookingController::class, 'pollBookings'])->middleware('workspace.can:links.view')->name('links.service-booking.bookings.poll');
        Route::post('links/{link}/service-booking/bookings/{booking}/status',[\App\Modules\User\Controllers\ServiceBookingController::class, 'updateBookingStatus'])->middleware('workspace.can:links.edit')->name('links.service-booking.bookings.status');

        // Plan upgrade, checkout & billing — these touch the workspace
        // owner's subscription/wallet/invoices, so they remain owner-only
        // regardless of any member's role inside the workspace.
        Route::get('upgrade', [\App\Modules\User\Controllers\UpgradeController::class, 'show'])->middleware('workspace.owner')->name('upgrade');
        Route::post('upgrade/switch-currency', [\App\Modules\User\Controllers\UpgradeController::class, 'switchCurrency'])->middleware('workspace.owner')->name('upgrade.switch-currency');
        Route::post('upgrade/activate', [\App\Modules\User\Controllers\UpgradeController::class, 'activate'])->middleware('workspace.owner')->name('upgrade.activate');

        // Checkout: plan+addons cart, tax preview, gateway picker, handoff.
        Route::get('checkout', [\App\Modules\User\Controllers\CheckoutController::class, 'show'])->middleware('workspace.owner')->name('checkout.show');
        Route::post('checkout/handoff', [\App\Modules\User\Controllers\CheckoutController::class, 'handoff'])->middleware('workspace.owner')->name('checkout.handoff');
        // Offline (manual bank/UPI) instructions page + buyer-submitted UPI reference.
        Route::get('checkout/offline/{invoice}', [\App\Modules\User\Controllers\CheckoutController::class, 'offline'])->middleware('workspace.owner')->name('checkout.offline');
        Route::post('checkout/offline/{invoice}/reference', [\App\Modules\User\Controllers\CheckoutController::class, 'offlineReference'])->middleware('workspace.owner')->name('checkout.offline.reference');

        // Billing dashboard (subscription lifecycle, invoices, refunds, credit notes).
        Route::get('billing', [\App\Modules\User\Controllers\BillingController::class, 'show'])->middleware('workspace.owner')->name('billing.show');
        Route::get('billing/upgrade', [\App\Modules\User\Controllers\BillingController::class, 'upgrade'])->middleware('workspace.owner')->name('billing.upgrade');
        Route::post('billing/upgrade/confirm', [\App\Modules\User\Controllers\BillingController::class, 'upgradeConfirm'])->middleware('workspace.owner')->name('billing.upgrade.confirm');
        Route::post('billing/upgrade/handoff', [\App\Modules\User\Controllers\BillingController::class, 'upgradeHandoff'])->middleware('workspace.owner')->name('billing.upgrade.handoff');
        Route::get('billing/downgrade', [\App\Modules\User\Controllers\BillingController::class, 'downgrade'])->middleware('workspace.owner')->name('billing.downgrade');
        Route::post('billing/downgrade/schedule', [\App\Modules\User\Controllers\BillingController::class, 'scheduleDowngrade'])->middleware('workspace.owner')->name('billing.downgrade.schedule');
        Route::post('billing/downgrade/cancel', [\App\Modules\User\Controllers\BillingController::class, 'cancelDowngrade'])->middleware('workspace.owner')->name('billing.downgrade.cancel');
        Route::post('billing/cancel', [\App\Modules\User\Controllers\BillingController::class, 'cancel'])->middleware('workspace.owner')->name('billing.cancel');
        Route::post('billing/resume', [\App\Modules\User\Controllers\BillingController::class, 'resume'])->middleware('workspace.owner')->name('billing.resume');
        Route::post('billing/invoices/{invoice}/refund', [\App\Modules\User\Controllers\BillingController::class, 'refundInvoice'])->middleware('workspace.owner')->name('billing.refund');
        Route::get('billing/credit-notes/{creditNote}.pdf', [\App\Modules\User\Controllers\BillingController::class, 'creditNotePdf'])->middleware('workspace.owner')->name('billing.credit-note.pdf');

        // Wallet & coins (customer-facing).
        Route::get ('wallet',                [\App\Modules\User\Controllers\WalletController::class, 'show'])->name('wallet.show');
        Route::get ('wallet/balance',        [\App\Modules\User\Controllers\WalletController::class, 'balance'])->name('wallet.balance');
        Route::get ('wallet/transactions',   [\App\Modules\User\Controllers\WalletController::class, 'transactions'])->name('wallet.transactions');
        Route::get ('wallet/buy',            [\App\Modules\User\Controllers\WalletController::class, 'buy'])->name('wallet.buy');
        Route::post('wallet/buy',            [\App\Modules\User\Controllers\WalletController::class, 'buyHandoff'])->name('wallet.buy.handoff');
        Route::post('addons/{addon}/activate-with-coins', [\App\Modules\User\Controllers\WalletController::class, 'activateAddon'])->name('addons.activate-with-coins');

        // ---- AI features (charge coins from the wallet via OpenAiService) ----
        // Each feature charges through OpenAiService::chat() with a
        // unique `feature` tag so admin reporting can attribute spend
        // back to the right product on /admin/ai-usage.
        Route::prefix('ai')->name('ai.')->group(function () {

            // Unified, read-only coin-cost estimate for AI triggers that don't
            // own a feature-specific estimate route (persona, ask-coach, card
            // scan, QR art, voice, minds, resume import/cover-letter).
            Route::post('cost-estimate', [\App\Modules\User\Controllers\AI\AiCostEstimateController::class, 'estimate'])->middleware('throttle:60,1')->name('cost-estimate');
            Route::get ('mind',        [\App\Modules\User\Controllers\AI\MindController::class, 'show'])->name('mind.show');
            Route::post('mind/think',  [\App\Modules\User\Controllers\AI\MindController::class, 'think'])->middleware('throttle:30,1')->name('mind.think');

            Route::get ('persona',           [\App\Modules\User\Controllers\AI\PersonaController::class, 'show'])->name('persona.show');
            Route::post('persona/generate',  [\App\Modules\User\Controllers\AI\PersonaController::class, 'generate'])->middleware('throttle:30,1')->name('persona.generate');
            Route::post('persona/save',      [\App\Modules\User\Controllers\AI\PersonaController::class, 'save'])->name('persona.save');
            Route::patch('personas/{persona}',  [\App\Modules\User\Controllers\AI\PersonaController::class, 'update'])->name('persona.update');
            Route::delete('personas/{persona}', [\App\Modules\User\Controllers\AI\PersonaController::class, 'destroy'])->name('persona.destroy');
            Route::post  ('persona/defaults',   [\App\Modules\User\Controllers\AI\PersonaController::class, 'saveDefaults'])->name('persona.defaults.save');
            Route::delete('persona/defaults',   [\App\Modules\User\Controllers\AI\PersonaController::class, 'clearDefaults'])->name('persona.defaults.clear');

            Route::get   ('companion',                       [\App\Modules\User\Controllers\AI\CompanionController::class, 'show'])->name('companion.show');
            Route::post  ('companion',                       [\App\Modules\User\Controllers\AI\CompanionController::class, 'store'])->name('companion.store');
            Route::post  ('companion/defaults',              [\App\Modules\User\Controllers\AI\CompanionController::class, 'saveDefaults'])->name('companion.defaults.save');
            Route::delete('companion/defaults',              [\App\Modules\User\Controllers\AI\CompanionController::class, 'clearDefaults'])->name('companion.defaults.clear');
            Route::get   ('companion/{thread}',              [\App\Modules\User\Controllers\AI\CompanionController::class, 'show'])->whereNumber('thread')->name('companion.thread');
            Route::post  ('companion/{thread}/send',         [\App\Modules\User\Controllers\AI\CompanionController::class, 'send'])->whereNumber('thread')->middleware('throttle:60,1')->name('companion.send');
            Route::post  ('companion/{thread}/rename',       [\App\Modules\User\Controllers\AI\CompanionController::class, 'rename'])->whereNumber('thread')->name('companion.rename');
            Route::get   ('companion/{thread}/export',       [\App\Modules\User\Controllers\AI\CompanionController::class, 'export'])->whereNumber('thread')->name('companion.export');
            Route::delete('companion/{thread}',              [\App\Modules\User\Controllers\AI\CompanionController::class, 'destroy'])->whereNumber('thread')->name('companion.destroy');

            Route::get ('coach',         [\App\Modules\User\Controllers\AI\CoachController::class, 'show'])->name('coach.show');
            Route::post('coach/suggest', [\App\Modules\User\Controllers\AI\CoachController::class, 'suggest'])->middleware('throttle:30,1')->name('coach.suggest');
            Route::post  ('coach/defaults', [\App\Modules\User\Controllers\AI\CoachController::class, 'saveDefaults'])->name('coach.defaults.save');
            Route::delete('coach/defaults', [\App\Modules\User\Controllers\AI\CoachController::class, 'clearDefaults'])->name('coach.defaults.clear');

            // Ask Coach — multi-turn data-aware self-support chatbot
            // (separate from the per-link Coach above; spend tagged
            //  `ask_coach.chat` for admin reporting).
            Route::get   ('ask-coach',                              [\App\Modules\User\Controllers\AI\AskCoachController::class, 'show'])->name('ask-coach.show');
            Route::post  ('ask-coach',                              [\App\Modules\User\Controllers\AI\AskCoachController::class, 'store'])->name('ask-coach.store');
            Route::get   ('ask-coach/{thread}',                     [\App\Modules\User\Controllers\AI\AskCoachController::class, 'show'])->whereNumber('thread')->name('ask-coach.thread');
            Route::post  ('ask-coach/{thread}/send',                [\App\Modules\User\Controllers\AI\AskCoachController::class, 'send'])->whereNumber('thread')->middleware('throttle:30,1')->name('ask-coach.send');
            Route::post  ('ask-coach/{thread}/rename',              [\App\Modules\User\Controllers\AI\AskCoachController::class, 'rename'])->whereNumber('thread')->name('ask-coach.rename');
            Route::get   ('ask-coach/{thread}/export',              [\App\Modules\User\Controllers\AI\AskCoachController::class, 'export'])->whereNumber('thread')->name('ask-coach.export');
            Route::delete('ask-coach/{thread}',                     [\App\Modules\User\Controllers\AI\AskCoachController::class, 'destroy'])->whereNumber('thread')->name('ask-coach.destroy');
            Route::post  ('ask-coach/messages/{message}/feedback',  [\App\Modules\User\Controllers\AI\AskCoachController::class, 'feedback'])->whereNumber('message')->middleware('throttle:30,1')->name('ask-coach.feedback');
            Route::post  ('ask-coach/defaults',                     [\App\Modules\User\Controllers\AI\AskCoachController::class, 'saveDefaults'])->name('ask-coach.defaults.save');
            Route::delete('ask-coach/defaults',                     [\App\Modules\User\Controllers\AI\AskCoachController::class, 'clearDefaults'])->name('ask-coach.defaults.clear');

            // Marketing Strategist (Task #3060) — AI Digital Performer.
            // Creator toggles their OWN data → goal + parameters → an
            // organic + paid strategy built around Sayzio features, then
            // chat-refine (streamed, metered) + one-click apply suggestions.
            // Spend tagged `marketing_strategist` with auto-refund.
            Route::get   ('marketing-strategist',                            [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'index'])->name('marketing-strategist.index');
            Route::get   ('marketing-strategist/create',                     [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'create'])->name('marketing-strategist.create');
            Route::post  ('marketing-strategist/estimate',                   [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'estimate'])->middleware('throttle:30,1')->name('marketing-strategist.estimate');
            Route::post  ('marketing-strategist',                            [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'store'])->middleware('throttle:12,1')->name('marketing-strategist.store');
            Route::get   ('marketing-strategist/{strategy}',                 [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'show'])->whereNumber('strategy')->name('marketing-strategist.show');
            Route::delete('marketing-strategist/{strategy}',                 [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'destroy'])->whereNumber('strategy')->name('marketing-strategist.destroy');
            Route::get   ('marketing-strategist/sample',                     [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'sample'])->name('marketing-strategist.sample');
            Route::get   ('marketing-strategist/profile',                    [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'profile'])->name('marketing-strategist.profile');
            Route::get   ('marketing-strategist/projects',                   [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'projectsIndex'])->name('marketing-strategist.projects.index');
            Route::get   ('marketing-strategist/projects/create',            [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'projectCreate'])->name('marketing-strategist.projects.create');
            Route::post  ('marketing-strategist/projects',                   [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'projectStore'])->name('marketing-strategist.projects.store');
            Route::get   ('marketing-strategist/projects/{project}/edit',    [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'projectEdit'])->whereNumber('project')->name('marketing-strategist.projects.edit');
            Route::put   ('marketing-strategist/projects/{project}',         [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'projectUpdate'])->whereNumber('project')->name('marketing-strategist.projects.update');
            Route::delete('marketing-strategist/projects/{project}',         [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'projectDestroy'])->whereNumber('project')->name('marketing-strategist.projects.destroy');
            Route::get   ('marketing-strategist/{strategy}/export',          [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'export'])->whereNumber('strategy')->name('marketing-strategist.export');
            Route::post  ('marketing-strategist/{strategy}/report',          [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'report'])->whereNumber('strategy')->middleware('throttle:12,1')->name('marketing-strategist.report');
            Route::post  ('marketing-strategist/{strategy}/rescore',         [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'rescore'])->whereNumber('strategy')->middleware('throttle:30,1')->name('marketing-strategist.rescore');
            Route::post  ('marketing-strategist/{strategy}/outcome',         [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'refreshOutcome'])->whereNumber('strategy')->middleware('throttle:30,1')->name('marketing-strategist.outcome');
            Route::post  ('marketing-strategist/{strategy}/share',           [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'share'])->whereNumber('strategy')->name('marketing-strategist.share');
            Route::delete('marketing-strategist/{strategy}/share',           [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'unshare'])->whereNumber('strategy')->name('marketing-strategist.unshare');
            Route::post  ('marketing-strategist/{strategy}/chat',            [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'chat'])->whereNumber('strategy')->middleware('throttle:30,1')->name('marketing-strategist.chat');
            Route::post  ('marketing-strategist/suggestions/{suggestion}/apply',   [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'applySuggestion'])->whereNumber('suggestion')->middleware('throttle:30,1')->name('marketing-strategist.suggestions.apply');
            Route::post  ('marketing-strategist/suggestions/{suggestion}/dismiss', [\App\Modules\User\Controllers\AI\MarketingStrategistController::class, 'dismissSuggestion'])->whereNumber('suggestion')->name('marketing-strategist.suggestions.dismiss');

            // AI Staff (Task #3523) — configurable AI agents across billing,
            // contacts, inbox and general domains. Confirm-before-act
            // suggestions mirror the Marketing Strategist pattern above.
            Route::get   ('staff',                                [\App\Modules\User\Controllers\AI\AiStaffController::class, 'index'])->name('staff.index');
            Route::post  ('staff',                                [\App\Modules\User\Controllers\AI\AiStaffController::class, 'store'])->middleware('throttle:20,1')->name('staff.store');
            Route::get   ('staff/{staff}',                        [\App\Modules\User\Controllers\AI\AiStaffController::class, 'show'])->whereNumber('staff')->name('staff.show');
            Route::put   ('staff/{staff}',                        [\App\Modules\User\Controllers\AI\AiStaffController::class, 'update'])->whereNumber('staff')->name('staff.update');
            Route::delete('staff/{staff}',                        [\App\Modules\User\Controllers\AI\AiStaffController::class, 'destroy'])->whereNumber('staff')->name('staff.destroy');
            Route::post  ('staff/{staff}/chat',                   [\App\Modules\User\Controllers\AI\AiStaffController::class, 'chat'])->whereNumber('staff')->middleware('throttle:30,1')->name('staff.chat');
            Route::post  ('staff/{staff}/draft-invoice',          [\App\Modules\User\Controllers\AI\AiStaffController::class, 'draftInvoice'])->whereNumber('staff')->middleware('throttle:15,1')->name('staff.draft-invoice');
            Route::post  ('staff/{staff}/chase-suggestions',      [\App\Modules\User\Controllers\AI\AiStaffController::class, 'generateChaseSuggestions'])->whereNumber('staff')->middleware('throttle:15,1')->name('staff.chase-suggestions');
            Route::post  ('staff/{staff}/contacts/{contact}/summarize',    [\App\Modules\User\Controllers\AI\AiStaffController::class, 'summarizeContact'])->whereNumber(['staff', 'contact'])->middleware('throttle:30,1')->name('staff.contacts.summarize');
            Route::post  ('staff/{staff}/contacts/{contact}/draft-followup', [\App\Modules\User\Controllers\AI\AiStaffController::class, 'draftFollowup'])->whereNumber(['staff', 'contact'])->middleware('throttle:30,1')->name('staff.contacts.draft-followup');
            Route::post  ('staff/suggestions/{suggestion}/apply',   [\App\Modules\User\Controllers\AI\AiStaffController::class, 'applySuggestion'])->whereNumber('suggestion')->middleware('throttle:30,1')->name('staff.suggestions.apply');
            Route::post  ('staff/suggestions/{suggestion}/dismiss', [\App\Modules\User\Controllers\AI\AiStaffController::class, 'dismissSuggestion'])->whereNumber('suggestion')->name('staff.suggestions.dismiss');

            // Voice Assistant — floating mic on every page. STT/LLM/TTS
            // are billed separately (`voice_stt`, `voice_llm`, `voice_tts`).
            // `voice` is the gate surface: engine-off / plan-gated users
            // land on the shared self-serve upgrade page instead of a
            // silent widget or a bare 403; allowed users bounce to the
            // dashboard where the mic is already live.
            Route::get ('voice',              [\App\Modules\User\Controllers\AI\VoiceAssistantController::class, 'show'])->name('voice.show');
            Route::get ('voice/capabilities', [\App\Modules\User\Controllers\AI\VoiceAssistantController::class, 'capabilities'])->name('voice.capabilities');
            Route::post('voice/turn',         [\App\Modules\User\Controllers\AI\VoiceAssistantController::class, 'turn'])->middleware('throttle:30,1')->name('voice.turn');
            Route::post('voice/transcribe',   [\App\Modules\User\Controllers\AI\VoiceAssistantController::class, 'transcribe'])->middleware('throttle:30,1')->name('voice.transcribe');
        });

        // AI Minds — labelled knowledge bases (text/docs/FAQs/links/
        // Sayzio features) every AI Persona / Coach can draw on. Note:
        // distinct from the stateless `ai/mind` summary tool above.
        Route::prefix('minds')->name('minds.')->group(function () {
            Route::get ('/',                   [\App\Modules\User\Controllers\MindController::class, 'index'])->name('index');
            Route::get ('create',              [\App\Modules\User\Controllers\MindController::class, 'create'])->middleware(CheckPlanLimit::class . ':ai_minds')->name('create');
            Route::post('/',                   [\App\Modules\User\Controllers\MindController::class, 'store'])->middleware(CheckPlanLimit::class . ':ai_minds')->name('store');
            Route::get ('{mind}',              [\App\Modules\User\Controllers\MindController::class, 'edit'])->whereNumber('mind')->name('edit');
            Route::put ('{mind}',              [\App\Modules\User\Controllers\MindController::class, 'update'])->whereNumber('mind')->name('update');
            Route::delete('{mind}',            [\App\Modules\User\Controllers\MindController::class, 'destroy'])->whereNumber('mind')->name('destroy');
            Route::post('{mind}/refresh',      [\App\Modules\User\Controllers\MindController::class, 'refresh'])->whereNumber('mind')->name('refresh');
            // Sources
            Route::get ('{mind}/sources/{source}', [\App\Modules\User\Controllers\MindSourceController::class, 'show'])->whereNumber('mind')->whereNumber('source')->name('sources.show');
            Route::post('{mind}/sources',      [\App\Modules\User\Controllers\MindSourceController::class, 'store'])->whereNumber('mind')->name('sources.store');
            Route::post('{mind}/sources/{source}/refresh', [\App\Modules\User\Controllers\MindSourceController::class, 'refresh'])->whereNumber('mind')->whereNumber('source')->name('sources.refresh');
            Route::post('{mind}/sources/{source}/rotate-webhook', [\App\Modules\User\Controllers\MindSourceController::class, 'rotateWebhook'])->whereNumber('mind')->whereNumber('source')->name('sources.rotate-webhook');
            Route::delete('{mind}/sources/{source}', [\App\Modules\User\Controllers\MindSourceController::class, 'destroy'])->whereNumber('mind')->whereNumber('source')->name('sources.destroy');
            // Test chat — AJAX in-page panel.
            Route::post('{mind}/ask',          [\App\Modules\User\Controllers\MindChatController::class, 'ask'])->whereNumber('mind')->middleware('throttle:20,1')->name('ask');
            // Sharing into teams / badge groups (owner only).
            Route::post  ('{mind}/shares',          [\App\Modules\User\Controllers\AiResourceShareController::class, 'storeMind'])->whereNumber('mind')->name('shares.store');
            Route::delete('{mind}/shares/{share}',  [\App\Modules\User\Controllers\AiResourceShareController::class, 'destroyMind'])->whereNumber('mind')->whereNumber('share')->name('shares.destroy');
        });

        // AI Personas — configurable conversational agents that the
        // user later wires into widgets / inbox / Coach. Each save
        // writes a new ai_persona_versions row and can be rolled back.
        Route::prefix('ai-personas')->name('ai-personas.')->group(function () {
            Route::get   ('/',                 [\App\Modules\User\Controllers\AI\PersonasController::class, 'index'])->name('index');
            Route::get   ('create',            [\App\Modules\User\Controllers\AI\PersonasController::class, 'create'])->middleware(CheckPlanLimit::class . ':ai_personas')->name('create');
            Route::post  ('/',                 [\App\Modules\User\Controllers\AI\PersonasController::class, 'store'])->middleware(CheckPlanLimit::class . ':ai_personas')->name('store');
            Route::get   ('{persona}',         [\App\Modules\User\Controllers\AI\PersonasController::class, 'edit'])->whereNumber('persona')->name('edit');
            Route::put   ('{persona}',         [\App\Modules\User\Controllers\AI\PersonasController::class, 'update'])->whereNumber('persona')->name('update');
            Route::delete('{persona}',         [\App\Modules\User\Controllers\AI\PersonasController::class, 'destroy'])->whereNumber('persona')->name('destroy');
            Route::post  ('{persona}/duplicate',[\App\Modules\User\Controllers\AI\PersonasController::class, 'duplicate'])->whereNumber('persona')->middleware(CheckPlanLimit::class . ':ai_personas')->name('duplicate');
            Route::post  ('{persona}/versions/{version}/rollback', [\App\Modules\User\Controllers\AI\PersonasController::class, 'rollback'])->whereNumber('persona')->whereNumber('version')->name('rollback');
            Route::post  ('{persona}/test',    [\App\Modules\User\Controllers\AI\PersonasController::class, 'test'])->whereNumber('persona')->middleware('throttle:20,1')->name('test');
            // Sharing into teams / badge groups (owner only).
            Route::post  ('{persona}/shares',         [\App\Modules\User\Controllers\AiResourceShareController::class, 'storePersona'])->whereNumber('persona')->name('shares.store');
            Route::delete('{persona}/shares/{share}', [\App\Modules\User\Controllers\AiResourceShareController::class, 'destroyPersona'])->whereNumber('persona')->whereNumber('share')->name('shares.destroy');
        });

        // AI Companions — placement-bound chatbots that bind a Persona
        // to a biolink block, an external embed snippet, or the inbox
        // auto-reply bot. CRUD + conversation browser + analytics.
        Route::prefix('ai-companions')->name('ai-companions.')->group(function () {
            Route::get   ('/',                            [\App\Modules\User\Controllers\AI\CompanionsController::class, 'index'])->name('index');
            Route::get   ('create',                       [\App\Modules\User\Controllers\AI\CompanionsController::class, 'create'])->middleware(CheckPlanLimit::class . ':ai_companions')->name('create');
            Route::post  ('/',                            [\App\Modules\User\Controllers\AI\CompanionsController::class, 'store'])->middleware(CheckPlanLimit::class . ':ai_companions')->name('store');
            Route::get   ('{companion}',                  [\App\Modules\User\Controllers\AI\CompanionsController::class, 'edit'])->whereNumber('companion')->name('edit');
            Route::put   ('{companion}',                  [\App\Modules\User\Controllers\AI\CompanionsController::class, 'update'])->whereNumber('companion')->name('update');
            Route::delete('{companion}',                  [\App\Modules\User\Controllers\AI\CompanionsController::class, 'destroy'])->whereNumber('companion')->name('destroy');
            Route::get   ('{companion}/conversations',    [\App\Modules\User\Controllers\AI\CompanionsController::class, 'conversations'])->whereNumber('companion')->name('conversations');
            Route::get   ('{companion}/conversations/{conversation}', [\App\Modules\User\Controllers\AI\CompanionsController::class, 'conversation'])->whereNumber('companion')->whereNumber('conversation')->name('conversation');
        });

        // Page & card templates (admin-curated presets) — picker reads
        // require links.view; apply mutates the link so requires links.edit.
        Route::get('links/{link}/templates', [\App\Modules\User\Controllers\LinkTemplateController::class, 'picker'])->middleware('workspace.can:links.view')->name('links.templates.picker');
        Route::post('links/{link}/templates/apply-page', [\App\Modules\User\Controllers\LinkTemplateController::class, 'applyPage'])->middleware('workspace.can:links.edit')->name('links.templates.apply-page');
        Route::get('links/{link}/templates/cards', [\App\Modules\User\Controllers\LinkTemplateController::class, 'cardGallery'])->middleware('workspace.can:links.view')->name('links.templates.cards');
        Route::post('links/{link}/templates/apply-card', [\App\Modules\User\Controllers\LinkTemplateController::class, 'applyCard'])->middleware('workspace.can:links.edit')->name('links.templates.apply-card');

        // Standalone splash pages — reusable across multiple links. Read
        // under links.view, mutate under links.edit.
        // NOTE: write-side resource (which includes `create`/`edit`) must be
        // registered BEFORE the read-side resource so the literal
        // `splash-pages/create` path is matched ahead of the parameterized
        // `splash-pages/{splash_page}` show route. Otherwise route-model
        // binding tries to load a SplashPage with id="create" and Postgres
        // 500s on the bigint cast.
        Route::resource('splash-pages', \App\Modules\User\Controllers\SplashPageController::class)->except(['index', 'show', 'store'])->middleware('workspace.can:links.edit');
        Route::post('splash-pages', [\App\Modules\User\Controllers\SplashPageController::class, 'store'])->middleware(['workspace.can:links.edit', CheckPlanLimit::class . ':splash_pages'])->name('splash-pages.store');
        Route::resource('splash-pages', \App\Modules\User\Controllers\SplashPageController::class)->only(['index', 'show'])->middleware('workspace.can:links.view');
        Route::get('splash-pages/{splash_page}/preview', [\App\Modules\User\Controllers\SplashPageController::class, 'preview'])->middleware('workspace.can:links.view')->name('splash-pages.preview');

        // Reusable third-party integration configurations (payment / sms / email)
        // and connected social accounts — workspace-level settings, gated under
        // the `settings` feature.
        Route::get('settings/connections',                     [\App\Modules\User\Controllers\SocialAccountController::class, 'index'])->middleware('workspace.can:settings.view')->name('social-accounts.index');
        Route::post('social-accounts',                         [\App\Modules\User\Controllers\SocialAccountController::class, 'store'])->middleware('workspace.can:settings.edit')->name('social-accounts.store');
        Route::post('social-accounts/{connection}/refresh',    [\App\Modules\User\Controllers\SocialAccountController::class, 'refresh'])->middleware('workspace.can:settings.edit')->name('social-accounts.refresh');
        Route::delete('social-accounts/{connection}',          [\App\Modules\User\Controllers\SocialAccountController::class, 'destroy'])->middleware('workspace.can:settings.edit')->name('social-accounts.destroy');
        Route::post('social-accounts/broken-emails/preference', [\App\Modules\User\Controllers\SocialAccountController::class, 'updateBrokenEmailPreference'])->middleware('workspace.can:settings.edit')->name('social-accounts.broken-emails.preference');

        // OAuth connect / callback for providers that need a per-user token.
        // Each provider activates only when its CLIENT_ID + CLIENT_SECRET env
        // vars are set; otherwise the UI falls back to manual token paste.
        Route::get('social-oauth/{provider}/connect',  [\App\Modules\User\Controllers\SocialOAuthController::class, 'connect'])->middleware('workspace.can:settings.edit')->name('social-oauth.connect');
        Route::get('social-oauth/{provider}/merge',    [\App\Modules\User\Controllers\SocialOAuthController::class, 'mergeConnect'])->middleware('workspace.can:settings.edit')->name('social-oauth.merge');

        // Inline "merge accounts?" offer raised when a Connect flow finds the
        // provider identity already bound to a different account. Accept jumps
        // straight to the merge preview (the OAuth round-trip already proved
        // ownership); decline dismisses the offer.
        Route::post('social-oauth/merge-offer/accept',  [\App\Modules\User\Controllers\SocialOAuthController::class, 'acceptMergeOffer'])->middleware('workspace.can:settings.edit')->name('social-oauth.merge-offer.accept');
        Route::post('social-oauth/merge-offer/decline', [\App\Modules\User\Controllers\SocialOAuthController::class, 'declineMergeOffer'])->middleware('workspace.can:settings.edit')->name('social-oauth.merge-offer.decline');

        // Linked identifiers (multi-identity account settings).
        Route::prefix('identifiers')->name('identifiers.')->middleware('workspace.can:settings.view')->group(function () {
            Route::get('/',                                [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'index'])->name('index');
            Route::post('start',                           [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'start'])->middleware('throttle:5,1')->name('start');
            Route::post('confirm',                         [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'confirm'])->middleware('throttle:10,1')->name('confirm');
            Route::delete('{identifier}',                  [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'destroy'])->name('destroy');
            Route::post('{identifier}/promote',            [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'promote'])->name('promote');
        });

        // Account merge flow.
        Route::prefix('settings/security/merge')->name('merge.')->middleware('workspace.can:settings.edit')->group(function () {
            Route::get('/',           [\App\Modules\User\Controllers\AccountMergeController::class, 'start'])->name('start');
            Route::post('challenge',  [\App\Modules\User\Controllers\AccountMergeController::class, 'challenge'])->middleware('throttle:5,1')->name('challenge');
            Route::get('preview',     [\App\Modules\User\Controllers\AccountMergeController::class, 'preview'])->name('preview');
            Route::post('confirm',    [\App\Modules\User\Controllers\AccountMergeController::class, 'confirm'])->name('confirm');
            Route::post('cancel',     [\App\Modules\User\Controllers\AccountMergeController::class, 'cancel'])->name('cancel');
        });

        Route::get('settings/integrations',              [\App\Modules\User\Controllers\IntegrationConfigController::class, 'index'])->middleware('workspace.can:settings.view')->name('integrations.index');
        Route::get('integrations/{kind}/create',         [\App\Modules\User\Controllers\IntegrationConfigController::class, 'create'])->middleware('workspace.can:settings.edit')->name('integrations.create')->where('kind', 'payment|sms|email');
        Route::post('integrations/{kind}',               [\App\Modules\User\Controllers\IntegrationConfigController::class, 'store'])->middleware('workspace.can:settings.edit')->name('integrations.store')->where('kind', 'payment|sms|email');
        Route::get('integrations/{integrationConfig}/edit',         [\App\Modules\User\Controllers\IntegrationConfigController::class, 'edit'])->middleware('workspace.can:settings.edit')->name('integrations.edit');
        Route::put('integrations/{integrationConfig}',              [\App\Modules\User\Controllers\IntegrationConfigController::class, 'update'])->middleware('workspace.can:settings.edit')->name('integrations.update');
        Route::delete('integrations/{integrationConfig}',           [\App\Modules\User\Controllers\IntegrationConfigController::class, 'destroy'])->middleware('workspace.can:settings.edit')->name('integrations.destroy');
        Route::post('integrations/{integrationConfig}/set-default', [\App\Modules\User\Controllers\IntegrationConfigController::class, 'setDefault'])->middleware('workspace.can:settings.edit')->name('integrations.set-default');
        Route::post('integrations/{integrationConfig}/toggle',      [\App\Modules\User\Controllers\IntegrationConfigController::class, 'toggleActive'])->middleware('workspace.can:settings.edit')->name('integrations.toggle');

        // Splash attachment for a specific link (picker UI)
        Route::get('links/{link}/splash',  [LinkController::class, 'splashSettings'])->middleware('workspace.can:links.view')->name('links.splash');
        Route::post('links/{link}/splash', [LinkController::class, 'updateSplash'])->middleware('workspace.can:links.edit')->name('links.splash.update');

        // Per-link analytics (heatmap / clicks export / qrcode for a link).
        Route::get('links/{link}/heatmap', [LinkController::class, 'heatmap'])->middleware('workspace.can:stats.view')->name('links.heatmap');
        Route::get('links/{link}/heatmap/live', [LinkController::class, 'heatmapLive'])->middleware('workspace.can:stats.view')->name('links.heatmap.live');
        Route::get('links/{link}/heatmap/live/stream', [LinkController::class, 'heatmapLiveStream'])->middleware('workspace.can:stats.view')->name('links.heatmap.live.stream');
        Route::get('links/{link}/clicks/partial', [LinkController::class, 'recentClicksPartial'])->middleware('workspace.can:stats.view')->name('links.clicks.partial');
        // Per-block analytics drill-down (JSON) — used by the modal that
        // opens when a creator clicks a row in the block stats table.
        Route::get('links/{link}/analytics/blocks/{blockId}.json', [LinkController::class, 'blockAnalytics'])->whereNumber('blockId')->middleware('workspace.can:stats.view')->name('links.analytics.block');
        Route::get('links/{link}/clicks/export', [LinkController::class, 'exportClicks'])->middleware('workspace.can:stats.view')->name('links.clicks.export');
        Route::get('links/{link}/qrcode', [QrCodeController::class, 'show'])->middleware('workspace.can:links.view')->name('links.qrcode');
        Route::post('links/{link}/qrcode', [QrCodeController::class, 'generate'])->middleware('workspace.can:links.edit')->name('links.qrcode.download');
        Route::get('links/{link}/qrcode/preview', [QrCodeController::class, 'preview'])->middleware('workspace.can:links.view')->name('links.qrcode.preview');

        Route::get('qrcode', [QrCodeController::class, 'standalone'])->middleware('workspace.can:links.view')->name('qrcode');

        // ===== Contacts & Dialer =====
        // Contacts/dialer/calendar/events are workspace-account-level data
        // (CRM). Read endpoints require `settings.view`, mutations
        // `settings.edit` so non-admin members can't see or modify the
        // workspace's address book.
        Route::get('contacts',                              [ContactController::class, 'index'])->middleware(['workspace.can:settings.view', 'contacts.sync-on-open'])->name('contacts.index');
        // Consolidated "everything I need to follow up on" list. Must be
        // registered before the contacts/{contact} wildcard below so the
        // literal "follow-ups" segment isn't captured as a contact id.
        Route::get('contacts/follow-ups',                   [ContactController::class, 'followUps'])->middleware('workspace.can:settings.view')->name('contacts.follow-ups');
        Route::get('contacts/create',                       [ContactController::class, 'create'])->middleware('workspace.can:settings.edit')->name('contacts.create');
        Route::post('contacts',                             [ContactController::class, 'store'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':contacts_max', CheckPlanLimit::class . ':leads'])->name('contacts.store');
        Route::get('contacts/import',                       [ContactController::class, 'importForm'])->middleware('workspace.can:settings.edit')->name('contacts.import');

        // ── AI Card / Brochure Scanner ───────────────────────────────
        // Upload an image or PDF, get a structured contact + biolink
        // draft proposal back. The actual extraction is metered through
        // the standard AI credit ledger (feature='card_scan').
        Route::get ('contacts/scan',                        [\App\Modules\User\Controllers\CardScanController::class, 'create'])->middleware('workspace.can:settings.edit')->name('contacts.scan.create');
        // Plan-limit checks are enforced inside the controller — only
        // when the user actually elects to create a Contact. Seeding a
        // biolink-only draft must work even with a full contacts quota.
        Route::post('contacts/scan',                        [\App\Modules\User\Controllers\CardScanController::class, 'store'])->middleware('workspace.can:settings.edit')->name('contacts.scan.store');
        Route::get ('contacts/scan/{scan}',                 [\App\Modules\User\Controllers\CardScanController::class, 'show'])->whereNumber('scan')->middleware('workspace.can:settings.edit')->name('contacts.scan.show');
        Route::post('contacts/scan/{scan}/save',            [\App\Modules\User\Controllers\CardScanController::class, 'save'])->whereNumber('scan')->middleware('workspace.can:settings.edit')->name('contacts.scan.save');
        Route::post('contacts/import',                      [ContactController::class, 'import'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':contacts_max', CheckPlanLimit::class . ':leads'])->name('contacts.import.store');
        Route::get('contacts/import/preview/{token}',       [ContactController::class, 'importPreview'])->middleware('workspace.can:settings.edit')->name('contacts.import.preview');
        Route::post('contacts/import/preview/{token}/row/{index}', [ContactController::class, 'importRowUpdate'])->whereNumber('index')->middleware('workspace.can:settings.edit')->name('contacts.import.preview.row.update');
        Route::post('contacts/import/preview/{token}/row/{index}/skip', [ContactController::class, 'importRowSkip'])->whereNumber('index')->middleware('workspace.can:settings.edit')->name('contacts.import.preview.row.skip');
        Route::post('contacts/import/confirm/{token}',      [ContactController::class, 'importConfirm'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':contacts_max', CheckPlanLimit::class . ':leads'])->name('contacts.import.confirm');
        Route::post('contacts/import/cancel/{token}',       [ContactController::class, 'importCancel'])->middleware('workspace.can:settings.edit')->name('contacts.import.cancel');
        Route::get('contacts/import/{import}',              [ContactController::class, 'importShow'])->middleware('workspace.can:settings.view')->name('contacts.import.show');
        Route::get('contacts/import/{import}/status',       [ContactController::class, 'importStatus'])->middleware('workspace.can:settings.view')->name('contacts.import.status');
        Route::get('contacts/{contact}',                    [ContactController::class, 'show'])->middleware('workspace.can:settings.view')->name('contacts.show');
        Route::get('contacts/{contact}/edit',               [ContactController::class, 'edit'])->middleware('workspace.can:settings.edit')->name('contacts.edit');
        Route::put('contacts/{contact}',                    [ContactController::class, 'update'])->middleware('workspace.can:settings.edit')->name('contacts.update');
        Route::delete('contacts/{contact}',                 [ContactController::class, 'destroy'])->middleware('workspace.can:settings.edit')->name('contacts.destroy');
        Route::post('contacts/{contact}/biolink/detach',    [ContactController::class, 'detachBiolink'])->middleware('workspace.can:settings.edit')->name('contacts.biolink.detach');
        Route::post('contacts/{contact}/biolink/attach',    [ContactController::class, 'attachBiolink'])->middleware('workspace.can:settings.edit')->name('contacts.biolink.attach');
        Route::post('contacts/{contact}/biolink/sms',       [ContactController::class, 'smsBiolink'])->middleware('workspace.can:settings.edit')->name('contacts.biolink.sms');
        Route::post('contacts/{contact}/follow-up',         [ContactController::class, 'setFollowUp'])->middleware('workspace.can:settings.edit')->name('contacts.follow-up.set');
        Route::delete('contacts/{contact}/follow-up',       [ContactController::class, 'clearFollowUp'])->middleware('workspace.can:settings.edit')->name('contacts.follow-up.clear');

        // Google Contacts OAuth + sync.
        Route::get('contacts/google/connect',               [GoogleContactsAccountController::class, 'connect'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':contacts_google_sync'])->name('contacts.google.connect');
        Route::get('contacts/google/callback',              [GoogleContactsAccountController::class, 'callback'])->middleware('workspace.can:settings.edit')->name('contacts.google.callback');
        Route::post('contacts/google/{account}/sync',       [GoogleContactsAccountController::class, 'syncNow'])->middleware('workspace.can:settings.edit')->name('contacts.google.sync');
        Route::delete('contacts/google/{account}',          [GoogleContactsAccountController::class, 'destroy'])->middleware('workspace.can:settings.edit')->name('contacts.google.destroy');

        // Connected Apps (CRM two-way sync + Google Analytics forwarding).
        // Plan-gated on the `connected_apps` feature key; the public OAuth
        // callback lives in routes/web.php (stateless, shared with mobile).
        Route::get   ('settings/connections/apps',          [\App\Modules\User\Controllers\ConnectedAppController::class, 'index'])->middleware('workspace.can:settings.view')->name('connected-apps.index');
        Route::get   ('connected-apps/connect/{provider}',  [\App\Modules\User\Controllers\ConnectedAppController::class, 'connect'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':connected_apps'])->name('connected-apps.connect');
        Route::post  ('connected-apps/google-analytics',    [\App\Modules\User\Controllers\ConnectedAppController::class, 'saveGa'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':connected_apps'])->name('connected-apps.google-analytics');
        Route::put   ('connected-apps/{connectedApp}',      [\App\Modules\User\Controllers\ConnectedAppController::class, 'update'])->whereNumber('connectedApp')->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':connected_apps'])->name('connected-apps.update');
        Route::post  ('connected-apps/{connectedApp}/sync', [\App\Modules\User\Controllers\ConnectedAppController::class, 'syncNow'])->whereNumber('connectedApp')->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':connected_apps'])->name('connected-apps.sync');
        Route::delete('connected-apps/{connectedApp}',      [\App\Modules\User\Controllers\ConnectedAppController::class, 'destroy'])->whereNumber('connectedApp')->middleware('workspace.can:settings.edit')->name('connected-apps.destroy');

        // Dialer.
        Route::get('dialer',                                [DialerController::class, 'index'])->middleware('workspace.can:settings.view')->name('dialer.index');
        Route::get('dialer/suggestions',                    [DialerController::class, 'suggestions'])->middleware('workspace.can:settings.view')->name('dialer.suggestions');
        Route::get('dialer/search',                         [DialerController::class, 'search'])->middleware('workspace.can:settings.view')->name('dialer.search');
        Route::get('dialer/profile',                        [DialerController::class, 'profile'])->middleware('workspace.can:settings.view')->name('dialer.profile');
        Route::get('dialer/live',                           [DialerController::class, 'live'])->middleware('workspace.can:settings.view')->name('dialer.live');
        // Everyday-tool mutations (favorites, flags, call log, callbacks).
        Route::post  ('dialer/favorites',                   [DialerController::class, 'favoriteStore'])->middleware('workspace.can:settings.edit')->name('dialer.favorites.store');
        Route::post  ('dialer/favorites/reorder',           [DialerController::class, 'favoritesReorder'])->middleware('workspace.can:settings.edit')->name('dialer.favorites.reorder');
        Route::delete('dialer/favorites/{favorite}',        [DialerController::class, 'favoriteDestroy'])->whereNumber('favorite')->middleware('workspace.can:settings.edit')->name('dialer.favorites.destroy');
        Route::post  ('dialer/flag',                        [DialerController::class, 'flag'])->middleware('workspace.can:settings.edit')->name('dialer.flag');
        Route::post  ('dialer/log',                         [DialerController::class, 'logCall'])->middleware('workspace.can:settings.edit')->name('dialer.log');
        Route::post  ('dialer/callback',                    [DialerController::class, 'callbackSet'])->middleware('workspace.can:settings.edit')->name('dialer.callback.set');
        Route::delete('dialer/callback/{log}',              [DialerController::class, 'callbackClear'])->whereNumber('log')->middleware('workspace.can:settings.edit')->name('dialer.callback.clear');
        Route::post('dialer/manual',                        [DialerController::class, 'updateManual'])->middleware('workspace.can:settings.edit')->name('dialer.manual');
        Route::post('dialer/channels',                      [DialerController::class, 'channelsUpdate'])->middleware('workspace.can:settings.edit')->name('dialer.channels');

        // ===== Events calendar (month / week / day / list views) =====
        Route::get('events',                                [CalendarAccountController::class, 'events'])->middleware('workspace.can:settings.view')->name('events.index');
        Route::get('events/feed',                           [CalendarAccountController::class, 'eventsFeed'])->middleware('workspace.can:settings.view')->name('events.feed');

        // ===== Calendar accounts (Google / Microsoft / CalDAV sync) =====
        Route::get('calendar',                              [CalendarAccountController::class, 'index'])->middleware('workspace.can:settings.view')->name('calendar.index');
        Route::get('calendar/connect/{provider}',           [CalendarAccountController::class, 'connect'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':calendar_sync'])->name('calendar.connect')->where('provider', 'google|microsoft|caldav');
        Route::get('calendar/callback/{provider}',          [CalendarAccountController::class, 'callback'])->middleware('workspace.can:settings.edit')->name('calendar.callback')->where('provider', 'google|microsoft|caldav');
        Route::post('calendar/{account}/sync',              [CalendarAccountController::class, 'syncNow'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':calendar_sync'])->name('calendar.sync');
        Route::put('calendar/{account}',                    [CalendarAccountController::class, 'update'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':calendar_sync'])->name('calendar.update');
        Route::delete('calendar/{account}',                 [CalendarAccountController::class, 'destroy'])->middleware('workspace.can:settings.edit')->name('calendar.destroy');

        // ===== RSVPs (guest list on Event Invite links) — followers feature.
        Route::get('links/{link}/rsvps',                    [RsvpController::class, 'index'])->middleware('workspace.can:followers.view')->name('links.rsvps.index');
        Route::get('links/{link}/rsvps/export',             [RsvpController::class, 'export'])->middleware('workspace.can:followers.view')->name('links.rsvps.export');
        Route::delete('links/{link}/rsvps/{rsvp}',          [RsvpController::class, 'destroy'])->middleware('workspace.can:followers.edit')->name('links.rsvps.destroy');
        Route::post  ('links/{link}/rsvps/{rsvp}/promote',  [RsvpController::class, 'promote'])->middleware('workspace.can:followers.edit')->name('links.rsvps.promote');
        Route::post  ('links/{link}/rsvps/erase-voter',     [RsvpController::class, 'eraseVoter'])->middleware('workspace.can:followers.edit')->name('links.rsvps.erase-voter');
        Route::post  ('settings/auto-sync-calendar',        [\App\Modules\User\Controllers\CalendarAccountController::class, 'updateAutoSync'])->middleware('workspace.owner')->name('calendar.auto-sync');

        // ===== Poll votes (per biolink-block) — followers feature.
        Route::get('links/{link}/blocks/{block}/poll-votes',          [PollVoteController::class, 'index'])->middleware('workspace.can:followers.view')->name('links.poll-votes.index');
        Route::get('links/{link}/blocks/{block}/poll-votes/export',   [PollVoteController::class, 'export'])->middleware('workspace.can:followers.view')->name('links.poll-votes.export');
        Route::delete('links/{link}/blocks/{block}/poll-votes/{vote}',[PollVoteController::class, 'destroy'])->middleware('workspace.can:followers.edit')->name('links.poll-votes.destroy');
        Route::post  ('links/{link}/blocks/{block}/poll-votes/erase-voter',[PollVoteController::class, 'eraseVoter'])->middleware('workspace.can:followers.edit')->name('links.poll-votes.erase-voter');
        Route::get   ('links/{link}/blocks/{block}/poll-votes/erasures',  [PollVoteController::class, 'erasures'])->middleware('workspace.can:followers.view')->name('links.poll-votes.erasures');
        Route::post('links/{link}/blocks/{block}/poll-votes/reset',   [PollVoteController::class, 'reset'])->middleware('workspace.can:followers.edit')->name('links.poll-votes.reset');
        Route::post('links/{link}/blocks/{block}/poll-votes/snapshots/{snapshot}/undo', [PollVoteController::class, 'undoReset'])->middleware('workspace.can:followers.edit')->name('links.poll-votes.undo-reset');

        // ===== Community Layer: Insider feed, comment moderation, fan leaderboard.
        Route::get   ('links/{link}/blocks/{block}/insider',                  [\App\Modules\User\Controllers\InsiderBlockController::class, 'index'])->middleware('workspace.can:followers.view')->name('links.insider.index');
        Route::get   ('links/{link}/blocks/{block}/insider/members',          [\App\Modules\User\Controllers\InsiderBlockController::class, 'members'])->middleware('workspace.can:followers.view')->name('links.insider.members');
        Route::post  ('links/{link}/blocks/{block}/insider/posts',            [\App\Modules\User\Controllers\InsiderBlockController::class, 'storePost'])->middleware('workspace.can:followers.edit')->name('links.insider.posts.store');
        Route::put   ('links/{link}/blocks/{block}/insider/posts/{post}',     [\App\Modules\User\Controllers\InsiderBlockController::class, 'updatePost'])->middleware('workspace.can:followers.edit')->name('links.insider.posts.update');
        Route::delete('links/{link}/blocks/{block}/insider/posts/{post}',     [\App\Modules\User\Controllers\InsiderBlockController::class, 'destroyPost'])->middleware('workspace.can:followers.edit')->name('links.insider.posts.destroy');
        Route::post  ('links/{link}/blocks/{block}/insider/members/{member}/ban', [\App\Modules\User\Controllers\InsiderBlockController::class, 'banMember'])->middleware('workspace.can:followers.edit')->name('links.insider.members.ban');

        // Polls (creator-side CRUD; any biolink block can carry polls).
        Route::get   ('links/{link}/blocks/{block}/polls',                    [\App\Modules\User\Controllers\BlockPollController::class, 'index'])->middleware('workspace.can:followers.view')->name('links.polls.index');
        Route::post  ('links/{link}/blocks/{block}/polls',                    [\App\Modules\User\Controllers\BlockPollController::class, 'store'])->middleware('workspace.can:followers.edit')->name('links.polls.store');
        Route::put   ('links/{link}/blocks/{block}/polls/{poll}',             [\App\Modules\User\Controllers\BlockPollController::class, 'update'])->middleware('workspace.can:followers.edit')->name('links.polls.update');
        Route::delete('links/{link}/blocks/{block}/polls/{poll}',             [\App\Modules\User\Controllers\BlockPollController::class, 'destroy'])->middleware('workspace.can:followers.edit')->name('links.polls.destroy');

        Route::get   ('links/{link}/blocks/{block}/comments',                  [\App\Modules\User\Controllers\BlockCommentController::class, 'index'])->middleware('workspace.can:followers.view')->name('links.comments.index');
        Route::patch ('links/{link}/blocks/{block}/comments/{comment}',        [\App\Modules\User\Controllers\BlockCommentController::class, 'update'])->middleware('workspace.can:followers.edit')->name('links.comments.update');
        Route::delete('links/{link}/blocks/{block}/comments/{comment}',        [\App\Modules\User\Controllers\BlockCommentController::class, 'destroy'])->middleware('workspace.can:followers.edit')->name('links.comments.destroy');
        Route::post  ('links/{link}/blocks/{block}/comments/{comment}/ban',    [\App\Modules\User\Controllers\BlockCommentController::class, 'banAuthor'])->middleware('workspace.can:followers.edit')->name('links.comments.ban-author');

        Route::get   ('links/{link}/leaderboard',                              [\App\Modules\User\Controllers\FanLeaderboardController::class, 'edit'])->middleware('workspace.can:followers.view')->name('links.leaderboard.edit');
        Route::put   ('links/{link}/leaderboard',                              [\App\Modules\User\Controllers\FanLeaderboardController::class, 'update'])->middleware('workspace.can:followers.edit')->name('links.leaderboard.update');
        Route::post('qrcode', [QrCodeController::class, 'generateStandalone'])->middleware('workspace.can:links.create')->name('qrcode.download');
        Route::get('qrcode/preview', [QrCodeController::class, 'previewStandalone'])->middleware('workspace.can:links.view')->name('qrcode.preview');

        // QR Studio (full builder + library) — workspace links feature.
        Route::get   ('qr-codes',                    [QrCodeController::class, 'index'])    ->middleware('workspace.can:links.view')->name('qr-codes.index');
        Route::get   ('qr-codes/create',             [QrCodeController::class, 'create'])   ->middleware('workspace.can:links.create')->name('qr-codes.create');
        Route::post  ('qr-codes',                    [QrCodeController::class, 'store'])    ->middleware('workspace.can:links.create')->name('qr-codes.store');
        Route::get   ('qr-codes/{qrCode}/edit',      [QrCodeController::class, 'edit'])     ->middleware('workspace.can:links.edit')->name('qr-codes.edit');
        Route::put   ('qr-codes/{qrCode}',           [QrCodeController::class, 'update'])   ->middleware('workspace.can:links.edit')->name('qr-codes.update');
        Route::delete('qr-codes/{qrCode}',           [QrCodeController::class, 'destroy'])  ->middleware('workspace.can:links.delete')->name('qr-codes.destroy');
        Route::post  ('qr-codes/{qrCode}/duplicate', [QrCodeController::class, 'duplicate'])->middleware('workspace.can:links.create')->name('qr-codes.duplicate');
        Route::post  ('qr-codes/resolve',            [QrCodeController::class, 'resolvePayload'])->middleware('workspace.can:links.view')->name('qr-codes.resolve');
        Route::post  ('qr-codes/upload-logo',        [QrCodeController::class, 'uploadLogo'])->middleware('workspace.can:links.create')->name('qr-codes.upload-logo');
        Route::post  ('qr-codes/generate-art',       [QrCodeController::class, 'generateArt'])->middleware('workspace.can:links.create')->name('qr-codes.generate-art');

        // Pixels — analytics tags, gated under stats feature.
        Route::resource('pixels', PixelController::class)->only(['index'])->middleware('workspace.can:stats.view');
        Route::resource('pixels', PixelController::class)->except(['show', 'store', 'index'])->middleware('workspace.can:stats.edit');
        Route::post('pixels', [PixelController::class, 'store'])->middleware(['workspace.can:stats.edit', CheckPlanLimit::class . ':pixels'])->name('pixels.store');

        // Custom domains — workspace settings.
        Route::prefix('settings/domains')->name('domains.')->middleware([CheckPlanLimit::class . ':custom_domains'])->group(function () {
            Route::get('/', [\App\Modules\User\Controllers\DomainController::class, 'index'])->middleware('workspace.can:settings.view')->name('index');
            Route::post('/', [\App\Modules\User\Controllers\DomainController::class, 'store'])->middleware('workspace.can:settings.edit')->name('store');
            Route::post('{domain}/verify', [\App\Modules\User\Controllers\DomainController::class, 'verify'])->middleware('workspace.can:settings.edit')->name('verify');
            Route::delete('{domain}', [\App\Modules\User\Controllers\DomainController::class, 'destroy'])->middleware('workspace.can:settings.edit')->name('destroy');
        });

        // Social proofs (notification widget on biolinks) — links feature.
        Route::prefix('social-proofs')->name('social-proofs.')->group(function () {
            Route::get('/',                            [\App\Modules\User\Controllers\SocialProofController::class, 'index'])->middleware('workspace.can:links.view')->name('index');
            Route::get('create',                       [\App\Modules\User\Controllers\SocialProofController::class, 'create'])->middleware('workspace.can:links.create')->name('create');
            Route::post('/',                           [\App\Modules\User\Controllers\SocialProofController::class, 'store'])->middleware(['workspace.can:links.create', CheckPlanLimit::class . ':buzz_popups'])->name('store');
            Route::get('{socialProof}/edit',           [\App\Modules\User\Controllers\SocialProofController::class, 'edit'])->middleware('workspace.can:links.edit')->name('edit');
            Route::put('{socialProof}',                [\App\Modules\User\Controllers\SocialProofController::class, 'update'])->middleware('workspace.can:links.edit')->name('update');
            Route::post('{socialProof}/toggle',        [\App\Modules\User\Controllers\SocialProofController::class, 'toggleActive'])->middleware('workspace.can:links.edit')->name('toggle');
            Route::delete('{socialProof}',             [\App\Modules\User\Controllers\SocialProofController::class, 'destroy'])->middleware('workspace.can:links.delete')->name('destroy');
            Route::post('{socialProof}/items',         [\App\Modules\User\Controllers\SocialProofController::class, 'storeItem'])->middleware('workspace.can:links.edit')->name('items.store');
            Route::delete('{socialProof}/items/{item}',[\App\Modules\User\Controllers\SocialProofController::class, 'destroyItem'])->middleware('workspace.can:links.edit')->name('items.destroy');
        });

        // User-uploaded files (image library, etc.) — links feature: any
        // role allowed to edit links can pick / upload media for them.
        Route::prefix('files')->name('files.')->group(function () {
            Route::get('/', [UserFileController::class, 'index'])->middleware('workspace.can:links.view')->name('index');
            Route::post('upload', [UserFileController::class, 'upload'])->middleware(['workspace.can:links.edit', CheckPlanLimit::class . ':files'])->name('upload');
            Route::post('import-url', [UserFileController::class, 'importUrl'])->middleware(['workspace.can:links.edit', CheckPlanLimit::class . ':files'])->name('import-url');
            Route::delete('{file}', [UserFileController::class, 'destroy'])->middleware('workspace.can:links.edit')->name('destroy');
            Route::get('quota', [UserFileController::class, 'quota'])->middleware('workspace.can:links.view')->name('quota');
            Route::post('reoptimize-notice/dismiss', [UserFileController::class, 'dismissReoptimizeNotice'])->middleware('workspace.can:links.view')->name('reoptimize-notice.dismiss');
        });

        // Inbox: parent gate is `inbox.view` (members without view can't reach
        // anything here). Each mutating endpoint adds an action-specific gate
        // — viewers cannot reply/edit/delete; reply role cannot edit/delete;
        // delete role is required for destructive actions.
        Route::prefix('inbox')->name('inbox.')->middleware('workspace.can:inbox.view')->group(function () {
            Route::get('/', [InboxController::class, 'index'])->name('index');
            Route::get('export', [InboxController::class, 'exportFiltered'])->name('export');
            Route::get('spam-settings', [InboxController::class, 'settings'])->name('spam-settings');
            Route::post('spam-settings', [InboxController::class, 'updateSettings'])->middleware('workspace.can:inbox.edit')->name('spam-settings.update');
            Route::post('spam-settings/import', [InboxController::class, 'importTrustedCsv'])->middleware('workspace.can:inbox.edit')->name('spam-settings.import');
            Route::post('spam-settings/disable-keyword', [InboxController::class, 'disableKeyword'])->middleware('workspace.can:inbox.edit')->name('spam-settings.disable-keyword');
            Route::post('spam-settings/enable-default-keyword', [InboxController::class, 'enableDefaultKeyword'])->middleware('workspace.can:inbox.edit')->name('spam-settings.enable-default-keyword');
            Route::post('bulk', [InboxController::class, 'bulk'])->middleware('workspace.can:inbox.edit')->name('bulk');

            // Account-level forwarding rules: send new inbox messages to
            // an email address or webhook URL with optional source filter.
            Route::prefix('forwards')->name('forwards.')->group(function () {
                Route::get('/',                          [\App\Modules\User\Controllers\InboxForwardController::class, 'index'])->name('index');
                Route::post('/',                         [\App\Modules\User\Controllers\InboxForwardController::class, 'store'])->middleware('workspace.can:inbox.create')->name('store');
                Route::put('{forward}',                  [\App\Modules\User\Controllers\InboxForwardController::class, 'update'])->middleware('workspace.can:inbox.edit')->name('update');
                Route::post('{forward}/toggle',          [\App\Modules\User\Controllers\InboxForwardController::class, 'toggle'])->middleware('workspace.can:inbox.edit')->name('toggle');
                Route::post('{forward}/test',            [\App\Modules\User\Controllers\InboxForwardController::class, 'test'])->middleware(['throttle:10,1', 'workspace.can:inbox.edit'])->name('test');
                Route::delete('{forward}',               [\App\Modules\User\Controllers\InboxForwardController::class, 'destroy'])->middleware('workspace.can:inbox.delete')->name('destroy');
                Route::post('deliveries/{delivery}/retry', [\App\Modules\User\Controllers\InboxForwardController::class, 'retry'])->middleware('workspace.can:inbox.edit')->name('deliveries.retry');
            });

            // Direct messages from biolink viewers (separate stream from
            // form-submission/subscriber items). Anti-spam + blocking lives in
            // the dedicated InboxDirectMessageController.
            Route::prefix('dms')->name('dms.')->group(function () {
                Route::get('/',                              [\App\Modules\User\Controllers\InboxDirectMessageController::class, 'index'])->name('index');
                Route::get('{conversation}',                 [\App\Modules\User\Controllers\InboxDirectMessageController::class, 'thread'])->whereNumber('conversation')->name('thread');
                Route::post('{conversation}/reply',          [\App\Modules\User\Controllers\InboxDirectMessageController::class, 'reply'])->whereNumber('conversation')->middleware('workspace.can:inbox.reply')->name('reply');
                Route::post('{conversation}/block',          [\App\Modules\User\Controllers\InboxDirectMessageController::class, 'block'])->whereNumber('conversation')->middleware('workspace.can:inbox.edit')->name('block');
                Route::post('{conversation}/unblock',        [\App\Modules\User\Controllers\InboxDirectMessageController::class, 'unblock'])->whereNumber('conversation')->middleware('workspace.can:inbox.edit')->name('unblock');
                Route::put ('{conversation}/auto-reply',     [\App\Modules\User\Controllers\InboxDirectMessageController::class, 'setAutoReply'])->whereNumber('conversation')->middleware('workspace.can:inbox.edit')->name('auto-reply');

                // Paid DMs (Task #1210): access settings, mass-message
                // broadcasts and welcome-message rules.
                Route::get ('settings/access',              [\App\Modules\User\Controllers\CreatorDmController::class, 'settings'])->middleware('workspace.can:inbox.view')->name('settings');
                Route::post('settings/access',              [\App\Modules\User\Controllers\CreatorDmController::class, 'updateSettings'])->middleware('workspace.can:inbox.edit')->name('settings.update');

                Route::prefix('broadcasts')->name('broadcasts.')->group(function () {
                    Route::get ('/',              [\App\Modules\User\Controllers\CreatorDmController::class, 'broadcastsIndex'])->middleware('workspace.can:inbox.view')->name('index');
                    Route::post('/',              [\App\Modules\User\Controllers\CreatorDmController::class, 'broadcastStore'])->middleware('workspace.can:inbox.create')->name('store');
                    Route::post('{broadcast}/send', [\App\Modules\User\Controllers\CreatorDmController::class, 'broadcastSend'])->whereNumber('broadcast')->middleware('workspace.can:inbox.edit')->name('send');
                    Route::delete('{broadcast}',  [\App\Modules\User\Controllers\CreatorDmController::class, 'broadcastDestroy'])->whereNumber('broadcast')->middleware('workspace.can:inbox.delete')->name('destroy');
                });

                Route::prefix('welcome')->name('welcome.')->group(function () {
                    Route::get ('/',                 [\App\Modules\User\Controllers\CreatorDmController::class, 'welcomeIndex'])->middleware('workspace.can:inbox.view')->name('index');
                    Route::post('/',                 [\App\Modules\User\Controllers\CreatorDmController::class, 'welcomeStore'])->middleware('workspace.can:inbox.create')->name('store');
                    Route::post('{rule}/toggle',     [\App\Modules\User\Controllers\CreatorDmController::class, 'welcomeToggle'])->whereNumber('rule')->middleware('workspace.can:inbox.edit')->name('toggle');
                    Route::delete('{rule}',          [\App\Modules\User\Controllers\CreatorDmController::class, 'welcomeDestroy'])->whereNumber('rule')->middleware('workspace.can:inbox.delete')->name('destroy');
                });
            });

            // AI Companion as inbox participant — owners (and team
            // members with inbox.view) can chat with each Companion as
            // if it were a contact. Useful for testing prompts /
            // knowledge before exposing the bot to visitors.
            Route::prefix('ai-companions')->name('ai-companions.')->group(function () {
                Route::get ('/',                          [\App\Modules\User\Controllers\InboxAiCompanionController::class, 'index'])->name('index');
                Route::get ('{companion}',                [\App\Modules\User\Controllers\InboxAiCompanionController::class, 'show'])->whereNumber('companion')->name('show');
                Route::post('{companion}/message',        [\App\Modules\User\Controllers\InboxAiCompanionController::class, 'send'])->whereNumber('companion')->middleware(['workspace.can:inbox.reply', 'throttle:60,1'])->name('send');
            });

            Route::get('{type}/{id}', [InboxController::class, 'show'])
                ->where('type', 'form_submission|subscriber')->whereNumber('id')->name('show');
            Route::post('{type}/{id}', [InboxController::class, 'update'])
                ->where('type', 'form_submission|subscriber')->whereNumber('id')->middleware('workspace.can:inbox.edit')->name('update');
            Route::post('{type}/{id}/reply', [InboxController::class, 'reply'])
                ->where('type', 'form_submission|subscriber')->whereNumber('id')->middleware('workspace.can:inbox.reply')->name('reply');

            // ---- Inbox 2.0: unified, triaged, actionable ----
            // Sits alongside the legacy form/subscriber inbox. Backed by the
            // new inbox_threads + inbox_messages schema, kept in sync from
            // every existing source (forms, subscribers, viewer DMs).
            Route::prefix('unified')->name('unified.')->group(function () {
                Route::get('/',                 [\App\Modules\User\Controllers\InboxUnifiedController::class, 'index'])->name('index');
                // Inbox Agent: settings panel + manual AI reply drafting.
                Route::get('agent',             [\App\Modules\User\Controllers\InboxUnifiedController::class, 'agentSettings'])->middleware('workspace.owner')->name('agent');
                Route::post('agent',            [\App\Modules\User\Controllers\InboxUnifiedController::class, 'agentSettingsUpdate'])->middleware('workspace.owner')->name('agent.update');
                Route::post('{thread}/ai-draft',[\App\Modules\User\Controllers\InboxUnifiedController::class, 'aiDraft'])->whereNumber('thread')->middleware('workspace.can:inbox.reply')->name('ai-draft');
                Route::get('snippets',          [\App\Modules\User\Controllers\InboxUnifiedController::class, 'snippetsIndex'])->name('snippets.index');
                Route::post('snippets',         [\App\Modules\User\Controllers\InboxUnifiedController::class, 'snippetsStore'])->middleware('workspace.can:inbox.edit')->name('snippets.store');
                Route::delete('snippets/{snippet}', [\App\Modules\User\Controllers\InboxUnifiedController::class, 'snippetsDestroy'])->whereNumber('snippet')->middleware('workspace.can:inbox.delete')->name('snippets.destroy');
                Route::post('bulk',             [\App\Modules\User\Controllers\InboxUnifiedController::class, 'bulk'])->middleware('workspace.can:inbox.edit')->name('bulk');
                Route::get('{thread}',          [\App\Modules\User\Controllers\InboxUnifiedController::class, 'show'])->whereNumber('thread')->name('show');
                Route::post('{thread}/update',  [\App\Modules\User\Controllers\InboxUnifiedController::class, 'update'])->whereNumber('thread')->middleware('workspace.can:inbox.edit')->name('update');
                Route::post('{thread}/reply',   [\App\Modules\User\Controllers\InboxUnifiedController::class, 'reply'])->whereNumber('thread')->middleware('workspace.can:inbox.reply')->name('reply');
                Route::post('{thread}/convert/kanban',   [\App\Modules\User\Controllers\InboxUnifiedController::class, 'convertToKanban'])->whereNumber('thread')->middleware('workspace.can:inbox.edit')->name('convert.kanban');
                Route::post('{thread}/convert/contact',  [\App\Modules\User\Controllers\InboxUnifiedController::class, 'convertToContact'])->whereNumber('thread')->middleware('workspace.can:inbox.edit')->name('convert.contact');
                Route::post('{thread}/convert/vault',    [\App\Modules\User\Controllers\InboxUnifiedController::class, 'convertToVault'])->whereNumber('thread')->middleware('workspace.can:inbox.edit')->name('convert.vault');
                Route::post('{thread}/convert/calendar', [\App\Modules\User\Controllers\InboxUnifiedController::class, 'convertToCalendar'])->whereNumber('thread')->middleware('workspace.can:inbox.edit')->name('convert.calendar');
            });
        });

        // Subscribers feed inbox/digests — gate reads under inbox.view and
        // mutations (compose/send/settings/toggle/destroy) under inbox.edit
        // / inbox.delete so view-only members can't delete subscribers or
        // blast messages.
        Route::prefix('subscribers')->name('subscribers.')->middleware('workspace.can:inbox.view')->group(function () {
            Route::get('/', [SubscriberController::class, 'index'])->name('index');
            Route::get('settings', [SubscriberController::class, 'settings'])->name('settings');
            Route::post('settings', [SubscriberController::class, 'updateSettings'])->middleware('workspace.can:inbox.edit')->name('settings.update');
            Route::get('compose', [SubscriberController::class, 'compose'])->middleware('workspace.can:inbox.create')->name('compose');
            Route::post('send', [SubscriberController::class, 'send'])->middleware('workspace.can:inbox.create')->name('send');
            Route::get('export', [SubscriberController::class, 'export'])->name('export');
            Route::get('messages', [SubscriberController::class, 'messageHistory'])->name('messages');
            Route::post('{subscriber}/toggle', [SubscriberController::class, 'toggleStatus'])->middleware('workspace.can:inbox.edit')->name('toggle');
            Route::delete('{subscriber}', [SubscriberController::class, 'destroy'])->middleware('workspace.can:inbox.delete')->name('destroy');
        });

        // Referrals: read endpoints under `referrals.view`, mutation requires
        // `referrals.edit` so view-only members can't change the referral code.
        Route::prefix('referrals')->name('referrals.')->middleware('workspace.can:referrals.view')->group(function () {
            Route::get('/', [\App\Modules\User\Controllers\ReferralController::class, 'index'])->name('index');
            Route::put('code', [\App\Modules\User\Controllers\ReferralController::class, 'updateCode'])->middleware('workspace.can:referrals.edit')->name('code.update');
        });

        // ---- Tasks: workspace kanban boards (personal + team) ----
        // Reads gated by tasks.view, mutations escalate to create/edit/delete.
        // Personal-board owners always pass even when their workspace role is
        // below those actions (enforced inside the controller).
        Route::prefix('tasks')->name('tasks.')->middleware('workspace.can:tasks.view')->group(function () {
            Route::get('/',                       [\App\Modules\User\Controllers\TaskBoardController::class, 'index'])->name('index');
            // Board creation is NOT gated by tasks.create at the route level so
            // that a viewer-role member can still maintain a private personal
            // board. The controller enforces tasks.create for team boards.
            Route::post('boards',                 [\App\Modules\User\Controllers\TaskBoardController::class, 'store'])->middleware(CheckPlanLimit::class . ':tasks')->name('boards.store');
            Route::get('boards/{board}',          [\App\Modules\User\Controllers\TaskBoardController::class, 'show'])->name('show');
            Route::put('boards/{board}',          [\App\Modules\User\Controllers\TaskBoardController::class, 'updateBoard'])->name('boards.update');
            Route::post('boards/{board}/archive',   [\App\Modules\User\Controllers\TaskBoardController::class, 'archiveBoard'])->name('boards.archive');
            Route::post('boards/{board}/unarchive', [\App\Modules\User\Controllers\TaskBoardController::class, 'unarchiveBoard'])->name('boards.unarchive');
            Route::delete('boards/{board}',       [\App\Modules\User\Controllers\TaskBoardController::class, 'destroyBoard'])->name('boards.destroy');

            Route::post('boards/{board}/columns',          [\App\Modules\User\Controllers\TaskBoardController::class, 'storeColumn'])->name('columns.store');
            Route::put('columns/{column}',                 [\App\Modules\User\Controllers\TaskBoardController::class, 'updateColumn'])->name('columns.update');
            Route::delete('columns/{column}',              [\App\Modules\User\Controllers\TaskBoardController::class, 'destroyColumn'])->name('columns.destroy');
            Route::post('boards/{board}/columns/reorder',  [\App\Modules\User\Controllers\TaskBoardController::class, 'reorderColumns'])->name('columns.reorder');

            // Card mutations are gated inside the controller via authorize*()
            // so personal-board owners can manage their own cards regardless
            // of workspace role; team-board cards still require the matching
            // tasks.create / tasks.edit / tasks.delete action.
            Route::post('boards/{board}/cards',   [\App\Modules\User\Controllers\TaskBoardController::class, 'storeCard'])->name('cards.store');
            Route::get('cards/{card}',            [\App\Modules\User\Controllers\TaskBoardController::class, 'showCard'])->name('cards.show');
            Route::patch('cards/{card}',          [\App\Modules\User\Controllers\TaskBoardController::class, 'updateCard'])->name('cards.update');
            Route::post('cards/{card}/move',      [\App\Modules\User\Controllers\TaskBoardController::class, 'moveCard'])->name('cards.move');
            Route::delete('cards/{card}',         [\App\Modules\User\Controllers\TaskBoardController::class, 'destroyCard'])->name('cards.destroy');
            Route::post('cards/{card}/assign',    [\App\Modules\User\Controllers\TaskBoardController::class, 'assign'])->name('cards.assign');
            Route::delete('cards/{card}/assignees/{user}', [\App\Modules\User\Controllers\TaskBoardController::class, 'unassign'])->name('cards.unassign');

            Route::post('cards/{card}/subtasks',           [\App\Modules\User\Controllers\TaskBoardController::class, 'storeSubtask'])->name('subtasks.store');
            Route::post('subtasks/{subtask}/toggle',       [\App\Modules\User\Controllers\TaskBoardController::class, 'toggleSubtask'])->name('subtasks.toggle');
            Route::post('cards/{card}/subtasks/reorder',   [\App\Modules\User\Controllers\TaskBoardController::class, 'reorderSubtasks'])->name('subtasks.reorder');
            Route::delete('subtasks/{subtask}',            [\App\Modules\User\Controllers\TaskBoardController::class, 'destroySubtask'])->name('subtasks.destroy');

            Route::post('cards/{card}/comments',           [\App\Modules\User\Controllers\TaskBoardController::class, 'storeComment'])->name('comments.store');

            Route::post('boards/{board}/labels',           [\App\Modules\User\Controllers\TaskBoardController::class, 'storeLabel'])->name('labels.store');
            Route::post('cards/{card}/labels',             [\App\Modules\User\Controllers\TaskBoardController::class, 'attachLabel'])->name('cards.labels.attach');
            Route::delete('cards/{card}/labels/{label}',   [\App\Modules\User\Controllers\TaskBoardController::class, 'detachLabel'])->name('cards.labels.detach');

            // File attachments — 10 MB cap enforced in the controller.
            Route::post('cards/{card}/attachments',        [\App\Modules\User\Controllers\TaskBoardController::class, 'storeAttachment'])->name('attachments.store');
            Route::get('attachments/{attachment}/download',[\App\Modules\User\Controllers\TaskBoardController::class, 'downloadAttachment'])->name('attachments.download');
            Route::delete('attachments/{attachment}',      [\App\Modules\User\Controllers\TaskBoardController::class, 'destroyAttachment'])->name('attachments.destroy');

            // Time tracking + per-board billed-column setting (kanban billing).
            Route::post('cards/{card}/timer/start',        [\App\Modules\User\Controllers\TaskBoardController::class, 'startTimer'])->name('timer.start');
            Route::post('cards/{card}/timer/stop',         [\App\Modules\User\Controllers\TaskBoardController::class, 'stopTimer'])->name('timer.stop');
            Route::post('cards/{card}/time-entries',       [\App\Modules\User\Controllers\TaskBoardController::class, 'storeTimeEntry'])->name('time-entries.store');
            Route::delete('time-entries/{entry}',          [\App\Modules\User\Controllers\TaskBoardController::class, 'destroyTimeEntry'])->name('time-entries.destroy');
            Route::put('boards/{board}/billed-column',     [\App\Modules\User\Controllers\TaskBoardController::class, 'setBilledColumn'])->name('boards.billed-column');
        });

        // ---- Delivery Projects: turn a finalized sale into a shared project ----
        // Reads gated by tasks.view, mutations escalate to tasks.edit. Shares
        // the tasks.* permission namespace (same "delivery/ops" surface).
        Route::prefix('delivery-projects')->name('delivery-projects.')->middleware('workspace.can:tasks.view')->group(function () {
            Route::get('/',                       [\App\Modules\User\Controllers\DeliveryProjectController::class, 'index'])->name('index');
            Route::get('create',                  [\App\Modules\User\Controllers\DeliveryProjectController::class, 'create'])->middleware('workspace.can:tasks.edit')->name('create');
            Route::post('/',                      [\App\Modules\User\Controllers\DeliveryProjectController::class, 'store'])->middleware('workspace.can:tasks.edit')->name('store');
            Route::get('{deliveryProject}',       [\App\Modules\User\Controllers\DeliveryProjectController::class, 'show'])->whereNumber('deliveryProject')->name('show');
            Route::put('{deliveryProject}',       [\App\Modules\User\Controllers\DeliveryProjectController::class, 'update'])->whereNumber('deliveryProject')->middleware('workspace.can:tasks.edit')->name('update');
            Route::delete('{deliveryProject}',    [\App\Modules\User\Controllers\DeliveryProjectController::class, 'destroy'])->whereNumber('deliveryProject')->middleware('workspace.can:tasks.delete')->name('destroy');
            Route::post('{deliveryProject}/share-token', [\App\Modules\User\Controllers\DeliveryProjectController::class, 'regenerateShareToken'])->whereNumber('deliveryProject')->middleware('workspace.can:tasks.edit')->name('share-token');
            Route::put('{deliveryProject}/calendar-privacy', [\App\Modules\User\Controllers\DeliveryProjectController::class, 'updateCalendarPrivacy'])->whereNumber('deliveryProject')->middleware('workspace.can:tasks.edit')->name('calendar-privacy');

            Route::post('{deliveryProject}/comments',     [\App\Modules\User\Controllers\DeliveryProjectController::class, 'storeComment'])->whereNumber('deliveryProject')->middleware('workspace.can:tasks.edit')->name('comments.store');

            Route::post('{deliveryProject}/tasks',        [\App\Modules\User\Controllers\DeliveryProjectController::class, 'storeTask'])->whereNumber('deliveryProject')->middleware('workspace.can:tasks.edit')->name('tasks.store');
            Route::post('{deliveryProject}/tasks/reorder',[\App\Modules\User\Controllers\DeliveryProjectController::class, 'reorderTasks'])->whereNumber('deliveryProject')->middleware('workspace.can:tasks.edit')->name('tasks.reorder');
            Route::patch('tasks/{task}',          [\App\Modules\User\Controllers\DeliveryProjectController::class, 'updateTask'])->whereNumber('task')->middleware('workspace.can:tasks.edit')->name('tasks.update');
            Route::delete('tasks/{task}',         [\App\Modules\User\Controllers\DeliveryProjectController::class, 'destroyTask'])->whereNumber('task')->middleware('workspace.can:tasks.edit')->name('tasks.destroy');
        });

        // ---- Client invoices (kanban -> Stripe) ----
        // Workspace-scoped editor + dashboard. Pay endpoints sit on a
        // public signed-URL route outside this auth group below.
        Route::prefix('client-invoices')->name('client-invoices.')->middleware('workspace.can:tasks.view')->group(function () {
            Route::get('/',                          [\App\Modules\User\Controllers\ClientInvoiceController::class, 'dashboard'])->name('dashboard');
            Route::post('drafts',                    [\App\Modules\User\Controllers\ClientInvoiceController::class, 'createDraft'])->middleware('workspace.can:tasks.edit')->name('draft');
            Route::get('create',                     [\App\Modules\User\Controllers\ClientInvoiceController::class, 'create'])->middleware('workspace.can:tasks.edit')->name('create');
            Route::post('/',                         [\App\Modules\User\Controllers\ClientInvoiceController::class, 'store'])->middleware('workspace.can:tasks.edit')->name('store');
            Route::get('receipts/create',            [\App\Modules\User\Controllers\ClientInvoiceController::class, 'createReceipt'])->middleware('workspace.can:tasks.edit')->name('receipts.create');
            Route::post('receipts',                  [\App\Modules\User\Controllers\ClientInvoiceController::class, 'storeReceipt'])->middleware('workspace.can:tasks.edit')->name('receipts.store');
            Route::get('{invoice}',                  [\App\Modules\User\Controllers\ClientInvoiceController::class, 'edit'])->name('edit');
            Route::put('{invoice}',                  [\App\Modules\User\Controllers\ClientInvoiceController::class, 'update'])->middleware('workspace.can:tasks.edit')->name('update');
            Route::post('{invoice}/send',            [\App\Modules\User\Controllers\ClientInvoiceController::class, 'send'])->middleware('workspace.can:tasks.edit')->name('send');
            Route::post('{invoice}/remind',          [\App\Modules\User\Controllers\ClientInvoiceController::class, 'sendReminder'])->middleware(['workspace.can:tasks.edit', 'throttle:10,1'])->name('remind');
            Route::post('{invoice}/mark-paid',       [\App\Modules\User\Controllers\ClientInvoiceController::class, 'markPaid'])->middleware('workspace.can:tasks.edit')->name('mark-paid');
            Route::post('{invoice}/refund',          [\App\Modules\User\Controllers\ClientInvoiceController::class, 'refund'])->middleware('workspace.can:tasks.edit')->name('refund');
            Route::get('{invoice}/receipt',          [\App\Modules\User\Controllers\ClientInvoiceController::class, 'receipt'])->name('receipt');
        });

        // ---- Invoicing & Accounting Suite ----
        Route::prefix('billing')->name('billing.')->middleware('workspace.can:tasks.view')->group(function () {
            // Billing companies (issuing legal entities). The index moved to
            // the consolidated Settings hub (Task #3220) at /user/settings/billing
            // — see the "Settings hub" block near the end of this file. The
            // create/edit/etc. actions stay here under the billing prefix.
            Route::get('companies/create',           [\App\Modules\User\Controllers\BillingCompanyController::class, 'create'])->middleware('workspace.can:tasks.edit')->name('companies.create');
            Route::post('companies',                 [\App\Modules\User\Controllers\BillingCompanyController::class, 'store'])->middleware('workspace.can:tasks.edit')->name('companies.store');
            Route::get('companies/{company}/edit',   [\App\Modules\User\Controllers\BillingCompanyController::class, 'edit'])->middleware('workspace.can:tasks.edit')->name('companies.edit');
            Route::put('companies/{company}',        [\App\Modules\User\Controllers\BillingCompanyController::class, 'update'])->middleware('workspace.can:tasks.edit')->name('companies.update');
            Route::delete('companies/{company}',     [\App\Modules\User\Controllers\BillingCompanyController::class, 'destroy'])->middleware('workspace.can:tasks.edit')->name('companies.destroy');

            // Per-company SMTP actions (handshake check + test send).
            Route::post('companies/{company}/smtp/verify', [\App\Modules\User\Controllers\BillingCompanyController::class, 'verifySmtp'])->middleware('workspace.can:tasks.edit')->name('companies.smtp.verify');
            Route::post('companies/{company}/smtp/test',   [\App\Modules\User\Controllers\BillingCompanyController::class, 'testSmtp'])->middleware('workspace.can:tasks.edit')->name('companies.smtp.test');

            // Per-company client-facing email templates (invoice + receipt).
            Route::get('companies/{company}/emails',              [\App\Modules\User\Controllers\CompanyEmailTemplateController::class, 'index'])->name('companies.emails.index');
            Route::get('companies/{company}/emails/{key}/edit',   [\App\Modules\User\Controllers\CompanyEmailTemplateController::class, 'edit'])->middleware('workspace.can:tasks.edit')->name('companies.emails.edit');
            Route::put('companies/{company}/emails/{key}',        [\App\Modules\User\Controllers\CompanyEmailTemplateController::class, 'update'])->middleware('workspace.can:tasks.edit')->name('companies.emails.update');
            Route::delete('companies/{company}/emails/{key}',     [\App\Modules\User\Controllers\CompanyEmailTemplateController::class, 'reset'])->middleware('workspace.can:tasks.edit')->name('companies.emails.reset');
            Route::post('companies/{company}/emails/{key}/preview', [\App\Modules\User\Controllers\CompanyEmailTemplateController::class, 'preview'])->middleware('workspace.can:tasks.edit')->name('companies.emails.preview');

            // Tax rules.
            Route::get('tax-rules',                  [\App\Modules\User\Controllers\TaxRuleController::class, 'index'])->name('tax-rules.index');
            Route::post('tax-rules',                 [\App\Modules\User\Controllers\TaxRuleController::class, 'store'])->middleware('workspace.can:tasks.edit')->name('tax-rules.store');
            Route::put('tax-rules/{taxRule}',        [\App\Modules\User\Controllers\TaxRuleController::class, 'update'])->middleware('workspace.can:tasks.edit')->name('tax-rules.update');
            Route::delete('tax-rules/{taxRule}',     [\App\Modules\User\Controllers\TaxRuleController::class, 'destroy'])->middleware('workspace.can:tasks.edit')->name('tax-rules.destroy');

            // Catalog (categories + items).
            Route::get('catalog',                    [\App\Modules\User\Controllers\CatalogController::class, 'index'])->name('catalog.index');
            Route::post('catalog/categories',        [\App\Modules\User\Controllers\CatalogController::class, 'storeCategory'])->middleware('workspace.can:tasks.edit')->name('catalog.categories.store');
            Route::delete('catalog/categories/{category}', [\App\Modules\User\Controllers\CatalogController::class, 'destroyCategory'])->middleware('workspace.can:tasks.edit')->name('catalog.categories.destroy');
            Route::post('catalog/items',             [\App\Modules\User\Controllers\CatalogController::class, 'storeItem'])->middleware('workspace.can:tasks.edit')->name('catalog.items.store');
            Route::put('catalog/items/{item}',       [\App\Modules\User\Controllers\CatalogController::class, 'updateItem'])->middleware('workspace.can:tasks.edit')->name('catalog.items.update');
            Route::delete('catalog/items/{item}',    [\App\Modules\User\Controllers\CatalogController::class, 'destroyItem'])->middleware('workspace.can:tasks.edit')->name('catalog.items.destroy');

            // Expenses.
            Route::get('expenses',                   [\App\Modules\User\Controllers\ExpenseController::class, 'index'])->name('expenses.index');
            Route::post('expenses',                  [\App\Modules\User\Controllers\ExpenseController::class, 'store'])->middleware('workspace.can:tasks.edit')->name('expenses.store');
            Route::put('expenses/{expense}',         [\App\Modules\User\Controllers\ExpenseController::class, 'update'])->middleware('workspace.can:tasks.edit')->name('expenses.update');
            Route::delete('expenses/{expense}',      [\App\Modules\User\Controllers\ExpenseController::class, 'destroy'])->middleware('workspace.can:tasks.edit')->name('expenses.destroy');

            // Recurring invoices.
            Route::get('recurring',                  [\App\Modules\User\Controllers\RecurringInvoiceController::class, 'index'])->name('recurring.index');
            Route::get('recurring/create',           [\App\Modules\User\Controllers\RecurringInvoiceController::class, 'create'])->middleware('workspace.can:tasks.edit')->name('recurring.create');
            Route::post('recurring',                 [\App\Modules\User\Controllers\RecurringInvoiceController::class, 'store'])->middleware('workspace.can:tasks.edit')->name('recurring.store');
            Route::get('recurring/{recurring}/edit', [\App\Modules\User\Controllers\RecurringInvoiceController::class, 'edit'])->middleware('workspace.can:tasks.edit')->name('recurring.edit');
            Route::put('recurring/{recurring}',      [\App\Modules\User\Controllers\RecurringInvoiceController::class, 'update'])->middleware('workspace.can:tasks.edit')->name('recurring.update');
            Route::delete('recurring/{recurring}',   [\App\Modules\User\Controllers\RecurringInvoiceController::class, 'destroy'])->middleware('workspace.can:tasks.edit')->name('recurring.destroy');
            Route::post('recurring/{recurring}/toggle', [\App\Modules\User\Controllers\RecurringInvoiceController::class, 'toggle'])->middleware('workspace.can:tasks.edit')->name('recurring.toggle');
            Route::post('recurring/{recurring}/run', [\App\Modules\User\Controllers\RecurringInvoiceController::class, 'runNow'])->middleware('workspace.can:tasks.edit')->name('recurring.run');

            // Ledger / P&L report.
            Route::get('ledger',                     [\App\Modules\User\Controllers\LedgerController::class, 'index'])->name('ledger.index');
            Route::get('ledger/export',              [\App\Modules\User\Controllers\LedgerController::class, 'export'])->name('ledger.export');
        });

        // ---- Workspace Vault: encrypted credentials + client records ----
        // Reads gated by vault.view; mutations escalate to vault.create/edit/delete.
        // Reveal/decrypt and export endpoints sit under the same gate but write
        // an audit row inside the controller. Export is workspace-owner-only.
        Route::prefix('vault')->name('vault.')->middleware('workspace.can:vault.view')->group(function () {
            Route::get('/', function () { return redirect()->route('user.vault.credentials.index'); })->name('index');

            Route::get('credentials',                              [\App\Modules\User\Controllers\VaultCredentialController::class, 'index'])->name('credentials.index');
            Route::get('credentials/create',                       [\App\Modules\User\Controllers\VaultCredentialController::class, 'create'])->middleware('workspace.can:vault.create')->name('credentials.create');
            Route::post('credentials',                             [\App\Modules\User\Controllers\VaultCredentialController::class, 'store'])->middleware(['workspace.can:vault.create', CheckPlanLimit::class . ':vaults'])->name('credentials.store');
            Route::get('credentials/{credential}',                 [\App\Modules\User\Controllers\VaultCredentialController::class, 'show'])->name('credentials.show');
            Route::get('credentials/{credential}/edit',            [\App\Modules\User\Controllers\VaultCredentialController::class, 'edit'])->middleware('workspace.can:vault.edit')->name('credentials.edit');
            Route::put('credentials/{credential}',                 [\App\Modules\User\Controllers\VaultCredentialController::class, 'update'])->middleware('workspace.can:vault.edit')->name('credentials.update');
            Route::delete('credentials/{credential}',              [\App\Modules\User\Controllers\VaultCredentialController::class, 'destroy'])->middleware('workspace.can:vault.delete')->name('credentials.destroy');
            Route::post('credentials/{credential}/reveal',         [\App\Modules\User\Controllers\VaultCredentialController::class, 'reveal'])->name('credentials.reveal');

            Route::get('clients',                                  [\App\Modules\User\Controllers\VaultClientController::class, 'index'])->name('clients.index');
            Route::get('clients/create',                           [\App\Modules\User\Controllers\VaultClientController::class, 'create'])->middleware('workspace.can:vault.create')->name('clients.create');
            Route::post('clients',                                 [\App\Modules\User\Controllers\VaultClientController::class, 'store'])->middleware('workspace.can:vault.create')->name('clients.store');
            Route::get('clients/{client}',                         [\App\Modules\User\Controllers\VaultClientController::class, 'show'])->name('clients.show');
            Route::get('clients/{client}/edit',                    [\App\Modules\User\Controllers\VaultClientController::class, 'edit'])->middleware('workspace.can:vault.edit')->name('clients.edit');
            Route::put('clients/{client}',                         [\App\Modules\User\Controllers\VaultClientController::class, 'update'])->middleware('workspace.can:vault.edit')->name('clients.update');
            Route::delete('clients/{client}',                      [\App\Modules\User\Controllers\VaultClientController::class, 'destroy'])->middleware('workspace.can:vault.delete')->name('clients.destroy');
            Route::post('clients/{client}/reveal-notes',           [\App\Modules\User\Controllers\VaultClientController::class, 'revealNotes'])->name('clients.reveal-notes');

            Route::post('clients/{client}/attachments',            [\App\Modules\User\Controllers\VaultAttachmentController::class, 'storeForClient'])->middleware('workspace.can:vault.edit')->name('attachments.store');
            Route::get('attachments/{attachment}/download',        [\App\Modules\User\Controllers\VaultAttachmentController::class, 'download'])->name('attachments.download');
            Route::delete('attachments/{attachment}',              [\App\Modules\User\Controllers\VaultAttachmentController::class, 'destroy'])->middleware('workspace.can:vault.delete')->name('attachments.destroy');

            Route::get('audit',                                    [\App\Modules\User\Controllers\VaultAuditController::class, 'index'])->name('audit.index');

            Route::get('export',                                   [\App\Modules\User\Controllers\VaultExportController::class, 'show'])->middleware('workspace.owner')->name('export.show');
            Route::post('export',                                  [\App\Modules\User\Controllers\VaultExportController::class, 'download'])->middleware('workspace.owner')->name('export.download');
        });

        // ---- Cloud File Library (Drive / Dropbox / OneDrive) ----
        //
        // Permission policy:
        //   * connect / disconnect own cloud account → workspace.can:files.view
        //     (intentional — every member with library access may link their
        //     OWN accounts; we do NOT require files.create here because
        //     attaching credentials is per-user, not a workspace mutation)
        //   * add files to the shared library              → files.create
        //   * remove files from the shared library         → files.delete
        //   * manage workspace OAuth app credentials       → workspace.owner
        //
        // If product intent later changes (e.g. only contributors may link
        // accounts), elevate the start/callback/destroy gates to files.create.
        Route::get('cloud-oauth/{provider}/start',    [\App\Modules\User\Controllers\CloudOAuthController::class, 'start'])->middleware('workspace.can:files.view')->name('cloud-oauth.start');
        Route::get('cloud-oauth/{provider}/callback', [\App\Modules\User\Controllers\CloudOAuthController::class, 'callback'])->middleware('workspace.can:files.view')->name('cloud-oauth.callback');

        Route::prefix('cloud-files')->name('cloud-files.')->middleware('workspace.can:files.view')->group(function () {
            Route::get('/',                                [\App\Modules\User\Controllers\CloudFileController::class, 'index'])->name('index');
            Route::post('/',                               [\App\Modules\User\Controllers\CloudFileController::class, 'store'])->middleware('workspace.can:files.create')->name('store');
            Route::delete('{cloudFile}',                   [\App\Modules\User\Controllers\CloudFileController::class, 'destroy'])->middleware('workspace.can:files.delete')->name('destroy');

            Route::get('connections',                      [\App\Modules\User\Controllers\CloudConnectionController::class, 'index'])->name('connections');
            Route::delete('connections/{connection}',      [\App\Modules\User\Controllers\CloudConnectionController::class, 'destroy'])->name('connections.destroy');
            Route::post('connections/{connection}/dismiss-banner', [\App\Modules\User\Controllers\CloudConnectionController::class, 'dismissBanner'])->name('connections.dismiss-banner');

            Route::get('picker/{connection}',              [\App\Modules\User\Controllers\CloudFilePickerController::class, 'browse'])->name('picker.browse');

            // Library picker for composers (post / task / inbox-reply).
            // Read access mirrors the rest of the cloud-files group.
            Route::get('library',                          [\App\Modules\User\Controllers\CloudFileAttachmentController::class, 'library'])->name('library');
            Route::post('attach',                          [\App\Modules\User\Controllers\CloudFileAttachmentController::class, 'attach'])->name('attach');
            Route::delete('attach/{attachment}',           [\App\Modules\User\Controllers\CloudFileAttachmentController::class, 'destroy'])->name('attach.destroy');

            // Owner-only OAuth-app credential management.
            Route::get('settings',                         [\App\Modules\User\Controllers\CloudProviderAppController::class, 'index'])->middleware('workspace.owner')->name('settings.index');
            Route::put('settings/{provider}',              [\App\Modules\User\Controllers\CloudProviderAppController::class, 'update'])->middleware('workspace.owner')->name('settings.update');
            Route::post('settings/{provider}/test',        [\App\Modules\User\Controllers\CloudProviderAppController::class, 'test'])->middleware('workspace.owner')->name('settings.test');
            Route::delete('settings/{provider}',           [\App\Modules\User\Controllers\CloudProviderAppController::class, 'destroy'])->middleware('workspace.owner')->name('settings.destroy');
        });

        // Account verification (blue-tick request) — workspace-account-level.
        Route::prefix('settings/verification')->name('verification.')->middleware('workspace.can:settings.view')->group(function () {
            Route::get('/', [VerificationController::class, 'index'])->name('index');
            Route::get('request', [VerificationController::class, 'create'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':verification_eligible'])->name('request');
            Route::post('request', [VerificationController::class, 'store'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':verification_eligible'])->name('store');
            Route::post('blocks/{block}/toggle', [VerificationController::class, 'toggleBlock'])->middleware('workspace.can:settings.edit')->name('block.toggle');
        });

        // Self-serve account badge requests (Task #2910) — users ask for an
        // existing or custom account badge; admins review from their own
        // queue. Mirrors the account-verification gate.
        Route::prefix('settings/verification/badges')->name('badge-requests.')->middleware('workspace.can:settings.view')->group(function () {
            Route::get('/',  [\App\Modules\User\Controllers\BadgeRequestController::class, 'index'])->name('index');
            Route::post('/', [\App\Modules\User\Controllers\BadgeRequestController::class, 'store'])->middleware('workspace.can:settings.edit')->name('store');
            // Creator → creator badge gifting (Task #3045): live handle lookup
            // plus the give action (a creator passes on a badge they hold).
            Route::get('give/lookup', [\App\Modules\User\Controllers\BadgeRequestController::class, 'lookupHandle'])
                ->middleware('throttle:60,1')->name('give.lookup');
            Route::post('give', [\App\Modules\User\Controllers\BadgeRequestController::class, 'give'])
                ->middleware('workspace.can:settings.edit')->name('give');
        });

        Route::middleware('user.can:user.plans.manage')->group(function () {
            Route::resource('plans', PlanManagementController::class)->except(['show']);
        });

        Route::middleware('user.can:user.verifications.review')->group(function () {
            Route::get('verification-admin', [VerificationController::class, 'adminIndex'])->name('verification.admin');
            Route::get('verification-admin/{verificationRequest}', [VerificationController::class, 'adminReview'])->name('verification.admin.review');
            Route::post('verification-admin/{verificationRequest}/approve', [VerificationController::class, 'adminApprove'])->name('verification.admin.approve');
            Route::post('verification-admin/{verificationRequest}/reject', [VerificationController::class, 'adminReject'])->name('verification.admin.reject');
        });

        // ===================================================================
        // Settings hub (Task #3220)
        // -------------------------------------------------------------------
        // Every scattered account/settings surface now lives under the single
        // /user/settings/{tab} hub (see SettingsTabs + user.layouts.settings).
        // The real GET landing routes were repointed in-place above (keeping
        // their names + controllers + middleware). What lives here is:
        //   1. the Billing & Identity tab landing route (moved out of the
        //      billing prefix group so it can sit at /settings/billing while
        //      keeping the `user.billing.companies.index` name), and
        //   2. legacy-URL redirects so old bookmarks/links still resolve.
        //
        // These redirects are registered LAST on purpose: Route::redirect
        // responds to any verb, so placing them after every real POST/PUT/
        // DELETE route guarantees the real routes win for their own methods
        // and the redirects only catch stale GET landings.
        // ===================================================================
        Route::get('settings/billing', [\App\Modules\User\Controllers\BillingCompanyController::class, 'index'])
            ->middleware('workspace.can:tasks.view')
            ->name('billing.companies.index');

        // Hub root → default (Profile) tab.
        Route::redirect('settings', 'user/settings/profile');

        // Legacy landing-URL redirects into the corresponding hub tab.
        Route::redirect('profile',                  'user/settings/profile');
        Route::redirect('creator-profile',          'user/settings/creator');
        Route::redirect('account/two-factor',       'user/settings/security');
        Route::redirect('security/logins',          'user/settings/security/logins');
        Route::redirect('settings/sessions',        'user/settings/security/devices');
        Route::redirect('merge',                    'user/settings/security/merge');
        Route::redirect('social-accounts',          'user/settings/connections');
        Route::redirect('connected-apps',           'user/settings/connections/apps');
        Route::redirect('integrations',             'user/settings/integrations');
        Route::redirect('domains',                  'user/settings/domains');
        Route::redirect('notifications/preferences', 'user/settings/notifications');
        Route::redirect('billing/companies',        'user/settings/billing');
        Route::redirect('api-keys',                 'user/settings/developer');
        Route::redirect('verification',             'user/settings/verification');
        Route::redirect('badge-requests',           'user/settings/verification/badges');

        // Self-service "who has admin powers on the user side" page. Gated
        // by the `user.roles.manage` permission so only operators that
        // already hold the user-admin role (or anyone explicitly given
        // user.roles.manage) can promote/demote others.
        Route::middleware('user.can:user.roles.manage')->prefix('access')->name('access.')->group(function () {
            Route::get ('users',                   [UserAccessController::class, 'index'])->name('users.index');
            Route::get ('users/audit.csv',         [UserAccessController::class, 'export'])->name('users.audit.export');
            Route::post('users/{user}/roles',      [UserAccessController::class, 'update'])->whereNumber('user')->name('users.update');

            // Full role-change audit log + CSV export. Same gate as
            // the rest of the access pages — anyone who can promote
            // can also see who promoted whom.
            Route::get('audit',        [UserAccessController::class, 'audit'])->name('audit.index');
            Route::get('audit/export', [UserAccessController::class, 'auditExport'])->name('audit.export');

            // CRUD for the user-pool roles themselves. Lets operators
            // create/rename/delete roles and edit their permission
            // checklists from the UI instead of hand-editing seeds.
            Route::get   ('roles',              [RoleManagementController::class, 'index'])->name('roles.index');
            Route::get   ('roles/create',       [RoleManagementController::class, 'create'])->name('roles.create');
            Route::post  ('roles',              [RoleManagementController::class, 'store'])->name('roles.store');
            Route::get   ('roles/{role}/edit',  [RoleManagementController::class, 'edit'])->whereNumber('role')->name('roles.edit');
            Route::put   ('roles/{role}',       [RoleManagementController::class, 'update'])->whereNumber('role')->name('roles.update');
            Route::delete('roles/{role}',       [RoleManagementController::class, 'destroy'])->whereNumber('role')->name('roles.destroy');
        });
    });
});
