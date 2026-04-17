<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Common\Controllers\RedirectController;
use App\Modules\Common\Controllers\PublicQrController;
use App\Modules\User\Controllers\UserFileController;

// ---- Public Social-Proof Widget ----
Route::get   ('/sp/{uuid}.js',    [\App\Modules\Common\Controllers\SocialProofPublicController::class, 'loaderJs'])->name('sp.public.js')->where('uuid', '[a-f0-9-]{36}');
Route::get   ('/sp/{uuid}.json',  [\App\Modules\Common\Controllers\SocialProofPublicController::class, 'config'])  ->name('sp.public.config')->where('uuid', '[a-f0-9-]{36}');
Route::post  ('/sp/{uuid}/track', [\App\Modules\Common\Controllers\SocialProofPublicController::class, 'track'])   ->name('sp.public.track')->where('uuid', '[a-f0-9-]{36}')->middleware('throttle:120,1');
Route::options('/sp/{uuid}/track',[\App\Modules\Common\Controllers\SocialProofPublicController::class, 'preflight'])->where('uuid', '[a-f0-9-]{36}');

Route::get('/qr/link/{alias}', [PublicQrController::class, 'forLink'])->name('qr.public.link');
Route::get('/qr/render', [PublicQrController::class, 'render'])->name('qr.public.render');

Route::get('/f/{id}/{filename}', [UserFileController::class, 'serve'])->name('file.serve')->where('id', '[0-9]+');

// ---- Public Forms ----
Route::get('/f/{slug}',          [\App\Modules\User\Controllers\FormController::class, 'publicShow'])->name('forms.public.show')->where('slug', '[a-z0-9-]+');
Route::get('/f/{slug}/iframe',   [\App\Modules\User\Controllers\FormController::class, 'publicIframe'])->name('forms.public.iframe')->where('slug', '[a-z0-9-]+');
Route::get('/f/{slug}/embed.js', [\App\Modules\User\Controllers\FormController::class, 'publicEmbedJs'])->name('forms.public.embed')->where('slug', '[a-z0-9-]+');
Route::post('/f/{slug}',         [\App\Modules\User\Controllers\FormController::class, 'publicSubmit'])->name('forms.public.submit')->where('slug', '[a-z0-9-]+')->middleware('throttle:10,1');

Route::get('/{alias}/manifest.json', [RedirectController::class, 'manifest'])->name('redirect.manifest')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f).*$');
Route::get('/{alias}', [RedirectController::class, 'handle'])->name('redirect.handle')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f).*$');
Route::post('/{alias}/track/session', [\App\Modules\Common\Controllers\EngagementController::class, 'startSession'])->name('track.session.start')->where('alias', '[^/]+')->middleware('throttle:60,1');
Route::post('/{alias}/track/heartbeat', [\App\Modules\Common\Controllers\EngagementController::class, 'heartbeat'])->name('track.heartbeat')->where('alias', '[^/]+')->middleware('throttle:120,1');
Route::post('/{alias}/subscribe', [RedirectController::class, 'subscribe'])->name('redirect.subscribe')->where('alias', '^(?!user|admin|qr|storage|sanctum|api).*$')->middleware('throttle:10,1');
Route::post('/{alias}', [RedirectController::class, 'handle'])->where('alias', '^(?!user|admin|qr|storage|sanctum|api).*$');
Route::get('/{alias}/b/{blockId}', [RedirectController::class, 'handleBlockClick'])->name('redirect.block')->where('alias', '^(?!user|admin|qr|storage|sanctum|api).*$');
Route::get('/{alias}/download', [RedirectController::class, 'rawFileDownload'])->name('redirect.file.raw')->where('alias', '^(?!user|admin|qr|storage|sanctum|api).*$');
Route::get('/{alias}/rsvp',  [RedirectController::class, 'rsvpForm'])->name('redirect.rsvp.form')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f).*$');
Route::post('/{alias}/rsvp', [RedirectController::class, 'rsvpSubmit'])->name('redirect.rsvp.submit')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f).*$')->middleware('throttle:10,1');
