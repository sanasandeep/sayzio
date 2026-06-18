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

// ---- Schema readiness probe (Task #1679) ----
// Lightweight, unauthenticated readiness signal for deployment monitoring.
// Reports whether the DB schema is in sync (no pending migrations) and returns
// HTTP 503 when it is out of date, so external uptime/monitoring can catch an
// incomplete deploy without a human reading the deploy log. Only exposes a
// count (never table/column internals); the admin dashboard banner carries the
// detailed pending-migration list. Note: this is a *separate* signal from the
// deploy's own startup health check (which stays on `/` so the app keeps
// serving on a partial schema — see artifact.toml).
Route::get('/up/schema', function () {
    $report = \App\Modules\Common\Support\SchemaHealth::cached();

    if (! ($report['available'] ?? false)) {
        return response()->json([
            'status'             => 'unknown',
            'pending_migrations' => null,
        ], 200);
    }

    $count = count($report['pending'] ?? []);
    return response()->json([
        'status'             => $count === 0 ? 'ok' : 'out_of_date',
        'pending_migrations' => $count,
    ], $count === 0 ? 200 : 503);
})->name('health.schema');

Route::get('/admin-assets/{id}/{filename}', [AdminAssetController::class, 'serve'])
    ->where('filename', '.*')
    ->name('admin.assets.serve');

// Shareable Dialer "Export vCard" link. Signed-URL HMAC is the only
// authorization (no session/auth) so the owner can hand the URL to anyone;
// the `u` param scopes resolution to that owner.
Route::get('/dialer/vcard', [\App\Modules\User\Controllers\DialerVcardController::class, 'show'])
    ->middleware('signed')
    ->name('user.dialer.vcard');

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

// ── Visitor 18+ age gate (Task #1208) ─────────────────────────────
// Posted from the interstitial shown on /@handle when the creator
// has the adult flag enabled. Stores a per-device cookie for 30 days.
Route::post('/age-gate/confirm', [\App\Modules\Common\Controllers\AgeGateController::class, 'confirm'])
    ->middleware('throttle:30,1')->name('age-gate.confirm');
Route::get ('/age-gate/leave',   [\App\Modules\Common\Controllers\AgeGateController::class, 'leave'])->name('age-gate.leave');

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

    // ── Public Roadmap block (submit / vote / comment / list) ──────────
    Route::get ('blocks/{block}/roadmap',                       [\App\Modules\Common\Controllers\RoadmapPublicController::class, 'list'])->name('community.roadmap.list');
    Route::post('blocks/{block}/roadmap/submit',                [\App\Modules\Common\Controllers\RoadmapPublicController::class, 'submit'])->middleware('throttle:10,1')->name('community.roadmap.submit');
    Route::post('blocks/{block}/roadmap/items/{item}/vote',     [\App\Modules\Common\Controllers\RoadmapPublicController::class, 'vote'])->middleware('throttle:60,1')->name('community.roadmap.vote');
    Route::post('blocks/{block}/roadmap/items/{item}/comments', [\App\Modules\Common\Controllers\RoadmapPublicController::class, 'comment'])->middleware('throttle:20,1')->name('community.roadmap.comment');
});

// Direct-message block: viewer sending/loading a thread on a biolink page.
Route::get ('/viewer/dm/{link}/thread', [\App\Modules\Common\Controllers\ViewerDirectMessageController::class, 'thread'])->where('link', '[0-9]+')->name('viewer.dm.thread');
Route::post('/viewer/dm/{link}/send',   [\App\Modules\Common\Controllers\ViewerDirectMessageController::class, 'send'])->where('link', '[0-9]+')->middleware('throttle:20,1')->name('viewer.dm.send');

// Paid DMs (Task #1210): profile-scoped DM endpoints (/@handle Message
// button), per-attachment unlock checkout, and in-DM tipping. The
// access probe is fast/cheap so it deliberately skips the per-IP send
// throttle that protects the send endpoint from spam.
Route::get ('/viewer/dm/profile/{handle}/access',     [\App\Modules\Common\Controllers\ProfileDirectMessageController::class, 'access'])->name('viewer.profile-dm.access');
Route::get ('/viewer/dm/profile/{handle}/thread',     [\App\Modules\Common\Controllers\ProfileDirectMessageController::class, 'thread'])->name('viewer.profile-dm.thread');
Route::post('/viewer/dm/profile/{handle}/send',       [\App\Modules\Common\Controllers\ProfileDirectMessageController::class, 'send'])->middleware('throttle:30,1')->name('viewer.profile-dm.send');
Route::post('/viewer/dm/attachments/{attachment}/unlock', [\App\Modules\Common\Controllers\ProfileDirectMessageController::class, 'unlockAttachment'])->whereNumber('attachment')->middleware('throttle:30,1')->name('viewer.dm.attachment.unlock');
Route::post('/viewer/dm/threads/{conversation}/tip',  [\App\Modules\Common\Controllers\ProfileDirectMessageController::class, 'tip'])->whereNumber('conversation')->middleware('throttle:20,1')->name('viewer.dm.tip');

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

// ---- Legacy /storage/* fallback → CloudFront ----
// Avatars, covers, post images, verification logos, etc. were historically
// stored as plain `/storage/...` URLs that the local symlink served directly.
// Once the `public` disk is S3-backed the local file is gone, so requests fall
// through to this route (php's dev server / production still serve any file
// that is still present locally). We then redirect to the S3/CloudFront URL.
// Guarded so it never loops when the `public` disk is still local.
Route::get('/storage/{path}', function (string $path) {
    if (config('filesystems.disks.public.driver') !== 's3') {
        abort(404);
    }
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    if (!$disk->exists($path)) {
        abort(404);
    }
    return redirect($disk->url($path), 302);
})->where('path', '.*')->name('storage.cdn.fallback');

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
    Route::view('/docs/api', 'public.api-docs', ['seoKey' => 'api-docs'])->name('site.api-docs');
    // Standalone marketing page for the Résumé / Portfolio Builder module.
    Route::view('/resume-builder', 'public.resume-builder', ['seoKey' => 'resume-builder'])->name('site.resume-builder');
    Route::get('/services', fn () => app(\App\Modules\Common\Controllers\SitePageController::class)->show('services'))->name('site.services');
    // Competitor comparison "vs" landing pages. Data driven by ComparisonContent.
    Route::get('/compare', [\App\Modules\Common\Controllers\SitePageController::class, 'compareIndex'])->name('site.compare.index');
    Route::get('/compare/{competitor}', [\App\Modules\Common\Controllers\SitePageController::class, 'compareShow'])
        ->where('competitor', \App\Modules\Common\Support\ComparisonContent::rivalKeysPattern())
        ->name('site.compare.show');

    // Dedicated "1INME for X" use-case landing pages. Unknown personas fall
    // through the `where` constraint and 404. Persona list is the single
    // source of truth in SitePagesContent::useCaseSlugs().
    Route::get('/for/{persona}', [\App\Modules\Common\Controllers\SitePageController::class, 'useCase'])
        ->where('persona', implode('|', \App\Modules\Common\Support\SitePagesContent::useCaseSlugs()))
        ->name('site.use-case');
    Route::view('/analytics',    'public.analytics',    ['seoKey' => 'analytics'])   ->name('site.analytics');
    Route::view('/audience',     'public.audience',     ['seoKey' => 'audience'])    ->name('site.audience');
    Route::view('/integrations', 'public.integrations', ['seoKey' => 'integrations'])->name('site.integrations');
    Route::view('/domains',      'public.domains',      ['seoKey' => 'domains'])     ->name('site.domains');
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

// ---- Marketing XML sitemap + robots.txt (must precede the catch-all /{alias} routes) ----
// URL list sourced from MarketingSeo so it stays in lockstep with per-page SEO meta.
Route::get('/sitemap_index.xml', [\App\Modules\Common\Controllers\SitemapController::class, 'index'])->name('site.sitemap.index');
Route::get('/sitemap.xml', [\App\Modules\Common\Controllers\SitemapController::class, 'sitemap'])->name('site.sitemap');
Route::get('/robots.txt',  [\App\Modules\Common\Controllers\SitemapController::class, 'robots'])->name('site.robots');
// IndexNow ownership key file (proves we own the host before search engines
// honour our change notifications). Constrained to the 32-hex key format so it
// can't shadow other top-level .txt routes.
Route::get('/{key}.txt', [\App\Modules\Common\Controllers\SitemapController::class, 'indexNowKey'])
    ->where('key', '[a-f0-9]{32}')->name('site.indexnow-key');

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
    // Public JSON feed for the standalone marketing site (1inme.com). Must
    // precede the catch-all /{slug} show route below so 'feed.json' isn't
    // captured as a post slug.
    Route::get('/feed.json',           'feed')->name('feed');
    Route::options('/feed.json',       'feedPreflight');
    Route::get('/feed/{slug}.json',    'feedShow')->name('feed.show')->where('slug', '[a-z0-9-]+');
    Route::options('/feed/{slug}.json','feedPreflight')->where('slug', '[a-z0-9-]+');
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

// Versioned PDF download — same controller, slug captured into $slug.
Route::get('/{handle}/resume/v/{slug}.pdf',
    [\App\Modules\User\Controllers\ResumeController::class, 'downloadByHandle'])
    ->middleware(['web', 'throttle:60,1'])
    ->where('handle', '[A-Za-z0-9_.-]{2,40}')
    ->where('slug', '[a-z0-9\-]{1,60}')
    ->name('resume.public.pdf.version');

// Public Resume page at /{handle}/resume. Registered BEFORE the
// `/{alias}` catch-alls so the literal `/resume` second segment routes
// here (the alias regex only covers single-segment paths and a small
// allow-list of suffixes that does not include "resume").
Route::get ('/{handle}/resume', [\App\Modules\Common\Controllers\PublicResumeController::class, 'show'])
    ->name('resume.public.show')
    ->where('handle', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks|login|register|features|how-it-works|about|contact|faqs|terms|refunds|privacy|gdpr|cookies|discovery|creators-feed|workspace-team|buzz|ai-chatbot|ai-agent|ai-widget|ai-voice-assistant|docs|newsletter|pricing|coins|premium-features|blogs|creators|feed|viewer|companion|embed|assistant|sp|r|analytics|audience|integrations|compare|for)[a-zA-Z0-9_\-\.]+$');
Route::post('/{handle}/resume', [\App\Modules\Common\Controllers\PublicResumeController::class, 'unlock'])
    ->name('resume.public.unlock')
    ->middleware('throttle:10,1')
    ->where('handle', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks).*$');

// Versioned public page — /{handle}/resume/v/{slug}. The default
// version stays at /{handle}/resume so old shared URLs keep working.
Route::get ('/{handle}/resume/v/{slug}', [\App\Modules\Common\Controllers\PublicResumeController::class, 'show'])
    ->name('resume.public.show.version')
    ->where('handle', '[A-Za-z0-9_.-]{2,40}')
    ->where('slug', '[a-z0-9\-]{1,60}');
Route::post('/{handle}/resume/v/{slug}', [\App\Modules\Common\Controllers\PublicResumeController::class, 'unlock'])
    ->name('resume.public.unlock.version')
    ->middleware('throttle:10,1')
    ->where('handle', '[A-Za-z0-9_.-]{2,40}')
    ->where('slug', '[a-z0-9\-]{1,60}');

// ---- AR Business Card (public) ---------------------------------------
// Reserved /ar/* prefix — must be declared BEFORE the catch-all /{alias}
// matcher so the alias regex doesn't swallow it.
Route::prefix('ar/{alias}')->where(['alias' => '[A-Za-z0-9._-]+'])->group(function () {
    Route::get('/',           [\App\Modules\Common\Controllers\ArCardController::class, 'view'])->name('ar.card.view');
    Route::get('model.glb',   [\App\Modules\Common\Controllers\ArCardController::class, 'glb'])->name('ar.card.glb');
    Route::get('model.usdz',  [\App\Modules\Common\Controllers\ArCardController::class, 'usdz'])->name('ar.card.usdz');
    Route::get('texture.png', [\App\Modules\Common\Controllers\ArCardController::class, 'texture'])->name('ar.card.texture');
    Route::get('kit',         [\App\Modules\Common\Controllers\ArCardController::class, 'kit'])->name('ar.card.kit');
    Route::get('kit.pdf',     [\App\Modules\Common\Controllers\ArCardController::class, 'kitPdf'])->name('ar.card.kit.pdf');
});

// ── Creator Profile: public /@handle surface (Task #1207) ──────────
// MUST live above the catch-all `/{alias}` matcher so the @-prefixed
// URLs resolve here instead of being treated as biolink aliases. The
// `@` is part of the URL, mirroring Twitter / Instagram conventions.
Route::get('/@{handle}', [\App\Modules\Common\Controllers\CreatorProfilePublicController::class, 'show'])
    ->where('handle', '[A-Za-z0-9_]+')
    ->name('creator-profile.show');

// Task #1211 — generated OG/share image for /@handle. Cached server-side
// so crawler hits don't re-render every time.
Route::get('/@{handle}/og.png', [\App\Modules\Common\Controllers\CreatorOgImageController::class, 'show'])
    ->where('handle', '[A-Za-z0-9_]+')
    ->name('creator-profile.og');

// Task #1211 — public DMCA / IP takedown intake.
Route::get ('/legal/dmca', [\App\Modules\Common\Controllers\DmcaController::class, 'show'])->name('legal.dmca.show');
Route::post('/legal/dmca', [\App\Modules\Common\Controllers\DmcaController::class, 'store'])->middleware('throttle:10,60')->name('legal.dmca.store');

// Task #1211 — watermarked image streamer. /watermark/p/{post}/{idx}.png
// returns a viewer-stamped PNG when the creator has watermarking on,
// otherwise 302s to the original URL.
Route::get('/watermark/p/{post}/{idx}.png', [\App\Modules\Common\Controllers\WatermarkController::class, 'serve'])
    ->whereNumber('post')->whereNumber('idx')
    ->middleware('throttle:120,1')
    ->name('watermark.serve');

// Task #1211 — short-lived signed media URLs for paywalled posts.
Route::get('/signed-media/p/{post}/{idx}', [\App\Modules\Common\Controllers\SignedMediaController::class, 'serve'])
    ->whereNumber('post')->whereNumber('idx')
    ->name('signed-media.serve');

// Task #1211 — viewer-side block/report endpoints. All POSTs because they
// mutate state; coalescing + rate-limit live inside the controllers.
Route::post('/u/{creator}/block',  [\App\Modules\Common\Controllers\UserBlockController::class, 'toggle'])
    ->whereNumber('creator')->middleware('throttle:30,1')->name('users.block.toggle');
Route::post('/u/{creator}/report', [\App\Modules\Common\Controllers\UserReportController::class, 'reportUser'])
    ->whereNumber('creator')->middleware('throttle:20,1')->name('users.report');
Route::post('/p/{post}/report',    [\App\Modules\Common\Controllers\UserReportController::class, 'reportPost'])
    ->whereNumber('post')->middleware('throttle:20,1')->name('posts.report');
Route::post('/c/{comment}/report', [\App\Modules\Common\Controllers\UserReportController::class, 'reportComment'])
    ->whereNumber('comment')->middleware('throttle:20,1')->name('comments.report');
Route::post('/m/{message}/report', [\App\Modules\Common\Controllers\UserReportController::class, 'reportMessage'])
    ->whereNumber('message')->middleware('throttle:20,1')->name('messages.report');
Route::post('/@{handle}/p/{post}/react', [\App\Modules\Common\Controllers\CreatorProfilePublicController::class, 'react'])
    ->where(['handle' => '[A-Za-z0-9_]+', 'post' => '[0-9]+'])
    ->middleware('throttle:120,1')
    ->name('creator-profile.react');
Route::post('/@{handle}/p/{post}/comment', [\App\Modules\Common\Controllers\CreatorProfilePublicController::class, 'comment'])
    ->where(['handle' => '[A-Za-z0-9_]+', 'post' => '[0-9]+'])
    ->middleware('throttle:60,1')
    ->name('creator-profile.comment');
Route::delete('/@{handle}/c/{comment}', [\App\Modules\Common\Controllers\CreatorProfilePublicController::class, 'deleteComment'])
    ->where(['handle' => '[A-Za-z0-9_]+', 'comment' => '[0-9]+'])
    ->name('creator-profile.comment.destroy');

// ── Creator Profile monetization (Task #1209): subscribe / unlock /
// tip surfaces hosted on /@handle. Routes live with the rest of the
// /@handle URLs so deep-links from the profile (and from the
// Creator Profile mobile app) hit the same paths.
Route::get   ('/@{handle}/subscribe', [\App\Modules\Common\Controllers\CreatorMonetizationPublicController::class, 'subscribePage'])
    ->where('handle', '[A-Za-z0-9_]+')->name('creator-profile.subscribe.show');
Route::post  ('/@{handle}/subscribe', [\App\Modules\Common\Controllers\CreatorMonetizationPublicController::class, 'subscribe'])
    ->where('handle', '[A-Za-z0-9_]+')->middleware('throttle:30,1')->name('creator-profile.subscribe');
Route::post  ('/@{handle}/p/{post}/unlock', [\App\Modules\Common\Controllers\CreatorMonetizationPublicController::class, 'unlock'])
    ->where(['handle' => '[A-Za-z0-9_]+', 'post' => '[0-9]+'])
    ->middleware('throttle:30,1')->name('creator-profile.unlock');
Route::post  ('/@{handle}/tip', [\App\Modules\Common\Controllers\CreatorMonetizationPublicController::class, 'tip'])
    ->where('handle', '[A-Za-z0-9_]+')->middleware('throttle:30,1')->name('creator-profile.tip');
Route::post  ('/@{handle}/p/{post}/tip', [\App\Modules\Common\Controllers\CreatorMonetizationPublicController::class, 'tip'])
    ->where(['handle' => '[A-Za-z0-9_]+', 'post' => '[0-9]+'])
    ->middleware('throttle:30,1')->name('creator-profile.tip.post');
Route::get   ('/@{handle}/manage-subscription', [\App\Modules\Common\Controllers\CreatorMonetizationPublicController::class, 'manage'])
    ->where('handle', '[A-Za-z0-9_]+')->name('creator-profile.subscription.manage');
Route::post  ('/@{handle}/manage-subscription/cancel', [\App\Modules\Common\Controllers\CreatorMonetizationPublicController::class, 'cancel'])
    ->where('handle', '[A-Za-z0-9_]+')->name('creator-profile.subscription.cancel');
Route::post  ('/@{handle}/manage-subscription/resume', [\App\Modules\Common\Controllers\CreatorMonetizationPublicController::class, 'resume'])
    ->where('handle', '[A-Za-z0-9_]+')->name('creator-profile.subscription.resume');

// Preview-mode hosted-checkout pages for Task #1209. Real provider
// adapters (Stripe Connect / PayPal / Razorpay / CCBill / Segpay) take
// over these URLs once their env credentials are configured.
Route::get ('/checkout/preview', [\App\Modules\Common\Controllers\MonetizationCheckoutController::class, 'preview'])->name('checkout.preview');
Route::post('/checkout/preview/confirm', [\App\Modules\Common\Controllers\MonetizationCheckoutController::class, 'confirmPreview'])->name('checkout.preview.confirm');
Route::get ('/checkout/return',  [\App\Modules\Common\Controllers\MonetizationCheckoutController::class, 'returnHandler'])->name('checkout.return');

// Carbon-Neutral Biolinks: public methodology page (linked from the
// "Carbon Neutral" badge popover on every opted-in biolink) and a
// JSON endpoint the badge JS hits on first open. Both must be public
// because biolink visitors are anonymous.
Route::get('/sustainability/methodology', [\App\Modules\Common\Controllers\CarbonPublicController::class, 'methodology'])
    ->name('public.carbon.methodology');
Route::get('/sustainability/badge/{link}', [\App\Modules\Common\Controllers\CarbonPublicController::class, 'badge'])
    ->whereNumber('link')->middleware('throttle:60,1')->name('public.carbon.badge');

Route::get('/{alias}/manifest.json', [RedirectController::class, 'manifest'])->name('redirect.manifest')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks|login|register|features|how-it-works|about|contact|faqs|terms|refunds|privacy|gdpr|cookies|discovery|creators-feed|workspace-team|buzz|ai-chatbot|ai-agent|ai-widget|ai-voice-assistant|docs|newsletter|pricing|coins|premium-features|blogs|legal|watermark|signed-media|stats|moderation|u|p|c|m|sustainability|checkout|analytics|audience|integrations|compare|for).*$');
Route::get('/{alias}', [RedirectController::class, 'handle'])->name('redirect.handle')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks|login|register|features|how-it-works|about|contact|faqs|terms|refunds|privacy|gdpr|cookies|discovery|creators-feed|workspace-team|buzz|ai-chatbot|ai-agent|ai-widget|ai-voice-assistant|docs|newsletter|pricing|coins|premium-features|blogs|legal|watermark|signed-media|stats|moderation|u|p|c|m|sustainability|checkout|analytics|audience|integrations|compare|for)[^/]+$');
// ── Conversational Biolink visitor endpoints ─────────────────────
// Use the /cv/ prefix so they don't collide with the catch-all /{alias} route.
Route::post('/sl/{alias}/view',            [\App\Modules\Common\Controllers\SlideEventController::class, 'view'])
    ->where('alias', '[^/]+')->middleware('throttle:240,1')->name('sl.public.view');

Route::post('/cv/{alias}/start',           [\App\Modules\Common\Controllers\ConversationPublicController::class, 'start'])
    ->where('alias', '[^/]+')->middleware('throttle:30,1')->name('cv.public.start');
Route::post('/cv/{publicId}/answer',       [\App\Modules\Common\Controllers\ConversationPublicController::class, 'answer'])
    ->where('publicId', 'cvs_[a-z0-9]{20}')->middleware('throttle:120,1')->name('cv.public.answer');
Route::post('/cv/{publicId}/drop',         [\App\Modules\Common\Controllers\ConversationPublicController::class, 'drop'])
    ->where('publicId', 'cvs_[a-z0-9]{20}')->middleware('throttle:60,1')->name('cv.public.drop');
Route::post('/cv/{publicId}/capture-email',[\App\Modules\Common\Controllers\ConversationPublicController::class, 'captureEmail'])
    ->where('publicId', 'cvs_[a-z0-9]{20}')->middleware('throttle:20,1')->name('cv.public.captureEmail');
Route::post('/cv/{publicId}/upload',       [\App\Modules\Common\Controllers\ConversationPublicController::class, 'captureFile'])
    ->where('publicId', 'cvs_[a-z0-9]{20}')->middleware('throttle:30,1')->name('cv.public.upload');

// ── Restaurant Menu visitor endpoints (Task #1536) ───────────────
// Use the /rm/ prefix so they don't collide with the catch-all /{alias}.
Route::post('/rm/{alias}/order', [\App\Modules\Common\Controllers\PublicRestaurantController::class, 'placeOrder'])
    ->where('alias', '[^/]+')->middleware('throttle:20,1')->name('rm.public.order');
Route::get('/rm/order/{token}/status', [\App\Modules\Common\Controllers\PublicRestaurantController::class, 'orderStatus'])
    ->where('token', '[A-Za-z0-9\-]+')->middleware('throttle:120,1')->name('rm.public.order.status');

Route::post('/{alias}/track/session', [\App\Modules\Common\Controllers\EngagementController::class, 'startSession'])->name('track.session.start')->where('alias', '[^/]+')->middleware('throttle:60,1');
Route::post('/{alias}/track/heartbeat', [\App\Modules\Common\Controllers\EngagementController::class, 'heartbeat'])->name('track.heartbeat')->where('alias', '[^/]+')->middleware('throttle:120,1');
Route::post('/{alias}/subscribe', [RedirectController::class, 'subscribe'])->name('redirect.subscribe')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks).*$')->middleware('throttle:10,1');

// Public, no-login review submission for a standalone Reviews page. Honeypot
// + SpamChecker live inside the controller; per-IP throttle here.
Route::post('/{alias}/reviews', [\App\Modules\Common\Controllers\ReviewSubmissionController::class, 'submit'])
    ->name('redirect.reviews.submit')
    ->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks).*$')
    ->middleware('throttle:10,1');

// One-time email confirmation link for customer-verified reviews.
Route::get('/{alias}/reviews/verify/{token}', [\App\Modules\Common\Controllers\ReviewSubmissionController::class, 'verify'])
    ->name('redirect.reviews.verify')
    ->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks).*$')
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:30,1');

// Visitor-filed report on a public biolink (spam/abuse moderation queue).
// CAPTCHA + honeypot + per-IP RateLimiter live inside the controller.
Route::post('/{alias}/report', [\App\Modules\Common\Controllers\BiolinkReportController::class, 'store'])
    ->name('biolink.report')
    ->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks|biolink).*$')
    ->middleware('throttle:30,10');

// Owner-side appeal endpoint for warned/hidden biolinks. Auth required —
// only the link's owner may submit; controller enforces ownership too.
Route::post('/biolink/{link}/appeal', [\App\Modules\Common\Controllers\BiolinkReportController::class, 'appeal'])
    ->middleware('auth')
    ->name('biolink.appeal')
    ->where('link', '[0-9]+');
Route::post('/{alias}', [RedirectController::class, 'handle'])->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks)[^/]+$');
Route::get('/{alias}/b/{blockId}', [RedirectController::class, 'handleBlockClick'])->name('redirect.block')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks).*$');
Route::get('/{alias}/download', [RedirectController::class, 'rawFileDownload'])->name('redirect.file.raw')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|webhooks).*$');
Route::get('/{alias}/rsvp',  [RedirectController::class, 'rsvpForm'])->name('redirect.rsvp.form')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks).*$');
Route::post('/{alias}/rsvp', [RedirectController::class, 'rsvpSubmit'])->name('redirect.rsvp.submit')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks).*$')->middleware('throttle:10,1');
Route::get ('/{alias}/rsvp/manage/{token}',  [\App\Modules\Common\Controllers\RsvpManageController::class, 'show'])->name('redirect.rsvp.manage')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks).*$');
Route::post('/{alias}/rsvp/manage/{token}',  [\App\Modules\Common\Controllers\RsvpManageController::class, 'update'])->name('redirect.rsvp.manage.update')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks).*$')->middleware('throttle:20,1');
Route::post('/{alias}/rsvp/manage/{token}/cancel', [\App\Modules\Common\Controllers\RsvpManageController::class, 'cancel'])->name('redirect.rsvp.manage.cancel')->where('alias', '^(?!user|admin|qr|storage|sanctum|api|f|webhooks).*$')->middleware('throttle:20,1');
