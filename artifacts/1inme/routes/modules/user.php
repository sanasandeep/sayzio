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

        // First-run onboarding wizard
        Route::prefix('onboarding')->name('onboarding.')->group(function () {
            Route::get('persona',  [\App\Modules\User\Controllers\OnboardingController::class, 'persona'])->name('persona');
            Route::post('persona', [\App\Modules\User\Controllers\OnboardingController::class, 'savePersona'])->name('persona.save');
            Route::get('template', [\App\Modules\User\Controllers\OnboardingController::class, 'template'])->name('template');
            Route::post('template',[\App\Modules\User\Controllers\OnboardingController::class, 'applyTemplate'])->name('template.apply');
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

        Route::get('links/{link}/visitors', [\App\Modules\User\Controllers\VisitorAnalyticsController::class, 'index'])->middleware('workspace.can:stats.view')->name('links.visitors');
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

        // ---- Forms ----
        // Forms feed inbox — gate read endpoints with `inbox.view` and
        // mutations with the matching create/edit/delete actions so a
        // view-only or reply-only member cannot reshape the form, while
        // submissions starring/destroy live under inbox.edit/inbox.delete.
        Route::get('forms', [FormController::class, 'index'])->middleware('workspace.can:inbox.view')->name('forms.index');
        Route::get('forms/create', [FormController::class, 'create'])->middleware('workspace.can:inbox.create')->name('forms.create');
        Route::post('forms', [FormController::class, 'store'])->middleware('workspace.can:inbox.create')->name('forms.store');
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

        // Additional (alternative) aliases per link — same page served, no redirect.
        Route::post('links/{link}/aliases', [\App\Modules\User\Controllers\LinkAliasController::class, 'store'])->middleware('workspace.can:links.edit')->name('links.aliases.store');
        Route::delete('links/{link}/aliases/{alias}', [\App\Modules\User\Controllers\LinkAliasController::class, 'destroy'])->middleware('workspace.can:links.edit')->name('links.aliases.destroy');
        Route::post('links/{link}/aliases/{alias}/promote', [\App\Modules\User\Controllers\LinkAliasController::class, 'promote'])->middleware('workspace.can:links.edit')->name('links.aliases.promote');

        Route::get('links-file/create', [FileLinkController::class, 'create'])->middleware('workspace.can:links.create')->name('links.file.create');
        Route::post('links-file', [FileLinkController::class, 'store'])->middleware(['workspace.can:links.create', CheckPlanLimit::class . ':links'])->name('links.file.store');
        Route::get('links-ics/create', [IcsLinkController::class, 'create'])->middleware('workspace.can:links.create')->name('links.ics.create');
        Route::post('links-ics', [IcsLinkController::class, 'store'])->middleware(['workspace.can:links.create', CheckPlanLimit::class . ':links'])->name('links.ics.store');
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

        // Page & card templates (admin-curated presets) — picker reads
        // require links.view; apply mutates the link so requires links.edit.
        Route::get('links/{link}/templates', [\App\Modules\User\Controllers\LinkTemplateController::class, 'picker'])->middleware('workspace.can:links.view')->name('links.templates.picker');
        Route::post('links/{link}/templates/apply-page', [\App\Modules\User\Controllers\LinkTemplateController::class, 'applyPage'])->middleware('workspace.can:links.edit')->name('links.templates.apply-page');
        Route::get('links/{link}/templates/cards', [\App\Modules\User\Controllers\LinkTemplateController::class, 'cardGallery'])->middleware('workspace.can:links.view')->name('links.templates.cards');
        Route::post('links/{link}/templates/apply-card', [\App\Modules\User\Controllers\LinkTemplateController::class, 'applyCard'])->middleware('workspace.can:links.edit')->name('links.templates.apply-card');

        // Standalone splash pages — reusable across multiple links. Read
        // under links.view, mutate under links.edit.
        Route::resource('splash-pages', \App\Modules\User\Controllers\SplashPageController::class)->only(['index', 'show'])->middleware('workspace.can:links.view');
        Route::resource('splash-pages', \App\Modules\User\Controllers\SplashPageController::class)->except(['index', 'show'])->middleware('workspace.can:links.edit');
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
        Route::post('contacts',                             [ContactController::class, 'store'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':contacts_max'])->name('contacts.store');
        Route::get('contacts/import',                       [ContactController::class, 'importForm'])->middleware('workspace.can:settings.edit')->name('contacts.import');
        Route::post('contacts/import',                      [ContactController::class, 'import'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':contacts_max'])->name('contacts.import.store');
        Route::get('contacts/import/preview/{token}',       [ContactController::class, 'importPreview'])->middleware('workspace.can:settings.edit')->name('contacts.import.preview');
        Route::post('contacts/import/preview/{token}/row/{index}', [ContactController::class, 'importRowUpdate'])->whereNumber('index')->middleware('workspace.can:settings.edit')->name('contacts.import.preview.row.update');
        Route::post('contacts/import/preview/{token}/row/{index}/skip', [ContactController::class, 'importRowSkip'])->whereNumber('index')->middleware('workspace.can:settings.edit')->name('contacts.import.preview.row.skip');
        Route::post('contacts/import/confirm/{token}',      [ContactController::class, 'importConfirm'])->middleware(['workspace.can:settings.edit', CheckPlanLimit::class . ':contacts_max'])->name('contacts.import.confirm');
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
        Route::get('calendar/connect/{provider}',           [CalendarAccountController::class, 'connect'])->middleware('workspace.can:settings.edit')->name('calendar.connect')->where('provider', 'google|microsoft|caldav');
        Route::get('calendar/callback/{provider}',          [CalendarAccountController::class, 'callback'])->middleware('workspace.can:settings.edit')->name('calendar.callback')->where('provider', 'google|microsoft|caldav');
        Route::post('calendar/{account}/sync',              [CalendarAccountController::class, 'syncNow'])->middleware('workspace.can:settings.edit')->name('calendar.sync');
        Route::put('calendar/{account}',                    [CalendarAccountController::class, 'update'])->middleware('workspace.can:settings.edit')->name('calendar.update');
        Route::delete('calendar/{account}',                 [CalendarAccountController::class, 'destroy'])->middleware('workspace.can:settings.edit')->name('calendar.destroy');

        // ===== RSVPs (guest list on Event Invite links) — followers feature.
        Route::get('links/{link}/rsvps',                    [RsvpController::class, 'index'])->middleware('workspace.can:followers.view')->name('links.rsvps.index');
        Route::get('links/{link}/rsvps/export',             [RsvpController::class, 'export'])->middleware('workspace.can:followers.view')->name('links.rsvps.export');
        Route::delete('links/{link}/rsvps/{rsvp}',          [RsvpController::class, 'destroy'])->middleware('workspace.can:followers.edit')->name('links.rsvps.destroy');
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
            Route::post('/',                           [\App\Modules\User\Controllers\SocialProofController::class, 'store'])->middleware('workspace.can:links.create')->name('store');
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
            Route::post('upload', [UserFileController::class, 'upload'])->middleware('workspace.can:links.edit')->name('upload');
            Route::post('import-url', [UserFileController::class, 'importUrl'])->middleware('workspace.can:links.edit')->name('import-url');
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
            Route::post('boards',                 [\App\Modules\User\Controllers\TaskBoardController::class, 'store'])->name('boards.store');
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
            Route::post('credentials',                             [\App\Modules\User\Controllers\VaultCredentialController::class, 'store'])->middleware('workspace.can:vault.create')->name('credentials.store');
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
        // OAuth flows are user-scoped — connect lives under files.view so any
        // member with library access can attach their own cloud account.
        Route::get('cloud-oauth/{provider}/start',    [\App\Modules\User\Controllers\CloudOAuthController::class, 'start'])->middleware('workspace.can:files.view')->name('cloud-oauth.start');
        Route::get('cloud-oauth/{provider}/callback', [\App\Modules\User\Controllers\CloudOAuthController::class, 'callback'])->name('cloud-oauth.callback');

        Route::prefix('cloud-files')->name('cloud-files.')->middleware('workspace.can:files.view')->group(function () {
            Route::get('/',                                [\App\Modules\User\Controllers\CloudFileController::class, 'index'])->name('index');
            Route::post('/',                               [\App\Modules\User\Controllers\CloudFileController::class, 'store'])->middleware('workspace.can:files.create')->name('store');
            Route::delete('{cloudFile}',                   [\App\Modules\User\Controllers\CloudFileController::class, 'destroy'])->middleware('workspace.can:files.delete')->name('destroy');

            Route::get('connections',                      [\App\Modules\User\Controllers\CloudConnectionController::class, 'index'])->name('connections');
            Route::delete('connections/{connection}',      [\App\Modules\User\Controllers\CloudConnectionController::class, 'destroy'])->name('connections.destroy');

            Route::get('picker/{connection}',              [\App\Modules\User\Controllers\CloudFilePickerController::class, 'browse'])->name('picker.browse');

            // Owner-only OAuth-app credential management.
            Route::get('settings',                         [\App\Modules\User\Controllers\CloudProviderAppController::class, 'index'])->middleware('workspace.owner')->name('settings.index');
            Route::put('settings/{provider}',              [\App\Modules\User\Controllers\CloudProviderAppController::class, 'update'])->middleware('workspace.owner')->name('settings.update');
            Route::delete('settings/{provider}',           [\App\Modules\User\Controllers\CloudProviderAppController::class, 'destroy'])->middleware('workspace.owner')->name('settings.destroy');
        });

        // Account verification (blue-tick request) — workspace-account-level.
        Route::prefix('verification')->name('verification.')->middleware('workspace.can:settings.view')->group(function () {
            Route::get('/', [VerificationController::class, 'index'])->name('index');
            Route::get('request', [VerificationController::class, 'create'])->middleware('workspace.can:settings.edit')->name('request');
            Route::post('request', [VerificationController::class, 'store'])->middleware('workspace.can:settings.edit')->name('store');
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
