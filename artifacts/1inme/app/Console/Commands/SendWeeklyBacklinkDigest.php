<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\Backlink;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Hourly job that emails each creator a weekly digest of new backlinks
 * the browser-extension radar found pointing at their short links,
 * biolink username and custom domains in the last 7 days. Without this,
 * fresh mentions only show up when the creator opens the extension
 * popup — many creators don't open the extension every week and the
 * radar's value goes unread.
 *
 * Runs every hour but only sends to users whose preferred local
 * weekday+hour matches the current local weekday+hour in their
 * timezone — so a creator who picked Friday 5pm in America/Los_Angeles
 * receives the digest at 17:00 LA time. DST is honoured naturally
 * because Carbon's setTimezone does the work.
 *
 * - Skipped (no email sent) when the user has zero new backlinks in
 *   the window.
 * - Honours the per-user `backlink_digest` notification preference's
 *   email channel (defaults to on per the catalog).
 * - One-click signed unsubscribe link flips that same email flag off
 *   without requiring a login.
 * - 6-day cooldown via `last_backlink_digest_sent_at` prevents a
 *   double-send within the same week if a user changes their preferred
 *   slot mid-week (or admin re-triggers).
 */
class SendWeeklyBacklinkDigest extends Command
{
    protected $signature = 'backlinks:send-weekly-digest
        {--user= : Optional user id to digest (default: all eligible)}
        {--hour= : Override the "current hour" used for matching (0-23). Defaults to now (UTC).}
        {--any-time : Ignore each user\'s preferred weekday+hour and send to anyone eligible}
        {--force : Send even if the user already received a digest in the last 6 days}';

    protected $description = 'Email each opted-in creator a weekly digest of new backlinks the radar found, honouring their preferred local weekday+hour.';

    public function handle(NotificationService $prefs): int
    {
        $now     = now();
        $runHour = $this->option('hour') !== null
            ? (int) $this->option('hour')
            : (int) $now->copy()->utc()->format('G');
        $anyTime = (bool) $this->option('any-time');
        $force   = (bool) $this->option('force');
        $userId  = $this->option('user');

        $windowStart   = $now->copy()->subDays(7);
        $cooldownStart = $now->copy()->subDays(6);
        // Cooldown chosen at 6 days (not 7) on purpose:
        //   * Next week's matching slot is exactly 7 days after the last
        //     send, which is strictly older than the 6-days-ago boundary,
        //     so a clean weekly cadence always fires (and is robust to
        //     small clock drift between hourly scheduler ticks).
        //   * Any *earlier* slot the user might pick mid-week (e.g.
        //     switching from Mon 9am to Fri 5pm in the same week) is
        //     less than 6 days after the previous send and is therefore
        //     blocked — so changing the preference mid-week never causes
        //     a double-send.

        $userIdsWithBacklinks = Backlink::where('first_seen_at', '>=', $windowStart)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->distinct()
            ->pluck('user_id');

        $sent = 0;
        $skipped = 0;

        User::whereIn('id', $userIdsWithBacklinks)
            ->chunkById(200, function ($users) use (&$sent, &$skipped, $prefs, $now, $runHour, $anyTime, $force, $windowStart, $cooldownStart) {
                foreach ($users as $user) {
                    if (! $user->email || ! $user->email_verified_at) {
                        $skipped++;
                        continue;
                    }

                    if (! $prefs->prefersChannel($user->id, 'backlink_digest', 'email')) {
                        $skipped++;
                        continue;
                    }

                    // Match the user's preferred local weekday+hour against
                    // the local weekday+hour the run timestamp represents
                    // in their timezone. Carbon's setTimezone naturally
                    // handles DST.
                    if (! $anyTime) {
                        $tz = $user->timezone ?: 'UTC';
                        try {
                            $userMoment = $now->copy()->utc()->setTime($runHour, 0)->setTimezone($tz);
                        } catch (\Throwable $e) {
                            $userMoment = $now->copy()->utc()->setTime($runHour, 0);
                        }
                        $localHour    = (int) $userMoment->format('G');
                        // Carbon dayOfWeekIso: 1 = Monday ... 7 = Sunday.
                        $localWeekday = (int) $userMoment->dayOfWeekIso;

                        $preferredHour = (int) ($user->backlink_digest_preferred_hour ?? 9);
                        if ($preferredHour < 0 || $preferredHour > 23) $preferredHour = 9;
                        $preferredWeekday = (int) ($user->backlink_digest_preferred_weekday ?? 1);
                        if ($preferredWeekday < 1 || $preferredWeekday > 7) $preferredWeekday = 1;

                        if ($localHour !== $preferredHour || $localWeekday !== $preferredWeekday) {
                            $skipped++;
                            continue;
                        }
                    }

                    if (! $force
                        && $user->last_backlink_digest_sent_at
                        && $user->last_backlink_digest_sent_at->greaterThanOrEqualTo($cooldownStart)) {
                        $skipped++;
                        continue;
                    }

                    $rows = Backlink::where('user_id', $user->id)
                        ->where('first_seen_at', '>=', $windowStart)
                        ->orderByDesc('first_seen_at')
                        ->get();

                    // Defensive: skip if zero new backlinks for this user
                    // in the window (the prefilter above should already
                    // handle this, but the contract is "never email an
                    // empty digest").
                    if ($rows->isEmpty()) {
                        $skipped++;
                        continue;
                    }

                    if ($this->emailDigest($user, $rows)) {
                        $user->forceFill(['last_backlink_digest_sent_at' => now()])->save();
                        $sent++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->info("Backlink digest run complete. Sent: {$sent}, skipped: {$skipped}.");
        return self::SUCCESS;
    }

    /**
     * Group rows by destination property and send one email per user.
     */
    private function emailDigest(User $user, $rows): bool
    {
        $byProperty = [];
        foreach ($rows as $row) {
            $key = $row->matched_property_type . '|' . ($row->matched_property_value ?? '');
            if (! isset($byProperty[$key])) {
                $byProperty[$key] = [
                    'property_type'  => $row->matched_property_type,
                    'property_label' => $this->propertyLabel($row->matched_property_type),
                    'property_value' => $row->matched_property_value,
                    'matched_url'    => $row->matched_url,
                    'mentions'       => [],
                ];
            }
            $byProperty[$key]['mentions'][] = [
                'page_url'    => $row->page_url,
                'page_host'   => $row->page_host,
                'page_title'  => $row->page_title,
                'anchor_text' => $row->anchor_text,
                'matched_url' => $row->matched_url,
                'first_seen'  => optional($row->first_seen_at)->toDayDateTimeString(),
            ];
        }

        $totalBacklinks = $rows->count();
        $propertyCount  = count($byProperty);

        $unsubscribeUrl = URL::signedRoute(
            'user.notifications.backlink-digest.unsubscribe',
            ['user' => $user->id]
        );

        $subject = "Your weekly backlink digest: {$totalBacklinks} new mention"
            . ($totalBacklinks === 1 ? '' : 's')
            . " across {$propertyCount} propert"
            . ($propertyCount === 1 ? 'y' : 'ies');

        $viewData = [
            'subject'        => $subject,
            'userName'       => $user->name ?: 'there',
            'totalBacklinks' => $totalBacklinks,
            'propertyCount'  => $propertyCount,
            'properties'     => array_values($byProperty),
            'unsubscribeUrl' => $unsubscribeUrl,
        ];

        try {
            Mail::send(
                ['emails.backlink-digest', 'emails.backlink-digest-text'],
                $viewData,
                function ($m) use ($user, $subject, $unsubscribeUrl) {
                    $m->to($user->email)->subject($subject);
                    // RFC 8058 / RFC 2369: mailbox providers (Gmail, Apple)
                    // expose a one-click unsubscribe chip when these
                    // headers are present.
                    $m->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
                    $m->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                }
            );
            return true;
        } catch (\Throwable $e) {
            Log::warning('backlink digest send failed for user ' . $user->id . ': ' . $e->getMessage());
            return false;
        }
    }

    private function propertyLabel(string $type): string
    {
        return match ($type) {
            'short_link'        => 'Short link',
            'biolink_username'  => 'Biolink username',
            'custom_domain'     => 'Custom domain',
            default             => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
