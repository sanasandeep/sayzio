<?php

namespace App\Modules\Common\Controllers;

use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Embeddable Link Codes (task #2617).
 *
 * Serves the CORS-open, cross-origin-friendly endpoints that let a creator
 * drop any of their links onto an external website:
 *
 *   - GET  /embed/link/{alias}/card     compact action card (short/file/event/contact)
 *   - GET  /embed/link/{alias}/iframe   canonical iframe target (page-style → the page;
 *                                       card-style → the card document)
 *   - GET  /embed/link/{alias}/embed.js auto-rendering loader injecting a sized iframe
 *   - OPTIONS /embed/link/{alias}/{any} CORS preflight
 *
 * Visibility: embeds are loaded anonymously from a third-party origin, where
 * we can never trust a session/follower context. Any non-public link therefore
 * renders a minimal "view on site" card instead of leaking gated content.
 *
 * Tracking: card buttons and the page iframe both resolve through the canonical
 * short URL ({@see Link::getShortUrl()} / `/{alias}`), so views and clicks flow
 * through the existing {@see RedirectController} tracking path with no new code.
 */
class PublicEmbedController extends Controller
{
    /** Compact action-card document (also the iframe target for card-style links). */
    public function card(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link) {
            return $this->cardResponse($this->missingViewData($alias), 404);
        }

        return $this->cardResponse($this->cardViewData($link));
    }

    /**
     * Canonical iframe target. Page-style links resolve to the real public
     * page (which already sets framing headers and tracks the view); card-style
     * links render the self-contained card document.
     */
    public function iframe(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link) {
            return $this->cardResponse($this->missingViewData($alias), 404);
        }

        if ($link->isEmbedCard() || !$this->isPublic($link) || !$link->isAccessible()) {
            return $this->cardResponse($this->cardViewData($link));
        }

        // Page-style + public + accessible → hand off to the live page, which
        // serves its own framing headers and records the visit.
        return redirect()->away($link->getShortUrl());
    }

    /** Auto-rendering loader: injects a responsive iframe into the host page. */
    public function js(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        $card = !$link || $link->isEmbedCard();
        $src  = ($link ? $link->embedBaseUrl() : rtrim(config('app.url'), '/'))
            . '/embed/link/' . $alias . '/iframe';

        $js = $this->loaderScript($alias, $src, $card);

        $response = response($js, 200, ['Content-Type' => 'application/javascript; charset=utf-8']);

        return $this->withCors($response);
    }

    /** CORS preflight for the embed endpoints. */
    public function preflight()
    {
        return $this->withCors(response('', 204));
    }

    /* ------------------------------------------------------------------ *
     * Internals
     * ------------------------------------------------------------------ */

    protected function isPublic(Link $link): bool
    {
        return ($link->visibility ?? 'public') === 'public';
    }

    /**
     * @return array<string,mixed>
     */
    protected function cardViewData(Link $link): array
    {
        $public     = $this->isPublic($link);
        $accessible = $link->isAccessible();
        $shortUrl   = $link->getShortUrl();

        if (!$public) {
            return [
                'state'    => 'gated',
                'alias'    => $link->alias,
                'title'    => $link->title ?: $link->type_label,
                // Single explanation line — the card renders NO extra footnote
                // row for fallback states (task #6714) so the layout fits the
                // height the static no-JS iframe snippet was copied with.
                'subtitle' => 'Private link — open to view if you have access.',
                'favicon'  => $this->favicon($link),
                'action'   => ['label' => 'View on site', 'icon' => 'open'],
                'url'      => $shortUrl,
                'badge'    => $link->type_label,
            ];
        }

        if (!$accessible) {
            return [
                'state'    => 'unavailable',
                'alias'    => $link->alias,
                'title'    => $link->title ?: $link->type_label,
                'subtitle' => 'This link is not available right now.',
                'favicon'  => $this->favicon($link),
                'action'   => null,
                'url'      => $shortUrl,
                'badge'    => $link->type_label,
            ];
        }

        return [
            'state'    => 'ok',
            'alias'    => $link->alias,
            'title'    => $link->title ?: $link->type_label,
            'subtitle' => $this->subtitle($link),
            'favicon'  => $this->favicon($link),
            'action'   => $link->embedAction(),
            'url'      => $shortUrl,
            'badge'    => $link->type_label,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    protected function missingViewData(string $alias): array
    {
        return [
            'state'    => 'missing',
            'alias'    => $alias,
            'title'    => 'Link not found',
            'subtitle' => 'This embedded link is no longer available.',
            'favicon'  => null,
            'action'   => null,
            'url'      => rtrim(config('app.url'), '/'),
            'badge'    => null,
        ];
    }

    protected function subtitle(Link $link): ?string
    {
        // Delegates to the model so the static iframe snippet's height
        // (Link::embedCardIframeHeight) stays in lockstep with what the
        // card actually renders.
        return $link->embedCardSubtitle();
    }

    protected function favicon(Link $link): ?string
    {
        if ($link->favicon) {
            return $link->favicon;
        }

        if ($link->type === 'url' && $link->long_url) {
            $host = parse_url($link->long_url, PHP_URL_HOST);
            if ($host) {
                return 'https://www.google.com/s2/favicons?domain=' . urlencode($host) . '&sz=64';
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $data
     */
    protected function cardResponse(array $data, int $status = 200): Response
    {
        $response = response()->view('common.embed.card', $data, $status);
        $response->headers->set('X-Frame-Options', 'ALLOWALL');
        $response->headers->set('Content-Security-Policy', 'frame-ancestors *');

        return $this->withCors($response);
    }

    protected function withCors(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }

    protected function loaderScript(string $alias, string $src, bool $card): string
    {
        $aliasJs = json_encode($alias);
        $srcJs   = json_encode($src);
        $initialHeight = $card ? 188 : 560;
        $minHeight     = $card ? 120 : 560;

        return <<<JS
(function(){
  var ALIAS = {$aliasJs};
  var SRC = {$srcJs};
  function mount(host){
    if(host.getAttribute('data-1inme-mounted')) return;
    host.setAttribute('data-1inme-mounted','1');
    var f = document.createElement('iframe');
    f.src = SRC;
    f.setAttribute('loading','lazy');
    f.setAttribute('title','Embedded link');
    f.style.border = '0';
    f.style.width = '100%';
    f.style.maxWidth = {$card} ? '420px' : '100%';
    f.style.height = '{$initialHeight}px';
    f.style.minHeight = '{$minHeight}px';
    host.appendChild(f);
    window.addEventListener('message', function(e){
      var d = e.data;
      if(!d || d.type !== '1inme-embed-resize' || d.alias !== ALIAS) return;
      if(d.height && d.height > 0){ f.style.height = d.height + 'px'; f.style.minHeight = '0'; }
    });
  }
  function run(){
    var hosts = document.querySelectorAll('[data-1inme-embed="' + ALIAS + '"]');
    for(var i=0;i<hosts.length;i++){ mount(hosts[i]); }
  }
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', run);
  } else { run(); }
})();
JS;
    }
}
