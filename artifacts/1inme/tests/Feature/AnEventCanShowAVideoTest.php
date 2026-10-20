<?php

namespace Tests\Feature;

use App\Modules\User\Models\IcsData;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * An organiser pastes one URL into the event editor and the page has to
 * decide what to do with it.
 *
 * None of the players here are new. common/blocks/youtube.blade.php,
 * video.blade.php and iframe-embed.blade.php have been rendering biolink
 * pages for a long time; the event page simply had no field pointing at
 * them. So the only thing worth pinning is the routing decision: which
 * renderer gets the URL, and -- more importantly -- which URLs never reach
 * an iframe at all.
 *
 * That last part is the reason this is an allow-list. The field is typed by
 * the organiser, and an organiser is not always the person who owns the
 * account by the time a link is pasted.
 */
class AnEventCanShowAVideoTest extends TestCase
{
    private function ics(?string $url): IcsData
    {
        $ics = new IcsData();
        $ics->video_url = $url;

        return $ics;
    }

    public static function routed(): array
    {
        return [
            'youtube watch'   => ['https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'common.blocks.youtube'],
            'youtube short'   => ['https://youtu.be/aqz-KE-bpKQ', 'common.blocks.youtube'],
            'youtube mobile'  => ['https://m.youtube.com/watch?v=aqz-KE-bpKQ', 'common.blocks.youtube'],
            'mp4 file'        => ['https://cdn.example.com/clips/opening.mp4', 'common.blocks.video'],
            'webm file'       => ['https://cdn.example.com/clips/opening.webm', 'common.blocks.video'],
            'vimeo'           => ['https://vimeo.com/76979871', 'common.blocks.iframe-embed'],
            'vimeo player'    => ['https://player.vimeo.com/video/76979871', 'common.blocks.iframe-embed'],
            'loom'            => ['https://www.loom.com/share/abc123', 'common.blocks.iframe-embed'],
        ];
    }

    #[DataProvider('routed')]
    public function test_a_url_reaches_the_renderer_that_can_show_it(string $url, string $view): void
    {
        $embed = $this->ics($url)->videoEmbed();

        $this->assertNotNull($embed, "Nothing was rendered for {$url}");
        $this->assertSame($view, $embed['view']);
    }

    public static function refused(): array
    {
        return [
            'empty'             => [''],
            'whitespace only'   => ['   '],
            'plain http'        => ['http://www.youtube.com/watch?v=aqz-KE-bpKQ'],
            'javascript scheme' => ['javascript:alert(1)'],
            'data scheme'       => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
            'file scheme'       => ['file:///etc/passwd'],
            'unknown host'      => ['https://evil.test/player'],
            'no host'           => ['https:///watch'],
        ];
    }

    #[DataProvider('refused')]
    public function test_a_url_we_will_not_frame_renders_nothing(string $url): void
    {
        $this->assertNull(
            $this->ics($url)->videoEmbed(),
            "This URL should not have reached an iframe: {$url}"
        );
    }

    public function test_null_is_not_a_video(): void
    {
        $this->assertNull($this->ics(null)->videoEmbed());
    }

    public function test_the_youtube_renderer_is_handed_the_whole_url(): void
    {
        // The youtube partial extracts the id itself with its own regex.
        // Pre-parsing it here would mean two places that have to agree.
        $url = 'https://www.youtube.com/watch?v=aqz-KE-bpKQ&t=90s';
        $embed = $this->ics($url)->videoEmbed();

        $this->assertSame($url, $embed['s']['video_id']);
    }

    public function test_a_framed_player_is_given_a_height(): void
    {
        // iframe-embed falls back to 400 when height is absent, which is
        // shorter than a 16:9 frame at event-page width.
        $embed = $this->ics('https://vimeo.com/76979871')->videoEmbed();

        $this->assertSame('https://vimeo.com/76979871', $embed['s']['url']);
        $this->assertArrayHasKey('height', $embed['s']);
    }

    public function test_a_direct_file_is_played_by_us_and_not_framed(): void
    {
        // An mp4 we can play with <video> never needs a third-party frame.
        $embed = $this->ics('https://cdn.example.com/a.mp4')->videoEmbed();

        $this->assertSame('common.blocks.video', $embed['view']);
        $this->assertSame('https://cdn.example.com/a.mp4', $embed['s']['url']);
    }

    public function test_the_host_match_ignores_case_and_a_www_prefix(): void
    {
        $this->assertNotNull($this->ics('https://WWW.Vimeo.com/76979871')->videoEmbed());
        $this->assertNotNull($this->ics('https://YouTu.be/aqz-KE-bpKQ')->videoEmbed());
    }
}
