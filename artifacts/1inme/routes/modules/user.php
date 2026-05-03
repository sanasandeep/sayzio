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
use App\Modules\User\Middleware\SuperAdmin;

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

Route::prefix('user')->name('user.')->group(function () {
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    // Registration was previously unthrottled — easy spam-farm vector.
    // Now keyed on IP via the auth-register limiter (3/min, 20/hour).
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:auth-register')
        ->name('register.submit');
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
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

    Route::get('verify-email', [AuthController::class, 'showVerifyEmail'])->middleware('auth')->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['auth', 'signed'])->name('verification.verify');
    Route::post('verify-email/resend', [AuthController::class, 'resendVerification'])->middleware(['auth', 'throttle:6,1'])->name('verification.send');

    // ---- Workspace invite landing (public — no workspace context yet) ----
    Route::get('workspaces/invites/{token}', [\App\Modules\User\Controllers\AcceptInviteController::class, 'show'])
        ->name('workspaces.invite.show');
    // Public on purpose: controller stashes the token in session and redirects
    // unauthenticated visitors to the OTP signup flow, then auto-attaches the
    // invite when the new account is verified.
    Route::post('workspaces/invites/{token}/accept', [\App\Modules\User\Controllers\AcceptInviteController::class, 'accept'])
        ->name('workspaces.invite.accept');

    Route::middleware(['auth', 'workspace.scope'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [DashboardController::class, 'index'])->middleware('onboarding.gate')->name('dashboard');

        // ---- Workspaces ----
        Route::post('workspaces',                              [\App\Modules\User\Controllers\WorkspaceController::class, 'store'])  ->name('workspaces.store');
        Route::post('workspaces/access-request',               [\App\Modules\User\Controllers\WorkspaceController::class, 'requestAccess'])
            ->middleware('throttle:6,60')
            ->name('workspaces.request-access');
        Route::post('workspaces/{workspace}/switch',           [\App\Modules\User\Controllers\WorkspaceController::class, 'switch']) ->name('workspaces.switch');
        Route::put ('workspaces/{workspace}',                  [\App\Modules\User\Controllers\WorkspaceController::class, 'update']) ->name('workspaces.update');
        Route::delete('workspaces/{workspace}',                [\App\Modules\User\Controllers\WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

        // ---- Team (members + invites) ----
        Route::get   ('team',                                  [\App\Modules\User\Controllers\TeamController::class, 'index'])  ->name('team.index');
        Route::post  ('team/invite',                           [\App\Modules\User\Controllers\TeamController::class, 'invite']) ->name('team.invite');
        Route::post  ('team/invites/{invite}/resend',          [\App\Modules\User\Controllers\TeamController::class, 'resend']) ->name('team.invites.resend');
        Route::delete('team/invites/{invite}',                 [\App\Modules\User\Controllers\TeamController::class, 'revoke']) ->name('team.invites.revoke');
        Route::put   ('team/members/{member}',                 [\App\Modules\User\Controllers\TeamController::class, 'updateMember'])->name('team.members.update');
        Route::delete('team/members/{member}',                 [\App\Modules\User\Controllers\TeamController::class, 'removeMember'])->name('team.members.remove');

        // ---- Roles & Permissions (Owner + Admin) ----
        Route::get   ('team/roles',                            [\App\Modules\User\Controllers\WorkspaceRolesController::class, 'index']) ->name('team.roles.index');
        Route::put   ('team/roles',                            [\App\Modules\User\Controllers\WorkspaceRolesController::class, 'update'])->name('team.roles.update');
        Route::post  ('team/roles/reset',                      [\App\Modules\User\Controllers\WorkspaceRolesController::class, 'reset']) ->name('team.roles.reset');

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
        });

        // ===== Social: followers, posts, notifications (dashboard) =====
        Route::get('followers', [\App\Modules\User\Controllers\FollowController::class, 'followers'])->middleware('workspace.can:followers.view')->name('followers.index');
        Route::get('following', [\App\Modules\User\Controllers\FollowController::class, 'following'])->middleware('workspace.can:followers.view')->name('following.index');
        Route::get('posts',  [\App\Modules\User\Controllers\CreatorPostController::class, 'index'])->middleware('workspace.can:posts.view')->name('posts.index');
        Route::post('posts', [\App\Modules\User\Controllers\CreatorPostController::class, 'store'])->middleware('workspace.can:posts.create')->name('posts.store');
        Route::post('posts/{post}/pin', [\App\Modules\User\Controllers\CreatorPostController::class, 'pin'])->middleware('workspace.can:posts.edit')->name('posts.pin');
        Route::post('posts/{post}/unpin', [\App\Modules\User\Controllers\CreatorPostController::class, 'unpin'])->middleware('workspace.can:posts.edit')->name('posts.unpin');
        Route::delete('posts/{post}', [\App\Modules\User\Controllers\CreatorPostController::class, 'destroy'])->middleware('workspace.can:posts.delete')->name('posts.destroy');
        // Notifications are scoped to the signed-in user (not the workspace
        // owner) — every team member has their own notification feed.
        Route::get('notifications',  [\App\Modules\User\Controllers\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read', [\App\Modules\User\Controllers\NotificationController::class, 'markRead'])->name('notifications.read');
        Route::get('notifications/preferences', [\App\Modules\User\Controllers\NotificationController::class, 'preferences'])->name('notifications.preferences');
        Route::put('notifications/preferences', [\App\Modules\User\Controllers\NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');

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
        Route::prefix('profile')->name('profile.')->middleware('workspace.can:settings.view')->group(function () {
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
            Route::put  ('summary',           [\App\Modules\User\Controllers\ResumeController::class, 'updateSummary'])->name('summary.update');
            Route::put  ('template',          [\App\Modules\User\Controllers\ResumeController::class, 'updateTemplate'])->name('template.update');
            Route::put  ('color-theme',       [\App\Modules\User\Controllers\ResumeController::class, 'updateColorTheme'])->name('color-theme.update');

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
        Route::get('forms/{form}/embed', [FormController::class, 'embed'])->middleware('workspace.can:inbox.view')->name('forms.embed');
        Route::get('forms/{form}/submissions', [FormController::class, 'submissions'])->middleware('workspace.can:inbox.view')->name('forms.submissions');
        Route::get('forms/{form}/submissions/export', [FormController::class, 'exportSubmissions'])->middleware('workspace.can:inbox.view')->name('forms.submissions.export');
        Route::get('forms/{form}/submissions/{submission}', [FormController::class, 'showSubmission'])->middleware('workspace.can:inbox.view')->name('forms.submissions.show');
        Route::post('forms/{form}/submissions/{submission}/star', [FormController::class, 'toggleSubmissionStar'])->middleware('workspace.can:inbox.edit')->name('forms.submissions.star');
        Route::delete('forms/{form}/submissions/{submission}', [FormController::class, 'destroySubmission'])->middleware('workspace.can:inbox.delete')->name('forms.submissions.destroy');
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

        Route::resource('links', LinkController::class)->except(['store', 'update', 'destroy'])->middleware('workspace.can:links.view');
        Route::put('links/{link}',  [LinkController::class, 'update'])->middleware('workspace.can:links.edit')->name('links.update');
        Route::patch('links/{link}',[LinkController::class, 'update'])->middleware('workspace.can:links.edit');
        Route::delete('links/{link}', [LinkController::class, 'destroy'])->middleware('workspace.can:links.delete')->name('links.destroy');
        Route::post('links/choose-type', [LinkController::class, 'chooseType'])->middleware('workspace.can:links.create')->name('links.choose-type');
        Route::get('links-url/create', [LinkController::class, 'createUrl'])->middleware('workspace.can:links.create')->name('links.url.create');
        Route::get('links-biolink/create', [LinkController::class, 'createBiolink'])->middleware('workspace.can:links.create')->name('links.biolink.create');
        Route::post('links', [LinkController::class, 'store'])->middleware(['workspace.can:links.create', CheckPlanLimit::class . ':links'])->name('links.store');
        Route::post('links/{link}/toggle-active', [LinkController::class, 'toggleActive'])->middleware('workspace.can:links.edit')->name('links.toggle-active');
        Route::post('links/{link}/duplicate', [LinkController::class, 'duplicate'])->middleware(['workspace.can:links.create', CheckPlanLimit::class . ':links'])->name('links.duplicate');
        // Cross-workspace move (owner-only — see LinkController::move).
        Route::post('links/{link}/move',  [LinkController::class, 'move'])->middleware('workspace.can:links.edit')->name('links.move');
        Route::post('links/move-bulk',    [LinkController::class, 'moveBulk'])->middleware('workspace.can:links.edit')->name('links.move-bulk');
        Route::post('links/{link}/coach-action', [LinkController::class, 'coachAction'])->middleware('workspace.can:links.edit')->name('links.coach-action');
        Route::post('links/{link}/performance-coach/settings', [LinkController::class, 'updatePerformanceCoachSettings'])->middleware('workspace.can:links.edit')->name('links.performance-coach.settings');
        Route::post('links/coach-undo', [LinkController::class, 'coachUndo'])->middleware('workspace.can:links.edit')->name('links.coach-undo');
        Route::delete('links/{link}/stats', [LinkController::class, 'resetStats'])->middleware('workspace.can:links.delete')->name('links.reset-stats');
        Route::put('links/{link}/alias', [LinkController::class, 'updateAlias'])->middleware('workspace.can:links.edit')->name('links.update-alias');
        // Mint a fresh signed preview URL for the editor's device-preview iframe.
        // Called by the editor when the existing 24h URL is about to expire so
        // the iframe never falls into Laravel's "Invalid signature" page.
        Route::get('links/{link}/preview-url', [LinkController::class, 'previewUrl'])->middleware('workspace.can:links.view')->name('links.preview-url');

        // Additional (alternative) aliases per link — same page served, no redirect.
        Route::post('links/{link}/aliases', [\App\Modules\User\Controllers\LinkAliasController::class, 'store'])->middleware('workspace.can:links.edit')->name('links.aliases.store');
        Route::delete('links/{link}/aliases/{alias}', [\App\Modules\User\Controllers\LinkAliasController::class, 'destroy'])->middleware('workspace.can:links.edit')->name('links.aliases.destroy');
        Route::post('links/{link}/aliases/{alias}/promote', [\App\Modules\User\Controllers\LinkAliasController::class, 'promote'])->middleware('workspace.can:links.edit')->name('links.aliases.promote');

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

        // Biolink blocks live under a link — same gating as the parent link.
        Route::get('links/{link}/blocks', [BiolinkBlockController::class, 'editor'])->middleware('workspace.can:links.view')->name('links.blocks.editor');
        Route::get('links/{link}/settings', [BiolinkBlockController::class, 'settings'])->middleware('workspace.can:links.view')->name('links.blocks.settings');
        Route::get('links/{link}/settings/appearance', [BiolinkBlockController::class, 'settingsAppearance'])->middleware('workspace.can:links.view')->name('links.settings.appearance');
        Route::get('links/{link}/settings/layout', [BiolinkBlockController::class, 'settingsLayout'])->middleware('workspace.can:links.view')->name('links.settings.layout');
        Route::get('links/{link}/settings/block-theme', [BiolinkBlockController::class, 'settingsBlockTheme'])->middleware('workspace.can:links.view')->name('links.settings.block-theme');
        Route::get('links/{link}/settings/advanced', [BiolinkBlockController::class, 'settingsAdvanced'])->middleware('workspace.can:links.view')->name('links.settings.advanced');
        Route::post('links/{link}/blocks', [BiolinkBlockController::class, 'store'])->middleware('workspace.can:links.edit')->name('links.blocks.store');
        Route::put('links/{link}/blocks/{block}', [BiolinkBlockController::class, 'update'])->middleware('workspace.can:links.edit')->name('links.blocks.update');
        Route::get('links/{link}/blocks/{block}/edit-form', [BiolinkBlockController::class, 'editForm'])->middleware('workspace.can:links.view')->name('links.blocks.editForm');
        Route::delete('links/{link}/blocks/{block}', [BiolinkBlockController::class, 'destroy'])->middleware('workspace.can:links.edit')->name('links.blocks.destroy');
        Route::post('links/{link}/blocks/reorder', [BiolinkBlockController::class, 'reorder'])->middleware('workspace.can:links.edit')->name('links.blocks.reorder');
        Route::post('links/{link}/blocks/{block}/toggle', [BiolinkBlockController::class, 'toggleActive'])->middleware('workspace.can:links.edit')->name('links.blocks.toggle');
        Route::post('links/{link}/blocks/{block}/move', [BiolinkBlockController::class, 'moveBlock'])->middleware('workspace.can:links.edit')->name('links.blocks.move');
        Route::post('links/{link}/page-settings', [BiolinkBlockController::class, 'updatePageSettings'])->middleware('workspace.can:links.edit')->name('links.page-settings');
        Route::post('links/{link}/preview-draft', [BiolinkBlockController::class, 'previewDraft'])->middleware('workspace.can:links.edit')->name('links.preview-draft');

        // Plan upgrade, checkout & billing — these touch the workspace
        // owner's subscription/wallet/invoices, so they remain owner-only
        // regardless of any member's role inside the workspace.
        Route::get('upgrade', [\App\Modules\User\Controllers\UpgradeController::class, 'show'])->middleware('workspace.owner')->name('upgrade');
        Route::post('upgrade/switch-currency', [\App\Modules\User\Controllers\UpgradeController::class, 'switchCurrency'])->middleware('workspace.owner')->name('upgrade.switch-currency');
        Route::post('upgrade/activate', [\App\Modules\User\Controllers\UpgradeController::class, 'activate'])->middleware('workspace.owner')->name('upgrade.activate');

        // Checkout: plan+addons cart, tax preview, gateway picker, handoff.
        Route::get('checkout', [\App\Modules\User\Controllers\CheckoutController::class, 'show'])->middleware('workspace.owner')->name('checkout.show');
        Route::post('checkout/handoff', [\App\Modules\User\Controllers\CheckoutController::class, 'handoff'])->middleware('workspace.owner')->name('checkout.handoff');

        // Billing dashboard (subscription lifecycle, invoices, refunds, credit notes).
        Route::get('billing', [\App\Modules\User\Controllers\BillingController::class, 'show'])->middleware('workspace.owner')->name('billing.show');
        Route::get('billing/upgrade', [\App\Modules\User\Controllers\BillingController::class, 'upgrade'])->middleware('workspace.owner')->name('billing.upgrade');
        Route::post('billing/upgrade/confirm', [\App\Modules\User\Controllers\BillingController::class, 'upgradeConfirm'])->middleware('workspace.owner')->name('billing.upgrade.confirm');
        Route::post('billing/upgrade/handoff', [\App\Modules\User\Controllers\BillingController::class, 'upgradeHandoff'])->middleware('workspace.owner')->name('billing.upgrade.handoff');
        Route::post('billing/cancel', [\App\Modules\User\Controllers\BillingController::class, 'cancel'])->middleware('workspace.owner')->name('billing.cancel');
        Route::post('billing/resume', [\App\Modules\User\Controllers\BillingController::class, 'resume'])->middleware('workspace.owner')->name('billing.resume');
        Route::post('billing/invoices/{invoice}/refund', [\App\Modules\User\Controllers\BillingController::class, 'refundInvoice'])->middleware('workspace.owner')->name('billing.refund');
        Route::get('billing/credit-notes/{creditNote}.pdf', [\App\Modules\User\Controllers\BillingController::class, 'creditNotePdf'])->middleware('workspace.owner')->name('billing.credit-note.pdf');

        // Wallet & coins (customer-facing).
        Route::get ('wallet',                [\App\Modules\User\Controllers\WalletController::class, 'show'])->name('wallet.show');
        Route::get ('wallet/transactions',   [\App\Modules\User\Controllers\WalletController::class, 'transactions'])->name('wallet.transactions');
        Route::get ('wallet/buy',            [\App\Modules\User\Controllers\WalletController::class, 'buy'])->name('wallet.buy');
        Route::post('wallet/buy',            [\App\Modules\User\Controllers\WalletController::class, 'buyHandoff'])->name('wallet.buy.handoff');
        Route::post('addons/{addon}/activate-with-coins', [\App\Modules\User\Controllers\WalletController::class, 'activateAddon'])->name('addons.activate-with-coins');

        // AI credits — separate ledger from the wallet. Buying converts
        // wallet coins into AI credits at the admin-set rate.
        Route::get ('ai-credits',              [\App\Modules\User\Controllers\AiCreditsController::class, 'show'])->name('ai-credits.show');
        Route::get ('ai-credits/transactions', [\App\Modules\User\Controllers\AiCreditsController::class, 'transactions'])->name('ai-credits.transactions');
        Route::post('ai-credits/buy',          [\App\Modules\User\Controllers\AiCreditsController::class, 'buy'])->name('ai-credits.buy');

        // ---- AI features (spend credits via OpenAiService) ----
        // Each feature charges through OpenAiService::chat() with a
        // unique `feature` tag so admin reporting can attribute spend
        // back to the right product on /admin/ai-usage.
        Route::prefix('ai')->name('ai.')->group(function () {
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

            // Voice Assistant — floating mic on every page. STT/LLM/TTS
            // are billed separately (`voice_stt`, `voice_llm`, `voice_tts`).
            Route::get ('voice/capabilities', [\App\Modules\User\Controllers\AI\VoiceAssistantController::class, 'capabilities'])->name('voice.capabilities');
            Route::post('voice/turn',         [\App\Modules\User\Controllers\AI\VoiceAssistantController::class, 'turn'])->middleware('throttle:30,1')->name('voice.turn');
        });

        // AI Minds — labelled knowledge bases (text/docs/FAQs/links/
        // 1INME features) every AI Persona / Coach can draw on. Note:
        // distinct from the stateless `ai/mind` summary tool above.
        Route::prefix('minds')->name('minds.')->group(function () {
            Route::get ('/',                   [\App\Modules\User\Controllers\MindController::class, 'index'])->name('index');
            Route::get ('create',              [\App\Modules\User\Controllers\MindController::class, 'create'])->name('create');
            Route::post('/',                   [\App\Modules\User\Controllers\MindController::class, 'store'])->name('store');
            Route::get ('{mind}',              [\App\Modules\User\Controllers\MindController::class, 'edit'])->whereNumber('mind')->name('edit');
            Route::put ('{mind}',              [\App\Modules\User\Controllers\MindController::class, 'update'])->whereNumber('mind')->name('update');
            Route::delete('{mind}',            [\App\Modules\User\Controllers\MindController::class, 'destroy'])->whereNumber('mind')->name('destroy');
            Route::post('{mind}/refresh',      [\App\Modules\User\Controllers\MindController::class, 'refresh'])->whereNumber('mind')->name('refresh');
            // Sources
            Route::get ('{mind}/sources/{source}', [\App\Modules\User\Controllers\MindSourceController::class, 'show'])->whereNumber('mind')->whereNumber('source')->name('sources.show');
            Route::post('{mind}/sources',      [\App\Modules\User\Controllers\MindSourceController::class, 'store'])->whereNumber('mind')->name('sources.store');
            Route::post('{mind}/sources/{source}/refresh', [\App\Modules\User\Controllers\MindSourceController::class, 'refresh'])->whereNumber('mind')->whereNumber('source')->name('sources.refresh');
            Route::delete('{mind}/sources/{source}', [\App\Modules\User\Controllers\MindSourceController::class, 'destroy'])->whereNumber('mind')->whereNumber('source')->name('sources.destroy');
            // Test chat — AJAX in-page panel.
            Route::post('{mind}/ask',          [\App\Modules\User\Controllers\MindChatController::class, 'ask'])->whereNumber('mind')->middleware('throttle:20,1')->name('ask');
        });

        // AI Personas — configurable conversational agents that the
        // user later wires into widgets / inbox / Coach. Each save
        // writes a new ai_persona_versions row and can be rolled back.
        Route::prefix('ai-personas')->name('ai-personas.')->group(function () {
            Route::get   ('/',                 [\App\Modules\User\Controllers\AI\PersonasController::class, 'index'])->name('index');
            Route::get   ('create',            [\App\Modules\User\Controllers\AI\PersonasController::class, 'create'])->name('create');
            Route::post  ('/',                 [\App\Modules\User\Controllers\AI\PersonasController::class, 'store'])->name('store');
            Route::get   ('{persona}',         [\App\Modules\User\Controllers\AI\PersonasController::class, 'edit'])->whereNumber('persona')->name('edit');
            Route::put   ('{persona}',         [\App\Modules\User\Controllers\AI\PersonasController::class, 'update'])->whereNumber('persona')->name('update');
            Route::delete('{persona}',         [\App\Modules\User\Controllers\AI\PersonasController::class, 'destroy'])->whereNumber('persona')->name('destroy');
            Route::post  ('{persona}/duplicate',[\App\Modules\User\Controllers\AI\PersonasController::class, 'duplicate'])->whereNumber('persona')->name('duplicate');
            Route::post  ('{persona}/versions/{version}/rollback', [\App\Modules\User\Controllers\AI\PersonasController::class, 'rollback'])->whereNumber('persona')->whereNumber('version')->name('rollback');
            Route::post  ('{persona}/test',    [\App\Modules\User\Controllers\AI\PersonasController::class, 'test'])->whereNumber('persona')->middleware('throttle:20,1')->name('test');
        });

        // AI Companions — placement-bound chatbots that bind a Persona
        // to a biolink block, an external embed snippet, or the inbox
        // auto-reply bot. CRUD + conversation browser + analytics.
        Route::prefix('ai-companions')->name('ai-companions.')->group(function () {
            Route::get   ('/',                            [\App\Modules\User\Controllers\AI\CompanionsController::class, 'index'])->name('index');
            Route::get   ('create',                       [\App\Modules\User\Controllers\AI\CompanionsController::class, 'create'])->name('create');
            Route::post  ('/',                            [\App\Modules\User\Controllers\AI\CompanionsController::class, 'store'])->name('store');
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
        Route::get('social-accounts',                          [\App\Modules\User\Controllers\SocialAccountController::class, 'index'])->middleware('workspace.can:settings.view')->name('social-accounts.index');
        Route::post('social-accounts',                         [\App\Modules\User\Controllers\SocialAccountController::class, 'store'])->middleware('workspace.can:settings.edit')->name('social-accounts.store');
        Route::post('social-accounts/{connection}/refresh',    [\App\Modules\User\Controllers\SocialAccountController::class, 'refresh'])->middleware('workspace.can:settings.edit')->name('social-accounts.refresh');
        Route::delete('social-accounts/{connection}',          [\App\Modules\User\Controllers\SocialAccountController::class, 'destroy'])->middleware('workspace.can:settings.edit')->name('social-accounts.destroy');
        Route::post('social-accounts/broken-emails/preference', [\App\Modules\User\Controllers\SocialAccountController::class, 'updateBrokenEmailPreference'])->middleware('workspace.can:settings.edit')->name('social-accounts.broken-emails.preference');

        // OAuth connect / callback for providers that need a per-user token.
        // Each provider activates only when its CLIENT_ID + CLIENT_SECRET env
        // vars are set; otherwise the UI falls back to manual token paste.
        Route::get('social-oauth/{provider}/connect',  [\App\Modules\User\Controllers\SocialOAuthController::class, 'connect'])->middleware('workspace.can:settings.edit')->name('social-oauth.connect');
        Route::get('social-oauth/{provider}/merge',    [\App\Modules\User\Controllers\SocialOAuthController::class, 'mergeConnect'])->middleware('workspace.can:settings.edit')->name('social-oauth.merge');

        // Linked identifiers (multi-identity account settings).
        Route::prefix('identifiers')->name('identifiers.')->middleware('workspace.can:settings.view')->group(function () {
            Route::get('/',                                [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'index'])->name('index');
            Route::post('start',                           [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'start'])->middleware('throttle:5,1')->name('start');
            Route::post('confirm',                         [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'confirm'])->middleware('throttle:10,1')->name('confirm');
            Route::delete('{identifier}',                  [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'destroy'])->name('destroy');
            Route::post('{identifier}/promote',            [\App\Modules\User\Controllers\LinkedIdentifierController::class, 'promote'])->name('promote');
        });

        // Account merge flow.
        Route::prefix('merge')->name('merge.')->middleware('workspace.can:settings.edit')->group(function () {
            Route::get('/',           [\App\Modules\User\Controllers\AccountMergeController::class, 'start'])->name('start');
            Route::post('challenge',  [\App\Modules\User\Controllers\AccountMergeController::class, 'challenge'])->middleware('throttle:5,1')->name('challenge');
            Route::get('preview',     [\App\Modules\User\Controllers\AccountMergeController::class, 'preview'])->name('preview');
            Route::post('confirm',    [\App\Modules\User\Controllers\AccountMergeController::class, 'confirm'])->name('confirm');
            Route::post('cancel',     [\App\Modules\User\Controllers\AccountMergeController::class, 'cancel'])->name('cancel');
        });

        Route::get('integrations',                       [\App\Modules\User\Controllers\IntegrationConfigController::class, 'index'])->middleware('workspace.can:settings.view')->name('integrations.index');
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
        Route::get('contacts',                              [ContactController::class, 'index'])->middleware('workspace.can:settings.view')->name('contacts.index');
        Route::get('contacts/create',                       [ContactController::class, 'create'])->middleware('workspace.can:settings.edit')->name('contacts.create');
        Route::post('contacts',                             [ContactController::class, 'store'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':contacts_max', CheckPlanLimit::class . ':leads'])->name('contacts.store');
        Route::get('contacts/import',                       [ContactController::class, 'importForm'])->middleware('workspace.can:settings.edit')->name('contacts.import');
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

        // Google Contacts OAuth + sync.
        Route::get('contacts/google/connect',               [GoogleContactsAccountController::class, 'connect'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':contacts_google_sync'])->name('contacts.google.connect');
        Route::get('contacts/google/callback',              [GoogleContactsAccountController::class, 'callback'])->middleware('workspace.can:settings.edit')->name('contacts.google.callback');
        Route::post('contacts/google/{account}/sync',       [GoogleContactsAccountController::class, 'syncNow'])->middleware('workspace.can:settings.edit')->name('contacts.google.sync');
        Route::delete('contacts/google/{account}',          [GoogleContactsAccountController::class, 'destroy'])->middleware('workspace.can:settings.edit')->name('contacts.google.destroy');

        // Dialer.
        Route::get('dialer',                                [DialerController::class, 'index'])->middleware('workspace.can:settings.view')->name('dialer.index');
        Route::get('dialer/profile',                        [DialerController::class, 'profile'])->middleware('workspace.can:settings.view')->name('dialer.profile');

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
        Route::post  ('links/{link}/rsvps/erase-voter',     [RsvpController::class, 'eraseVoter'])->middleware('workspace.can:followers.edit')->name('links.rsvps.erase-voter');

        // ===== Poll votes (per biolink-block) — followers feature.
        Route::get('links/{link}/blocks/{block}/poll-votes',          [PollVoteController::class, 'index'])->middleware('workspace.can:followers.view')->name('links.poll-votes.index');
        Route::get('links/{link}/blocks/{block}/poll-votes/export',   [PollVoteController::class, 'export'])->middleware('workspace.can:followers.view')->name('links.poll-votes.export');
        Route::delete('links/{link}/blocks/{block}/poll-votes/{vote}',[PollVoteController::class, 'destroy'])->middleware('workspace.can:followers.edit')->name('links.poll-votes.destroy');
        Route::post  ('links/{link}/blocks/{block}/poll-votes/erase-voter',[PollVoteController::class, 'eraseVoter'])->middleware('workspace.can:followers.edit')->name('links.poll-votes.erase-voter');
        Route::get   ('links/{link}/blocks/{block}/poll-votes/erasures',  [PollVoteController::class, 'erasures'])->middleware('workspace.can:followers.view')->name('links.poll-votes.erasures');
        Route::post('links/{link}/blocks/{block}/poll-votes/reset',   [PollVoteController::class, 'reset'])->middleware('workspace.can:followers.edit')->name('links.poll-votes.reset');
        Route::post('links/{link}/blocks/{block}/poll-votes/snapshots/{snapshot}/undo', [PollVoteController::class, 'undoReset'])->middleware('workspace.can:followers.edit')->name('links.poll-votes.undo-reset');
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

        // Pixels — analytics tags, gated under stats feature.
        Route::resource('pixels', PixelController::class)->only(['index'])->middleware('workspace.can:stats.view');
        Route::resource('pixels', PixelController::class)->except(['show', 'store', 'index'])->middleware('workspace.can:stats.edit');
        Route::post('pixels', [PixelController::class, 'store'])->middleware(['workspace.can:stats.edit', CheckPlanLimit::class . ':pixels'])->name('pixels.store');

        // Custom domains — workspace settings.
        Route::prefix('domains')->name('domains.')->middleware([CheckPlanLimit::class . ':custom_domains'])->group(function () {
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
        Route::prefix('verification')->name('verification.')->middleware('workspace.can:settings.view')->group(function () {
            Route::get('/', [VerificationController::class, 'index'])->name('index');
            Route::get('request', [VerificationController::class, 'create'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':verification_eligible'])->name('request');
            Route::post('request', [VerificationController::class, 'store'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':verification_eligible'])->name('store');
            Route::post('blocks/{block}/toggle', [VerificationController::class, 'toggleBlock'])->middleware('workspace.can:settings.edit')->name('block.toggle');
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
