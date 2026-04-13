<?php

use Illuminate\Support\Facades\Route;
use App\Modules\User\Controllers\AuthController;
use App\Modules\User\Controllers\DashboardController;
use App\Modules\User\Controllers\ProfileController;
use App\Modules\User\Controllers\ProjectController;
use App\Modules\User\Controllers\LinkController;
use App\Modules\User\Controllers\PixelController;
use App\Modules\User\Controllers\FileLinkController;
use App\Modules\User\Controllers\IcsLinkController;
use App\Modules\User\Controllers\VcfLinkController;

Route::get('/', function () {
    return redirect()->route('user.login');
});

Route::prefix('user')->name('user.')->group(function () {
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->name('register.submit');
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');

    Route::middleware('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'edit'])->name('edit');
            Route::put('/', [ProfileController::class, 'update'])->name('update');
            Route::put('password', [ProfileController::class, 'updatePassword'])->name('password');
        });

        Route::resource('projects', ProjectController::class);
        Route::resource('links', LinkController::class);
        Route::post('links/{link}/toggle-active', [LinkController::class, 'toggleActive'])->name('links.toggle-active');

        Route::get('links-file/create', [FileLinkController::class, 'create'])->name('links.file.create');
        Route::post('links-file', [FileLinkController::class, 'store'])->name('links.file.store');
        Route::get('links-ics/create', [IcsLinkController::class, 'create'])->name('links.ics.create');
        Route::post('links-ics', [IcsLinkController::class, 'store'])->name('links.ics.store');
        Route::get('links-vcf/create', [VcfLinkController::class, 'create'])->name('links.vcf.create');
        Route::post('links-vcf', [VcfLinkController::class, 'store'])->name('links.vcf.store');

        Route::resource('pixels', PixelController::class)->except(['show']);
    });
});
