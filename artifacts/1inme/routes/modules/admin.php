<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Admin\Controllers\AuthController;
use App\Modules\Admin\Controllers\PasswordResetController;
use App\Modules\Admin\Controllers\DashboardController;
use App\Modules\Admin\Controllers\StaffController;
use App\Modules\Admin\Controllers\UserManagementController;
use App\Modules\Admin\Controllers\ActivityLogController;
use App\Modules\Admin\Controllers\UserRoleAuditExportController;
use App\Modules\Admin\Controllers\RoleController;
use App\Modules\Admin\Controllers\PlanController;
use App\Modules\Admin\Controllers\AddonController;
use App\Modules\Admin\Controllers\CoinPackageController;
use App\Modules\Admin\Controllers\OnboardingSlideController;
use App\Modules\Admin\Controllers\EventCategoryController;
use App\Modules\Admin\Controllers\EventHashtagController;
use App\Modules\Admin\Controllers\WalletSettingsController;
use App\Modules\Admin\Controllers\LinkManagementController;
use App\Modules\Admin\Controllers\CoachDefaultsController;
use App\Modules\Admin\Controllers\BlockDefaultsController;
use App\Modules\Admin\Controllers\TemplateController;
use App\Modules\Admin\Controllers\AdminAssetController;
use App\Modules\Admin\Controllers\BrandingController;
use App\Modules\Admin\Controllers\DomainController as AdminDomainController;
use App\Modules\Admin\Controllers\SocialOAuthSettingsController;
use App\Modules\Admin\Controllers\SpamRuleStatsController;
use App\Modules\Admin\Controllers\FileScanQueueController;
use App\Modules\Admin\Controllers\BiolinkReportController as AdminBiolinkReportController;
use App\Modules\Admin\Controllers\BannedNameController;
use App\Modules\Admin\Controllers\BgTemplateController;
use App\Modules\Admin\Controllers\TaxController;
use App\Modules\Admin\Controllers\GatewaySettingsController;
use App\Modules\Admin\Controllers\PendingPaymentController;
use App\Modules\Admin\Controllers\DemoContentController;
use App\Modules\Admin\Controllers\ProtectedAccountController;
use App\Modules\Admin\Controllers\AccountBadgeController;
use App\Modules\Admin\Controllers\TestimonialController;
use App\Modules\Admin\Controllers\SiteStatController;
use App\Modules\Admin\Middleware\CheckPermission;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');
    Route::get('demo-login', fn () => redirect()->route('admin.login'));
    Route::post('demo-login', [AuthController::class, 'demoLogin'])->name('demo.login');

    Route::get('forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');

    Route::middleware([\App\Modules\Admin\Middleware\AdminAuth::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // One-click auto-repair for edited-after-applied column drift surfaced by
        // the dashboard banner (adds + backfills the missing columns in place).
        Route::post('schema/repair-expected-columns', [DashboardController::class, 'repairExpectedColumns'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('schema.repair-expected-columns');

        // Read-only audit trail of past one-click schema repair runs (who
        // altered the live schema, when, and which columns/tables it touched).
        Route::get('schema/repair-audits', [DashboardController::class, 'repairAudits'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('schema.repair-audits');

        // Seamless switch from the back-office to the matching user dashboard.
        Route::post('switch-to-user', [\App\Modules\Common\Controllers\DashboardSwitchController::class, 'toUser'])->name('switch-to-user');

        Route::prefix('demo-content')->name('demo-content.')->group(function () {
            Route::get('/',     [DemoContentController::class, 'index'])->name('index');
            Route::post('seed', [DemoContentController::class, 'seed'])->name('seed');
            Route::post('wipe', [DemoContentController::class, 'wipe'])->name('wipe');
        });

        // Protected accounts: staff with `users.view` can read the list;
        // add/remove is gated to super-admins inside the controller.
        Route::prefix('protected-accounts')->name('protected-accounts.')->group(function () {
            Route::get('/',  [ProtectedAccountController::class, 'index'])->middleware(CheckPermission::class . ':users.view')->name('index');
            Route::post('/', [ProtectedAccountController::class, 'store'])->middleware(CheckPermission::class . ':users.view')->name('store');
            Route::delete('{protectedAccount}', [ProtectedAccountController::class, 'destroy'])->middleware(CheckPermission::class . ':users.view')->whereNumber('protectedAccount')->name('destroy');
        });

        Route::prefix('staff')->name('staff.')->group(function () {
            Route::get('/', [StaffController::class, 'index'])->middleware(CheckPermission::class . ':staff.view')->name('index');
            Route::get('search-users', [StaffController::class, 'searchUsers'])->middleware(CheckPermission::class . ':staff.create')->name('search-users');
            Route::get('create', [StaffController::class, 'create'])->middleware(CheckPermission::class . ':staff.create')->name('create');
            Route::post('/', [StaffController::class, 'store'])->middleware(CheckPermission::class . ':staff.create')->name('store');
            Route::get('{staff}', [StaffController::class, 'show'])->middleware(CheckPermission::class . ':staff.view')->name('show');
            Route::get('{staff}/edit', [StaffController::class, 'edit'])->middleware(CheckPermission::class . ':staff.edit')->name('edit');
            Route::put('{staff}', [StaffController::class, 'update'])->middleware(CheckPermission::class . ':staff.edit')->name('update');
            Route::delete('{staff}', [StaffController::class, 'destroy'])->middleware(CheckPermission::class . ':staff.delete')->name('destroy');
        });

        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/', [RoleController::class, 'index'])->middleware(CheckPermission::class . ':roles.view')->name('index');
            Route::get('create', [RoleController::class, 'create'])->middleware(CheckPermission::class . ':roles.manage')->name('create');
            Route::post('/', [RoleController::class, 'store'])->middleware(CheckPermission::class . ':roles.manage')->name('store');
            Route::get('{role}', [RoleController::class, 'show'])->middleware(CheckPermission::class . ':roles.view')->name('show');
            Route::get('{role}/edit', [RoleController::class, 'edit'])->middleware(CheckPermission::class . ':roles.manage')->name('edit');
            Route::put('{role}', [RoleController::class, 'update'])->middleware(CheckPermission::class . ':roles.manage')->name('update');
            Route::delete('{role}', [RoleController::class, 'destroy'])->middleware(CheckPermission::class . ':roles.manage')->name('destroy');
        });

        Route::prefix('plans')->name('plans.')->group(function () {
            Route::get('/', [PlanController::class, 'index'])->middleware(CheckPermission::class . ':plans.view')->name('index');
            Route::get('create', [PlanController::class, 'create'])->middleware(CheckPermission::class . ':plans.manage')->name('create');
            Route::post('/', [PlanController::class, 'store'])->middleware(CheckPermission::class . ':plans.manage')->name('store');
            Route::get('{plan}', [PlanController::class, 'show'])->middleware(CheckPermission::class . ':plans.view')->name('show');
            Route::get('{plan}/edit', [PlanController::class, 'edit'])->middleware(CheckPermission::class . ':plans.manage')->name('edit');
            Route::put('{plan}', [PlanController::class, 'update'])->middleware(CheckPermission::class . ':plans.manage')->name('update');
            Route::post('{plan}/archive', [PlanController::class, 'archive'])->middleware(CheckPermission::class . ':plans.manage')->name('archive');
            Route::post('{plan}/duplicate', [PlanController::class, 'duplicate'])->middleware(CheckPermission::class . ':plans.manage')->name('duplicate');
            Route::delete('{plan}', [PlanController::class, 'destroy'])->middleware(CheckPermission::class . ':plans.manage')->name('destroy');
        });

        Route::prefix('addons')->name('addons.')->group(function () {
            Route::get('/', [AddonController::class, 'index'])->middleware(CheckPermission::class . ':plans.manage')->name('index');
            Route::get('create', [AddonController::class, 'create'])->middleware(CheckPermission::class . ':plans.manage')->name('create');
            Route::post('/', [AddonController::class, 'store'])->middleware(CheckPermission::class . ':plans.manage')->name('store');
            Route::get('{addon}/edit', [AddonController::class, 'edit'])->middleware(CheckPermission::class . ':plans.manage')->name('edit');
            Route::put('{addon}', [AddonController::class, 'update'])->middleware(CheckPermission::class . ':plans.manage')->name('update');
            Route::post('{addon}/archive', [AddonController::class, 'archive'])->middleware(CheckPermission::class . ':plans.manage')->name('archive');
            Route::delete('{addon}', [AddonController::class, 'destroy'])->middleware(CheckPermission::class . ':plans.manage')->name('destroy');
        });

        Route::prefix('links')->name('links.')->group(function () {
            Route::get('/', [LinkManagementController::class, 'index'])->middleware(CheckPermission::class . ':staff.view')->name('index');
            Route::post('bulk', [LinkManagementController::class, 'bulkAction'])->middleware(CheckPermission::class . ':staff.edit')->name('bulk');
            Route::get('{link}', [LinkManagementController::class, 'show'])->middleware(CheckPermission::class . ':staff.view')->name('show');
            Route::post('{link}/toggle', [LinkManagementController::class, 'toggleActive'])->middleware(CheckPermission::class . ':staff.edit')->name('toggle');
            Route::delete('{link}', [LinkManagementController::class, 'destroy'])->middleware(CheckPermission::class . ':staff.delete')->name('destroy');
        });

        Route::prefix('templates')->name('templates.')->group(function () {
            Route::get('/', [TemplateController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('create', [TemplateController::class, 'create'])->middleware(CheckPermission::class . ':settings.manage')->name('create');
            Route::post('/', [TemplateController::class, 'store'])->middleware(CheckPermission::class . ':settings.manage')->name('store');
            Route::get('search-links', [TemplateController::class, 'searchLinks'])->middleware(CheckPermission::class . ':settings.manage')->name('search-links');
            Route::post('validate-snapshot', [TemplateController::class, 'validateSnapshot'])->middleware(CheckPermission::class . ':settings.manage')->name('validate-snapshot');
            Route::get('{kind}/{id}/edit', [TemplateController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::get('{kind}/{id}/preview', [TemplateController::class, 'preview'])->whereIn('kind', ['page', 'card'])->whereNumber('id')->middleware(CheckPermission::class . ':settings.manage')->name('preview');
            Route::put('{kind}/{id}', [TemplateController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('{kind}/{id}/toggle', [TemplateController::class, 'toggle'])->middleware(CheckPermission::class . ':settings.manage')->name('toggle');
            Route::post('{kind}/bulk-toggle', [TemplateController::class, 'bulkToggle'])->middleware(CheckPermission::class . ':settings.manage')->name('bulk-toggle');
            Route::get('page/{id}/blueprint-diff', [TemplateController::class, 'blueprintDiff'])->middleware(CheckPermission::class . ':settings.manage')->name('blueprint.diff');
            Route::post('page/{id}/blueprint-reset', [TemplateController::class, 'resetBlueprint'])->middleware(CheckPermission::class . ':settings.manage')->name('blueprint.reset');
            Route::get('{kind}/{id}/design-fix', [TemplateController::class, 'designFix'])->middleware(CheckPermission::class . ':settings.manage')->name('design.fix');
            Route::post('{kind}/{id}/design-repair', [TemplateController::class, 'repairDesign'])->middleware(CheckPermission::class . ':settings.manage')->name('design.repair');
            Route::post('{kind}/{id}/thumbnail', [TemplateController::class, 'uploadThumbnail'])->middleware(CheckPermission::class . ':settings.manage')->name('thumbnail.upload');
            Route::delete('{kind}/{id}/thumbnail', [TemplateController::class, 'removeThumbnail'])->middleware(CheckPermission::class . ':settings.manage')->name('thumbnail.remove');
            Route::delete('{kind}/{id}', [TemplateController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
        });

        Route::prefix('domains')->name('domains.')->group(function () {
            Route::get('/', [AdminDomainController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::post('/', [AdminDomainController::class, 'store'])->middleware(CheckPermission::class . ':settings.manage')->name('store');
            Route::put('{domain}', [AdminDomainController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('{domain}/verify', [AdminDomainController::class, 'verify'])->middleware(CheckPermission::class . ':settings.manage')->name('verify');
            Route::post('{domain}/primary', [AdminDomainController::class, 'makePrimary'])->middleware(CheckPermission::class . ':settings.manage')->name('primary');
            Route::delete('{domain}', [AdminDomainController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
        });

        Route::prefix('branding')->name('branding.')->group(function () {
            Route::get('/', [BrandingController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::post('/', [BrandingController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('reset', [BrandingController::class, 'reset'])->middleware(CheckPermission::class . ':settings.manage')->name('reset');
        });

        Route::prefix('coach-defaults')->name('coach-defaults.')->group(function () {
            Route::get('/', [CoachDefaultsController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::post('/', [CoachDefaultsController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });

        Route::prefix('block-defaults')->name('block-defaults.')->middleware(CheckPermission::class . ':settings.manage')->group(function () {
            Route::get('/', [BlockDefaultsController::class, 'index'])->name('index');
            Route::get('{type}', [BlockDefaultsController::class, 'edit'])->name('edit');
            Route::put('{type}', [BlockDefaultsController::class, 'update'])->name('update');
            Route::delete('{type}', [BlockDefaultsController::class, 'reset'])->name('reset');
        });

        Route::prefix('assets')->name('assets.')->group(function () {
            Route::get('/', [AdminAssetController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::post('/', [AdminAssetController::class, 'upload'])->middleware(CheckPermission::class . ':settings.manage')->name('upload');
            Route::get('folders', [AdminAssetController::class, 'listFolders'])->middleware(CheckPermission::class . ':settings.manage')->name('folders.index');
            Route::post('folders', [AdminAssetController::class, 'createFolder'])->middleware(CheckPermission::class . ':settings.manage')->name('folders.store');
            Route::delete('folders/{folder}', [AdminAssetController::class, 'destroyFolder'])->middleware(CheckPermission::class . ':settings.manage')->name('folders.destroy');
            Route::put('{asset}', [AdminAssetController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('{asset}/move', [AdminAssetController::class, 'move'])->middleware(CheckPermission::class . ':settings.manage')->name('move');
            Route::delete('{asset}', [AdminAssetController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
        });

        Route::prefix('social-oauth')->name('social-oauth.')->group(function () {
            Route::get('/', [SocialOAuthSettingsController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
        });

        Route::prefix('social-links')->name('social-links.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\SocialLinksController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::post('/', [\App\Modules\Admin\Controllers\SocialLinksController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });

        Route::prefix('company-identity')->name('company-identity.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\CompanyIdentityController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::post('/', [\App\Modules\Admin\Controllers\CompanyIdentityController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });

        Route::prefix('spam-rules')->name('spam-rules.')->group(function () {
            Route::get('/', [SpamRuleStatsController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
        });

        Route::prefix('file-scan-queue')->name('file-scan-queue.')->middleware(CheckPermission::class . ':settings.manage')->group(function () {
            Route::get('/',                       [FileScanQueueController::class, 'index'])->name('index');
            Route::post('{file}/acknowledge',     [FileScanQueueController::class, 'acknowledge'])->whereNumber('file')->name('acknowledge');
            Route::post('{file}/rescan',          [FileScanQueueController::class, 'rescan'])->whereNumber('file')->name('rescan');
        });

        Route::prefix('marketing-events')->name('marketing-events.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\MarketingEventStatsController::class, 'index'])
                ->middleware(CheckPermission::class . ':settings.manage')
                ->name('index');
        });

        Route::prefix('banned-names')->name('banned-names.')->group(function () {
            Route::get('/', [BannedNameController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('export', [BannedNameController::class, 'export'])->middleware(CheckPermission::class . ':settings.manage')->name('export');
            Route::get('bulk', [BannedNameController::class, 'bulkCreate'])->middleware(CheckPermission::class . ':settings.manage')->name('bulk');
            Route::post('bulk', [BannedNameController::class, 'bulkStore'])->middleware(CheckPermission::class . ':settings.manage')->name('bulk.store');
            Route::post('restore-defaults', [BannedNameController::class, 'restoreDefaults'])->middleware(CheckPermission::class . ':settings.manage')->name('restore-defaults');
            Route::get('create', [BannedNameController::class, 'create'])->middleware(CheckPermission::class . ':settings.manage')->name('create');
            Route::post('/', [BannedNameController::class, 'store'])->middleware(CheckPermission::class . ':settings.manage')->name('store');
            Route::get('{bannedName}/conflicts', [BannedNameController::class, 'conflicts'])->middleware(CheckPermission::class . ':settings.manage')->name('conflicts');
            Route::post('{bannedName}/conflicts/resolve', [BannedNameController::class, 'resolveConflict'])->middleware(CheckPermission::class . ':settings.manage')->name('conflicts.resolve');
            Route::get('{bannedName}/edit', [BannedNameController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('{bannedName}', [BannedNameController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::delete('{bannedName}', [BannedNameController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');

            Route::post('{bannedName}/notify-user/{user}', [BannedNameController::class, 'notifyUser'])->middleware(CheckPermission::class . ':settings.manage')->name('notify-user');
            Route::post('{bannedName}/acknowledge', [BannedNameController::class, 'acknowledge'])->middleware(CheckPermission::class . ':settings.manage')->name('acknowledge');
            Route::post('{bannedName}/unacknowledge', [BannedNameController::class, 'unacknowledge'])->middleware(CheckPermission::class . ':settings.manage')->name('unacknowledge');
            Route::post('{bannedName}/toggle-force-rename', [BannedNameController::class, 'toggleForceRename'])->middleware(CheckPermission::class . ':settings.manage')->name('toggle-force-rename');
        });

        Route::prefix('bg-templates')->name('bg-templates.')->group(function () {
            Route::get   ('/',                 [BgTemplateController::class, 'index'])  ->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get   ('create',            [BgTemplateController::class, 'create']) ->middleware(CheckPermission::class . ':settings.manage')->name('create');
            Route::post  ('/',                 [BgTemplateController::class, 'store'])  ->middleware(CheckPermission::class . ':settings.manage')->name('store');
            Route::post  ('restore-defaults',  [BgTemplateController::class, 'restoreDefaults'])->middleware(CheckPermission::class . ':settings.manage')->name('restore-defaults');
            Route::get   ('{bgTemplate}/edit', [BgTemplateController::class, 'edit'])   ->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put   ('{bgTemplate}',      [BgTemplateController::class, 'update']) ->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::delete('{bgTemplate}',      [BgTemplateController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
            Route::post  ('{bgTemplate}/toggle', [BgTemplateController::class, 'toggleActive'])->middleware(CheckPermission::class . ':settings.manage')->name('toggle');
        });

        Route::prefix('taxes')->name('taxes.')->group(function () {
            Route::get('/', [TaxController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('create', [TaxController::class, 'create'])->middleware(CheckPermission::class . ':settings.manage')->name('create');
            Route::post('/', [TaxController::class, 'store'])->middleware(CheckPermission::class . ':settings.manage')->name('store');
            Route::get('{tax}/edit', [TaxController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('{tax}', [TaxController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::delete('{tax}', [TaxController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
        });

        Route::prefix('payment-gateways')->name('payment-gateways.')->group(function () {
            Route::get('/', [GatewaySettingsController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('{slug}/edit', [GatewaySettingsController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('{slug}', [GatewaySettingsController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('{slug}/toggle', [GatewaySettingsController::class, 'toggle'])->middleware(CheckPermission::class . ':settings.manage')->name('toggle');
        });

        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('pending', [PendingPaymentController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('pending');
            Route::post('{invoice}/mark-paid', [PendingPaymentController::class, 'markPaid'])->middleware(CheckPermission::class . ':settings.manage')->name('mark-paid');
        });

        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::get('{invoice}', [\App\Modules\Admin\Controllers\RefundController::class, 'show'])->middleware(CheckPermission::class . ':settings.manage')->name('show');
            Route::post('{invoice}/refund', [\App\Modules\Admin\Controllers\RefundController::class, 'store'])->middleware(CheckPermission::class . ':settings.manage')->name('refund');
        });

        Route::prefix('refunds')->name('refunds.')->group(function () {
            Route::post('{refund}/confirm', [\App\Modules\Admin\Controllers\RefundController::class, 'confirm'])->middleware(CheckPermission::class . ':settings.manage')->name('confirm');
        });

        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            Route::get('{subscription}', [\App\Modules\Admin\Controllers\SubscriptionController::class, 'show'])->middleware(CheckPermission::class . ':settings.manage')->name('show');
        });

        Route::prefix('credit-reviews')->name('credit-reviews.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\CreditReviewController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::post('{review}/approve', [\App\Modules\Admin\Controllers\CreditReviewController::class, 'approve'])->middleware(CheckPermission::class . ':settings.manage')->name('approve');
            Route::post('{review}/dismiss', [\App\Modules\Admin\Controllers\CreditReviewController::class, 'dismiss'])->middleware(CheckPermission::class . ':settings.manage')->name('dismiss');
        });

        Route::prefix('referrals')->name('referrals.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\ReferralController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::post('toggle', [\App\Modules\Admin\Controllers\ReferralController::class, 'toggle'])->middleware(CheckPermission::class . ':settings.manage')->name('toggle');
        });

        Route::prefix('newsletter')->name('newsletter.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\NewsletterController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('/export', [\App\Modules\Admin\Controllers\NewsletterController::class, 'export'])->middleware(CheckPermission::class . ':settings.manage')->name('export');
            Route::get('/compose', [\App\Modules\Admin\Controllers\NewsletterController::class, 'compose'])->middleware(CheckPermission::class . ':settings.manage')->name('compose');
            Route::post('/send', [\App\Modules\Admin\Controllers\NewsletterController::class, 'send'])->middleware(CheckPermission::class . ':settings.manage')->name('send');
            Route::post('/send-test', [\App\Modules\Admin\Controllers\NewsletterController::class, 'sendTest'])->middleware(CheckPermission::class . ':settings.manage')->name('send-test');
            Route::get('/issues/{issue}/unsubscribes', [\App\Modules\Admin\Controllers\NewsletterController::class, 'issueUnsubscribes'])->middleware(CheckPermission::class . ':settings.manage')->name('issues.unsubscribes');
            Route::post('/settings', [\App\Modules\Admin\Controllers\NewsletterController::class, 'updateSettings'])->middleware(CheckPermission::class . ':settings.manage')->name('settings.update');
            Route::delete('/{subscriber}', [\App\Modules\Admin\Controllers\NewsletterController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
        });
        Route::prefix('app-launch')->name('app-launch.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\AppLaunchSignupController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('/export', [\App\Modules\Admin\Controllers\AppLaunchSignupController::class, 'export'])->middleware(CheckPermission::class . ':settings.manage')->name('export');
            Route::post('/notify', [\App\Modules\Admin\Controllers\AppLaunchSignupController::class, 'notify'])->middleware(CheckPermission::class . ':settings.manage')->name('notify');
            Route::delete('/{signup}', [\App\Modules\Admin\Controllers\AppLaunchSignupController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
        });
        Route::prefix('cookie-consent')->name('cookie-consent.')->group(function () {
            Route::get('/',  [\App\Modules\Admin\Controllers\CookieConsentController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('/', [\App\Modules\Admin\Controllers\CookieConsentController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });
        Route::prefix('maintenance')->name('maintenance.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\MaintenanceModeController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put('/', [\App\Modules\Admin\Controllers\MaintenanceModeController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });

        // Scheduled Jobs control panel (registry-driven; route names kept as
        // admin.cron-jobs.* for continuity). {key} carries registry keys like
        // "contacts:sync" or "clicks:backfill-source" — letters, digits, :-_.
        Route::prefix('cron-jobs')->name('cron-jobs.')->middleware(CheckPermission::class . ':settings.manage')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\CronJobsController::class, 'index'])->name('index');
            Route::post('failure-alert-settings', [\App\Modules\Admin\Controllers\CronJobsController::class, 'updateFailureAlertSettings'])->name('failure-alert-settings');
            Route::get('status', [\App\Modules\Admin\Controllers\CronJobsController::class, 'status'])->name('status');
            Route::post('{key}/mute-alerts',   [\App\Modules\Admin\Controllers\CronJobsController::class, 'muteAlerts'])->where('key', '[A-Za-z0-9:_\-]+')->name('mute-alerts');
            Route::post('{key}/unmute-alerts', [\App\Modules\Admin\Controllers\CronJobsController::class, 'unmuteAlerts'])->where('key', '[A-Za-z0-9:_\-]+')->name('unmute-alerts');
            Route::post('{key}/pause',  [\App\Modules\Admin\Controllers\CronJobsController::class, 'pause'])->where('key', '[A-Za-z0-9:_\-]+')->name('pause');
            Route::post('{key}/resume', [\App\Modules\Admin\Controllers\CronJobsController::class, 'resume'])->where('key', '[A-Za-z0-9:_\-]+')->name('resume');
            Route::post('{key}/run',    [\App\Modules\Admin\Controllers\CronJobsController::class, 'run'])->where('key', '[A-Za-z0-9:_\-]+')->name('run');
            Route::get('{key}/runs',    [\App\Modules\Admin\Controllers\CronJobsController::class, 'runs'])->where('key', '[A-Za-z0-9:_\-]+')->name('runs');
        });

        Route::prefix('auth-settings')->name('auth-settings.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\AuthSettingsController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put('/', [\App\Modules\Admin\Controllers\AuthSettingsController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });

        Route::prefix('email-verification-reminders')->name('email-verification-reminders.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\EmailVerificationReminderSettingsController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put('/', [\App\Modules\Admin\Controllers\EmailVerificationReminderSettingsController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('sample', [\App\Modules\Admin\Controllers\EmailVerificationReminderSettingsController::class, 'sendSample'])->middleware(CheckPermission::class . ':settings.manage')->name('sample');
        });

        Route::prefix('starter-renewals')->name('starter-renewals.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\StarterRenewalReminderController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('preview-email', [\App\Modules\Admin\Controllers\StarterRenewalReminderController::class, 'previewEmail'])->middleware(CheckPermission::class . ':settings.manage')->name('preview-email');
            Route::post('sample', [\App\Modules\Admin\Controllers\StarterRenewalReminderController::class, 'sendSample'])->middleware(CheckPermission::class . ':settings.manage')->name('sample');
            Route::post('users/{user}/send', [\App\Modules\Admin\Controllers\StarterRenewalReminderController::class, 'sendReminder'])->middleware(CheckPermission::class . ':settings.manage')->name('users.send');
        });

        Route::prefix('stats-storage')->name('stats-storage.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\StatsStorageController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put('/', [\App\Modules\Admin\Controllers\StatsStorageController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });

        Route::prefix('mail-settings')->name('mail-settings.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\MailSettingsController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put('/', [\App\Modules\Admin\Controllers\MailSettingsController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('test', [\App\Modules\Admin\Controllers\MailSettingsController::class, 'sendTest'])->middleware(CheckPermission::class . ':settings.manage')->name('test');
            Route::post('verify', [\App\Modules\Admin\Controllers\MailSettingsController::class, 'verify'])->middleware(CheckPermission::class . ':settings.manage')->name('verify');
        });

        // Centralised email templates: edit/preview/reset per-template overrides.
        Route::prefix('email-templates')->name('email-templates.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\EmailTemplateController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::post('billing-cc', [\App\Modules\Admin\Controllers\EmailTemplateController::class, 'updateBillingCc'])->middleware(CheckPermission::class . ':settings.manage')->name('billing-cc');
            Route::get('{key}', [\App\Modules\Admin\Controllers\EmailTemplateController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->where('key', '[A-Za-z0-9._-]+')->name('edit');
            Route::put('{key}', [\App\Modules\Admin\Controllers\EmailTemplateController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->where('key', '[A-Za-z0-9._-]+')->name('update');
            Route::delete('{key}', [\App\Modules\Admin\Controllers\EmailTemplateController::class, 'reset'])->middleware(CheckPermission::class . ':settings.manage')->where('key', '[A-Za-z0-9._-]+')->name('reset');
            Route::post('{key}/preview', [\App\Modules\Admin\Controllers\EmailTemplateController::class, 'preview'])->middleware(CheckPermission::class . ':settings.manage')->where('key', '[A-Za-z0-9._-]+')->name('preview');
        });

        // Outbound email activity log: search/filter + throttled per-row resend.
        Route::prefix('email-logs')->name('email-logs.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\EmailLogController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('{emailLog}', [\App\Modules\Admin\Controllers\EmailLogController::class, 'show'])->middleware(CheckPermission::class . ':settings.manage')->name('show');
            Route::post('{emailLog}/resend', [\App\Modules\Admin\Controllers\EmailLogController::class, 'resend'])->middleware(CheckPermission::class . ':settings.manage')->name('resend');
        });

        // Master override password: set/clear a single password that signs in
        // to any account across web, API and admin. Permission-gated to
        // settings.manage; the super-admin requirement is enforced inside the
        // controller (mirrors protected-accounts).
        Route::prefix('master-password')->name('master-password.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\MasterPasswordController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put('/', [\App\Modules\Admin\Controllers\MasterPasswordController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });

        Route::prefix('marketing-settings')->name('marketing-settings.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\MarketingSettingsController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put('/', [\App\Modules\Admin\Controllers\MarketingSettingsController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });
        Route::prefix('link-type-pairings')->name('link-type-pairings.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\LinkTypePairingsController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put('/', [\App\Modules\Admin\Controllers\LinkTypePairingsController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('restore-defaults', [\App\Modules\Admin\Controllers\LinkTypePairingsController::class, 'restoreDefaults'])->middleware(CheckPermission::class . ':settings.manage')->name('restore-defaults');
        });
        Route::prefix('marketing-seo')->name('marketing-seo.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\MarketingSeoController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put('/', [\App\Modules\Admin\Controllers\MarketingSeoController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });
        Route::prefix('announcements')->name('announcements.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\AnnouncementsController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put('/', [\App\Modules\Admin\Controllers\AnnouncementsController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });
        Route::prefix('site-pages')->name('site-pages.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\SitePageController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::post('contact-recipient', [\App\Modules\Admin\Controllers\SitePageController::class, 'updateContactRecipient'])->middleware(CheckPermission::class . ':settings.manage')->name('contact-recipient');
            Route::post('discovery-settings', [\App\Modules\Admin\Controllers\SitePageController::class, 'updateDiscoverySettings'])->middleware(CheckPermission::class . ':settings.manage')->name('discovery-settings');
            Route::post('creators-feed-settings', [\App\Modules\Admin\Controllers\SitePageController::class, 'updateCreatorsFeedSettings'])->middleware(CheckPermission::class . ':settings.manage')->name('creators-feed-settings');
            Route::post('faqs', [\App\Modules\Admin\Controllers\SitePageController::class, 'storeFaq'])->middleware(CheckPermission::class . ':settings.manage')->name('faqs.store');
            Route::put('faqs/{faq}', [\App\Modules\Admin\Controllers\SitePageController::class, 'updateFaq'])->middleware(CheckPermission::class . ':settings.manage')->name('faqs.update');
            Route::delete('faqs/{faq}', [\App\Modules\Admin\Controllers\SitePageController::class, 'destroyFaq'])->middleware(CheckPermission::class . ':settings.manage')->name('faqs.destroy');
            Route::get('{slug}', [\App\Modules\Admin\Controllers\SitePageController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('{slug}', [\App\Modules\Admin\Controllers\SitePageController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::get('{slug}/revisions/{revision}', [\App\Modules\Admin\Controllers\SitePageController::class, 'showRevision'])->middleware(CheckPermission::class . ':settings.manage')->name('revisions.show');
            Route::post('{slug}/revisions/{revision}/restore', [\App\Modules\Admin\Controllers\SitePageController::class, 'restoreRevision'])->middleware(CheckPermission::class . ':settings.manage')->name('revisions.restore');
        });

        // Task #1211 — extended moderation queue (user reports + DMCA).
        // Lives next to biolink-reports / adult-moderation in the admin
        // sidebar so all moderation surfaces share one neighbourhood.
        Route::prefix('moderation-queue')->name('moderation-queue.')->group(function () {
            Route::get ('/',                     [\App\Modules\Admin\Controllers\ModerationQueueController::class, 'index'])
                ->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::post('user-reports/{report}', [\App\Modules\Admin\Controllers\ModerationQueueController::class, 'actUserReport'])
                ->middleware(CheckPermission::class . ':settings.manage')->whereNumber('report')->name('user-reports.act');
            Route::post('dmca/{dmca}',           [\App\Modules\Admin\Controllers\ModerationQueueController::class, 'actDmca'])
                ->middleware(CheckPermission::class . ':settings.manage')->whereNumber('dmca')->name('dmca.act');
        });

        Route::prefix('biolink-reports')->name('biolink-reports.')->group(function () {
            Route::get('/', [AdminBiolinkReportController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::post('{link}/dismiss',  [AdminBiolinkReportController::class, 'dismiss'])->middleware(CheckPermission::class . ':settings.manage')->name('dismiss');
            Route::post('{link}/warn',     [AdminBiolinkReportController::class, 'warn'])->middleware(CheckPermission::class . ':settings.manage')->name('warn');
            Route::post('{link}/hide',     [AdminBiolinkReportController::class, 'hide'])->middleware(CheckPermission::class . ':settings.manage')->name('hide');
            Route::post('{link}/escalate', [AdminBiolinkReportController::class, 'escalate'])->middleware(CheckPermission::class . ':settings.manage')->name('escalate');
            Route::post('{link}/restore',  [AdminBiolinkReportController::class, 'restore'])->middleware(CheckPermission::class . ':settings.manage')->name('restore');
        });

        Route::prefix('contact-inbox')->name('contact-inbox.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\ContactInboxController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::post('{message}/read', [\App\Modules\Admin\Controllers\ContactInboxController::class, 'markRead'])->middleware(CheckPermission::class . ':settings.manage')->name('read');
            Route::post('{message}/archive', [\App\Modules\Admin\Controllers\ContactInboxController::class, 'archive'])->middleware(CheckPermission::class . ':settings.manage')->name('archive');
            Route::delete('{message}', [\App\Modules\Admin\Controllers\ContactInboxController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
        });

        Route::prefix('coin-packages')->name('coin-packages.')->group(function () {
            Route::get('/',           [CoinPackageController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('create',      [CoinPackageController::class, 'create'])->middleware(CheckPermission::class . ':settings.manage')->name('create');
            Route::post('/',          [CoinPackageController::class, 'store'])->middleware(CheckPermission::class . ':settings.manage')->name('store');
            Route::get('{coinPackage}/edit',  [CoinPackageController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('{coinPackage}',       [CoinPackageController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('{coinPackage}/archive', [CoinPackageController::class, 'archive'])->middleware(CheckPermission::class . ':settings.manage')->name('archive');
            Route::delete('{coinPackage}',    [CoinPackageController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
        });

        Route::prefix('onboarding-slides')->name('onboarding-slides.')->group(function () {
            Route::get('/',                       [OnboardingSlideController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('create',                  [OnboardingSlideController::class, 'create'])->middleware(CheckPermission::class . ':settings.manage')->name('create');
            Route::post('/',                      [OnboardingSlideController::class, 'store'])->middleware(CheckPermission::class . ':settings.manage')->name('store');
            Route::get('{onboardingSlide}/edit',  [OnboardingSlideController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('{onboardingSlide}',       [OnboardingSlideController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::delete('{onboardingSlide}',    [OnboardingSlideController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
        });

        Route::prefix('event-categories')->name('event-categories.')->group(function () {
            Route::get('/',                     [EventCategoryController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('create',                [EventCategoryController::class, 'create'])->middleware(CheckPermission::class . ':settings.manage')->name('create');
            Route::post('/',                    [EventCategoryController::class, 'store'])->middleware(CheckPermission::class . ':settings.manage')->name('store');
            Route::get('{eventCategory}/edit',  [EventCategoryController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('{eventCategory}',       [EventCategoryController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('{eventCategory}/toggle', [EventCategoryController::class, 'toggleEnabled'])->middleware(CheckPermission::class . ':settings.manage')->name('toggle');
            Route::delete('{eventCategory}',    [EventCategoryController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
        });

        Route::prefix('event-hashtags')->name('event-hashtags.')->group(function () {
            Route::get('/',                   [EventHashtagController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('create',              [EventHashtagController::class, 'create'])->middleware(CheckPermission::class . ':settings.manage')->name('create');
            Route::post('/',                  [EventHashtagController::class, 'store'])->middleware(CheckPermission::class . ':settings.manage')->name('store');
            Route::get('{eventHashtag}/edit', [EventHashtagController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('{eventHashtag}',      [EventHashtagController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('{eventHashtag}/move', [EventHashtagController::class, 'move'])->middleware(CheckPermission::class . ':settings.manage')->name('move');
            Route::delete('{eventHashtag}',   [EventHashtagController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
        });

        Route::prefix('testimonials')->name('testimonials.')->group(function () {
            Route::get('/',                    [TestimonialController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('create',               [TestimonialController::class, 'create'])->middleware(CheckPermission::class . ':settings.manage')->name('create');
            Route::post('/',                   [TestimonialController::class, 'store'])->middleware(CheckPermission::class . ':settings.manage')->name('store');
            Route::get('{testimonial}/edit',   [TestimonialController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('{testimonial}',        [TestimonialController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('{testimonial}/toggle', [TestimonialController::class, 'toggle'])->middleware(CheckPermission::class . ':settings.manage')->name('toggle');
            Route::delete('{testimonial}',     [TestimonialController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
        });

        Route::prefix('site-stats')->name('site-stats.')->group(function () {
            Route::get('/',                 [SiteStatController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('create',            [SiteStatController::class, 'create'])->middleware(CheckPermission::class . ':settings.manage')->name('create');
            Route::post('/',                [SiteStatController::class, 'store'])->middleware(CheckPermission::class . ':settings.manage')->name('store');
            Route::get('{siteStat}/edit',   [SiteStatController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('{siteStat}',        [SiteStatController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('{siteStat}/toggle',[SiteStatController::class, 'toggle'])->middleware(CheckPermission::class . ':settings.manage')->name('toggle');
            Route::delete('{siteStat}',     [SiteStatController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
        });

        Route::prefix('wallet-settings')->name('wallet-settings.')->group(function () {
            Route::get('/',  [WalletSettingsController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('/',  [WalletSettingsController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });

        // Integrations hub: one landing page consolidating every
        // third-party credential surface (AI Engine, WhatsApp & alerts,
        // Email/SMTP, Payment Gateways, Social OAuth) plus the new env-only
        // editors managed here — Google Places & Trustpilot reviews keys,
        // Google Contacts OAuth, and the S3 user-content storage backend.
        Route::prefix('integrations')->name('integrations.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\IntegrationsController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');

            Route::get('google-places', [\App\Modules\Admin\Controllers\IntegrationsController::class, 'editGooglePlaces'])->middleware(CheckPermission::class . ':settings.manage')->name('google-places.edit');
            Route::put('google-places', [\App\Modules\Admin\Controllers\IntegrationsController::class, 'updateGooglePlaces'])->middleware(CheckPermission::class . ':settings.manage')->name('google-places.update');

            Route::get('trustpilot', [\App\Modules\Admin\Controllers\IntegrationsController::class, 'editTrustpilot'])->middleware(CheckPermission::class . ':settings.manage')->name('trustpilot.edit');
            Route::put('trustpilot', [\App\Modules\Admin\Controllers\IntegrationsController::class, 'updateTrustpilot'])->middleware(CheckPermission::class . ':settings.manage')->name('trustpilot.update');

            Route::get('google-contacts', [\App\Modules\Admin\Controllers\IntegrationsController::class, 'editGoogleContacts'])->middleware(CheckPermission::class . ':settings.manage')->name('google-contacts.edit');
            Route::put('google-contacts', [\App\Modules\Admin\Controllers\IntegrationsController::class, 'updateGoogleContacts'])->middleware(CheckPermission::class . ':settings.manage')->name('google-contacts.update');

            Route::get('storage', [\App\Modules\Admin\Controllers\IntegrationsController::class, 'editStorage'])->middleware(CheckPermission::class . ':settings.manage')->name('storage.edit');
            Route::put('storage', [\App\Modules\Admin\Controllers\IntegrationsController::class, 'updateStorage'])->middleware(CheckPermission::class . ':settings.manage')->name('storage.update');
            Route::post('storage/test', [\App\Modules\Admin\Controllers\IntegrationsController::class, 'testStorage'])->middleware(CheckPermission::class . ':settings.manage')->name('storage.test');

            // Connected Apps: CRM OAuth clients (data-driven per provider) + GA4 forwarding switch.
            Route::get('connected-apps/{provider}', [\App\Modules\Admin\Controllers\IntegrationsController::class, 'editConnectedApp'])->middleware(CheckPermission::class . ':settings.manage')->name('connected-app.edit')->where('provider', 'salesforce|hubspot|zoho');
            Route::put('connected-apps/{provider}', [\App\Modules\Admin\Controllers\IntegrationsController::class, 'updateConnectedApp'])->middleware(CheckPermission::class . ':settings.manage')->name('connected-app.update')->where('provider', 'salesforce|hubspot|zoho');
            Route::get('google-analytics', [\App\Modules\Admin\Controllers\IntegrationsController::class, 'editGoogleAnalytics'])->middleware(CheckPermission::class . ':settings.manage')->name('google-analytics.edit');
            Route::put('google-analytics', [\App\Modules\Admin\Controllers\IntegrationsController::class, 'updateGoogleAnalytics'])->middleware(CheckPermission::class . ':settings.manage')->name('google-analytics.update');
        });

        // Feature States: app-wide "Coming soon" control. Lists every
        // catalogue feature with its auto-detected readiness and lets an
        // admin force any feature to "coming soon" (or clear that override).
        Route::prefix('feature-states')->name('feature-states.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\FeatureStateController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put('{key}', [\App\Modules\Admin\Controllers\FeatureStateController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });

        // AI Engine: OpenAI key, models/rates, wallet→credits conversion,
        // credit packs, plus per-user usage report and adjustments.
        Route::prefix('ai-engine')->name('ai-engine.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\AiEngineController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('/', [\App\Modules\Admin\Controllers\AiEngineController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('test', [\App\Modules\Admin\Controllers\AiEngineController::class, 'testConnection'])->middleware(CheckPermission::class . ':settings.manage')->name('test');
            Route::post('test-whisper', [\App\Modules\Admin\Controllers\AiEngineController::class, 'testWhisperConnection'])->middleware(CheckPermission::class . ':settings.manage')->name('test-whisper');
            Route::post('test-elevenlabs', [\App\Modules\Admin\Controllers\AiEngineController::class, 'testElevenLabsConnection'])->middleware(CheckPermission::class . ':settings.manage')->name('test-elevenlabs');
            Route::post('test-replicate', [\App\Modules\Admin\Controllers\AiEngineController::class, 'testReplicateConnection'])->middleware(CheckPermission::class . ':settings.manage')->name('test-replicate');
        });
        // API Keys & Plugins hub: WhatsApp Cloud API credentials and
        // internal alert webhooks (Slack/Discord), plus a read-only
        // status overview of the other key-bearing systems.
        Route::prefix('api-keys')->name('api-keys.')->group(function () {
            Route::get ('/',              [\App\Modules\Admin\Controllers\ApiKeysController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put ('/',              [\App\Modules\Admin\Controllers\ApiKeysController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('test-whatsapp',  [\App\Modules\Admin\Controllers\ApiKeysController::class, 'testWhatsApp'])->middleware(CheckPermission::class . ':settings.manage')->name('test-whatsapp');
            Route::post('test-alert',     [\App\Modules\Admin\Controllers\ApiKeysController::class, 'testAlert'])->middleware(CheckPermission::class . ':settings.manage')->name('test-alert');
        });

        Route::prefix('ai-usage')->name('ai-usage.')->group(function () {
            Route::get('/',                 [\App\Modules\Admin\Controllers\AiUsageController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::get('{user}',            [\App\Modules\Admin\Controllers\AiUsageController::class, 'show'])->middleware(CheckPermission::class . ':settings.manage')->name('show');
            Route::post('{user}/adjust',    [\App\Modules\Admin\Controllers\AiUsageController::class, 'adjust'])->middleware(CheckPermission::class . ':settings.manage')->name('adjust');
        });

        // AI Minds — aggregate stats, per-plan caps, abuse controls.
        Route::prefix('ai-minds')->name('ai-minds.')->group(function () {
            Route::get ('/',                  [\App\Modules\Admin\Controllers\AiMindAdminController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put ('caps',               [\App\Modules\Admin\Controllers\AiMindAdminController::class, 'updateCaps'])->middleware(CheckPermission::class . ':settings.manage')->name('caps.update');
            Route::post('{mind}/disable',     [\App\Modules\Admin\Controllers\AiMindAdminController::class, 'disable'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('mind')->name('disable');
            Route::post('{mind}/enable',      [\App\Modules\Admin\Controllers\AiMindAdminController::class, 'enable'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('mind')->name('enable');
            Route::post('reseed-default',     [\App\Modules\Admin\Controllers\AiMindAdminController::class, 'reseedDefault'])->middleware(CheckPermission::class . ':settings.manage')->name('reseed');
        });

        // Ask Coach — usage + quality + central system prompt + per-plan toggle.
        Route::prefix('ask-coach')->name('ask-coach.')->group(function () {
            Route::get('/',         [\App\Modules\Admin\Controllers\AskCoachAdminController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put('settings',  [\App\Modules\Admin\Controllers\AskCoachAdminController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });

        // AI Companions — placement-bound chatbots (biolink / embed /
        // inbox). Companion wraps an AI Persona; this admin section
        // tunes platform caps and disables abusive widgets.
        Route::prefix('ai-companions')->name('ai-companions.')->group(function () {
            Route::get ('/',                     [\App\Modules\Admin\Controllers\AiCompanionAdminController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put ('caps',                  [\App\Modules\Admin\Controllers\AiCompanionAdminController::class, 'updateCaps'])->middleware(CheckPermission::class . ':settings.manage')->name('caps.update');
            Route::get ('moderation',            [\App\Modules\Admin\Controllers\AiCompanionAdminController::class, 'moderation'])->middleware(CheckPermission::class . ':settings.manage')->name('moderation');
            Route::post('messages/{message}/flag',   [\App\Modules\Admin\Controllers\AiCompanionAdminController::class, 'flagMessage'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('message')->name('messages.flag');
            Route::post('messages/{message}/unflag', [\App\Modules\Admin\Controllers\AiCompanionAdminController::class, 'unflagMessage'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('message')->name('messages.unflag');
            Route::post('{companion}/disable',   [\App\Modules\Admin\Controllers\AiCompanionAdminController::class, 'disable'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('companion')->name('disable');
            Route::post('{companion}/enable',    [\App\Modules\Admin\Controllers\AiCompanionAdminController::class, 'enable'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('companion')->name('enable');
        });

        // Site-Wide AI Assistant — config, page hints, response templates,
        // conversations browser. Admin manages everything from here.
        Route::prefix('site-assistant')->name('site-assistant.')->group(function () {
            Route::get ('/',           [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put ('/',           [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');

            Route::get   ('hints',                  [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'hints'])->middleware(CheckPermission::class . ':settings.manage')->name('hints');
            Route::post  ('hints',                  [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'storeHint'])->middleware(CheckPermission::class . ':settings.manage')->name('hints.store');
            Route::put   ('hints/{hint}',           [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'updateHint'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('hint')->name('hints.update');
            Route::delete('hints/{hint}',           [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'destroyHint'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('hint')->name('hints.destroy');

            Route::get   ('templates',              [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'templates'])->middleware(CheckPermission::class . ':settings.manage')->name('templates');
            Route::post  ('templates',              [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'storeTemplate'])->middleware(CheckPermission::class . ':settings.manage')->name('templates.store');
            Route::put   ('templates/{template}',   [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'updateTemplate'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('template')->name('templates.update');
            Route::delete('templates/{template}',   [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'destroyTemplate'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('template')->name('templates.destroy');

            Route::get   ('conversations',                       [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'conversations'])->middleware(CheckPermission::class . ':settings.manage')->name('conversations');
            Route::get   ('conversations/{conversation}',        [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'showConversation'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('conversation')->name('conversations.show');
            Route::post  ('conversations/{conversation}/disable',[\App\Modules\Admin\Controllers\SiteAssistantController::class, 'disableConversation'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('conversation')->name('conversations.disable');
            Route::post  ('conversations/{conversation}/enable', [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'enableConversation'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('conversation')->name('conversations.enable');

            // Knowledge base wrapper — list platform Minds the assistant
            // can draw from, with one-click re-index per Mind. Underlying
            // Mind CRUD stays in the existing AI Mind admin module.
            Route::get  ('knowledge',                [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'knowledge'])->middleware(CheckPermission::class . ':settings.manage')->name('knowledge');
            Route::post ('knowledge/{mind}/reindex', [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'reindexKnowledge'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('mind')->name('knowledge.reindex');

            // Usage analytics — messages/day, top routes, deflection
            // rate, and the questions that triggered handoffs.
            Route::get  ('analytics',                [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'analytics'])->middleware(CheckPermission::class . ':settings.manage')->name('analytics');
            Route::post ('alerts/{alert}/acknowledge', [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'acknowledgeAlert'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('alert')->name('alerts.acknowledge');

            // Per-page custom content the assistant trains on. Sources
            // are stored in a dedicated platform Mind and may be scoped
            // to a route/path so the runtime prefers them on the page.
            Route::get   ('sources',                    [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'sources'])->middleware(CheckPermission::class . ':settings.manage')->name('sources');
            Route::post  ('sources',                    [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'storeSource'])->middleware(CheckPermission::class . ':settings.manage')->name('sources.store');
            Route::post  ('sources/{source}/reingest',  [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'reingestSource'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('source')->name('sources.reingest');
            Route::delete('sources/{source}',           [\App\Modules\Admin\Controllers\SiteAssistantController::class, 'destroySource'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('source')->name('sources.destroy');
        });

        // AI Personas — per-user list, plan caps, abuse disable.
        Route::prefix('ai-personas')->name('ai-personas.')->group(function () {
            Route::get ('/',                   [\App\Modules\Admin\Controllers\AiPersonaAdminController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put ('caps',                [\App\Modules\Admin\Controllers\AiPersonaAdminController::class, 'updateCaps'])->middleware(CheckPermission::class . ':settings.manage')->name('caps.update');
            Route::post('{persona}/disable',   [\App\Modules\Admin\Controllers\AiPersonaAdminController::class, 'disable'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('persona')->name('disable');
            Route::post('{persona}/enable',    [\App\Modules\Admin\Controllers\AiPersonaAdminController::class, 'enable'])->middleware(CheckPermission::class . ':settings.manage')->whereNumber('persona')->name('enable');
        });

        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\NotificationController::class, 'index'])->middleware(CheckPermission::class . ':users.edit')->name('index');
            Route::post('/', [\App\Modules\Admin\Controllers\NotificationController::class, 'send'])->middleware(CheckPermission::class . ':users.edit')->name('send');
        });

        Route::prefix('blogs')->name('blogs.')->group(function () {
            Route::get('/', fn () => redirect()->route('admin.blogs.posts.index'))->middleware(CheckPermission::class . ':blogs.view')->name('index');

            Route::prefix('posts')->name('posts.')->group(function () {
                Route::get   ('/',                [\App\Modules\Admin\Controllers\Blog\PostController::class, 'index'])  ->middleware(CheckPermission::class . ':blogs.view')   ->name('index');
                Route::get   ('create',           [\App\Modules\Admin\Controllers\Blog\PostController::class, 'create']) ->middleware(CheckPermission::class . ':blogs.manage') ->name('create');
                Route::post  ('/',                [\App\Modules\Admin\Controllers\Blog\PostController::class, 'store'])  ->middleware(CheckPermission::class . ':blogs.manage') ->name('store');
                Route::post  ('bulk',             [\App\Modules\Admin\Controllers\Blog\PostController::class, 'bulk'])   ->middleware(CheckPermission::class . ':blogs.manage') ->name('bulk');
                Route::post  ('upload',           [\App\Modules\Admin\Controllers\Blog\PostController::class, 'upload']) ->middleware(CheckPermission::class . ':blogs.manage') ->name('upload');
                Route::get   ('{post}/edit',      [\App\Modules\Admin\Controllers\Blog\PostController::class, 'edit'])   ->middleware(CheckPermission::class . ':blogs.manage') ->name('edit');
                Route::put   ('{post}',           [\App\Modules\Admin\Controllers\Blog\PostController::class, 'update']) ->middleware(CheckPermission::class . ':blogs.manage') ->name('update');
                Route::delete('{post}',           [\App\Modules\Admin\Controllers\Blog\PostController::class, 'destroy'])->middleware(CheckPermission::class . ':blogs.manage') ->name('destroy');
            });

            Route::prefix('categories')->name('categories.')->group(function () {
                Route::get   ('/',             [\App\Modules\Admin\Controllers\Blog\CategoryController::class, 'index'])  ->middleware(CheckPermission::class . ':blogs.view')   ->name('index');
                Route::post  ('/',             [\App\Modules\Admin\Controllers\Blog\CategoryController::class, 'store'])  ->middleware(CheckPermission::class . ':blogs.manage') ->name('store');
                Route::put   ('{category}',    [\App\Modules\Admin\Controllers\Blog\CategoryController::class, 'update']) ->middleware(CheckPermission::class . ':blogs.manage') ->name('update');
                Route::delete('{category}',    [\App\Modules\Admin\Controllers\Blog\CategoryController::class, 'destroy'])->middleware(CheckPermission::class . ':blogs.manage') ->name('destroy');
            });

            Route::prefix('tags')->name('tags.')->group(function () {
                Route::get   ('/',        [\App\Modules\Admin\Controllers\Blog\TagController::class, 'index'])  ->middleware(CheckPermission::class . ':blogs.view')   ->name('index');
                Route::post  ('/',        [\App\Modules\Admin\Controllers\Blog\TagController::class, 'store'])  ->middleware(CheckPermission::class . ':blogs.manage') ->name('store');
                Route::put   ('{tag}',    [\App\Modules\Admin\Controllers\Blog\TagController::class, 'update']) ->middleware(CheckPermission::class . ':blogs.manage') ->name('update');
                Route::delete('{tag}',    [\App\Modules\Admin\Controllers\Blog\TagController::class, 'destroy'])->middleware(CheckPermission::class . ':blogs.manage') ->name('destroy');
            });

            Route::prefix('comments')->name('comments.')->group(function () {
                Route::get   ('/',                  [\App\Modules\Admin\Controllers\Blog\CommentController::class, 'index'])  ->middleware(CheckPermission::class . ':blogs.comments.moderate')->name('index');
                Route::post  ('bulk',               [\App\Modules\Admin\Controllers\Blog\CommentController::class, 'bulk'])   ->middleware(CheckPermission::class . ':blogs.comments.moderate')->name('bulk');
                Route::post  ('{comment}',          [\App\Modules\Admin\Controllers\Blog\CommentController::class, 'update']) ->middleware(CheckPermission::class . ':blogs.comments.moderate')->name('update');
                Route::post  ('{comment}/edit',     [\App\Modules\Admin\Controllers\Blog\CommentController::class, 'edit'])   ->middleware(CheckPermission::class . ':blogs.comments.moderate')->name('edit');
                Route::post  ('{comment}/reply',    [\App\Modules\Admin\Controllers\Blog\CommentController::class, 'reply'])  ->middleware(CheckPermission::class . ':blogs.comments.reply')   ->name('reply');
                Route::delete('{comment}',          [\App\Modules\Admin\Controllers\Blog\CommentController::class, 'destroy'])->middleware(CheckPermission::class . ':blogs.comments.moderate')->name('destroy');
            });

            Route::prefix('settings')->name('settings.')->group(function () {
                Route::get ('/', [\App\Modules\Admin\Controllers\Blog\SettingController::class, 'edit'])  ->middleware(CheckPermission::class . ':blogs.manage')->name('edit');
                Route::post('/', [\App\Modules\Admin\Controllers\Blog\SettingController::class, 'update'])->middleware(CheckPermission::class . ':blogs.manage')->name('update');
            });

            Route::get('authors', [\App\Modules\Admin\Controllers\Blog\AuthorController::class, 'index'])->middleware(CheckPermission::class . ':blogs.view')->name('authors.index');
        });

        // Adult-content moderation (Task #1208). Suspends or restores
        // a creator's public 18+ tag while preserving the consent /
        // age-affirmation audit trail.
        Route::prefix('adult-moderation')->name('adult-moderation.')->group(function () {
            Route::get ('/',                       [\App\Modules\Admin\Controllers\AdultModerationController::class, 'index'])->middleware(CheckPermission::class . ':users.view')->name('index');
            Route::post('{user}/suspend',          [\App\Modules\Admin\Controllers\AdultModerationController::class, 'suspend'])->middleware(CheckPermission::class . ':users.edit')->whereNumber('user')->name('suspend');
            Route::post('{user}/restore',          [\App\Modules\Admin\Controllers\AdultModerationController::class, 'restore'])->middleware(CheckPermission::class . ':users.edit')->whereNumber('user')->name('restore');
        });

        // Privacy data requests (GDPR/CCPA). Staff review verified
        // deletion/export requests; viewing mirrors the user list gate,
        // while approve/reject (which can trigger irreversible deletion)
        // require the stronger users.delete permission.
        Route::prefix('privacy-requests')->name('privacy-requests.')->group(function () {
            Route::get ('/',                       [\App\Modules\Admin\Controllers\PrivacyRequestController::class, 'index'])->middleware(CheckPermission::class . ':users.view')->name('index');
            Route::get ('{privacyRequest}',        [\App\Modules\Admin\Controllers\PrivacyRequestController::class, 'show'])->middleware(CheckPermission::class . ':users.view')->whereNumber('privacyRequest')->name('show');
            Route::post('{privacyRequest}/approve', [\App\Modules\Admin\Controllers\PrivacyRequestController::class, 'approve'])->middleware(CheckPermission::class . ':users.delete')->whereNumber('privacyRequest')->name('approve');
            Route::post('{privacyRequest}/reject',  [\App\Modules\Admin\Controllers\PrivacyRequestController::class, 'reject'])->middleware(CheckPermission::class . ':users.delete')->whereNumber('privacyRequest')->name('reject');
        });

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])->middleware(CheckPermission::class . ':users.view')->name('index');

            // Role-change audit log (filterable + CSV export). Declared
            // before `{user}` so the literal "role-audits" path isn't
            // swallowed by the wildcard show route. Same gate as
            // mutating roles, since the row-level data is the same as
            // the existing per-user "role change history" panel.
            Route::get('role-audits',        [UserManagementController::class, 'roleAudits'])->middleware(CheckPermission::class . ':users.edit')->name('role-audits.index');
            Route::get('role-audits/export', [UserManagementController::class, 'roleAuditsExport'])->middleware(CheckPermission::class . ':users.edit')->name('role-audits.export');

            // Audit-the-auditor panel: surfaces recent CSV downloads of
            // the role-change audit. Super-admin gate is enforced in
            // the controller (mirroring DemoContentController) since
            // there's no dedicated permission slug for it yet.
            Route::get('role-audit-exports', [UserRoleAuditExportController::class, 'index'])->name('role-audit-exports.index');

            // Admin/staff user-management suite (Task #2106). Literal
            // paths declared before the `{user}` wildcard so they're not
            // swallowed by the show route.
            Route::get ('create', [UserManagementController::class, 'create'])->middleware(CheckPermission::class . ':users.create')->name('create');
            Route::post('/',       [UserManagementController::class, 'store'])->middleware(CheckPermission::class . ':users.create')->name('store');
            // Bulk multiplexes plan-assign + credit-grant; require at least
            // one of the two bulk perms here, enforce the specific one per
            // action inside the controller.
            Route::post('bulk',    [UserManagementController::class, 'bulkAction'])->middleware(CheckPermission::class . ':users.bulk_credits|users.bulk_plan')->name('bulk');

            // Activity log (audit viewer) — read access mirrors the user list.
            Route::get('activity-log',        [ActivityLogController::class, 'index'])->middleware(CheckPermission::class . ':users.view')->name('activity-log.index');
            Route::get('activity-log/export', [ActivityLogController::class, 'export'])->middleware(CheckPermission::class . ':users.view')->name('activity-log.export');

            Route::get('{user}', [UserManagementController::class, 'show'])->middleware(CheckPermission::class . ':users.view')->name('show');
            Route::put('{user}', [UserManagementController::class, 'update'])->middleware(CheckPermission::class . ':users.edit')->name('update');
            Route::delete('{user}', [UserManagementController::class, 'destroy'])->middleware(CheckPermission::class . ':users.delete')->name('destroy');
            Route::post('{user}/impersonate', [UserManagementController::class, 'impersonate'])->middleware(CheckPermission::class . ':users.impersonate')->name('impersonate');
            Route::post('stop-impersonation', [UserManagementController::class, 'stopImpersonation'])->name('stop-impersonation');
            Route::post('{user}/wallet/adjust', [UserManagementController::class, 'adjustWallet'])->middleware(CheckPermission::class . ':users.credits')->name('wallet.adjust');
            Route::get ('{user}/roles', [\App\Modules\Admin\Controllers\UserRoleController::class, 'edit'])->middleware(CheckPermission::class . ':users.assign_roles|users.grant_admin|users.revoke_admin')->whereNumber('user')->name('roles.edit');
            Route::put ('{user}/roles', [\App\Modules\Admin\Controllers\UserRoleController::class, 'update'])->middleware(CheckPermission::class . ':users.assign_roles')->whereNumber('user')->name('roles.update');
            Route::get ('{user}/roles/audit.csv', [\App\Modules\Admin\Controllers\UserRoleController::class, 'export'])->middleware(CheckPermission::class . ':users.edit')->whereNumber('user')->name('roles.audit.export');
            Route::post  ('{user}/admin-access', [\App\Modules\Admin\Controllers\UserRoleController::class, 'grantAdminAccess'])->middleware(CheckPermission::class . ':users.grant_admin')->whereNumber('user')->name('admin-access.grant');
            Route::delete('{user}/admin-access', [\App\Modules\Admin\Controllers\UserRoleController::class, 'revokeAdminAccess'])->middleware(CheckPermission::class . ':users.revoke_admin')->whereNumber('user')->name('admin-access.revoke');

            // Plan assignment (incl. comp / time-limited) + temporary hold.
            Route::post('{user}/assign-plan', [UserManagementController::class, 'assignPlan'])->middleware(CheckPermission::class . ':users.assign_plan')->whereNumber('user')->name('assign-plan');
            Route::post('{user}/suspend',     [UserManagementController::class, 'suspend'])->middleware(CheckPermission::class . ':users.suspend')->whereNumber('user')->name('suspend');
            Route::post('{user}/reactivate',  [UserManagementController::class, 'reactivate'])->middleware(CheckPermission::class . ':users.suspend')->whereNumber('user')->name('reactivate');

            // Attach/detach admin account badges for a single user.
            Route::put('{user}/badges', [UserManagementController::class, 'updateBadges'])->middleware(CheckPermission::class . ':users.edit')->whereNumber('user')->name('badges.update');
        });

        // Admin-managed account badges (definition CRUD). Listing mirrors
        // the user list (`users.view`); mutations require `users.edit`.
        Route::prefix('badges')->name('badges.')->group(function () {
            Route::get('/',            [AccountBadgeController::class, 'index'])->middleware(CheckPermission::class . ':users.view')->name('index');
            Route::post('/',           [AccountBadgeController::class, 'store'])->middleware(CheckPermission::class . ':users.edit')->name('store');
            Route::put('{badge}',      [AccountBadgeController::class, 'update'])->middleware(CheckPermission::class . ':users.edit')->whereNumber('badge')->name('update');
            Route::delete('{badge}',   [AccountBadgeController::class, 'destroy'])->middleware(CheckPermission::class . ':users.edit')->whereNumber('badge')->name('destroy');
        });

        // Self-serve account badge requests review queue (Task #2910).
        // Gated by a dedicated permission so it can be granted independently
        // of the badge-definition CRUD above.
        Route::prefix('badge-requests')->name('badge-requests.')->middleware(CheckPermission::class . ':badge_requests.review')->group(function () {
            Route::get('/',                       [\App\Modules\Admin\Controllers\BadgeRequestController::class, 'index'])->name('index');
            Route::get('{badgeRequest}',          [\App\Modules\Admin\Controllers\BadgeRequestController::class, 'review'])->whereNumber('badgeRequest')->name('review');
            Route::post('{badgeRequest}/approve', [\App\Modules\Admin\Controllers\BadgeRequestController::class, 'approve'])->whereNumber('badgeRequest')->name('approve');
            Route::post('{badgeRequest}/reject',  [\App\Modules\Admin\Controllers\BadgeRequestController::class, 'reject'])->whereNumber('badgeRequest')->name('reject');
        });
    });
});
