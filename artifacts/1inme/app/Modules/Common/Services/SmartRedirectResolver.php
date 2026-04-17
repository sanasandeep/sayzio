<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Smart Redirect Rule resolver.
 *
 * Stored under `settings['smart_rules']` as an ordered array of rule objects.
 * Rules are evaluated top-to-bottom; the FIRST matching rule wins. If nothing
 * matches we fall back to the link's normal long_url (with UTM params applied).
 *
 * Supported rule shapes:
 *
 *   ['type'=>'device',   'match'=>['mobile','tablet','desktop'], 'url'=>'https://...']
 *   ['type'=>'country',  'match'=>['IN','US'],                   'url'=>'https://...']
 *   ['type'=>'language', 'match'=>['hi','en'],                   'url'=>'https://...']
 *   ['type'=>'time',     'from'=>'08:00','to'=>'17:00','tz'=>'Asia/Kolkata','url'=>'https://...']
 *   ['type'=>'ab',       'variants'=>[
 *        ['url'=>'https://a.example','weight'=>50],
 *        ['url'=>'https://b.example','weight'=>50],
 *   ]]
 *
 * Security: every URL is run through a hard scheme allowlist (http/https only)
 * before it can become the redirect target. Rules with non-http(s) URLs (or
 * malformed URLs) are silently skipped.
 */
class SmartRedirectResolver
{
    /**
     * Decide where to send the visitor.
     *
     * @return array{url:string, cookie:?Cookie, rule:?array}
     */
    public function resolve(Link $link, Request $request): array
    {
        $rules = $link->settings['smart_rules'] ?? [];
        if (!is_array($rules) || empty($rules)) {
            return ['url' => $link->getDestinationUrl(), 'cookie' => null, 'rule' => null];
        }

        foreach ($rules as $rule) {
            if (!is_array($rule) || empty($rule['type'])) continue;

            switch ($rule['type']) {
                case 'device':
                    if ($this->matchDevice($rule, $request) && $url = $this->safeUrl($rule['url'] ?? null)) {
                        return ['url' => $this->withUtm($link, $url), 'cookie' => null, 'rule' => $rule];
                    }
                    break;
                case 'country':
                    if ($this->matchCountry($rule, $request) && $url = $this->safeUrl($rule['url'] ?? null)) {
                        return ['url' => $this->withUtm($link, $url), 'cookie' => null, 'rule' => $rule];
                    }
                    break;
                case 'language':
                    if ($this->matchLanguage($rule, $request) && $url = $this->safeUrl($rule['url'] ?? null)) {
                        return ['url' => $this->withUtm($link, $url), 'cookie' => null, 'rule' => $rule];
                    }
                    break;
                case 'time':
                    if ($this->matchTime($rule) && $url = $this->safeUrl($rule['url'] ?? null)) {
                        return ['url' => $this->withUtm($link, $url), 'cookie' => null, 'rule' => $rule];
                    }
                    break;
                case 'ab':
                    [$abUrl, $cookie] = $this->resolveAb($link, $rule, $request);
                    if ($abUrl) {
                        return ['url' => $this->withUtm($link, $abUrl), 'cookie' => $cookie, 'rule' => $rule];
                    }
                    break;
            }
        }

        return ['url' => $link->getDestinationUrl(), 'cookie' => null, 'rule' => null];
    }

    // ---- Matchers ----

    protected function matchDevice(array $rule, Request $request): bool
    {
        $allowed = $this->normalizeMatch($rule['match'] ?? null);
        if (empty($allowed)) return false;
        $device = $this->detectDevice($request->userAgent() ?? '');
        return in_array($device, $allowed, true);
    }

    protected function matchCountry(array $rule, Request $request): bool
    {
        $allowed = array_map('strtoupper', $this->normalizeMatch($rule['match'] ?? null));
        if (empty($allowed)) return false;
        $cc = app(GeoIpService::class)->detectCountry($request->ip());
        return $cc !== null && in_array(strtoupper($cc), $allowed, true);
    }

    protected function matchLanguage(array $rule, Request $request): bool
    {
        $allowed = array_map('strtolower', $this->normalizeMatch($rule['match'] ?? null));
        if (empty($allowed)) return false;
        // Match against ANY of the visitor's preferred languages, not just
        // the first one — so "fr-CH;q=0.5,en;q=1.0" still matches an English
        // rule even if French is listed first.
        foreach ($this->preferredLanguages($request->header('Accept-Language', '')) as $lang) {
            if (in_array($lang, $allowed, true)) return true;
        }
        return false;
    }

    protected function matchTime(array $rule): bool
    {
        $from = $rule['from'] ?? null;
        $to   = $rule['to']   ?? null;
        $tz   = $rule['tz']   ?? 'UTC';
        if (!$this->isHHMM($from) || !$this->isHHMM($to)) return false;

        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone($tz));
        } catch (\Exception $e) {
            return false;
        }

        $cur  = $this->minutes($now->format('H:i'));
        $f    = $this->minutes($from);
        $t    = $this->minutes($to);
        if ($f === $t) return false;
        // Overnight window (e.g. 22:00 → 06:00) wraps midnight.
        return $f < $t ? ($cur >= $f && $cur < $t) : ($cur >= $f || $cur < $t);
    }

    /**
     * A/B testing — sticky per visitor via a 30-day cookie that stores the
     * chosen variant's STABLE id (not its array index). Variant ids are
     * minted by the sanitizer when the rule is saved, so adding, removing,
     * or reordering variants in the editor never reshuffles existing
     * visitors as long as their assigned variant still exists.
     *
     * Returns [url|null, cookie|null]; cookie is null when an existing
     * sticky assignment held (no need to re-set it on every request).
     *
     * @return array{0:?string,1:?Cookie}
     */
    protected function resolveAb(Link $link, array $rule, Request $request): array
    {
        $variants = $rule['variants'] ?? [];
        if (!is_array($variants)) return [null, null];

        // Strip out anything without a safe URL or a positive weight, but
        // KEEP each variant's stable id (assigned at save time).
        $clean = [];
        foreach ($variants as $v) {
            if (!is_array($v)) continue;
            $vUrl = $this->safeUrl($v['url'] ?? null);
            if (!$vUrl) continue;
            $weight = isset($v['weight']) ? max(0, (int) $v['weight']) : 1;
            if ($weight === 0) continue;
            $vid = isset($v['id']) && is_string($v['id']) && $v['id'] !== '' ? $v['id'] : null;
            if ($vid === null) continue; // refuse to assign a sticky cookie to an id-less variant
            $clean[] = ['url' => $vUrl, 'weight' => $weight, 'id' => $vid];
        }
        if (empty($clean)) return [null, null];

        $cookieName = '_ab_' . $link->id;
        $existing   = $request->cookie($cookieName);

        // Sticky path: cookie holds the variant id from a previous visit.
        if (is_string($existing) && $existing !== '') {
            foreach ($clean as $c) {
                if ($c['id'] === $existing) {
                    return [$c['url'], null];
                }
            }
            // Existing assignment was deleted from the rule — fall through
            // and reassign so the visitor is never sent to a dead URL.
        }

        // First-time visitor (or stale assignment): weighted-random pick.
        $total = array_sum(array_column($clean, 'weight'));
        $r     = random_int(1, $total);
        $running = 0;
        foreach ($clean as $c) {
            $running += $c['weight'];
            if ($r <= $running) {
                $cookie = Cookie::create(
                    $cookieName,
                    $c['id'],
                    time() + (60 * 60 * 24 * 30), // 30 days
                    '/',
                    null,
                    $request->isSecure(),
                    true, // httpOnly
                    false,
                    Cookie::SAMESITE_LAX
                );
                return [$c['url'], $cookie];
            }
        }

        return [null, null];
    }

    // ---- Helpers ----

    /**
     * Detect a coarse device class from a User-Agent. Mirrors the existing
     * device_targeting logic in RedirectController so behaviour is consistent.
     */
    public function detectDevice(string $ua): string
    {
        if (preg_match('/Mobile|Android.*Mobile|iPhone/i', $ua)) return 'mobile';
        if (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) return 'tablet';
        return 'desktop';
    }

    /**
     * Return the visitor's preferred languages from an Accept-Language
     * header, ordered by q-value (highest first), as primary subtags
     * lowercased (e.g. "hi", "en"). Falls back to [] for empty/garbage.
     *
     * Example: "fr-CH;q=0.5, hi-IN, en;q=0.8"  ->  ["hi","en","fr"]
     */
    public function preferredLanguages(string $header): array
    {
        if ($header === '') return [];
        $items = [];
        foreach (explode(',', $header) as $i => $part) {
            $part = trim($part);
            if ($part === '') continue;
            $bits = array_map('trim', explode(';', $part));
            $tag = $bits[0] ?? '';
            $q   = 1.0;
            for ($k = 1; $k < count($bits); $k++) {
                if (str_starts_with($bits[$k], 'q=')) {
                    $q = (float) substr($bits[$k], 2);
                }
            }
            $primary = strtolower(explode('-', $tag)[0] ?? '');
            if (preg_match('/^[a-z]{2,3}$/', $primary)) {
                // Stable sort: higher q first, original order ties.
                $items[] = ['lang' => $primary, 'q' => $q, 'order' => $i];
            }
        }
        usort($items, fn($a, $b) => $b['q'] <=> $a['q'] ?: $a['order'] <=> $b['order']);
        $out = [];
        foreach ($items as $it) if (!in_array($it['lang'], $out, true)) $out[] = $it['lang'];
        return $out;
    }

    /**
     * Convenience wrapper — first preferred language, or null.
     * Kept for backwards compatibility with single-pick callers.
     */
    public function detectLanguage(string $header): ?string
    {
        return $this->preferredLanguages($header)[0] ?? null;
    }

    /**
     * Allowlist URL schemes. Exactly the same gate as AppLinkResolver — we
     * MUST NOT let javascript:/data:/file: etc. become a redirect target.
     */
    protected function safeUrl(?string $url): ?string
    {
        if (!is_string($url) || $url === '') return null;
        $parts = @parse_url($url);
        if (!$parts || empty($parts['host'])) return null;
        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== 'http' && $scheme !== 'https') return null;
        return $url;
    }

    /**
     * Apply the link's UTM params to the chosen URL the same way
     * Link::getDestinationUrl() does for the default destination.
     */
    protected function withUtm(Link $link, string $url): string
    {
        $params = [];
        foreach (['utm_source','utm_medium','utm_campaign','utm_term','utm_content'] as $k) {
            if (!empty($link->{$k})) $params[$k] = $link->{$k};
        }
        if (empty($params)) return $url;
        $sep = str_contains($url, '?') ? '&' : '?';
        return $url . $sep . http_build_query($params);
    }

    protected function normalizeMatch($match): array
    {
        if (is_string($match)) $match = [$match];
        if (!is_array($match)) return [];
        $out = [];
        foreach ($match as $v) {
            if (is_string($v) && trim($v) !== '') $out[] = strtolower(trim($v));
        }
        return array_values(array_unique($out));
    }

    protected function isHHMM($v): bool
    {
        return is_string($v) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) === 1;
    }

    protected function minutes(string $hhmm): int
    {
        [$h, $m] = explode(':', $hhmm);
        return ((int) $h) * 60 + (int) $m;
    }
}
