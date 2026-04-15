<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Common\Controllers\RedirectController;
use App\Modules\Common\Controllers\PublicQrController;
use App\Modules\User\Controllers\UserFileController;

Route::get('/qr/link/{alias}', [PublicQrController::class, 'forLink'])->name('qr.public.link');
Route::get('/qr/render', [PublicQrController::class, 'render'])->name('qr.public.render');

Route::get('/f/{id}/{filename}', [UserFileController::class, 'serve'])->name('file.serve')->where('id', '[0-9]+');

Route::get('/{alias}', [RedirectController::class, 'handle'])->name('redirect.handle')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f).*$');
Route::post('/{alias}', [RedirectController::class, 'handle'])->where('alias', '^(?!user|admin|qr|storage|sanctum|api).*$');
Route::get('/{alias}/b/{blockId}', [RedirectController::class, 'handleBlockClick'])->name('redirect.block')->where('alias', '^(?!user|admin|qr|storage|sanctum|api).*$');
Route::get('/{alias}/download', [RedirectController::class, 'rawFileDownload'])->name('redirect.file.raw')->where('alias', '^(?!user|admin|qr|storage|sanctum|api).*$');
