<?php

use Illuminate\Support\Facades\Route;
use App\Modules\User\Controllers\AuthController;
use App\Modules\User\Controllers\PasswordResetController;
use App\Modules\User\Controllers\DashboardController;
use App\Modules\User\Controllers\ProfileController;
use App\Modules\User\Controllers\ProjectController;
use App\Modules\User\Controllers\LinkController;
use App\Modules\User\Controllers\PixelController;
use App\Modules\User\Controllers\FileLinkController;
use App\Modules\User\Controllers\IcsLinkController;
use App\Modules\User\Controllers\VcfLinkController;
use App\Modules\User\Controllers\QrCodeController;
use App\Modules\User\Middleware\CheckPlanLimit;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::prefix('user')->name('user.')->group(function () {
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->name('register.submit');
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');

    Route::get('forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');

    Route::get('verify-email', [AuthController::class, 'showVerifyEmail'])->middleware('auth')->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['auth', 'signed'])->name('verification.verify');
    Route::post('verify-email/resend', [AuthController::class, 'resendVerification'])->middleware(['auth', 'throttle:6,1'])->name('verification.send');

    Route::middleware('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'edit'])->name('edit');
            Route::put('/', [ProfileController::class, 'update'])->name('update');
            Route::put('password', [ProfileController::class, 'updatePassword'])->name('password');
        });

        Route::resource('projects', ProjectController::class)->except(['store']);
        Route::post('projects', [ProjectController::class, 'store'])->middleware(CheckPlanLimit::class . ':projects')->name('projects.store');

        Route::resource('links', LinkController::class)->except(['store']);
        Route::post('links', [LinkController::class, 'store'])->middleware(CheckPlanLimit::class . ':links')->name('links.store');
        Route::post('links/{link}/toggle-active', [LinkController::class, 'toggleActive'])->name('links.toggle-active');

        Route::get('links-file/create', [FileLinkController::class, 'create'])->name('links.file.create');
        Route::post('links-file', [FileLinkController::class, 'store'])->middleware(CheckPlanLimit::class . ':links')->name('links.file.store');
        Route::get('links-ics/create', [IcsLinkController::class, 'create'])->name('links.ics.create');
        Route::post('links-ics', [IcsLinkController::class, 'store'])->middleware(CheckPlanLimit::class . ':links')->name('links.ics.store');
        Route::get('links-vcf/create', [VcfLinkController::class, 'create'])->name('links.vcf.create');
        Route::post('links-vcf', [VcfLinkController::class, 'store'])->middleware(CheckPlanLimit::class . ':links')->name('links.vcf.store');

        Route::get('links/{link}/qrcode', [QrCodeController::class, 'show'])->name('links.qrcode');
        Route::post('links/{link}/qrcode', [QrCodeController::class, 'generate'])->name('links.qrcode.download');
        Route::get('links/{link}/qrcode/preview', [QrCodeController::class, 'preview'])->name('links.qrcode.preview');

        Route::get('qrcode', [QrCodeController::class, 'standalone'])->name('qrcode');
        Route::post('qrcode', [QrCodeController::class, 'generateStandalone'])->name('qrcode.download');
        Route::get('qrcode/preview', [QrCodeController::class, 'previewStandalone'])->name('qrcode.preview');

        Route::resource('pixels', PixelController::class)->except(['show', 'store']);
        Route::post('pixels', [PixelController::class, 'store'])->middleware(CheckPlanLimit::class . ':pixels')->name('pixels.store');
    });
});
