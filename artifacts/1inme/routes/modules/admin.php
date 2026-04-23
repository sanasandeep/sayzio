<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Admin\Controllers\AuthController;
use App\Modules\Admin\Controllers\PasswordResetController;
use App\Modules\Admin\Controllers\DashboardController;
use App\Modules\Admin\Controllers\StaffController;
use App\Modules\Admin\Controllers\UserManagementController;
use App\Modules\Admin\Controllers\RoleController;
use App\Modules\Admin\Controllers\PlanController;
use App\Modules\Admin\Controllers\AddonController;
use App\Modules\Admin\Controllers\CoinPackageController;
use App\Modules\Admin\Controllers\OnboardingSlideController;
use App\Modules\Admin\Controllers\WalletSettingsController;
use App\Modules\Admin\Controllers\LinkManagementController;
use App\Modules\Admin\Controllers\CoachDefaultsController;
use App\Modules\Admin\Controllers\TemplateController;
use App\Modules\Admin\Controllers\AdminAssetController;
use App\Modules\Admin\Controllers\BrandingController;
use App\Modules\Admin\Controllers\DomainController as AdminDomainController;
use App\Modules\Admin\Controllers\SocialOAuthSettingsController;
use App\Modules\Admin\Controllers\SpamRuleStatsController;
use App\Modules\Admin\Controllers\BannedNameController;
use App\Modules\Admin\Controllers\TaxController;
use App\Modules\Admin\Controllers\GatewaySettingsController;
use App\Modules\Admin\Controllers\PendingPaymentController;
use App\Modules\Admin\Controllers\DemoContentController;
use App\Modules\Admin\Middleware\CheckPermission;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('demo-login', [AuthController::class, 'demoLogin'])->name('demo.login');

    Route::get('forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');

    Route::middleware([\App\Modules\Admin\Middleware\AdminAuth::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('demo-content')->name('demo-content.')->group(function () {
            Route::get('/',     [DemoContentController::class, 'index'])->name('index');
            Route::post('seed', [DemoContentController::class, 'seed'])->name('seed');
            Route::post('wipe', [DemoContentController::class, 'wipe'])->name('wipe');
        });

        Route::prefix('staff')->name('staff.')->group(function () {
            Route::get('/', [StaffController::class, 'index'])->middleware(CheckPermission::class . ':staff.view')->name('index');
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
            Route::get('{kind}/{id}/edit', [TemplateController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('{kind}/{id}', [TemplateController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('{kind}/{id}/toggle', [TemplateController::class, 'toggle'])->middleware(CheckPermission::class . ':settings.manage')->name('toggle');
            Route::delete('{kind}/{id}', [TemplateController::class, 'destroy'])->middleware(CheckPermission::class . ':settings.manage')->name('destroy');
        });

        Route::prefix('domains')->name('domains.')->group(function () {
            Route::get('/', [AdminDomainController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::post('/', [AdminDomainController::class, 'store'])->middleware(CheckPermission::class . ':settings.manage')->name('store');
            Route::put('{domain}', [AdminDomainController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
            Route::post('{domain}/verify', [AdminDomainController::class, 'verify'])->middleware(CheckPermission::class . ':settings.manage')->name('verify');
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

        Route::prefix('spam-rules')->name('spam-rules.')->group(function () {
            Route::get('/', [SpamRuleStatsController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
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
        Route::prefix('cookie-consent')->name('cookie-consent.')->group(function () {
            Route::get('/',  [\App\Modules\Admin\Controllers\CookieConsentController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('/', [\App\Modules\Admin\Controllers\CookieConsentController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });
        Route::prefix('marketing-settings')->name('marketing-settings.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\MarketingSettingsController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::put('/', [\App\Modules\Admin\Controllers\MarketingSettingsController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
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

        Route::prefix('wallet-settings')->name('wallet-settings.')->group(function () {
            Route::get('/',  [WalletSettingsController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('/',  [WalletSettingsController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
        });

        // AI Engine: OpenAI key, models/rates, wallet→credits conversion,
        // credit packs, plus per-user usage report and adjustments.
        Route::prefix('ai-engine')->name('ai-engine.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\AiEngineController::class, 'edit'])->middleware(CheckPermission::class . ':settings.manage')->name('edit');
            Route::put('/', [\App\Modules\Admin\Controllers\AiEngineController::class, 'update'])->middleware(CheckPermission::class . ':settings.manage')->name('update');
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

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])->middleware(CheckPermission::class . ':users.view')->name('index');
            Route::get('{user}', [UserManagementController::class, 'show'])->middleware(CheckPermission::class . ':users.view')->name('show');
            Route::put('{user}', [UserManagementController::class, 'update'])->middleware(CheckPermission::class . ':users.edit')->name('update');
            Route::delete('{user}', [UserManagementController::class, 'destroy'])->middleware(CheckPermission::class . ':users.delete')->name('destroy');
            Route::post('{user}/impersonate', [UserManagementController::class, 'impersonate'])->middleware(CheckPermission::class . ':users.impersonate')->name('impersonate');
            Route::post('stop-impersonation', [UserManagementController::class, 'stopImpersonation'])->name('stop-impersonation');
            Route::post('{user}/wallet/adjust', [UserManagementController::class, 'adjustWallet'])->middleware(CheckPermission::class . ':users.edit')->name('wallet.adjust');
        });
    });
});
