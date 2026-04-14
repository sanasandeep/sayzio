<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Common\Controllers\RedirectController;
use App\Modules\Common\Controllers\PublicQrController;

Route::get('/qr/link/{alias}', [PublicQrController::class, 'forLink'])->name('qr.public.link');
Route::get('/qr/render', [PublicQrController::class, 'render'])->name('qr.public.render');

Route::get('/{alias}', [RedirectController::class, 'handle'])->name('redirect.handle')->where('alias', '^(?!user|admin|qr|storage|sanctum|api).*$');
Route::post('/{alias}', [RedirectController::class, 'handle'])->where('alias', '^(?!user|admin|qr|storage|sanctum|api).*$');
Route::get('/{alias}/download', [RedirectController::class, 'rawFileDownload'])->name('redirect.file.raw')->where('alias', '^(?!user|admin|qr|storage|sanctum|api).*$');
