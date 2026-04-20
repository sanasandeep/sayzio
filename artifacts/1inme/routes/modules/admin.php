<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Admin\Controllers\AuthController;
use App\Modules\Admin\Controllers\PasswordResetController;
use App\Modules\Admin\Controllers\DashboardController;
use App\Modules\Admin\Controllers\StaffController;
use App\Modules\Admin\Controllers\UserManagementController;
use App\Modules\Admin\Controllers\RoleController;
use App\Modules\Admin\Controllers\PlanController;
use App\Modules\Admin\Controllers\LinkManagementController;
use App\Modules\Admin\Controllers\CoachDefaultsController;
use App\Modules\Admin\Controllers\TemplateController;
use App\Modules\Admin\Controllers\AdminAssetController;
use App\Modules\Admin\Controllers\BrandingController;
use App\Modules\Admin\Controllers\SocialOAuthSettingsController;
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
            Route::delete('{plan}', [PlanController::class, 'destroy'])->middleware(CheckPermission::class . ':plans.manage')->name('destroy');
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

        Route::prefix('referrals')->name('referrals.')->group(function () {
            Route::get('/', [\App\Modules\Admin\Controllers\ReferralController::class, 'index'])->middleware(CheckPermission::class . ':settings.manage')->name('index');
            Route::post('toggle', [\App\Modules\Admin\Controllers\ReferralController::class, 'toggle'])->middleware(CheckPermission::class . ':settings.manage')->name('toggle');
        });

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])->middleware(CheckPermission::class . ':users.view')->name('index');
            Route::get('{user}', [UserManagementController::class, 'show'])->middleware(CheckPermission::class . ':users.view')->name('show');
            Route::put('{user}', [UserManagementController::class, 'update'])->middleware(CheckPermission::class . ':users.edit')->name('update');
            Route::delete('{user}', [UserManagementController::class, 'destroy'])->middleware(CheckPermission::class . ':users.delete')->name('destroy');
            Route::post('{user}/impersonate', [UserManagementController::class, 'impersonate'])->middleware(CheckPermission::class . ':users.impersonate')->name('impersonate');
            Route::post('stop-impersonation', [UserManagementController::class, 'stopImpersonation'])->name('stop-impersonation');
        });
    });
});
