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
}
