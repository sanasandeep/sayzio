<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\Backlink;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Builds and sends the weekly backlink digest for a single user. Shared
 * by the scheduled SendWeeklyBacklinkDigest command and the on-demand
 * "Send sample now" preview button on the notification preferences
 * screen, so the wording, grouping and rendered HTML stay consistent
 * between the scheduled email and the preview.
 *
 * The scheduled cron is `weeklyOn(1, '09:00')` (UTC) — see
 * routes/console.php — so {@see nextScheduledRun()} reflects that.
 */
class BacklinkDigestService
{
    /** Day of week (1 = Monday) the weekly digest fires on. */
    public const SCHEDULED_DOW = 1;

    /** Hour (UTC) the weekly digest fires at. */
    public const SCHEDULED_HOUR = 9;

    /** Length of the digest window in days. */
    public const WINDOW_DAYS = 7;

    /**
     * Build per-property grouped digest data for $user covering the last
     * 7 days of backlinks. Returns null when there is nothing to send.
     *
     * @return array{subject:string, viewData:array, total:int, properties:int}|null
     */
    public function buildDigest(User $user, bool $isSample = false): ?array
    {
        $windowStart = now()->subDays(self::WINDOW_DAYS);

        $rows = Backlink::where('user_id', $user->id)
            ->where('first_seen_at', '>=', $windowStart)
            ->orderByDesc('first_seen_at')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

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

        $subject = ($isSample ? '[Sample] ' : '')
            . "Your weekly backlink digest: {$totalBacklinks} new mention"
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
            'isSample'       => $isSample,
        ];

        return [
            'subject'    => $subject,
            'viewData'   => $viewData,
            'total'      => $totalBacklinks,
            'properties' => $propertyCount,
        ];
    }

    /**
     * Send the weekly digest to $user. Returns true on success. Caller
     * is responsible for stamping `last_backlink_digest_sent_at` on the
     * scheduled path; the on-demand sample path does not stamp so it
     * doesn't push out the next real send.
     */
    public function send(User $user, bool $isSample = false): bool
    {
        $built = $this->buildDigest($user, $isSample);
        if ($built === null) {
            return false;
        }
        return $this->dispatchEmail($user, $built);
    }

    /**
     * Lower-level helper used when the caller has already pre-built the
     * digest (e.g. the scheduled command pre-filters users with rows in
     * the window via a single query and doesn't want to re-query here).
     *
     * @param  array{subject:string, viewData:array}  $built
     */
    public function dispatchEmail(User $user, array $built): bool
    {
        $unsubscribeUrl = $built['viewData']['unsubscribeUrl'] ?? URL::signedRoute(
            'user.notifications.backlink-digest.unsubscribe',
            ['user' => $user->id]
        );

        try {
            Mail::send(
                ['emails.backlink-digest', 'emails.backlink-digest-text'],
                $built['viewData'],
                function ($m) use ($user, $built, $unsubscribeUrl) {
                    $m->to($user->email)->subject($built['subject']);
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

    /**
     * Compute the next scheduled weekly send timestamp (UTC) relative
     * to $from. If the cron slot is still ahead today, returns today;
     * otherwise rolls forward to the next matching weekday.
     */
    public function nextScheduledRun(?Carbon $from = null): Carbon
    {
        $from = ($from ?: now())->copy()->utc();
        $candidate = $from->copy()->setTime(self::SCHEDULED_HOUR, 0, 0);

        $targetDow = self::SCHEDULED_DOW; // 1 = Monday
        $currentDow = (int) $candidate->dayOfWeekIso; // 1..7

        if ($currentDow === $targetDow && $candidate->greaterThan($from)) {
            return $candidate;
        }

        $daysAhead = ($targetDow - $currentDow + 7) % 7;
        if ($daysAhead === 0) {
            $daysAhead = 7;
        }
        return $candidate->addDays($daysAhead);
    }

    public function propertyLabel(string $type): string
    {
        return match ($type) {
            'short_link'        => 'Short link',
            'biolink_username'  => 'Biolink username',
            'custom_domain'     => 'Custom domain',
            default             => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
