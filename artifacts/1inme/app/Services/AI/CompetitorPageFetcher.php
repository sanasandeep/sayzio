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
     *   has_email_capture:bool, has_social_links:bool, text_excerpt:string
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
        ];
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
