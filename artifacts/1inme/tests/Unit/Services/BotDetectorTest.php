<?php

namespace Tests\Unit\Services;

use App\Modules\Common\Services\BotDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BotDetectorTest extends TestCase
{
    private BotDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new BotDetector();
    }

    #[DataProvider('botUserAgents')]
    public function test_known_bot_user_agents_are_flagged(string $label, string $ua): void
    {
        $this->assertTrue(
            $this->detector->isBot($ua),
            "Expected '$label' UA to be flagged as a bot: $ua"
        );
    }

    #[DataProvider('humanUserAgents')]
    public function test_real_human_user_agents_are_not_flagged(string $label, string $ua): void
    {
        $this->assertFalse(
            $this->detector->isBot($ua),
            "Expected '$label' UA to NOT be flagged as a bot: $ua"
        );
    }

    public function test_empty_or_null_user_agents_are_treated_as_bots(): void
    {
        $this->assertTrue($this->detector->isBot(null));
        $this->assertTrue($this->detector->isBot(''));
        $this->assertTrue($this->detector->isBot('   '));
    }

    #[DataProvider('botFamilyExpectations')]
    public function test_classify_family_buckets_known_bots(string $expected, string $ua): void
    {
        $this->assertSame(
            $expected,
            $this->detector->classifyFamily($ua),
            "Expected UA to bucket as '$expected': $ua"
        );
    }

    public function test_classify_family_returns_unknown_for_empty_ua(): void
    {
        $this->assertSame('Unknown (no UA)', $this->detector->classifyFamily(null));
        $this->assertSame('Unknown (no UA)', $this->detector->classifyFamily(''));
        $this->assertSame('Unknown (no UA)', $this->detector->classifyFamily('   '));
    }

    public function test_classify_family_falls_back_to_other_bot_for_unfamiliar_markers(): void
    {
        $this->assertSame('Other bot',     $this->detector->classifyFamily('Mozilla/5.0 SomeRandomBot/1.0'));
        $this->assertSame('Other crawler', $this->detector->classifyFamily('Unknown crawler/2.0'));
        $this->assertSame('Other spider',  $this->detector->classifyFamily('CustomSpider/1.0'));
    }

    public static function botFamilyExpectations(): array
    {
        return [
            ['Googlebot',       'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
            ['Bingbot',         'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'],
            ['AhrefsBot',       'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)'],
            ['SemrushBot',      'Mozilla/5.0 (compatible; SemrushBot/7~bl;)'],
            ['DuckDuckGo',      'DuckDuckBot/1.1; (+http://duckduckgo.com/duckduckbot.html)'],
            ['Yandex',          'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)'],
            ['Baidu',           'Mozilla/5.0 (compatible; Baiduspider/2.0;)'],
            ['GPTBot (OpenAI)', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.0; +https://openai.com/gptbot)'],
            ['ClaudeBot',       'Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)'],
            ['Headless Chrome', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 HeadlessChrome/118.0.0.0 Safari/537.36'],
            ['curl',            'curl/7.85.0'],
            ['wget',            'Wget/1.21.3'],
            ['Python script',   'python-requests/2.31.0'],
            ['UptimeRobot',     'Mozilla/5.0 (compatible; UptimeRobot/2.0;)'],
            ['Lighthouse',      'Mozilla/5.0 Chrome/119.0.0.0 Safari/537.36 Lighthouse'],
            ['Facebook',        'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'],
            ['Go client',       'Go-http-client/1.1'],
            ['Java client',     'Java/17.0.2'],
        ];
    }

    public static function botUserAgents(): array
    {
        return [
            ['Googlebot',         'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
            ['Bingbot',           'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'],
            ['AhrefsBot',         'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)'],
            ['SemrushBot',        'Mozilla/5.0 (compatible; SemrushBot/7~bl; +http://www.semrush.com/bot.html)'],
            ['DuckDuckBot',       'DuckDuckBot/1.1; (+http://duckduckgo.com/duckduckbot.html)'],
            ['YandexBot',         'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)'],
            ['Baiduspider',       'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)'],
            ['GPTBot',            'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.0; +https://openai.com/gptbot)'],
            ['ClaudeBot',         'Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)'],
            ['curl',              'curl/7.85.0'],
            ['wget',              'Wget/1.21.3'],
            ['python-requests',   'python-requests/2.31.0'],
            ['HeadlessChrome',    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/118.0.0.0 Safari/537.36'],
            ['Puppeteer',         'Mozilla/5.0 Puppeteer/21.0.0'],
            ['Playwright',        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Playwright/1.40.0'],
            ['Selenium',          'Mozilla/5.0 selenium-webdriver'],
            ['UptimeRobot',       'Mozilla/5.0+(compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)'],
            ['Lighthouse',        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/119.0.0.0 Safari/537.36 Lighthouse'],
            ['facebookexternalhit', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'],
            ['Slackbot',          'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)'],
            ['Twitterbot',        'Twitterbot/1.0'],
            ['Go HTTP client',    'Go-http-client/1.1'],
            ['Java HTTP',         'Java/17.0.2'],
        ];
    }

    public static function humanUserAgents(): array
    {
        return [
            ['Chrome desktop',    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'],
            ['Safari iPhone',     'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1'],
            ['Chrome Android',    'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36'],
            ['Firefox',           'Mozilla/5.0 (Windows NT 10.0; rv:121.0) Gecko/20100101 Firefox/121.0'],
            ['Edge',              'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0'],
            ['Opera',             'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/119.0.0.0 Safari/537.36 OPR/105.0.0.0'],
            ['Samsung Internet',  'Mozilla/5.0 (Linux; Android 13; SM-G998B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/23.0 Chrome/115.0.0.0 Mobile Safari/537.36'],
            // In-app browsers — these are real humans even though the app
            // name appears in the UA. Must NOT be flagged.
            ['WhatsApp in-app',   'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Mobile Safari/537.36 WhatsApp/2.23.24.76'],
            ['Instagram in-app',  'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 305.0.0.40.119'],
            ['Pinterest in-app',  'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [Pinterest/iOS]'],
            ['Snapchat in-app',   'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Snapchat/12.50.0.40'],
            ['Tumblr app',        'Tumblr/iOS/27.5 (iPhone; iPhone15,2; iOS 17.1)'],
            ['Facebook in-app',   'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBDV/iPhone15,2]'],
        ];
    }
}
