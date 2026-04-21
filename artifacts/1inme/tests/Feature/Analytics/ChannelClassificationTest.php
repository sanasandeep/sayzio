<?php

namespace Tests\Feature\Analytics;

use App\Modules\Common\Services\ChannelClassifier;
use App\Modules\User\Models\LinkClick;

class ChannelClassificationTest extends AnalyticsTestCase
{
    public function test_click_is_auto_flagged_with_classified_channel(): void
    {
        $this->fakeGeoIp([]);
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $this
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 314.0.0.20.119',
            ])
            ->get('/' . $link->alias)
            ->assertRedirect($link->long_url);

        $click = LinkClick::where('link_id', $link->id)->firstOrFail();
        $this->assertSame(ChannelClassifier::KEY_INSTAGRAM, $click->channel);
    }

    public function test_native_app_user_agent_is_recognized(): void
    {
        $this->fakeGeoIp([]);
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $this
            ->withHeaders(['User-Agent' => '1INMEMobileApp/1.4.2 (ios; expo)'])
            ->get('/' . $link->alias)
            ->assertRedirect($link->long_url);

        $click = LinkClick::where('link_id', $link->id)->firstOrFail();
        $this->assertSame(ChannelClassifier::KEY_1INME_APP, $click->channel);
    }

    public function test_regular_browser_is_recognized(): void
    {
        $this->fakeGeoIp([]);
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $this
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
            ])
            ->get('/' . $link->alias)
            ->assertRedirect($link->long_url);

        $click = LinkClick::where('link_id', $link->id)->firstOrFail();
        $this->assertSame(ChannelClassifier::KEY_BROWSER, $click->channel);
    }

    public function test_clicks_are_split_into_correct_channel_buckets(): void
    {
        $this->fakeGeoIp([]);
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        // 2 Instagram clicks, 1 real Chrome click
        foreach (['Instagram 314.0.0.20.119', 'Instagram 313.0.0.20.119'] as $ua) {
            $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 ' . $ua])
                ->get('/' . $link->alias);
        }
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36',
        ])->get('/' . $link->alias);

        $this->assertSame(2, LinkClick::where('link_id', $link->id)->where('channel', ChannelClassifier::KEY_INSTAGRAM)->count());
        $this->assertSame(1, LinkClick::where('link_id', $link->id)->where('channel', ChannelClassifier::KEY_BROWSER)->count());
    }
}
