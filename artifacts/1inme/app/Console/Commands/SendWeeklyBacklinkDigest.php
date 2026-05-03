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
 * Weekly job that emails each creator a digest of new backlinks the
 * browser-extension radar found pointing at their short links, biolink
 * username and custom domains in the last 7 days. Without this, fresh
 * mentions only show up when the creator opens the extension popup —
 * many creators don't open the extension every week and the radar's
 * value goes unread.
 *
 * - Skipped (no email sent) when the user has zero new backlinks in
 *   the window.
 * - Honours the per-user `backlink_digest` notification preference's
 *   email channel (defaults to on per the catalog).
 * - One-click signed unsubscribe link flips that same email flag off
 *   without requiring a login.
 * - Idempotent within a 7-day window via `last_backlink_digest_sent_at`,
 *   so re-running the command in the same week is a no-op.
 */
class SendWeeklyBacklinkDigest extends Command
{
    protected $signature = 'backlinks:send-weekly-digest
        {--user= : Optional user id to digest (default: all eligible)}
        {--force : Send even if the user already received a digest in the last 7 days}';

    protected $description = 'Email each opted-in creator a weekly digest of new backlinks the radar found.';

    public function handle(NotificationService $prefs): int
    {
        $force  = (bool) $this->option('force');
        $userId = $this->option('user');

        $windowStart  = now()->subDays(7);
        $cooldownStart = now()->subDays(7);

        $userIdsWithBacklinks = Backlink::where('first_seen_at', '>=', $windowStart)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->distinct()
            ->pluck('user_id');

        $sent = 0;
        $skipped = 0;

        User::whereIn('id', $userIdsWithBacklinks)
            ->chunkById(200, function ($users) use (&$sent, &$skipped, $prefs, $windowStart, $cooldownStart, $force) {
                foreach ($users as $user) {
                    if (! $user->email || ! $user->email_verified_at) {
                        $skipped++;
                        continue;
                    }

                    // Respect per-user email preference for this digest type.
                    if (! $prefs->prefersChannel($user->id, 'backlink_digest', 'email')) {
                        $skipped++;
                        continue;
                    }

                    // Cooldown: don't double-send if the user already got one
                    // this week (e.g. scheduler re-ran or admin re-triggered).
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
