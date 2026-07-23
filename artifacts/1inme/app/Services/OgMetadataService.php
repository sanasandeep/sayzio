<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Server-side Open Graph / page-metadata extractor for the biolink
 * block "Fetch details" feature.
 *
 * Extracts: title (og:title → <title>), description (og:description →
 * <meta name="description">), og:image (falls back to the site favicon).
 * Fetch is SSRF-safe (mirrors EventPageExtractor::guardAgainstPrivateHost),
 * size-capped at 1 MB, and time-capped at 8 s.
 */
class OgMetadataService
{
    public const MAX_BYTES = 1_000_000;
    public const TIMEOUT_SECONDS = 8;

    /** Cap for downloadImage() — big enough for OG covers, small enough to bound abuse. */
    public const MAX_IMAGE_BYTES = 5_000_000;

    /** Image MIME types downloadImage() will accept. */
    public const IMAGE_MIMES = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp',
        'image/x-icon', 'image/vnd.microsoft.icon', 'image/bmp',
    ];

    /** Cap on prominent in-page <img> candidates returned per page. */
    public const MAX_CONTENT_IMAGES = 12;

    /**
     * URL substrings that mark obvious non-content images (trackers,
     * sprites, pixels, ad beacons, icon sets). Matched case-insensitively
     * against the whole candidate URL.
     */
    private const CONTENT_IMAGE_URL_BLOCKLIST = [
        'sprite', 'pixel', 'tracking', 'tracker', 'beacon', 'spacer',
        'blank.', '1x1', 'counter', 'adserver', 'doubleclick', 'analytics',
        'badge', 'captcha', 'gravatar.com/avatar',
    ];

    /**
     * @return array{title:?string, description:?string, image_url:?string, favicon_url:?string}
     * @throws RuntimeException for caller-fixable problems (bad URL, unreachable host, blocked target).
     */
    public function extractFromUrl(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            throw new RuntimeException('Please provide a URL first.');
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

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(5)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; SayzioMetaBot/1.0; +https://1in.me)',
                    'Accept'     => 'text/html,application/xhtml+xml',
                ])
                ->withOptions(['allow_redirects' => ['max' => 5, 'strict' => true]])
                ->get($url);
        } catch (\Throwable $e) {
            throw new RuntimeException("We couldn't reach that URL.");
        }

        if (!$response->successful()) {
            throw new RuntimeException('That page returned an error (HTTP ' . $response->status() . ').');
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if ($contentType !== '' && !str_contains($contentType, 'html')) {
            throw new RuntimeException("That URL doesn't look like a web page.");
        }

        $html = $response->body();
        if (strlen($html) > self::MAX_BYTES) {
            $html = substr($html, 0, self::MAX_BYTES);
        }

        $finalUrl = $url;
        try {
            $effective = $response->effectiveUri();
            if ($effective) {
                $finalUrl = (string) $effective;
            }
        } catch (\Throwable $e) {
            // Non-fatal — keep the requested URL.
        }

        // Re-check the final (post-redirect) host for open-redirect SSRF.
        $finalHost = parse_url($finalUrl, PHP_URL_HOST);
        if (is_string($finalHost) && $finalHost !== '') {
            $this->guardAgainstPrivateHost($finalHost);
        }

        return $this->extract($html, $finalUrl);
    }

    /**
     * SSRF-safe image download for the AI builder's auto-sourcing step.
     *
     * Applies the same private-host guard (initial + post-redirect) as
     * extractFromUrl, caps the payload at MAX_IMAGE_BYTES, and verifies
     * the bytes decode as a real raster image of an accepted MIME type.
     * Best-effort by design: any failure returns null, never throws.
     *
     * @return array{bytes:string, mime:string}|null
     */
    public function downloadImage(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || mb_strlen($url) > 2048 || !preg_match('#^https?://#i', $url)) {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        try {
            $this->guardAgainstPrivateHost($host);

            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(5)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; SayzioMetaBot/1.0; +https://1in.me)',
                    'Accept'     => 'image/*',
                ])
                ->withOptions(['allow_redirects' => ['max' => 5, 'strict' => true]])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            // Re-check the final (post-redirect) host for open-redirect SSRF.
            try {
                $effective = $response->effectiveUri();
                if ($effective) {
                    $finalHost = parse_url((string) $effective, PHP_URL_HOST);
                    if (is_string($finalHost) && $finalHost !== '') {
                        $this->guardAgainstPrivateHost($finalHost);
                    }
                }
            } catch (RuntimeException $e) {
                return null;
            }

            $bytes = $response->body();
        } catch (\Throwable $e) {
            return null;
        }

        if ($bytes === '' || strlen($bytes) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        // The bytes must decode as a real image; trust the decoded MIME,
        // not the response header.
        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            return null;
        }
        $mime = strtolower((string) ($info['mime'] ?? ''));
        if (!in_array($mime, self::IMAGE_MIMES, true)) {
            return null;
        }

        return [
            'bytes'  => $bytes,
            'mime'   => $mime,
            'width'  => (int) ($info[0] ?? 0),
            'height' => (int) ($info[1] ?? 0),
        ];
    }

    /**
     * Extract OG metadata from raw HTML. Public so tests can exercise
     * parsing without a network fetch.
     *
     * @return array{title:?string, description:?string, image_url:?string, favicon_url:?string}
     */
    public function extract(string $html, string $url): array
    {
        $prevErr = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prevErr);

        $xpath = new \DOMXPath($doc);

        $title = $this->meta($xpath, 'og:title') ?? $this->docTitle($xpath);
        $description = $this->meta($xpath, 'og:description')
            ?? $this->meta($xpath, 'description');
        $imageUrl = $this->meta($xpath, 'og:image');

        // Favicon fallback when no og:image is found.
        $faviconUrl = null;
        if ($imageUrl === null) {
            $faviconUrl = $this->extractFavicon($xpath, $url);
        }

        return [
            'title'          => $title !== null ? mb_substr($title, 0, 160) : null,
            'description'    => $description !== null ? mb_substr($description, 0, 500) : null,
            'image_url'      => $imageUrl !== null ? mb_substr($imageUrl, 0, 2048) : null,
            'favicon_url'    => $faviconUrl !== null ? mb_substr($faviconUrl, 0, 2048) : null,
            'content_images' => $this->extractContentImages($xpath, $url),
        ];
    }

    /**
     * Prominent in-page <img> candidates in document order (hero images,
     * product shots, gallery photos). Attribute-level prefiltering only —
     * the caller still downloads (SSRF-safe) and applies real pixel-size
     * checks on the decoded bytes:
     *
     *   - data:/blob:/javascript: sources are skipped;
     *   - declared width/height attrs below 100px are skipped (icons);
     *   - obvious tracker/sprite/pixel/beacon URLs are skipped;
     *   - lazy-load `data-src`/`data-lazy-src` are honored over `src`;
     *   - duplicates removed, capped at {@see MAX_CONTENT_IMAGES}.
     *
     * @return list<string> absolute http(s) URLs
     */
    private function extractContentImages(\DOMXPath $xpath, string $pageUrl): array
    {
        $out  = [];
        $seen = [];

        foreach ($xpath->query('//img') as $node) {
            if (count($out) >= self::MAX_CONTENT_IMAGES) {
                break;
            }
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $src = trim($node->getAttribute('data-src'))
                ?: trim($node->getAttribute('data-lazy-src'))
                ?: trim($node->getAttribute('src'));
            if ($src === '' || mb_strlen($src) > 2048) {
                continue;
            }

            // Only http(s) or relative sources — never data:/blob:/etc.
            if (preg_match('/^[a-z][a-z0-9+.\-]*:/i', $src) && !preg_match('#^https?:#i', $src)) {
                continue;
            }

            // Declared-size prefilter: skip obvious icons/pixels.
            foreach (['width', 'height'] as $attr) {
                $v = trim($node->getAttribute($attr));
                if ($v !== '' && is_numeric($v) && (float) $v < 100) {
                    continue 2;
                }
            }

            $resolved = $this->resolveUrl($src, $pageUrl);
            if (!preg_match('#^https?://#i', $resolved)) {
                continue;
            }

            $lower = strtolower($resolved);
            if (str_ends_with(parse_url($lower, PHP_URL_PATH) ?? '', '.svg')) {
                continue; // vector icons/logos — not raster content
            }
            foreach (self::CONTENT_IMAGE_URL_BLOCKLIST as $needle) {
                if (str_contains($lower, $needle)) {
                    continue 2;
                }
            }

            if (isset($seen[$lower])) {
                continue;
            }
            $seen[$lower] = true;

            $out[] = mb_substr($resolved, 0, 2048);
        }

        return $out;
    }

    /**
     * Find the site favicon URL: first a <link rel="icon"> element, then
     * the conventional /favicon.ico path constructed from the base origin.
     */
    private function extractFavicon(\DOMXPath $xpath, string $pageUrl): ?string
    {
        // Prefer apple-touch-icon (larger), then any rel="icon".
        foreach (['apple-touch-icon', 'icon', 'shortcut icon'] as $rel) {
            $node = $xpath->query("//link[contains(@rel,\"{$rel}\")]")->item(0);
            if ($node instanceof \DOMElement) {
                $href = trim($node->getAttribute('href'));
                // Skip data:/javascript:/etc. — only http(s) or relative
                // hrefs can be resolved into a usable favicon URL.
                if (preg_match('/^[a-z][a-z0-9+.\-]*:/i', $href) && !preg_match('#^https?:#i', $href)) {
                    continue;
                }
                if ($href !== '') {
                    return $this->resolveUrl($href, $pageUrl);
                }
            }
        }

        // Conventional fallback at the origin root.
        $parsed = parse_url($pageUrl);
        if (is_array($parsed) && isset($parsed['host'])) {
            $scheme = $parsed['scheme'] ?? 'https';
            return $scheme . '://' . $parsed['host'] . '/favicon.ico';
        }

        return null;
    }

    /**
     * Resolve a potentially relative href against a base URL.
     */
    private function resolveUrl(string $href, string $base): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $parsed = parse_url($base);
        if (!is_array($parsed) || !isset($parsed['host'])) {
            return $href;
        }
        $scheme = $parsed['scheme'] ?? 'https';
        $host   = $parsed['host'];
        if (str_starts_with($href, '//')) {
            return $scheme . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $scheme . '://' . $host . $href;
        }
        $basePath = isset($parsed['path']) ? dirname($parsed['path']) : '/';
        return $scheme . '://' . $host . rtrim($basePath, '/') . '/' . ltrim($href, '/');
    }

    private function meta(\DOMXPath $xpath, string $name): ?string
    {
        $node = $xpath->query("//meta[@property=\"{$name}\"]")->item(0)
            ?? $xpath->query("//meta[@name=\"{$name}\"]")->item(0);
        if (!$node instanceof \DOMElement) {
            return null;
        }
        $content = trim($node->getAttribute('content'));
        return $content === '' ? null : $content;
    }

    private function docTitle(\DOMXPath $xpath): ?string
    {
        $title = trim((string) ($xpath->query('//title')->item(0)?->textContent ?? ''));
        return $title === '' ? null : $title;
    }

    /**
     * Block SSRF-style targets: localhost, .local/.internal, and any
     * hostname resolving to a private/reserved/loopback IP.
     * Mirrors EventPageExtractor::guardAgainstPrivateHost.
     */
    private function guardAgainstPrivateHost(string $host): void
    {
        $host = strtolower(trim($host, '[]'));
        if ($host === '' || $host === 'localhost'
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')) {
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
}
