<?php

namespace App\Modules\Common\Services;

class ChannelClassifier
{
    public const KEY_1INME_APP        = '1inme_app';
    public const KEY_INSTAGRAM        = 'instagram';
    public const KEY_TIKTOK           = 'tiktok';
    public const KEY_FACEBOOK         = 'facebook';
    public const KEY_MESSENGER        = 'messenger';
    public const KEY_SNAPCHAT         = 'snapchat';
    public const KEY_LINKEDIN         = 'linkedin';
    public const KEY_TWITTER          = 'twitter';
    public const KEY_PINTEREST        = 'pinterest';
    public const KEY_YOUTUBE          = 'youtube';
    public const KEY_WHATSAPP         = 'whatsapp';
    public const KEY_TELEGRAM         = 'telegram';
    public const KEY_GENERIC_WEBVIEW  = 'webview';
    public const KEY_BROWSER          = 'browser';
    public const KEY_BOT              = 'bot';
    public const KEY_UNKNOWN          = 'unknown';

    public const LABELS = [
        self::KEY_1INME_APP       => '1INME app',
        self::KEY_INSTAGRAM       => 'Instagram',
        self::KEY_TIKTOK          => 'TikTok',
        self::KEY_FACEBOOK        => 'Facebook',
        self::KEY_MESSENGER       => 'Messenger',
        self::KEY_SNAPCHAT        => 'Snapchat',
        self::KEY_LINKEDIN        => 'LinkedIn',
        self::KEY_TWITTER         => 'Twitter / X',
        self::KEY_PINTEREST       => 'Pinterest',
        self::KEY_YOUTUBE         => 'YouTube',
        self::KEY_WHATSAPP        => 'WhatsApp',
        self::KEY_TELEGRAM        => 'Telegram',
        self::KEY_GENERIC_WEBVIEW => 'Other in-app browser',
        self::KEY_BROWSER         => 'Regular browser',
        self::KEY_BOT             => 'Bot / crawler',
        self::KEY_UNKNOWN         => 'Unknown',
    ];

    public static function labelFor(?string $key): string
    {
        return self::LABELS[$key] ?? self::LABELS[self::KEY_UNKNOWN];
    }

    public static function validKeys(): array
    {
        return array_keys(self::LABELS);
    }

    /**
     * Map a raw user-agent string to a normalized channel key.
     * Order matters — the 1INME app token is checked first so it doesn't
     * fall through to one of the generic webview heuristics, and bots are
     * matched before browsers because many crawlers also pose as Chrome.
     */
    public static function classify(?string $ua): string
    {
        if ($ua === null || trim($ua) === '') {
            return self::KEY_UNKNOWN;
        }

        // Our own native shell — most specific signal first.
        if (preg_match('~1INMEMobileApp/~i', $ua)) {
            return self::KEY_1INME_APP;
        }

        // Bots / crawlers / link-preview fetchers (checked before browsers
        // because crawlers often impersonate Chrome / Safari).
        // WhatsApp link previews ("WhatsApp/2.x A") are bots, but the
        // in-app browser is a normal page-view (and is classified further
        // down). Match the slash-versioned preview agent explicitly first.
        if (preg_match('~WhatsApp/[0-9]~i', $ua)) {
            return self::KEY_BOT;
        }

        if (preg_match('~(bot|crawler|spider|crawling|slurp|bingpreview|facebookexternalhit|facebookcatalog|telegrambot|skypeuripreview|discordbot|slackbot|twitterbot|linkedinbot|embedly|quora link preview|outbrain|pinterest/0\.|google-inspectiontool|google-pagerendererservice|chrome-lighthouse|headlesschrome|phantomjs|puppeteer|playwright|httpclient|python-requests|curl/|wget/|go-http-client|okhttp/(?!.*Mobile))~i', $ua)) {
            return self::KEY_BOT;
        }

        // Named in-app webviews — order matters because some apps
        // (Messenger) embed FBAV markers from Facebook.
        if (preg_match('~Instagram~i', $ua))                       return self::KEY_INSTAGRAM;
        if (preg_match('~(TikTok|musical_ly|BytedanceWebview|Bytedance)~i', $ua)) return self::KEY_TIKTOK;
        if (preg_match('~(FB_IAB|FB4A|FBAV|FBAN/FBIOS|FBAN/FB4A)~i', $ua)) {
            // Messenger reuses the FBAN container with a distinct app id.
            if (preg_match('~(MessengerLite|Messenger|FBAN/MessengerForiOS|FBAN/MessengerLiteForiOS|Orca-Android)~i', $ua)) {
                return self::KEY_MESSENGER;
            }
            return self::KEY_FACEBOOK;
        }
        if (preg_match('~Snapchat~i', $ua))                         return self::KEY_SNAPCHAT;
        if (preg_match('~LinkedInApp~i', $ua))                      return self::KEY_LINKEDIN;
        if (preg_match('~(Twitter|TwitterAndroid|TwitteriPhone)~i', $ua)) return self::KEY_TWITTER;
        if (preg_match('~Pinterest~i', $ua))                        return self::KEY_PINTEREST;
        if (preg_match('~(YouTube|com\.google\.android\.youtube|com\.google\.ios\.youtube)~i', $ua)) return self::KEY_YOUTUBE;
        if (preg_match('~WhatsApp~i', $ua))                         return self::KEY_WHATSAPP;
        if (preg_match('~Telegram~i', $ua))                         return self::KEY_TELEGRAM;

        // Generic webview heuristics — Android wv flag and iOS WebKit
        // without the trailing Safari/<ver> token both indicate the page
        // is being rendered inside another app's embedded browser.
        if (preg_match('~Android~i', $ua) && preg_match('~; wv\)~i', $ua)) {
            return self::KEY_GENERIC_WEBVIEW;
        }
        if (preg_match('~\((iPhone|iPad|iPod)~i', $ua)
            && preg_match('~AppleWebKit~i', $ua)
            && !preg_match('~ Safari/~', $ua)
            && !preg_match('~CriOS|FxiOS|EdgiOS|OPiOS~', $ua)) {
            return self::KEY_GENERIC_WEBVIEW;
        }

        // Anything else with a recognizable browser engine is a regular
        // browser. We're conservative — unknown shapes fall through to
        // 'unknown' so they don't pollute the "Regular browser" bucket.
        if (preg_match('~Chrome/|Firefox/|Safari/|Edg/|OPR/|MSIE |Trident/|Gecko/|AppleWebKit/~i', $ua)) {
            return self::KEY_BROWSER;
        }

        return self::KEY_UNKNOWN;
    }
}
