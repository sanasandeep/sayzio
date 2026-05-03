<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Admin\Controllers\AdminAssetController;
use App\Modules\Common\Controllers\RedirectController;
use App\Modules\Common\Controllers\PublicQrController;
use App\Modules\User\Controllers\ExtensionHandshakeController;
use App\Modules\User\Controllers\UserFileController;

// ---- Browser extension sign-in handshake ----
// The 1INME browser extension opens this URL in a new tab. If signed
// in, the page embeds a fresh Sanctum token + user payload that the
// extension's content script captures via browser.runtime.sendMessage.
Route::get('/extension/handshake', [ExtensionHandshakeController::class, 'show'])
    ->name('extension.handshake');

Route::get('/admin-assets/{id}/{filename}', [AdminAssetController::class, 'serve'])
    ->where('filename', '.*')
    ->name('admin.assets.serve');

// Public hosted "pay this invoice" link delivered by email. Both routes
// are protected by Laravel signed-URL HMAC, so no session/auth needed.
Route::get('/pay/invoice/{invoice}',  [\App\Modules\User\Controllers\ClientInvoiceController::class, 'payPage'])->name('client-invoice.pay');
Route::post('/pay/invoice/{invoice}', [\App\Modules\User\Controllers\ClientInvoiceController::class, 'payHandoff'])->name('client-invoice.pay.handoff');

// ---- Universal Links / App Links manifests for the iOS + Android apps ----
Route::get('/.well-known/apple-app-site-association',
    [\App\Modules\Common\Controllers\UniversalLinksController::class, 'appleAppSiteAssociation'])
    ->name('well-known.aasa');
Route::get('/.well-known/assetlinks.json',
    [\App\Modules\Common\Controllers\UniversalLinksController::class, 'androidAssetLinks'])
    ->name('well-known.assetlinks');

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

// ---- Community Layer (public): Insider feed, comments/reactions/polls, fan leaderboard ----
// Bound to a parent Link + (optional) BiolinkBlock so the controller can authorize per-block visibility.
Route::prefix('community/{link}')->where(['link' => '[0-9]+'])->group(function () {
    Route::post('blocks/{block}/insider/join',     [\App\Modules\User\Controllers\CommunityPublicController::class, 'joinInsider'])->middleware('throttle:10,1')->name('community.insider.join');
    Route::post('blocks/{block}/insider/join-paid',[\App\Modules\User\Controllers\CommunityPublicController::class, 'joinInsiderPaid'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->middleware('throttle:30,1')->name('community.insider.join_paid');
    Route::get ('blocks/{block}/insider/feed',     [\App\Modules\User\Controllers\CommunityPublicController::class, 'feed'])->name('community.insider.feed');
    Route::get ('blocks/{block}/comments',         [\App\Modules\User\Controllers\CommunityPublicController::class, 'listComments'])->name('community.comments.list');
    Route::post('blocks/{block}/comments',         [\App\Modules\User\Controllers\CommunityPublicController::class, 'postComment'])->middleware('throttle:20,1')->name('community.comments.post');
    Route::post('blocks/{block}/reactions',        [\App\Modules\User\Controllers\CommunityPublicController::class, 'react'])->middleware('throttle:60,1')->name('community.reactions.toggle');
    Route::get ('blocks/{block}/polls',            [\App\Modules\User\Controllers\CommunityPublicController::class, 'listPolls'])->name('community.polls.list');
    Route::post('blocks/{block}/polls/{poll}/vote',[\App\Modules\User\Controllers\CommunityPublicController::class, 'votePoll'])->middleware('throttle:30,1')->name('community.polls.vote');
    Route::get ('leaderboard',                     [\App\Modules\User\Controllers\CommunityPublicController::class, 'leaderboard'])->name('community.leaderboard');
    Route::post('engagement',                      [\App\Modules\User\Controllers\CommunityPublicController::class, 'trackEngagement'])->middleware('throttle:120,1')->name('community.engagement.track');
});

// Direct-message block: viewer sending/loading a thread on a biolink page.
Route::get ('/viewer/dm/{link}/thread', [\App\Modules\Common\Controllers\ViewerDirectMessageController::class, 'thread'])->where('link', '[0-9]+')->name('viewer.dm.thread');
Route::post('/viewer/dm/{link}/send',   [\App\Modules\Common\Controllers\ViewerDirectMessageController::class, 'send'])->where('link', '[0-9]+')->middleware('throttle:20,1')->name('viewer.dm.send');

// ---- AI Companion public chat endpoint + embed bundle / iframe ----
// Public, auth-free. Origin checks are enforced inside the controller
// for the `embed` placement; biolink + inbox bypass that gate because
// they always run from a 1INME-owned origin.
Route::post   ('/companion/{publicId}/session', [\App\Modules\Common\Controllers\PublicCompanionController::class, 'session'])
    ->where('publicId', 'cmp_[a-z0-9]{20}')
    ->name('public.companion.session');
Route::post   ('/companion/{publicId}/rate',    [\App\Modules\Common\Controllers\PublicCompanionController::class, 'rate'])
    ->where('publicId', 'cmp_[a-z0-9]{20}')
    ->name('public.companion.rate');
Route::post   ('/companion/{publicId}/message', [\App\Modules\Common\Controllers\PublicCompanionController::class, 'message'])
    ->where('publicId', 'cmp_[a-z0-9]{20}')
    ->middleware('throttle:60,1')
    ->name('public.companion.message');
Route::options('/companion/{publicId}/message', [\App\Modules\Common\Controllers\PublicCompanionController::class, 'preflight'])
    ->where('publicId', 'cmp_[a-z0-9]{20}');
Route::get    ('/embed/companion.js',                  [\App\Modules\Common\Controllers\PublicCompanionController::class, 'bundle'])->name('public.companion.bundle');
Route::get    ('/embed/companion/{publicId}/iframe',   [\App\Modules\Common\Controllers\PublicCompanionController::class, 'iframe'])
    ->where('publicId', 'cmp_[a-z0-9]{20}')
    ->name('public.companion.iframe');

// ---- Site-wide AI Assistant (marketing + logged-in widget) ----
Route::prefix('assistant')->name('site-assistant.')->group(function () {
    Route::get ('bootstrap', [\App\Modules\Common\Controllers\SiteAssistantController::class, 'bootstrap'])->name('bootstrap');
    Route::post('session',   [\App\Modules\Common\Controllers\SiteAssistantController::class, 'session'])->middleware('throttle:60,1')->name('session');
    Route::post('message',   [\App\Modules\Common\Controllers\SiteAssistantController::class, 'message'])->middleware('throttle:60,1')->name('message');
    Route::post('stream',    [\App\Modules\Common\Controllers\SiteAssistantController::class, 'stream'])->middleware('throttle:60,1')->name('stream');
    Route::post('choice',    [\App\Modules\Common\Controllers\SiteAssistantController::class, 'choice'])->middleware('throttle:60,1')->name('choice');
    Route::post('handoff',   [\App\Modules\Common\Controllers\SiteAssistantController::class, 'handoff'])->middleware('throttle:10,1')->name('handoff');
    Route::post('low-balance-click', [\App\Modules\Common\Controllers\SiteAssistantController::class, 'lowBalanceClick'])->middleware('throttle:60,1')->name('low-balance-click');
});

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
    Route::get('/workspace-team', fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('workspace-team'))->name('site.workspace-team');
    Route::get('/buzz',           fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('buzz'))->name('site.buzz');
    Route::get('/ai-chatbot',         fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('ai-chatbot'))->name('site.ai-chatbot');
    Route::get('/ai-agent',           fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('ai-agent'))->name('site.ai-agent');
    Route::get('/ai-widget',          fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('ai-widget'))->name('site.ai-widget');
    Route::get('/ai-voice-assistant', fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('ai-voice-assistant'))->name('site.ai-voice-assistant');
    Route::view('/docs/api', 'public.api-docs')->name('site.api-docs');
    // Standalone marketing page for the Résumé / Portfolio Builder module.
    Route::view('/resume-builder', 'public.resume-builder')->name('site.resume-builder');
    Route::get('/services', fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('services'))->name('site.services');
    Route::get('/pricing',          [\App\Modules\Common\Controllers\PricingPagesController::class, 'plans'])   ->name('site.pricing');
    // Lightweight AJAX target the /pricing Alpine toggle pings whenever
    // the visitor flips Monthly ↔ Annual. The page itself doesn't
    // navigate on toggle, so this is what makes the choice survive a
    // refresh / menu navigation. CSRF stays on (token is in the page
    // meta tag and forwarded by the fetch call).
    Route::post('/pricing/billing-cycle', [\App\Modules\Common\Controllers\PricingPagesController::class, 'rememberCycle'])
        ->middleware('throttle:30,1')
        ->name('site.pricing.cycle');
    Route::get('/coins', function () {
        return redirect()->route('site.pricing', ['view' => 'coins'], 301);
    })->name('site.coins');
    Route::get('/premium-features', [\App\Modules\Common\Controllers\PricingPagesController::class, 'features'])->name('site.premium-features');
    Route::get('/{slug}/history', [\App\Modules\Common\Controllers\SitePageController::class, 'history'])
        ->where('slug', 'terms|privacy|refunds|cookies|gdpr')
        ->name('site.policy.history');
});
Route::post('/contact', [\App\Modules\Common\Controllers\SitePageController::class, 'submitContact'])
    ->name('site.contact.submit')->middleware('throttle:10,10');

// ---- Marketing CTA click tracking (anonymous, allow-listed) ----
Route::post('/marketing-events/track', [\App\Modules\Common\Controllers\MarketingEventController::class, 'track'])
    ->middleware('throttle:60,1')
    ->name('marketing-events.track');
Route::post('/newsletter/subscribe', [\App\Modules\Common\Controllers\NewsletterController::class, 'subscribe'])
    ->name('site.newsletter.subscribe')->middleware('throttle:10,10');
Route::get('/newsletter/unsubscribe/{subscriber}', [\App\Modules\Common\Controllers\NewsletterController::class, 'unsubscribe'])
    ->name('site.newsletter.unsubscribe')->middleware('throttle:30,10');
// RFC 8058 one-click POST target. Inbox providers (Gmail, Apple Mail) hit
// this same signed URL with POST when the recipient taps the native
// "Unsubscribe" chip. CSRF is exempted in VerifyCsrfToken.
// Provider one-click POSTs typically arrive from a small pool of mailbox-
// provider egress IPs (Gmail/Apple), so a tight per-IP throttle would cause
// false 429s and silently lose opt-outs across a campaign. The signed URL
// already bounds abuse, so we use a much more generous bucket here.
Route::post('/newsletter/unsubscribe/{subscriber}', [\App\Modules\Common\Controllers\NewsletterController::class, 'unsubscribe'])
    ->name('site.newsletter.unsubscribe.post')->middleware('throttle:600,1');

// ---- Public Unsubscribe Center (3-way Subscribe block "Manage subscriptions") ----
Route::get ('/subscriptions/manage',
    [\App\Modules\Common\Controllers\SubscriptionsController::class, 'manage'])
    ->name('site.subscriptions.manage');
Route::post('/subscriptions/manage/send-link',
    [\App\Modules\Common\Controllers\SubscriptionsController::class, 'sendLink'])
    ->name('site.subscriptions.manage.send')
    ->middleware('throttle:10,10');

// ---- Public Blogs (must precede the catch-all /{alias} routes) ----
Route::prefix('blogs')->name('site.blogs.')->controller(\App\Modules\Common\Controllers\BlogController::class)->group(function () {
    Route::get('/',                'index')->name('index');
    Route::get('/rss',             'rss')->name('rss');
    Route::get('/rss.xml',         'rss')->name('rss.xml');
    Route::get('/sitemap.xml',     'sitemap')->name('sitemap');
    Route::get('/category/{slug}', 'category')->name('category')->where('slug', '[a-z0-9-]+');
    Route::get('/tag/{slug}',      'tag')->name('tag')->where('slug', '[a-z0-9-]+');
    Route::get('/{slug}',          'show')->name('show')->where('slug', '[a-z0-9-]+');
    Route::post('/{slug}/comments','postComment')->name('comments.store')->where('slug', '[a-z0-9-]+')->middleware('throttle:10,1');
});

// Public referral tracking — must precede the catch-all /{alias} routes.
Route::get('/r/{code}', [\App\Modules\User\Controllers\ReferralController::class, 'track'])
    ->name('referrals.track')
    ->where('code', '[a-z0-9_\-]{3,32}');

// Stable resume PDF URL. The controller decides access:
//   * Owner (signed-in & handle matches) — always allowed.
//   * Anyone else — allowed only when the owner enabled `is_public_pdf`,
//     otherwise 404 so handle existence isn't leaked.
// Visitor traffic is throttled separately inside the controller using a
// per-IP+handle bucket so a public link can't be hammered. The route's
// own throttle is the broad upper bound for owners and visitors alike.
// Must precede the catch-all `/{alias}` routes below so this isn't
// swallowed by alias resolution.
Route::get('/{handle}/resume.pdf',
    [\App\Modules\User\Controllers\ResumeController::class, 'downloadByHandle'])
    ->middleware(['web', 'throttle:60,1'])
    ->where('handle', '[A-Za-z0-9_.-]{2,40}')
    ->name('resume.public.pdf');

// Public Resume page at /{handle}/resume. Registered BEFORE the
// `/{alias}` catch-alls so the literal `/resume` second segment routes
// here (the alias regex only covers single-segment paths and a small
// allow-list of suffixes that does not include "resume").
Route::get ('/{handle}/resume', [\App\Modules\Common\Controllers\PublicResumeController::class, 'show'])
    ->name('resume.public.show')
    ->where('handle', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks|login|register|features|how-it-works|about|contact|faqs|terms|refunds|privacy|gdpr|cookies|discovery|creators-feed|workspace-team|buzz|ai-chatbot|ai-agent|ai-widget|ai-voice-assistant|docs|newsletter|pricing|coins|premium-features|blogs|creators|feed|viewer|companion|embed|assistant|sp|r)[a-zA-Z0-9_\-\.]+$');
Route::post('/{handle}/resume', [\App\Modules\Common\Controllers\PublicResumeController::class, 'unlock'])
    ->name('resume.public.unlock')
    ->middleware('throttle:10,1')
    ->where('handle', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks).*$');

Route::get('/{alias}/manifest.json', [RedirectController::class, 'manifest'])->name('redirect.manifest')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks|login|register|features|how-it-works|about|contact|faqs|terms|refunds|privacy|gdpr|cookies|discovery|creators-feed|workspace-team|buzz|ai-chatbot|ai-agent|ai-widget|ai-voice-assistant|docs|newsletter|pricing|coins|premium-features|blogs).*$');
Route::get('/{alias}', [RedirectController::class, 'handle'])->name('redirect.handle')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks|login|register|features|how-it-works|about|contact|faqs|terms|refunds|privacy|gdpr|cookies|discovery|creators-feed|workspace-team|buzz|ai-chatbot|ai-agent|ai-widget|ai-voice-assistant|docs|newsletter|pricing|coins|premium-features|blogs).*$');
Route::post('/{alias}/track/session', [\App\Modules\Common\Controllers\EngagementController::class, 'startSession'])->name('track.session.start')->where('alias', '[^/]+')->middleware('throttle:60,1');
Route::post('/{alias}/track/heartbeat', [\App\Modules\Common\Controllers\EngagementController::class, 'heartbeat'])->name('track.heartbeat')->where('alias', '[^/]+')->middleware('throttle:120,1');
Route::post('/{alias}/subscribe', [RedirectController::class, 'subscribe'])->name('redirect.subscribe')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks).*$')->middleware('throttle:10,1');
Route::post('/{alias}', [RedirectController::class, 'handle'])->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks).*$');
Route::get('/{alias}/b/{blockId}', [RedirectController::class, 'handleBlockClick'])->name('redirect.block')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks).*$');
Route::get('/{alias}/download', [RedirectController::class, 'rawFileDownload'])->name('redirect.file.raw')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks).*$');
Route::get('/{alias}/rsvp',  [RedirectController::class, 'rsvpForm'])->name('redirect.rsvp.form')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks).*$');
Route::post('/{alias}/rsvp', [RedirectController::class, 'rsvpSubmit'])->name('redirect.rsvp.submit')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks).*$')->middleware('throttle:10,1');
