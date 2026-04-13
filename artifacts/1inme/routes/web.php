<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Common\Controllers\RedirectController;

Route::get('/r/{alias}', [RedirectController::class, 'handle'])->name('redirect.handle');
Route::post('/r/{alias}', [RedirectController::class, 'handle']);
Route::get('/r/{alias}/download', [RedirectController::class, 'rawFileDownload'])->name('redirect.file.raw');
