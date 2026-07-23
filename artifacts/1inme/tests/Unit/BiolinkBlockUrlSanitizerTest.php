<?php

namespace Tests\Unit;

use App\Modules\User\Controllers\BiolinkBlockController;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pins the rejection behavior of BiolinkBlockController::sanitizeUrl (and its
 * application through sanitizeSettings). The sanitizer was relaxed to accept
 * relative vault paths (/f/{id}/{filename}) so AI-built pages keep uploaded
 * images — this test guards against any future relaxation reopening a
 * stored-XSS or open-redirect vector via javascript:, data:, protocol-relative
 * hosts, backslash smuggling, or arbitrary relative paths.
 */
class BiolinkBlockUrlSanitizerTest extends TestCase
{
    private function sanitizeUrl(?string $url): string
    {
        $method = new ReflectionMethod(BiolinkBlockController::class, 'sanitizeUrl');
        $method->setAccessible(true);

        return $method->invoke(new BiolinkBlockController(), $url);
    }

    public static function keptUrls(): array
    {
        return [
            'https url'            => ['https://example.com/image.png'],
            'http url'             => ['http://example.com/a'],
            'https mixed case'     => ['HTTPS://Example.com/x'],
            'vault path'           => ['/f/123/photo.png'],
            'vault path nested id' => ['/f/abc-DEF_9/img.webp'],
        ];
    }

    #[DataProvider('keptUrls')]
    public function test_safe_urls_are_kept(string $url): void
    {
        $this->assertSame($url, $this->sanitizeUrl($url));
    }

    public static function rejectedUrls(): array
    {
        return [
            'javascript scheme'          => ['javascript:alert(1)'],
            'javascript mixed case'      => ['JaVaScRiPt:alert(1)'],
            'data uri'                   => ['data:text/html;base64,PHNjcmlwdD4='],
            'protocol-relative host'     => ['//evil.com/x.png'],
            'double slash after f'       => ['/f//x'],
            'double slash inside path'   => ['/f/123//x'],
            'backslash smuggling'        => ['/f/\\evil'],
            'backslash later in path'    => ['/f/123\\evil.com/x'],
            'non-vault relative path'    => ['/other/path.png'],
            'bare relative path'         => ['foo/bar.png'],
            'fragment only'              => ['#about'],
            'whitespace in vault path'   => ['/f/123/a b.png'],
            'newline in vault path'      => ["/f/123/a\nb.png"],
            'tab in vault path'          => ["/f/1\tx"],
            'vault prefix only'          => ['/f/'],
            'ftp scheme'                 => ['ftp://evil.com/x'],
            'file scheme'                => ['file:///etc/passwd'],
            'vbscript scheme'            => ['vbscript:msgbox(1)'],
            'empty string'               => [''],
            'null'                       => [null],
        ];
    }

    #[DataProvider('rejectedUrls')]
    public function test_unsafe_urls_are_blanked(?string $url): void
    {
        $this->assertSame('', $this->sanitizeUrl($url));
    }

    public function test_sanitize_settings_applies_url_sanitizer_to_url_fields(): void
    {
        $controller = new BiolinkBlockController();

        $settings = $controller->sanitizeSettings('image', [
            'image_url' => 'javascript:alert(1)',
            'url'       => '//evil.com/redirect',
            'link'      => '/f/\\evil',
        ]);

        $this->assertSame('', $settings['image_url']);
        $this->assertSame('', $settings['url']);
        $this->assertSame('', $settings['link']);

        $settings = $controller->sanitizeSettings('image', [
            'image_url' => '/f/42/photo.jpg',
            'url'       => 'https://example.com/target',
        ]);

        $this->assertSame('/f/42/photo.jpg', $settings['image_url']);
        $this->assertSame('https://example.com/target', $settings['url']);
    }
}
