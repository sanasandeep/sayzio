<?php

namespace App\Modules\Common\Services;

/**
 * Lightweight, dependency-free user-agent matcher that flags obvious crawlers,
 * scrapers, and headless automation tools so their hits can be excluded from
 * creator-facing analytics (totals, uniques, source breakdowns, dashboards).
 *
 * The list is intentionally conservative: we err on the side of letting an
 * unknown UA through (counted as a real visitor) rather than wrongly hiding a
 * genuine click. The patterns below cover the long tail of well-known bots
 * (search engines, SEO crawlers, social-card unfurlers, monitoring tools) and
 * generic markers (`bot`, `crawler`, `spider`, `headless`, common HTTP
 * clients) that real browsers never include in their UA string.
 */
class BotDetector
{
    /**
     * Case-insensitive substrings — high-confidence bot/automation markers
     * only. Anything that could plausibly appear in a real human's UA (e.g.
     * in-app browser tokens like "WhatsApp", "Pinterest", "Snapchat",
     * "Tumblr", or generic words like "preview") is intentionally excluded
     * so we don't quietly drop genuine clicks from creators' totals.
     */
    protected const BOT_SUBSTRINGS = [
        // Generic crawler / automation markers — never present in real UAs.
        'bot', 'crawler', 'spider', 'slurp', 'scraper', 'scrape',
        'headlesschrome', 'phantomjs', 'puppeteer', 'playwright', 'selenium',
        'electron-fetch', 'node-fetch', 'undici',

        // Common HTTP-client UAs (libraries, scripts, scrapers).
        'httpclient', 'http-client', 'okhttp', 'go-http-client',
        'python-requests', 'python-urllib', 'aiohttp', 'libwww-perl',
        'java/', 'apache-httpclient', 'wget/', 'curl/', 'lwp::', 'mechanize',
        'guzzlehttp', 'restsharp', 'axios/', 'httpie/',

        // Link-unfurl / preview bots (named "bot" or otherwise unambiguous).
        'facebookexternalhit', 'facebookcatalog', 'meta-externalagent',
        'embedly', 'prerender',

        // Synthetic-monitoring / performance tools.
        'lighthouse', 'pagespeed', 'gtmetrix', 'pingdom', 'uptimerobot',
        'statuscake', 'monitis', 'newrelicpinger', 'site24x7',
    ];

    /**
     * Specific named bots that don't include the generic markers above.
     * Matched as case-insensitive substrings.
     */
    protected const NAMED_BOTS = [
        'googlebot', 'adsbot-google', 'mediapartners-google', 'apis-google',
        'bingbot', 'bingpreview', 'msnbot', 'yandex', 'baiduspider',
        'duckduckbot', 'sogou', 'exabot', 'seznambot', 'naver',
        'ahrefsbot', 'semrushbot', 'mj12bot', 'dotbot', 'rogerbot',
        'screaming frog', 'sitebulb', 'serpstatbot', 'petalbot',
        'archive.org_bot', 'ia_archiver', 'wayback',
        'applebot', 'amazonbot', 'gptbot', 'oai-searchbot', 'chatgpt-user',
        'claudebot', 'anthropic-ai', 'perplexitybot', 'youbot', 'ccbot',
        'bytespider', 'cohere-ai',
    ];

    /**
     * Check whether a user-agent string matches a known bot/scraper signature.
     * Empty / null UAs are also treated as bots — real browsers always send one.
     */
    public function isBot(?string $userAgent): bool
    {
        if ($userAgent === null) {
            return true;
        }

        $ua = trim($userAgent);
        if ($ua === '') {
            return true;
        }

        $haystack = strtolower($ua);

        foreach (self::BOT_SUBSTRINGS as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        foreach (self::NAMED_BOTS as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ordered list of bot "families" — friendly display name => list of
     * case-insensitive substrings. The first family whose substring is found
     * in the UA wins, so order matters: more specific names come before
     * generic catch-alls. Used by analytics to break the "Bot hits filtered"
     * badge down by who's actually crawling the link.
     */
    protected const BOT_FAMILIES = [
        'Googlebot'         => ['googlebot', 'adsbot-google', 'mediapartners-google', 'apis-google'],
        'Bingbot'           => ['bingbot', 'bingpreview', 'msnbot'],
        'Yandex'            => ['yandex'],
        'Baidu'             => ['baiduspider'],
        'DuckDuckGo'        => ['duckduckbot'],
        'Applebot'          => ['applebot'],
        'Amazonbot'         => ['amazonbot'],
        'GPTBot (OpenAI)'   => ['gptbot', 'oai-searchbot', 'chatgpt-user'],
        'ClaudeBot'        => ['claudebot', 'anthropic-ai'],
        'PerplexityBot'     => ['perplexitybot'],
        'CommonCrawl'       => ['ccbot'],
        'ByteSpider'        => ['bytespider'],
        'Cohere'            => ['cohere-ai'],
        'YouBot'            => ['youbot'],
        'AhrefsBot'         => ['ahrefsbot'],
        'SemrushBot'        => ['semrushbot'],
        'MJ12bot'           => ['mj12bot'],
        'DotBot'            => ['dotbot'],
        'PetalBot'          => ['petalbot'],
        'Screaming Frog'    => ['screaming frog'],
        'Sitebulb'          => ['sitebulb'],
        'Serpstat'          => ['serpstatbot'],
        'Internet Archive'  => ['archive.org_bot', 'ia_archiver', 'wayback'],
        'Facebook'          => ['facebookexternalhit', 'facebookcatalog', 'meta-externalagent'],
        'Embedly'           => ['embedly'],
        'Prerender'         => ['prerender'],
        'Lighthouse'        => ['lighthouse', 'pagespeed'],
        'GTmetrix'          => ['gtmetrix'],
        'Pingdom'           => ['pingdom'],
        'UptimeRobot'       => ['uptimerobot'],
        'StatusCake'        => ['statuscake'],
        'New Relic'         => ['newrelicpinger'],
        'Site24x7'          => ['site24x7'],
        'Headless Chrome'   => ['headlesschrome'],
        'PhantomJS'         => ['phantomjs'],
        'Puppeteer'         => ['puppeteer'],
        'Playwright'        => ['playwright'],
        'Selenium'          => ['selenium'],
        'curl'              => ['curl/'],
        'wget'              => ['wget/'],
        'Python script'     => ['python-requests', 'python-urllib', 'aiohttp'],
        'Java client'       => ['java/', 'apache-httpclient'],
        'Go client'         => ['go-http-client'],
        'Node.js client'    => ['node-fetch', 'electron-fetch', 'undici', 'axios/'],
        'OkHttp'            => ['okhttp'],
        'Guzzle'            => ['guzzlehttp'],
        'HTTPie'            => ['httpie/'],
        'Perl LWP'          => ['libwww-perl', 'lwp::', 'mechanize'],
        'RestSharp'         => ['restsharp'],
    ];

    /**
     * Friendly display names of every bot family the detector knows about,
     * in the same order as {@see BOT_FAMILIES}. Used by the per-user
     * "block this bot family from being recorded" management screen so
     * the picker stays in sync with the classifier.
     *
     * @return array<int, string>
     */
    public function knownFamilies(): array
    {
        return array_keys(self::BOT_FAMILIES);
    }

    /**
     * Bucket a user-agent string into a friendly family name (e.g. "Googlebot",
     * "ClaudeBot", "Headless Chrome"). Returns "Unknown bot" for empty UAs and
     * "Other bot" for UAs that {@see isBot()} flagged but don't match a known
     * family. Safe to call on non-bot UAs too — they'll just fall into
     * "Other bot".
     */
    public function classifyFamily(?string $userAgent): string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return 'Unknown (no UA)';
        }

        $haystack = strtolower(trim($userAgent));

        foreach (self::BOT_FAMILIES as $family => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $family;
                }
            }
        }

        // Generic markers that didn't match a specific family.
        if (str_contains($haystack, 'crawler')) return 'Other crawler';
        if (str_contains($haystack, 'spider'))  return 'Other spider';
        if (str_contains($haystack, 'bot'))     return 'Other bot';
        if (str_contains($haystack, 'scrape'))  return 'Other scraper';

        return 'Other bot';
    }
}
