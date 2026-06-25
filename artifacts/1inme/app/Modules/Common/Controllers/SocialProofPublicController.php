<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\SocialProof;
use App\Modules\User\Models\SocialProofEvent;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Services\InboxForwarder;
use App\Modules\User\Services\SpamChecker;
use App\Services\BuzzImpressionMeter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public endpoints for the SocialProof embed widget.
 * No auth — these are intentionally open and CORS-permissive so any external
 * website can embed the widget script.
 */
class SocialProofPublicController extends Controller
{
    public function loaderJs(Request $request, string $uuid)
    {
        $proof = SocialProof::where('uuid', $uuid)->first();
        if (!$proof || !$proof->is_active) {
            $js = "/* 1inme social-proof: widget disabled or not found */\n";
            return response($js, 200, [
                'Content-Type'                => 'application/javascript; charset=utf-8',
                'Access-Control-Allow-Origin' => '*',
                'Cache-Control'               => 'public, max-age=60',
            ]);
        }

        $configUrl    = url('/sp/' . $uuid . '.json');
        $trackUrl     = url('/sp/' . $uuid . '/track');
        $subscribeUrl = url('/sp/' . $uuid . '/subscribe');
        $runtimeUrl   = url('/js/social-proof-widget.js');

        $js = <<<JS
            (function(){
              if (window.__1inmeSP && window.__1inmeSP.loaded) {
                window.__1inmeSP.boot && window.__1inmeSP.boot({uuid:"$uuid",configUrl:"$configUrl",trackUrl:"$trackUrl",subscribeUrl:"$subscribeUrl"});
                return;
              }
              window.__1inmeSP = window.__1inmeSP || { queue: [] };
              window.__1inmeSP.queue.push({uuid:"$uuid",configUrl:"$configUrl",trackUrl:"$trackUrl",subscribeUrl:"$subscribeUrl"});
              if (window.__1inmeSP.loading) return;
              window.__1inmeSP.loading = true;
              var s = document.createElement('script');
              s.src = "$runtimeUrl";
              s.async = true;
              document.head.appendChild(s);
            })();
            JS;

        return response($js, 200, [
            'Content-Type'                => 'application/javascript; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control'               => 'public, max-age=300',
        ]);
    }

    public function config(Request $request, string $uuid)
    {
        $proof = SocialProof::where('uuid', $uuid)->where('is_active', true)->first();
        if (!$proof) {
            return response()->json(['error' => 'not_found'], 404, $this->corsHeaders());
        }

        $notifications = is_array($proof->notifications) ? $proof->notifications : [];
        // Defensive normalization (in case of older un-normalized JSON)
        $notifications = array_map([SocialProof::class, 'normalizeNotification'], $notifications);
        // Filter inactive notifications + sort
        $notifications = array_values(array_filter($notifications, fn($n) => !empty($n['is_active'])));
        usort($notifications, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        // Per-biolink visitor-count gating (task #1180): when the campaign
        // owner's primary biolink has `biolink.privacy.hide_public_visitor_counts`
        // enabled (or unset — the privacy-first default), strip live-visitor
        // signals from the public config payload AND drop visitor_count
        // notifications from the widget so externally-embedded copies of
        // the widget still honour the toggle. Mirrors the data_get path
        // used in resources/views/common/blocks/social-proof.blade.php.
        $hideLive = $this->ownerHidesPublicVisitorCounts((int) $proof->user_id);
        if ($hideLive) {
            // Mirror the directory-side gating: strip every notification
            // type that surfaces a live visitor / click / conversion count.
            $liveCounterTypes = ['visitor_count', 'conversion_count'];
            $notifications = array_values(array_filter(
                $notifications,
                fn($n) => !in_array($n['type'] ?? '', $liveCounterTypes, true)
            ));
        }

        // Per-plan monthly Buzz impressions allowance (task #2304): once the
        // creator has used up their plan's `max_buzz_impressions` allowance
        // for the current period, stop serving notifications so the widget
        // shows nothing for the rest of the month. Resumes automatically
        // next period. A -1 (or missing) allowance never pauses.
        if (BuzzImpressionMeter::servingPaused($this->ownerOf((int) $proof->user_id))) {
            $notifications = [];
        }

        $payload = [
            'uuid'          => $proof->uuid,
            'name'          => $proof->name,
            'design'        => $proof->design   ?? SocialProof::defaultDesign(),
            'targeting'     => $proof->targeting?? SocialProof::defaultTargeting(),
            'notifications' => $notifications,
            'live_visitors' => $hideLive ? 0 : $this->liveVisitorCountFor($notifications),
        ];

        return response()->json($payload, 200, $this->corsHeaders());
    }

    /**
     * Returns true when the owner's primary (most-clicked active) biolink
     * has `biolink.privacy.hide_public_visitor_counts` enabled. Treats an
     * unset flag as "hidden" to match the privacy-first default already
     * used in social-proof.blade.php. Also returns true when the owner
     * has no active biolink (defensive).
     */
    private function ownerHidesPublicVisitorCounts(int $userId): bool
    {
        $bio = Link::where('user_id', $userId)
            ->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->where('is_active', true)
            ->orderByDesc('total_clicks')
            ->first(['settings']);
        if (!$bio) return true;
        $explicit = data_get($bio->settings, 'biolink.privacy.hide_public_visitor_counts', null);
        return $explicit === null ? true : (bool) $explicit;
    }

    public function track(Request $request, string $uuid)
    {
        $proof = SocialProof::where('uuid', $uuid)->where('is_active', true)->first();
        if (!$proof) {
            return response()->json(['ok' => false], 404, $this->corsHeaders());
        }

        $kind = $request->input('kind');
        if (!in_array($kind, ['impression', 'click', 'conversion'], true)) {
            return response()->json(['ok' => false, 'error' => 'bad_kind'], 422, $this->corsHeaders());
        }

        SocialProofEvent::create([
            'social_proof_id' => $proof->id,
            'notification_id' => substr((string)$request->input('notification_id', ''), 0, 64) ?: null,
            'kind'            => $kind,
            'page_url'        => substr((string)$request->input('page_url', ''), 0, 1000),
            'ip'              => $request->ip(),
            'user_agent'      => substr((string)$request->userAgent(), 0, 500),
            'created_at'      => now(),
        ]);

        $col = match ($kind) { 'impression' => 'impressions', 'click' => 'clicks', 'conversion' => 'conversions' };
        DB::table('social_proofs')->where('id', $proof->id)->increment($col);

        // Meter impressions against the creator's per-plan monthly Buzz
        // allowance (task #2304). Only impressions count toward the cap;
        // the cumulative `impressions` column above never resets, so we
        // keep a separate period-scoped counter for gating.
        if ($kind === 'impression') {
            BuzzImpressionMeter::record((int) $proof->user_id);
        }

        return response()->json(['ok' => true], 200, $this->corsHeaders());
    }

    /** Resolve the campaign owner once, swallowing any lookup failure. */
    private function ownerOf(int $userId): ?User
    {
        return $userId > 0 ? User::find($userId) : null;
    }

    /**
     * Capture an email submitted through a Buzz capture notification
     * (email_signup / exit_offer) and persist it as a Subscriber owned by
     * the campaign owner — the same place biolink email subscribers land.
     *
     * This is intentionally separate from track(): the conversion analytics
     * event still fires from the widget independently, so Buzz stats are
     * unaffected whether or not the capture succeeds.
     */
    public function subscribe(Request $request, string $uuid)
    {
        $proof = SocialProof::where('uuid', $uuid)->where('is_active', true)->first();
        if (!$proof) {
            return response()->json(['ok' => false], 404, $this->corsHeaders());
        }

        $email = trim((string) $request->input('email', ''));
        if ($email === '' || mb_strlen($email) > 200 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['ok' => false, 'error' => 'invalid_email'], 422, $this->corsHeaders());
        }

        // Resolve the originating notification and ensure it's an email-capture
        // type so this endpoint can't be used to stuff arbitrary emails through
        // a non-capture notification.
        $notificationId = substr((string) $request->input('notification_id', ''), 0, 64) ?: null;
        $notification = null;
        foreach ((array) $proof->notifications as $n) {
            if (is_array($n) && ($n['id'] ?? null) === $notificationId) { $notification = $n; break; }
        }
        $notificationType = $notification['type'] ?? null;
        $captureTypes = ['email_signup', 'exit_offer'];
        // Require the notification to resolve to an active capture-type entry.
        // A missing/unknown notification_id or a non-capture type is rejected so
        // this endpoint can't be used to stuff arbitrary emails through other
        // (or non-existent) notifications.
        if ($notification === null || !in_array($notificationType, $captureTypes, true)) {
            return response()->json(['ok' => false, 'error' => 'not_capture'], 422, $this->corsHeaders());
        }
        if (array_key_exists('is_active', $notification) && !$notification['is_active']) {
            return response()->json(['ok' => false, 'error' => 'not_capture'], 422, $this->corsHeaders());
        }

        $ownerId = (int) $proof->user_id;
        $source  = 'Buzz · ' . (trim((string) $proof->name) ?: 'Campaign');

        // Spam heuristics — mirror the biolink subscribe flow. Flagged
        // captures are still stored (visible in the Spam tab) but excluded
        // from forwarding/notifications.
        $spamCheck = app(SpamChecker::class)->check([
            'honeypot' => $request->input('_hp'),
            'ip'       => $request->ip(),
            'text'     => $email,
            'scope'    => 'buzz_subscribe:' . $proof->id,
            'user_id'  => $ownerId,
            'email'    => $email,
        ]);

        // Dedupe per owner + source (campaign) + email so the same visitor
        // isn't stored repeatedly for the same Buzz campaign.
        $existing = Subscriber::withoutGlobalScope('workspace')
            ->where('user_id', $ownerId)
            ->where('type', 'email')
            ->where('source', $source)
            ->where('email', $email)
            ->first();

        if ($existing) {
            if ($existing->status === 'unsubscribed') {
                $existing->update(['status' => 'active', 'unsubscribed_at' => null]);
            }
            return response()->json(['ok' => true, 'deduped' => true], 200, $this->corsHeaders());
        }

        // Public origin: no workspace is bound, so derive it from the campaign
        // (falling back to the owner's default workspace) — otherwise the row
        // lands with NULL workspace_id and is hidden from the owner's list.
        $workspaceId = $proof->workspace_id;
        if (empty($workspaceId)) {
            $workspaceId = optional($proof->user?->accessibleWorkspaces()->first())->id;
        }

        $subscriber = new Subscriber([
            'user_id'       => $ownerId,
            'type'          => 'email',
            'email'         => $email,
            'status'        => 'active',
            'source'        => $source,
            'metadata'      => array_filter([
                'origin'            => 'buzz',
                'social_proof_id'   => $proof->id,
                'social_proof_uuid' => $proof->uuid,
                'campaign'          => $proof->name,
                'notification_id'   => $notificationId,
                'notification_type' => $notificationType,
                'page_url'          => substr((string) $request->input('page_url', ''), 0, 1000) ?: null,
            ], fn ($v) => $v !== null && $v !== ''),
            'subscribed_at' => now(),
            'is_spam'       => $spamCheck['is_spam'],
            'spam_reason'   => $spamCheck['is_spam'] ? $spamCheck['reason'] : null,
        ]);
        // workspace_id is guarded (not fillable) — set it directly before save
        // so the BelongsToWorkspace creating hook leaves it intact.
        $subscriber->workspace_id = $workspaceId;
        $subscriber->save();

        // Fan out to the owner's email/webhook forwarding rules, same as a
        // biolink subscription.
        if (! $spamCheck['is_spam']) {
            try {
                app(InboxForwarder::class)->dispatchForSubscriber($ownerId, $subscriber);
            } catch (\Throwable $e) {
                logger()->warning('Inbox forwarder (buzz subscriber) failed: ' . $e->getMessage());
            }
        }

        return response()->json(['ok' => true], 200, $this->corsHeaders());
    }

    public function preflight(Request $request, string $uuid)
    {
        return response('', 204, $this->corsHeaders());
    }

    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With',
            'Access-Control-Max-Age'       => '600',
        ];
    }

    /**
     * For visitor_count notifications: deterministic per-30s plausible number.
     * If the campaign has any visitor_count notifications, derive the number
     * from the first one's min/max settings.
     */
    private function liveVisitorCountFor(array $notifications): int
    {
        $vc = null;
        foreach ($notifications as $n) {
            if (($n['type'] ?? '') === 'visitor_count') { $vc = $n; break; }
        }
        if (!$vc) return 0;
        $s = $vc['settings'] ?? [];
        $min = max(0, (int)($s['min'] ?? 5));
        $max = max($min, (int)($s['max'] ?? $min + 10));
        if ($max === $min) return $min;
        $bucket = (int) floor(time() / 30);
        return $min + ($bucket % ($max - $min + 1));
    }
}
