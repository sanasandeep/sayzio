<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Admin\Controllers\AdminAssetController;
use App\Modules\Common\Controllers\RedirectController;
use App\Modules\Common\Controllers\PublicQrController;
use App\Modules\User\Controllers\UserFileController;

Route::get('/admin-assets/{id}/{filename}', [AdminAssetController::class, 'serve'])
    ->where('filename', '.*')
    ->name('admin.assets.serve');

// ---- Public Creators directory ----
Route::get('/creators', [\App\Modules\Common\Controllers\CreatorsController::class, 'index'])->name('creators.index');

// Public viewer feed (works for ViewerSession OR dashboard auth).
Route::get ('/feed',                  [\App\Modules\User\Controllers\FeedController::class, 'index'])->name('feed.index');
Route::post('/feed/notifications/read',[\App\Modules\User\Controllers\FeedController::class, 'markAllRead'])->name('feed.notifications.read');

// ---- Viewer (biolink visitor) AJAX auth + follow toggle ----
Route::post  ('/viewer/otp/send',   [\App\Modules\Common\Controllers\ViewerAuthController::class, 'sendOtp'])->middleware('throttle:5,1')->name('viewer.otp.send');
Route::post  ('/viewer/otp/verify', [\App\Modules\Common\Controllers\ViewerAuthController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('viewer.otp.verify');
Route::get   ('/viewer/me',         [\App\Modules\Common\Controllers\ViewerAuthController::class, 'me'])->name('viewer.me');
Route::post  ('/viewer/logout',     [\App\Modules\Common\Controllers\ViewerAuthController::class, 'logout'])->name('viewer.logout');
Route::post  ('/viewer/follow/{creator}', [\App\Modules\Common\Controllers\ViewerAuthController::class, 'toggleFollow'])->middleware('throttle:30,1')->name('viewer.follow.toggle')->where('creator', '[0-9]+');

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

// ---- Public marketing & legal pages (must precede the catch-all /{alias} routes) ----
Route::get('/login',    fn () => redirect()->route('user.login'))->name('login.page');
Route::get('/register', fn () => redirect()->route('user.register'))->name('register.page');

Route::controller(\App\Modules\Common\Controllers\SitePageController::class)->group(function () {
    Route::get('/features',     fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('features'))->name('site.features');
    Route::get('/how-it-works', fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('how-it-works'))->name('site.how-it-works');
    Route::get('/about',        fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('about'))->name('site.about');
    Route::get('/contact',      fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('contact'))->name('site.contact');
    Route::get('/faqs',         fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('faqs'))->name('site.faqs');
    Route::get('/terms',        fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('terms'))->name('site.terms');
    Route::get('/refunds',      fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('refunds'))->name('site.refunds');
    Route::get('/privacy',      fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('privacy'))->name('site.privacy');
    Route::get('/gdpr',         fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('gdpr'))->name('site.gdpr');
    Route::get('/cookies',      fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('cookies'))->name('site.cookies');
    Route::get('/discovery',     fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('discovery'))->name('site.discovery');
    Route::get('/creators-feed', fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('creators-feed'))->name('site.creators-feed');
});
Route::post('/contact', [\App\Modules\Common\Controllers\SitePageController::class, 'submitContact'])
    ->name('site.contact.submit')->middleware('throttle:10,10');

// Public referral tracking — must precede the catch-all /{alias} routes.
Route::get('/r/{code}', [\App\Modules\User\Controllers\ReferralController::class, 'track'])
    ->name('referrals.track')
    ->where('code', '[a-z0-9_\-]{3,32}');

Route::get('/{alias}/manifest.json', [RedirectController::class, 'manifest'])->name('redirect.manifest')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks|login|register|features|how-it-works|about|contact|faqs|terms|refunds|privacy|gdpr|cookies|discovery|creators-feed).*$');
Route::get('/{alias}', [RedirectController::class, 'handle'])->name('redirect.handle')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks|login|register|features|how-it-works|about|contact|faqs|terms|refunds|privacy|gdpr|cookies|discovery|creators-feed).*$');
Route::post('/{alias}/track/session', [\App\Modules\Common\Controllers\EngagementController::class, 'startSession'])->name('track.session.start')->where('alias', '[^/]+')->middleware('throttle:60,1');
Route::post('/{alias}/track/heartbeat', [\App\Modules\Common\Controllers\EngagementController::class, 'heartbeat'])->name('track.heartbeat')->where('alias', '[^/]+')->middleware('throttle:120,1');
Route::post('/{alias}/subscribe', [RedirectController::class, 'subscribe'])->name('redirect.subscribe')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks).*$')->middleware('throttle:10,1');
Route::post('/{alias}', [RedirectController::class, 'handle'])->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks).*$');
Route::get('/{alias}/b/{blockId}', [RedirectController::class, 'handleBlockClick'])->name('redirect.block')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks).*$');
Route::get('/{alias}/download', [RedirectController::class, 'rawFileDownload'])->name('redirect.file.raw')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks).*$');
Route::get('/{alias}/rsvp',  [RedirectController::class, 'rsvpForm'])->name('redirect.rsvp.form')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks).*$');
Route::post('/{alias}/rsvp', [RedirectController::class, 'rsvpSubmit'])->name('redirect.rsvp.submit')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks).*$')->middleware('throttle:10,1');
