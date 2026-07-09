<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Server-side event-details extractor for "Add to calendar" flows.
 *
 * Mirrors the browser extension's content-script extractor
 * (artifacts/1inme-extension/src/content/event-extract.ts): it looks for
 * JSON-LD `Event` objects first, then schema.org microdata, then an
 * `og:type=event` meta block, and finally falls back to the page
 * <title>/description. Unlike the extension the page is fetched HERE on
 * the server (the mobile app can't inject content scripts), so the fetch
 * is SSRF-guarded and size/time capped like CompetitorPageFetcher.
 *
 * Extraction is best-effort: callers should treat every field except
 * `title` as optional and fall back to their manual inputs.
 */
class EventPageExtractor
{
    /** Hard cap on downloaded HTML so a huge page can't blow up memory/latency. */
    public const MAX_BYTES = 1_500_000;

    public const TIMEOUT_SECONDS = 10;

    /**
     * @return array{
     *   title:?string, description:?string, location:?string,
     *   start_at:?string, end_at:?string, image_url:?string,
     *   source:string, url:string
     * }
     * @throws RuntimeException for caller-fixable problems (bad URL,
     *         unreachable host, non-HTML response, blocked target…).
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
                ->connectTimeout(6)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; SayzioEventBot/1.0; +https://1in.me)',
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

        // Re-check the final (post-redirect) host too, in case of an open
        // redirect pointing at an internal target.
        $finalHost = parse_url($finalUrl, PHP_URL_HOST);
        if (is_string($finalHost) && $finalHost !== '') {
            $this->guardAgainstPrivateHost($finalHost);
        }

        return $this->extract($html, $finalUrl);
    }

    /**
     * Extract event details from raw HTML. Public so tests can exercise
     * parsing without a network fetch.
     *
     * @return array{
     *   title:?string, description:?string, location:?string,
     *   start_at:?string, end_at:?string, image_url:?string,
     *   source:string, url:string
     * }
     */
    public function extract(string $html, string $url): array
    {
        $prevErr = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prevErr);

        $xpath = new \DOMXPath($doc);

        return $this->fromJsonLd($xpath, $url)
            ?? $this->fromMicrodata($xpath, $url)
            ?? $this->fromOg($xpath, $url)
            ?? $this->fromTitle($xpath, $url);
    }

    // ── JSON-LD ──────────────────────────────────────────────────────

    private function fromJsonLd(\DOMXPath $xpath, string $url): ?array
    {
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $data = json_decode((string) $script->textContent, true);
            if ($data === null) {
                continue;
            }

            $candidates = [];
            if (is_array($data) && array_is_list($data)) {
                $candidates = $data;
            } elseif (is_array($data)) {
                $candidates = isset($data['@graph']) && is_array($data['@graph'])
                    ? $data['@graph']
                    : [$data];
            }

            foreach ($candidates as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $type = $item['@type'] ?? null;
                $typeStr = is_array($type) ? ($type[0] ?? null) : $type;
                if (!is_string($typeStr) || ($typeStr !== 'Event' && !str_ends_with($typeStr, 'Event'))) {
                    continue;
                }

                $title = $this->ldString($item['name'] ?? null)
                    ?? $this->ldString($item['headline'] ?? null);
                if ($title === null) {
                    continue;
                }

                $location = null;
                if (isset($item['location'])) {
                    $loc = $item['location'];
                    if (is_string($loc)) {
                        $location = $loc;
                    } elseif (is_array($loc)) {
                        $location = $this->ldString($loc['name'] ?? $loc['address'] ?? null);
                    }
                }

                $image = $item['image'] ?? null;

                return $this->candidate(
                    title: $title,
                    description: $this->ldString($item['description'] ?? null),
                    location: $location,
                    startAt: $this->parseDate($item['startDate'] ?? null),
                    endAt: $this->parseDate($item['endDate'] ?? null),
                    imageUrl: $this->ldString(is_array($image) && array_is_list($image) ? ($image[0] ?? null) : $image),
                    source: 'json-ld',
                    url: $url,
                );
            }
        }

        return null;
    }

    /** Mirrors the extension's firstFromLdValue (string | array | {@value|name|description}). */
    private function ldString(mixed $v): ?string
    {
        if (is_string($v)) {
            $v = trim($v);
            return $v === '' ? null : $v;
        }
        if (is_array($v)) {
            if (array_is_list($v)) {
                return $this->ldString($v[0] ?? null);
            }
            return $this->ldString($v['@value'] ?? $v['name'] ?? $v['description'] ?? $v['url'] ?? null);
        }
        return null;
    }

    // ── Microdata ────────────────────────────────────────────────────

    private function fromMicrodata(\DOMXPath $xpath, string $url): ?array
    {
        foreach ($xpath->query('//*[contains(@itemtype, "schema.org/Event")]') as $scope) {
            $title = $this->propValue($xpath, $scope, 'name');
            if ($title === null) {
                continue;
            }

            return $this->candidate(
                title: $title,
                description: $this->propValue($xpath, $scope, 'description'),
                location: $this->propValue($xpath, $scope, 'location'),
                startAt: $this->parseDate($this->propValue($xpath, $scope, 'startDate')),
                endAt: $this->parseDate($this->propValue($xpath, $scope, 'endDate')),
                imageUrl: $this->propValue($xpath, $scope, 'image'),
                source: 'microdata',
                url: $url,
            );
        }

        return null;
    }

    private function propValue(\DOMXPath $xpath, \DOMNode $scope, string $name): ?string
    {
        $el = $xpath->query(".//*[@itemprop=\"{$name}\"]", $scope)->item(0);
        if (!$el instanceof \DOMElement) {
            return null;
        }
        $value = match (strtoupper($el->tagName)) {
            'META' => $el->getAttribute('content'),
            'TIME' => $el->getAttribute('datetime') !== '' ? $el->getAttribute('datetime') : trim((string) $el->textContent),
            'IMG'  => $el->getAttribute('src'),
            'A'    => $el->getAttribute('href') !== '' ? $el->getAttribute('href') : trim((string) $el->textContent),
            default => trim(preg_replace('/\s+/', ' ', (string) $el->textContent) ?? ''),
        };
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    // ── OG / meta ────────────────────────────────────────────────────

    private function fromOg(\DOMXPath $xpath, string $url): ?array
    {
        if (strtolower((string) $this->meta($xpath, 'og:type')) !== 'event') {
            return null;
        }
        $title = $this->meta($xpath, 'og:title') ?? $this->docTitle($xpath);
        if ($title === null) {
            return null;
        }

        return $this->candidate(
            title: $title,
            description: $this->meta($xpath, 'og:description'),
            location: null,
            startAt: null,
            endAt: null,
            imageUrl: $this->meta($xpath, 'og:image'),
            source: 'og',
            url: $url,
        );
    }

    private function fromTitle(\DOMXPath $xpath, string $url): array
    {
        return $this->candidate(
            title: $this->docTitle($xpath) ?? $this->meta($xpath, 'og:title'),
            description: $this->meta($xpath, 'description') ?? $this->meta($xpath, 'og:description'),
            location: null,
            startAt: null,
            endAt: null,
            imageUrl: $this->meta($xpath, 'og:image'),
            source: 'title',
            url: $url,
        );
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

    // ── Helpers ──────────────────────────────────────────────────────

    /** Normalize any parseable date string to ISO-8601 UTC, else null. */
    private function parseDate(mixed $raw): ?string
    {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse(trim($raw))->toIso8601String();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function candidate(
        ?string $title,
        ?string $description,
        ?string $location,
        ?string $startAt,
        ?string $endAt,
        ?string $imageUrl,
        string $source,
        string $url,
    ): array {
        return [
            'title'       => $title !== null ? mb_substr($title, 0, 160) : null,
            'description' => $description !== null ? mb_substr($description, 0, 5000) : null,
            'location'    => $location !== null ? mb_substr($location, 0, 255) : null,
            'start_at'    => $startAt,
            'end_at'      => $endAt,
            'image_url'   => $imageUrl !== null ? mb_substr($imageUrl, 0, 2048) : null,
            'source'      => $source,
            'url'         => $url,
        ];
    }

    /**
     * Block SSRF-style targets: localhost, .local/.internal, and any
     * hostname resolving to a private/reserved/loopback IP (mirrors
     * CompetitorPageFetcher::guardAgainstPrivateHost).
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
}
