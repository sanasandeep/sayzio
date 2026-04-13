<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Admin\Controllers\AuthController;
use App\Modules\Admin\Controllers\DashboardController;
use App\Modules\Admin\Controllers\StaffController;
use App\Modules\Admin\Controllers\UserManagementController;
use App\Modules\Admin\Controllers\RoleController;
use App\Modules\Admin\Controllers\PlanController;
use App\Modules\Admin\Middleware\CheckPermission;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');

    Route::middleware([\App\Modules\Admin\Middleware\AdminAuth::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::middleware([CheckPermission::class . ':staff.view'])->group(function () {
            Route::resource('staff', StaffController::class)->parameters(['staff' => 'staff']);
        });

        Route::middleware([CheckPermission::class . ':roles.view'])->group(function () {
            Route::resource('roles', RoleController::class);
        });

        Route::middleware([CheckPermission::class . ':plans.view'])->group(function () {
            Route::resource('plans', PlanController::class);
        });

        Route::middleware([CheckPermission::class . ':users.view'])->group(function () {
            Route::prefix('users')->name('users.')->group(function () {
                Route::get('/', [UserManagementController::class, 'index'])->name('index');
                Route::get('{user}', [UserManagementController::class, 'show'])->name('show');
                Route::put('{user}', [UserManagementController::class, 'update'])->name('update');
                Route::delete('{user}', [UserManagementController::class, 'destroy'])->name('destroy');
                Route::post('{user}/impersonate', [UserManagementController::class, 'impersonate'])
                    ->middleware(CheckPermission::class . ':users.impersonate')
                    ->name('impersonate');
                Route::post('stop-impersonation', [UserManagementController::class, 'stopImpersonation'])->name('stop-impersonation');
            });
        });
    });
});
