<?php

namespace App\Modules\User\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Cached public RSS/Atom entries; never persist remote markup in blocks. */
class BiolinkFeedService
{
    public const MAX_BYTES = 1048576;
    private array $resolvedAddresses = [];

    public function entries(string $url, int $count = 5): array
    {
        $url = trim($url);
        if (! $this->isPublicUrl($url)) {
            return [];
        }

        $items = Cache::remember('biolink-feed:'.hash('sha256', $url), 1800, function () use ($url) {
            try {
                if (! defined('CURLOPT_RESOLVE')) return [];
                $host = parse_url($url, PHP_URL_HOST);
                $port = parse_url($url, PHP_URL_PORT) ?: (parse_url($url, PHP_URL_SCHEME) === 'http' ? 80 : 443);
                $addresses = implode(',', array_map(fn ($ip) => str_contains($ip, ':') ? '['.$ip.']' : $ip, $this->resolvedAddresses));
                // Reject redirects rather than allowing a public feed to redirect
                // the request into a private network. Pin validated addresses so
                // DNS cannot change between validation and the HTTP connection.
                $response = Http::timeout(5)->connectTimeout(2)
                    ->withOptions(['allow_redirects' => false, 'stream' => true,
                        'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.$addresses]]])
                    ->get($url);
                if (! $response->successful()) {
                    return [];
                }
                $stream = $response->toPsrResponse()->getBody();
                $body = '';
                while (! $stream->eof() && strlen($body) <= self::MAX_BYTES) {
                    $chunk = $stream->read(min(8192, self::MAX_BYTES + 1 - strlen($body)));
                    if ($chunk === '') break;
                    $body .= $chunk;
                }
                $stream->close();

                return $this->parse($body);
            } catch (\Throwable $e) {
                return [];
            }
        });

        return array_slice($items, 0, max(1, min(20, $count)));
    }

    protected function isPublicUrl(string $url): bool
    {
        $parts = parse_url($url);
        $this->resolvedAddresses = [];
        if (strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && ! in_array($parts['port'], [80, 443], true))) {
            return false;
        }
        $host = strtolower(trim($parts['host'], '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : [];
        if (! $ips) {
            foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                if ($ip) $ips[] = $ip;
            }
        }
        if (! $ips) return false;
        foreach ($ips as $ip) {
            if (str_starts_with(strtolower($ip), '::ffff:')
                || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        $this->resolvedAddresses = array_values(array_unique($ips));

        return true;
    }

    public function parse(string $body): array
    {
        if (strlen($body) > self::MAX_BYTES || preg_match('/<!DOCTYPE|<!ENTITY/i', $body)) {
            return [];
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) return [];
            $nodes = $xml->xpath('/*[local-name()="rss"]/*[local-name()="channel"]/*[local-name()="item"] | /*[local-name()="feed"]/*[local-name()="entry"]') ?: [];
            $items = [];
            foreach (array_slice($nodes, 0, 100) as $node) {
                $title = trim(strip_tags((string) (($node->xpath('./*[local-name()="title"]')[0] ?? ''))));
                $href = '';
                foreach ($node->xpath('./*[local-name()="link"]') ?: [] as $link) {
                    if (isset($link['href'])) {
                        if ((string) ($link['rel'] ?? 'alternate') !== 'alternate') continue;
                        $href = (string) $link['href'];
                    } else {
                        $href = trim((string) $link);
                    }
                    if ($href !== '') break;
                }
                if ($title === '' || ! filter_var($href, FILTER_VALIDATE_URL)
                    || ! in_array(strtolower(parse_url($href, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) continue;
                $items[] = ['title' => mb_substr($title, 0, 240), 'url' => $href];
                if (count($items) >= 20) break;
            }

            return $items;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
