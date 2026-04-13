<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Common\Controllers\RedirectController;
use App\Modules\Common\Controllers\PublicQrController;

Route::get('/r/{alias}', [RedirectController::class, 'handle'])->name('redirect.handle');
Route::post('/r/{alias}', [RedirectController::class, 'handle']);
Route::get('/r/{alias}/download', [RedirectController::class, 'rawFileDownload'])->name('redirect.file.raw');

Route::get('/qr/link/{alias}', [PublicQrController::class, 'forLink'])->name('qr.public.link');
Route::get('/qr/render', [PublicQrController::class, 'render'])->name('qr.public.render');
