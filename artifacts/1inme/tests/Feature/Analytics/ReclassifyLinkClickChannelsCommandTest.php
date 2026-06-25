<?php

namespace Tests\Feature\Analytics;

use App\Modules\Common\Services\ChannelClassifier;
use App\Modules\User\Models\LinkClick;

class ReclassifyLinkClickChannelsCommandTest extends AnalyticsTestCase
{
    public function test_command_rewrites_stale_channel_labels(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        // Simulate an "older" row that was classified before the classifier
        // learned to recognize the Sayzio native shell — it was filed as a
        // generic webview at the time.
        $stale = LinkClick::create([
            'link_id' => $link->id,
            'user_agent' => '1INMEMobileApp/1.4.2 (ios; expo)',
            'channel' => ChannelClassifier::KEY_GENERIC_WEBVIEW,
            'clicked_at' => now()->subDays(5),
        ]);

        // A row that's already correctly labelled — must be left alone.
        $current = LinkClick::create([
            'link_id' => $link->id,
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
            'channel' => ChannelClassifier::KEY_BROWSER,
            'clicked_at' => now()->subDays(2),
        ]);

        // A row with no user_agent — must be skipped (nothing to reclassify).
        $noUa = LinkClick::create([
            'link_id' => $link->id,
            'user_agent' => null,
            'channel' => null,
            'clicked_at' => now()->subDay(),
        ]);

        $this->artisan('link-clicks:reclassify-channels')
            ->assertExitCode(0);

        $stale->refresh();
        $current->refresh();
        $noUa->refresh();

        $this->assertSame(ChannelClassifier::KEY_1INME_APP, $stale->channel);
        $this->assertSame(ChannelClassifier::KEY_BROWSER, $current->channel);
        $this->assertNull($noUa->channel);
    }

    public function test_dry_run_reports_changes_without_writing(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $stale = LinkClick::create([
            'link_id' => $link->id,
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 314.0.0.20.119',
            'channel' => ChannelClassifier::KEY_UNKNOWN,
            'clicked_at' => now()->subDay(),
        ]);

        $this->artisan('link-clicks:reclassify-channels', ['--dry-run' => true])
            ->assertExitCode(0);

        $stale->refresh();
        $this->assertSame(ChannelClassifier::KEY_UNKNOWN, $stale->channel);
    }

    public function test_command_reclassifies_bot_rows_too(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        // A bot-flagged row whose stored channel is stale. The LinkClick
        // model has a global scope that hides bot rows from regular queries,
        // so the command must explicitly opt back in to find and update it.
        $bot = LinkClick::withBots()->create([
            'link_id' => $link->id,
            'user_agent' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
            'channel' => ChannelClassifier::KEY_UNKNOWN,
            'is_bot' => true,
            'clicked_at' => now()->subDay(),
        ]);

        $this->artisan('link-clicks:reclassify-channels')
            ->assertExitCode(0);

        $bot = LinkClick::withBots()->find($bot->id);
        $this->assertSame(ChannelClassifier::KEY_BOT, $bot->channel);
    }

    public function test_date_range_limits_which_rows_are_touched(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $oldStale = LinkClick::create([
            'link_id' => $link->id,
            'user_agent' => '1INMEMobileApp/1.4.2 (ios; expo)',
            'channel' => ChannelClassifier::KEY_GENERIC_WEBVIEW,
            'clicked_at' => '2026-01-15 12:00:00',
        ]);

        $newStale = LinkClick::create([
            'link_id' => $link->id,
            'user_agent' => '1INMEMobileApp/1.4.2 (ios; expo)',
            'channel' => ChannelClassifier::KEY_GENERIC_WEBVIEW,
            'clicked_at' => '2026-04-15 12:00:00',
        ]);

        $this->artisan('link-clicks:reclassify-channels', [
            '--from' => '2026-04-01',
            '--to'   => '2026-04-30',
        ])->assertExitCode(0);

        $oldStale->refresh();
        $newStale->refresh();

        $this->assertSame(ChannelClassifier::KEY_GENERIC_WEBVIEW, $oldStale->channel);
        $this->assertSame(ChannelClassifier::KEY_1INME_APP, $newStale->channel);
    }
}
