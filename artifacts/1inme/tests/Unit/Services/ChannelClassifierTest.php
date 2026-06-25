<?php

namespace Tests\Unit\Services;

use App\Modules\Common\Services\ChannelClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChannelClassifierTest extends TestCase
{
    #[DataProvider('userAgents')]
    public function test_classify(string $expected, ?string $ua): void
    {
        $this->assertSame($expected, ChannelClassifier::classify($ua));
    }

    public static function userAgents(): array
    {
        return [
            'null UA'                => [ChannelClassifier::KEY_UNKNOWN, null],
            'empty UA'               => [ChannelClassifier::KEY_UNKNOWN, '   '],

            'Sayzio mobile shell'     => [ChannelClassifier::KEY_1INME_APP, '1INMEMobileApp/1.4.2 (ios; expo) ExpoGo/2.31.0'],

            'Instagram iOS'          => [ChannelClassifier::KEY_INSTAGRAM, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 314.0.0.20.119'],
            'TikTok webview'         => [ChannelClassifier::KEY_TIKTOK, 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/103 Mobile Safari/537.36 musical_ly_30.5.0 JsSdk/2.0 NetType/WIFI Channel/googleplay AppName/musical_ly app_version/30.5.0 BytedanceWebview/d8a21c6'],
            'Facebook IAB Android'   => [ChannelClassifier::KEY_FACEBOOK, 'Mozilla/5.0 (Linux; Android 12) Chrome/121 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/447.0.0.36.109;]'],
            'Messenger iOS'          => [ChannelClassifier::KEY_MESSENGER, 'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 (KHTML) Mobile/15E148 [FBAN/MessengerForiOS;FBAV/450.0.0.36.107;]'],
            'Snapchat iOS'           => [ChannelClassifier::KEY_SNAPCHAT, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 Snapchat/12.62.0.61 (iPhone15,2; iOS 17.4; gzip)'],
            'LinkedIn webview'       => [ChannelClassifier::KEY_LINKEDIN, 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML) Chrome/121 Mobile Safari/537.36 LinkedInApp/4.1.943'],
            'Pinterest webview'      => [ChannelClassifier::KEY_PINTEREST, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4) AppleWebKit/605.1.15 Pinterest/11.34 (iPhone)'],
            'YouTube iOS'            => [ChannelClassifier::KEY_YOUTUBE, 'com.google.ios.youtube/19.16.3 (iPhone15,2; U; CPU iOS 17_4_1 like Mac OS X)'],

            'Generic Android wv'     => [ChannelClassifier::KEY_GENERIC_WEBVIEW, 'Mozilla/5.0 (Linux; Android 13; SM-S908B Build/TP1A.220624.014; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/121.0.0.0 Mobile Safari/537.36'],
            'iOS WKWebView no Safari'=> [ChannelClassifier::KEY_GENERIC_WEBVIEW, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148'],

            'Real Chrome on Android' => [ChannelClassifier::KEY_BROWSER, 'Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.6167.143 Mobile Safari/537.36'],
            'Real Safari macOS'      => [ChannelClassifier::KEY_BROWSER, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15'],
            'Firefox desktop'        => [ChannelClassifier::KEY_BROWSER, 'Mozilla/5.0 (X11; Linux x86_64; rv:124.0) Gecko/20100101 Firefox/124.0'],

            'GoogleBot'              => [ChannelClassifier::KEY_BOT, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
            'FacebookExternalHit'    => [ChannelClassifier::KEY_BOT, 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'],
            'curl'                   => [ChannelClassifier::KEY_BOT, 'curl/8.4.0'],

            'WhatsApp link preview'  => [ChannelClassifier::KEY_BOT, 'WhatsApp/2.23.24.79 A'],
        ];
    }

    public function test_label_for_returns_human_label(): void
    {
        $this->assertSame('Sayzio app', ChannelClassifier::labelFor(ChannelClassifier::KEY_1INME_APP));
        $this->assertSame('Unknown', ChannelClassifier::labelFor(null));
        $this->assertSame('Unknown', ChannelClassifier::labelFor('not-a-real-key'));
    }

    public function test_valid_keys_includes_every_label(): void
    {
        $keys = ChannelClassifier::validKeys();
        $this->assertContains(ChannelClassifier::KEY_1INME_APP, $keys);
        $this->assertContains(ChannelClassifier::KEY_BROWSER, $keys);
        $this->assertContains(ChannelClassifier::KEY_BOT, $keys);
        $this->assertContains(ChannelClassifier::KEY_UNKNOWN, $keys);
    }
}
