<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Server-side fetch + lightweight extraction for the Competitor Biolink
 * Teardown feature (Task #3532). Runs entirely BEFORE any AI credits are
 * spent so a bad/unreachable URL never costs the user anything.
 *
 * We deliberately avoid a headless browser here — a plain HTTP GET plus
 * DOMDocument parsing is enough to pull the signals the AI teardown
 * prompt needs (title, headings, CTA copy, form/link/image counts) and
 * keeps the feature dependency-free (no JS-rendering library in
 * composer.json).
 */
class CompetitorPageFetcher
{
    /** Hard cap on downloaded HTML so a huge page can't blow up memory/latency. */
    public const MAX_BYTES = 1_500_000;

    public const TIMEOUT_SECONDS = 12;

    /**
     * @return array{
     *   final_url:string, title:string, meta_description:string,
     *   h1:list<string>, h2:list<string>, cta_texts:list<string>,
     *   link_count:int, image_count:int, form_count:int,
     *   has_email_capture:bool, has_social_links:bool, text_excerpt:string
     * }
     * @throws RuntimeException for any caller-fixable problem (bad URL,
     *         unreachable host, non-HTML response, blocked target…).
     */
    public function fetchAndExtract(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            throw new RuntimeException('Please paste a competitor URL first.');
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        if (mb_strlen($url) > 2048) {
            throw new RuntimeException('That URL is too long.');
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new RuntimeException("That doesn't look like a valid URL.");
        }
        $this->guardAgainstPrivateHost($host);

        // Follow redirects manually so we can validate each hop's
        // destination before the request is issued. Automatic redirect
        // following (allow_redirects) only lets us inspect the final URL
        // *after* the internal request has already been sent, which is
        // too late to prevent blind SSRF through an open redirect.
        [$response, $finalUrl] = $this->fetchFollowingRedirects($url);

        if (!$response->successful()) {
            throw new RuntimeException('That page returned an error (HTTP ' . $response->status() . '). Please check the URL.');
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if ($contentType !== '' && !str_contains($contentType, 'html')) {
            throw new RuntimeException("That URL doesn't look like a web page.");
        }

        $html = $response->body();
        if (strlen($html) > self::MAX_BYTES) {
            $html = substr($html, 0, self::MAX_BYTES);
        }
        if (trim(strip_tags($html)) === '' && trim($html) === '') {
            throw new RuntimeException('That page returned no content to analyze.');
        }

        return $this->extract($html, $finalUrl);
    }

    /**
     * Issue a GET request and follow redirects manually, re-validating
     * each redirect destination against guardAgainstPrivateHost() before
     * issuing the next hop. This prevents an open-redirect on a public
     * URL from routing the server-side request to an internal network
     * target (blind SSRF), which would otherwise occur if we relied on
     * Guzzle's built-in allow_redirects and only checked the final URL
     * after the internal request had already been sent.
     *
     * @return array{0: \Illuminate\Http\Client\Response, 1: string}
     *   [response, finalUrl]
     */
    private function fetchFollowingRedirects(string $url, int $maxRedirects = 5): array
    {
        $hops = 0;
        while (true) {
            try {
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->connectTimeout(6)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; SayzioTeardownBot/1.0; +https://1in.me)',
                        'Accept'     => 'text/html,application/xhtml+xml',
                    ])
                    ->withOptions(['allow_redirects' => false])
                    ->get($url);
            } catch (\Throwable $e) {
                throw new RuntimeException("We couldn't reach that URL. Double-check it and try again.");
            }

            $status = $response->status();
            if (!in_array($status, [301, 302, 303, 307, 308], true)) {
                // Not a redirect — return the response together with the
                // URL that produced it (the effective final URL).
                return [$response, $url];
            }

            $hops++;
            if ($hops > $maxRedirects) {
                throw new RuntimeException("We couldn't reach that URL. Double-check it and try again.");
            }

            $location = $response->header('Location');
            if (!$location) {
                throw new RuntimeException("We couldn't reach that URL. Double-check it and try again.");
            }

            // Resolve a relative Location against the current URL.
            if (!preg_match('#^https?://#i', $location)) {
                $parsed = parse_url($url);
                $scheme  = $parsed['scheme'] ?? 'https';
                $host    = $parsed['host']   ?? '';
                $port    = isset($parsed['port']) ? ':' . $parsed['port'] : '';
                $location = $scheme . '://' . $host . $port . '/' . ltrim($location, '/');
            }

            // Validate the redirect target BEFORE following it.
            $destHost = parse_url($location, PHP_URL_HOST) ?: '';
            $this->guardAgainstPrivateHost($destHost);

            $url = $location;
        }
    }

    /**
     * Block SSRF-style targets: localhost, .local/.internal, and any
     * hostname that resolves to a private/reserved/loopback IP.
     */
    private function guardAgainstPrivateHost(string $host): void
    {
        $host = strtolower(trim($host, '[]'));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new RuntimeException("That URL isn't allowed.");
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                $ips = $resolved;
            }
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException("That URL isn't allowed.");
            }
        }
    }

    /**
     * @return array{
     *   final_url:string, title:string, meta_description:string,
     *   h1:list<string>, h2:list<string>, cta_texts:list<string>,
     *   link_count:int, image_count:int, form_count:int,
     *   has_email_capture:bool, has_social_links:bool, text_excerpt:string,
     *   palette:array{theme_color:?string, colors:list<string>, roles:array<string,string>}
     * }
     */
    private function extract(string $html, string $finalUrl): array
    {
        $prevErr = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prevErr);

        $xpath = new \DOMXPath($doc);

        $title = trim((string) ($xpath->query('//title')->item(0)?->textContent ?? ''));

        $metaDescription = '';
        foreach ($xpath->query('//meta[@name="description"]') as $node) {
            $metaDescription = trim((string) $node->getAttribute('content'));
            if ($metaDescription !== '') {
                break;
            }
        }

        $h1 = $this->collectText($xpath, '//h1', 6);
        $h2 = $this->collectText($xpath, '//h2', 10);

        $ctaTexts = [];
        foreach ($xpath->query('//a | //button') as $node) {
            $text = trim(preg_replace('/\s+/', ' ', (string) $node->textContent) ?? '');
            if ($text === '' || mb_strlen($text) > 60) {
                continue;
            }
            if (preg_match('/\b(sign up|subscribe|buy|shop|book|contact|get started|join|follow|download|learn more|call|whatsapp|order|schedule|register)\b/i', $text)) {
                $ctaTexts[] = $text;
            }
            if (count($ctaTexts) >= 15) {
                break;
            }
        }
        $ctaTexts = array_values(array_unique($ctaTexts));

        $linkCount  = $xpath->query('//a[@href]')->length;
        $imageCount = $xpath->query('//img')->length;
        $formCount  = $xpath->query('//form')->length;

        $bodyText = trim((string) ($xpath->query('//body')->item(0)?->textContent ?? ''));
        $bodyText = preg_replace('/\s+/', ' ', $bodyText) ?? '';

        $hasEmailCapture = (bool) preg_match('/type=["\']?email["\']?/i', $html) || str_contains(strtolower($bodyText), 'subscribe');
        $hasSocialLinks  = (bool) preg_match('#(instagram\.com|tiktok\.com|twitter\.com|x\.com|facebook\.com|youtube\.com|linkedin\.com)#i', $html);

        return [
            'final_url'         => $finalUrl,
            'title'             => mb_substr($title, 0, 300),
            'meta_description'  => mb_substr($metaDescription, 0, 500),
            'h1'                => $h1,
            'h2'                => $h2,
            'cta_texts'         => array_slice($ctaTexts, 0, 15),
            'link_count'        => $linkCount,
            'image_count'       => $imageCount,
            'form_count'        => $formCount,
            'has_email_capture' => $hasEmailCapture,
            'has_social_links'  => $hasSocialLinks,
            'text_excerpt'      => mb_substr($bodyText, 0, 3000),
            'palette'           => $this->extractPalette($html, $xpath),
        ];
    }

    /**
     * The page's colours, for the "match their style" build mode.
     *
     * Deliberately NOT added to the scoring prompt: buildMessages() in
     * CompetitorTeardownService whitelists the keys it forwards, and colours
     * tell a conversion critic nothing. This is stored on the teardown and
     * read only when a creator asks for a build that matches the competitor's
     * look, so the teardown's own cost estimate is unaffected.
     *
     * Colours were always arriving and always being thrown away -- Linktree's
     * own --profileBackground / --button-style-background values were sitting
     * inside text_excerpt as prose for the scorer to ignore.
     *
     * @return array{theme_color:?string, colors:list<string>, roles:array<string,string>}
     */
    private function extractPalette(string $html, \DOMXPath $xpath): array
    {
        $themeColor = null;
        foreach ($xpath->query('//meta[@name="theme-color"]') as $node) {
            $hex = $this->normaliseColor((string) $node->getAttribute('content'));
            if ($hex !== null) {
                $themeColor = $hex;
                break;
            }
        }

        // Only the <style> blocks and style="" attributes. Scanning the whole
        // document would pick colours out of body copy and inline SVG paths.
        $styleText = '';
        foreach ($xpath->query('//style') as $node) {
            $styleText .= "\n" . $node->textContent;
        }
        foreach ($xpath->query('//*[@style]') as $node) {
            $styleText .= "\n" . $node->getAttribute('style');
        }
        $styleText = mb_substr($styleText, 0, 200000);

        // Custom properties whose NAME says what the colour is for. Matching
        // the role word rather than one platform's variable names is what
        // keeps this from being a Linktree-shaped extractor.
        $roles = [];
        // Words that mean "a colour derived FROM the role" rather than the
        // role itself. Without this, --background-contrast-color wins the
        // `background` slot and hands the builder #000000 for a page whose
        // actual background is #eceef1. Found by running this against the
        // real Linktree payload before shipping it.
        $notTheRole = '/(contrast|shadow|skeleton|hover|active|focus|border|outline|default|disabled|placeholder|overlay|scrim|inverse)/i';
        $roleWords = [
            'button_bg'   => '(button|btn|cta)[-_a-z0-9]*(background|bg|fill)',
            'button_text' => '(button|btn|cta)[-_a-z0-9]*(text|color|colour|foreground)',
            'background'  => '^(?!.*(button|btn|cta))[-_a-z0-9]*(page|body|profile|backdrop|surface)?[-_a-z0-9]*(background|bg)',
            'text'        => '^(?!.*(button|btn|cta))[-_a-z0-9]*(body|page|profile)?[-_a-z0-9]*(text|ink)',
            'accent'      => '(accent|primary|brand|highlight)',
        ];
        if (preg_match_all('/--([a-z0-9-_]+)\s*:\s*([^;}]+)/i', $styleText, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $name  = strtolower($hit[1]);
                $value = $this->normaliseColor(trim($hit[2]));
                if ($value === null || preg_match($notTheRole, $name)) {
                    continue;
                }
                foreach ($roleWords as $role => $pattern) {
                    if (!isset($roles[$role]) && preg_match('/' . $pattern . '/i', $name)) {
                        $roles[$role] = $value;
                        break;
                    }
                }
            }
        }

        // Everything else, by how often it is used -- the page's working
        // palette rather than whatever happens to be declared first.
        $counts = [];
        if (preg_match_all('/#[0-9a-f]{3,8}\b|rgba?\([^)]*\)/i', $styleText, $hits)) {
            foreach ($hits[0] as $raw) {
                $hex = $this->normaliseColor($raw);
                if ($hex !== null) {
                    $counts[$hex] = ($counts[$hex] ?? 0) + 1;
                }
            }
        }
        arsort($counts);

        return [
            'theme_color' => $themeColor,
            'colors'      => array_slice(array_keys($counts), 0, 8),
            'roles'       => $roles,
        ];
    }

    /**
     * A CSS colour as #rrggbb, or null when it is not one we can use.
     *
     * Fully transparent colours are dropped: rgba(0,0,0,0) is "no colour",
     * and carrying it through as #000000 would hand the builder a black that
     * the page never actually shows.
     */
    private function normaliseColor(string $raw): ?string
    {
        $raw = trim(strtolower($raw));

        if (preg_match('/^#([0-9a-f]{3})$/', $raw, $m)) {
            return '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        }
        if (preg_match('/^#([0-9a-f]{6})(?:[0-9a-f]{2})?$/', $raw, $m)) {
            return '#' . $m[1];
        }
        if (preg_match('/^rgba?\(\s*([0-9]{1,3})[\s,]+([0-9]{1,3})[\s,]+([0-9]{1,3})\s*(?:[,\/]\s*([0-9.]+)\s*)?\)$/', $raw, $m)) {
            if (isset($m[4]) && $m[4] !== '' && (float) $m[4] === 0.0) {
                return null;
            }
            $rgb = array_map(static fn (string $v): int => max(0, min(255, (int) $v)), [$m[1], $m[2], $m[3]]);
            return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
        }

        return null;
    }

    /** @return list<string> */
    private function collectText(\DOMXPath $xpath, string $query, int $limit): array
    {
        $out = [];
        foreach ($xpath->query($query) as $node) {
            $text = trim(preg_replace('/\s+/', ' ', (string) $node->textContent) ?? '');
            if ($text === '') {
                continue;
            }
            $out[] = mb_substr($text, 0, 160);
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }
}
