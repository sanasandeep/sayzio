<?php

namespace Tests\Feature;

use App\Modules\User\Models\Backlink;
use App\Modules\User\Models\NotificationPreference;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the hourly scheduling rules for `backlinks:send-weekly-digest`:
 * timezone-aware local weekday+hour matching (incl. DST spring-forward
 * and fall-back), the 6-day cooldown, the never-email-empty contract
 * and the per-user `backlink_digest` email preference.
 */
class SendWeeklyBacklinkDigestTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $tz, int $weekday, int $hour): User
    {
        return User::factory()->create([
            'email_verified_at' => now()->subMonth(),
            'timezone' => $tz,
            'backlink_digest_preferred_weekday' => $weekday,
            'backlink_digest_preferred_hour' => $hour,
        ]);
    }

    private function giveBacklink(User $user, ?Carbon $seenAt = null): Backlink
    {
        return Backlink::create([
            'user_id'                => $user->id,
            'page_url'               => 'https://blog.example.com/post-'.Str::random(6),
            'page_host'              => 'blog.example.com',
            'page_title'             => 'A nice mention',
            'anchor_text'            => 'check this out',
            'matched_url'            => 'https://1in.me/'.Str::random(5),
            'matched_property_type'  => 'short_link',
            'matched_property_value' => 'foo',
            'first_seen_at'          => $seenAt ?: now()->subDay(),
        ]);
    }

    /** Snapshot the in-memory array transport so we can count new sends. */
    private function mailBaseline(): int
    {
        return count(Mail::mailer()->getSymfonyTransport()->messages()->all());
    }

    private function mailDelta(int $baseline): int
    {
        return count(Mail::mailer()->getSymfonyTransport()->messages()->all()) - $baseline;
    }

    public function test_sends_to_user_when_local_weekday_and_hour_match_in_their_timezone(): void
    {
        // Friday 17:00 America/Los_Angeles (PST, UTC-8) == Saturday 01:00 UTC.
        // Picked a winter date intentionally to keep this case DST-free.
        Carbon::setTestNow(Carbon::parse('2026-01-10 01:00:00', 'UTC'));

        $user = $this->makeUser('America/Los_Angeles', 5, 17); // Friday 5pm
        $this->giveBacklink($user);

        $before = $this->mailBaseline();
        $this->artisan('backlinks:send-weekly-digest')->assertExitCode(0);
        $this->assertSame(1, $this->mailDelta($before));

        $user->refresh();
        $this->assertNotNull($user->last_backlink_digest_sent_at);
    }

    public function test_skips_user_whose_local_slot_does_not_match(): void
    {
        // Same UTC moment as above — Friday 5pm in LA — but user wants
        // Monday 9am, so they must be skipped.
        Carbon::setTestNow(Carbon::parse('2026-01-10 01:00:00', 'UTC'));

        $user = $this->makeUser('America/Los_Angeles', 1, 9); // Monday 9am
        $this->giveBacklink($user);

        $before = $this->mailBaseline();
        $this->artisan('backlinks:send-weekly-digest')->assertExitCode(0);
        $this->assertSame(0, $this->mailDelta($before));
        $this->assertNull($user->fresh()->last_backlink_digest_sent_at);
    }

    public function test_matches_correct_user_across_multiple_timezones_in_same_run(): void
    {
        // 09:00 UTC. That's 09:00 in London, 10:00 CET, 17:00 in Singapore.
        // Use a Wednesday to dodge weekend edge-cases.
        Carbon::setTestNow(Carbon::parse('2026-01-14 09:00:00', 'UTC'));

        $london    = $this->makeUser('Europe/London',     3, 9);   // Wed 9am   — match
        $berlin    = $this->makeUser('Europe/Berlin',     3, 10);  // Wed 10am  — match
        $singapore = $this->makeUser('Asia/Singapore',    3, 17);  // Wed 5pm   — match
        $miss      = $this->makeUser('Europe/London',     3, 10);  // Wed 10am London — miss

        foreach ([$london, $berlin, $singapore, $miss] as $u) {
            $this->giveBacklink($u);
        }

        $before = $this->mailBaseline();
        $this->artisan('backlinks:send-weekly-digest')->assertExitCode(0);
        $this->assertSame(3, $this->mailDelta($before));

        $this->assertNotNull($london->fresh()->last_backlink_digest_sent_at);
        $this->assertNotNull($berlin->fresh()->last_backlink_digest_sent_at);
        $this->assertNotNull($singapore->fresh()->last_backlink_digest_sent_at);
        $this->assertNull($miss->fresh()->last_backlink_digest_sent_at);
    }

    public function test_handles_dst_spring_forward_in_new_york(): void
    {
        // US spring-forward 2026: Sunday 2026-03-08, 02:00 EST -> 03:00 EDT.
        // After the jump, 09:00 EDT == 13:00 UTC (vs. 14:00 UTC in winter).
        // A user who picked Sunday 9am NY must still receive at the correct
        // local hour on the spring-forward day.
        Carbon::setTestNow(Carbon::parse('2026-03-08 13:00:00', 'UTC'));

        $user = $this->makeUser('America/New_York', 7, 9); // Sunday 9am
        $this->giveBacklink($user);

        $before = $this->mailBaseline();
        $this->artisan('backlinks:send-weekly-digest')->assertExitCode(0);
        $this->assertSame(1, $this->mailDelta($before));
    }

    public function test_does_not_double_match_on_dst_spring_forward(): void
    {
        // Same Sunday — but 14:00 UTC is now 10:00 EDT, not 9am.
        // Without DST handling we'd wrongly match the 9am preference here.
        Carbon::setTestNow(Carbon::parse('2026-03-08 14:00:00', 'UTC'));

        $user = $this->makeUser('America/New_York', 7, 9); // Sunday 9am
        $this->giveBacklink($user);

        $before = $this->mailBaseline();
        $this->artisan('backlinks:send-weekly-digest')->assertExitCode(0);
        $this->assertSame(0, $this->mailDelta($before));
    }

    public function test_handles_dst_fall_back_in_new_york(): void
    {
        // US fall-back 2026: Sunday 2026-11-01, 02:00 EDT -> 01:00 EST,
        // so the wall clock shows 01:00 NY twice. We pick the second 1am
        // (06:00 UTC == 01:00 EST) to confirm the converter still resolves
        // it to "Sunday 1am" for matching purposes.
        Carbon::setTestNow(Carbon::parse('2026-11-01 06:00:00', 'UTC'));

        $user = $this->makeUser('America/New_York', 7, 1); // Sunday 1am
        $this->giveBacklink($user);

        $before = $this->mailBaseline();
        $this->artisan('backlinks:send-weekly-digest')->assertExitCode(0);
        $this->assertSame(1, $this->mailDelta($before));
    }

    public function test_respects_six_day_cooldown(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 01:00:00', 'UTC'));

        $user = $this->makeUser('America/Los_Angeles', 5, 17);
        $this->giveBacklink($user);

        // Sent 5 days ago — strictly within the 6-day cooldown — must skip.
        $user->forceFill([
            'last_backlink_digest_sent_at' => now()->subDays(5),
        ])->save();
        $stamp = $user->last_backlink_digest_sent_at->copy();

        $before = $this->mailBaseline();
        $this->artisan('backlinks:send-weekly-digest')->assertExitCode(0);
        $this->assertSame(0, $this->mailDelta($before));
        $this->assertTrue($stamp->equalTo($user->fresh()->last_backlink_digest_sent_at));
    }

    public function test_sends_again_after_seven_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 01:00:00', 'UTC'));

        $user = $this->makeUser('America/Los_Angeles', 5, 17);
        $this->giveBacklink($user);

        // 7 days ago — strictly older than the 6-day boundary, so the
        // weekly cadence must fire even though a previous digest exists.
        $user->forceFill([
            'last_backlink_digest_sent_at' => now()->subDays(7),
        ])->save();

        $before = $this->mailBaseline();
        $this->artisan('backlinks:send-weekly-digest')->assertExitCode(0);
        $this->assertSame(1, $this->mailDelta($before));

        // Stamp must have advanced to "now".
        $this->assertTrue(now()->equalTo($user->fresh()->last_backlink_digest_sent_at));
    }

    public function test_force_flag_overrides_cooldown(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 01:00:00', 'UTC'));

        $user = $this->makeUser('America/Los_Angeles', 5, 17);
        $this->giveBacklink($user);
        $user->forceFill([
            'last_backlink_digest_sent_at' => now()->subDay(),
        ])->save();

        $before = $this->mailBaseline();
        $this->artisan('backlinks:send-weekly-digest', ['--force' => true])->assertExitCode(0);
        $this->assertSame(1, $this->mailDelta($before));
    }

    public function test_never_sends_empty_digest(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 01:00:00', 'UTC'));

        // User matches the slot perfectly but has only stale (>7d) backlinks.
        $user = $this->makeUser('America/Los_Angeles', 5, 17);
        $this->giveBacklink($user, now()->subDays(30));

        $before = $this->mailBaseline();
        $this->artisan('backlinks:send-weekly-digest')->assertExitCode(0);
        $this->assertSame(0, $this->mailDelta($before));
        $this->assertNull($user->fresh()->last_backlink_digest_sent_at);
    }

    public function test_respects_backlink_digest_email_preference_when_disabled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 01:00:00', 'UTC'));

        $user = $this->makeUser('America/Los_Angeles', 5, 17);
        $this->giveBacklink($user);

        NotificationPreference::create([
            'user_id' => $user->id,
            'type'    => 'backlink_digest',
            'in_app'  => false,
            'email'   => false,
            'push'    => false,
        ]);

        $before = $this->mailBaseline();
        $this->artisan('backlinks:send-weekly-digest')->assertExitCode(0);
        $this->assertSame(0, $this->mailDelta($before));
        $this->assertNull($user->fresh()->last_backlink_digest_sent_at);
    }

    public function test_sends_when_email_preference_is_explicitly_enabled(): void
    {
        // Sanity-check companion to the disabled case: an explicit
        // pref row with email=true must still let the digest through.
        Carbon::setTestNow(Carbon::parse('2026-01-10 01:00:00', 'UTC'));

        $user = $this->makeUser('America/Los_Angeles', 5, 17);
        $this->giveBacklink($user);

        NotificationPreference::create([
            'user_id' => $user->id,
            'type'    => 'backlink_digest',
            'in_app'  => false,
            'email'   => true,
            'push'    => false,
        ]);

        $before = $this->mailBaseline();
        $this->artisan('backlinks:send-weekly-digest')->assertExitCode(0);
        $this->assertSame(1, $this->mailDelta($before));
    }

    public function test_skips_users_without_verified_email(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 01:00:00', 'UTC'));

        $user = $this->makeUser('America/Los_Angeles', 5, 17);
        $user->forceFill(['email_verified_at' => null])->save();
        $this->giveBacklink($user);

        $before = $this->mailBaseline();
        $this->artisan('backlinks:send-weekly-digest')->assertExitCode(0);
        $this->assertSame(0, $this->mailDelta($before));
    }
}
