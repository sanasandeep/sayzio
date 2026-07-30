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
    /** Bump on every public/js/social-proof-widget.js change (cache-buster). */
    public const WIDGET_VERSION = 3;

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
        $submitUrl    = url('/sp/' . $uuid . '/submit');
        // Cache-busting version — bump when public/js/social-proof-widget.js changes.
        $runtimeUrl   = url('/js/social-proof-widget.js') . '?v=' . self::WIDGET_VERSION;

        $js = <<<JS
            (function(){
              if (window.__1inmeSP && window.__1inmeSP.loaded) {
                window.__1inmeSP.boot && window.__1inmeSP.boot({uuid:"$uuid",configUrl:"$configUrl",trackUrl:"$trackUrl",subscribeUrl:"$subscribeUrl",submitUrl:"$submitUrl"});
                return;
              }
              window.__1inmeSP = window.__1inmeSP || { queue: [] };
              window.__1inmeSP.queue.push({uuid:"$uuid",configUrl:"$configUrl",trackUrl:"$trackUrl",subscribeUrl:"$subscribeUrl",submitUrl:"$submitUrl"});
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
            // Notifications are normalized above, so legacy visitor_count /
            // conversion_count entries already surface as 'counter'; the
            // legacy keys are kept defensively.
            $liveCounterTypes = ['counter', 'visitor_count', 'conversion_count'];
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
        // Raw stored notifications may still carry legacy type keys; the
        // consolidated 'capture_prompt' covers both looks going forward.
        $captureTypes = ['capture_prompt', 'email_signup', 'exit_offer'];
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

    /**
     * Generic submission endpoint for collector/feedback notification types
     * (task #6179). Stores a row in the shared social_proof_submissions
     * store; when the payload carries a valid email on an email-capture
     * type, the visitor is also mirrored into the owner's Subscribers list.
     */
    public function submit(Request $request, string $uuid)
    {
        $proof = SocialProof::where('uuid', $uuid)->where('is_active', true)->first();
        if (!$proof) {
            return response()->json(['ok' => false], 404, $this->corsHeaders());
        }

        // Resolve the originating notification and require it to be an active
        // submission-capable type so this endpoint can't be used to stuff
        // arbitrary data through non-collector notifications.
        $notificationId = substr((string) $request->input('notification_id', ''), 0, 64) ?: null;
        $notification = null;
        foreach ((array) $proof->notifications as $n) {
            if (is_array($n) && ($n['id'] ?? null) === $notificationId) { $notification = $n; break; }
        }
        $type = $notification['type'] ?? null;
        if ($notification === null || !in_array($type, SocialProof::SUBMISSION_TYPES, true)) {
            return response()->json(['ok' => false, 'error' => 'not_collector'], 422, $this->corsHeaders());
        }
        if (array_key_exists('is_active', $notification) && !$notification['is_active']) {
            return response()->json(['ok' => false, 'error' => 'not_collector'], 422, $this->corsHeaders());
        }

        $name    = mb_substr(trim((string) $request->input('name', '')), 0, 200);
        $email   = mb_substr(trim((string) $request->input('email', '')), 0, 200);
        $phone   = mb_substr(trim((string) $request->input('phone', '')), 0, 40);
        $message = mb_substr(trim((string) $request->input('message', '')), 0, 5000);
        $answer  = mb_substr(trim((string) $request->input('answer', '')), 0, 300);
        $rating  = $request->filled('rating') ? (int) $request->input('rating') : null;
        if ($rating !== null) $rating = max(-10, min(10, $rating));

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['ok' => false, 'error' => 'invalid_email'], 422, $this->corsHeaders());
        }
        // Require at least one captured value.
        if ($name === '' && $email === '' && $phone === '' && $message === '' && $answer === '' && $rating === null) {
            return response()->json(['ok' => false, 'error' => 'empty'], 422, $this->corsHeaders());
        }

        // Spam heuristics — same checker as the subscribe flow. Flagged rows
        // are stored but marked.
        $spamCheck = app(SpamChecker::class)->check([
            'honeypot' => $request->input('_hp'),
            'ip'       => $request->ip(),
            'text'     => trim($email . ' ' . $message . ' ' . $answer),
            'scope'    => 'buzz_submit:' . $proof->id,
            'user_id'  => (int) $proof->user_id,
            'email'    => $email ?: null,
        ]);

        \App\Modules\User\Models\SocialProofSubmission::create([
            'social_proof_id' => $proof->id,
            'notification_id' => $notificationId,
            'type'            => $type,
            'name'            => $name ?: null,
            'email'           => $email ?: null,
            'phone'           => $phone ?: null,
            'message'         => $message ?: null,
            'answer'          => $answer ?: null,
            'rating'          => $rating,
            'page_url'        => substr((string) $request->input('page_url', ''), 0, 1000) ?: null,
            'ip'              => $request->ip(),
            'is_spam'         => (bool) $spamCheck['is_spam'],
        ]);

        // Mirror email captures into the owner's Subscribers list, reusing the
        // exact storage semantics of subscribe(). Best-effort: a subscriber
        // failure must not lose the submission.
        if ($email !== '' && in_array($type, SocialProof::EMAIL_CAPTURE_TYPES, true)) {
            try {
                $this->storeBuzzSubscriber($proof, $email, $notificationId, $type, (string) $request->input('page_url', ''), $spamCheck);
            } catch (\Throwable $e) {
                logger()->warning('Buzz submit → subscriber mirror failed: ' . $e->getMessage());
            }
        }

        return response()->json(['ok' => true], 200, $this->corsHeaders());
    }

    /** Store an email capture as a Subscriber (shared by subscribe + submit). */
    private function storeBuzzSubscriber(SocialProof $proof, string $email, ?string $notificationId, ?string $notificationType, string $pageUrl, array $spamCheck): void
    {
        $ownerId = (int) $proof->user_id;
        $source  = 'Buzz · ' . (trim((string) $proof->name) ?: 'Campaign');

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
            return;
        }

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
                'page_url'          => substr($pageUrl, 0, 1000) ?: null,
            ], fn ($v) => $v !== null && $v !== ''),
            'subscribed_at' => now(),
            'is_spam'       => $spamCheck['is_spam'],
            'spam_reason'   => $spamCheck['is_spam'] ? ($spamCheck['reason'] ?? null) : null,
        ]);
        $subscriber->workspace_id = $workspaceId;
        $subscriber->save();

        if (! $spamCheck['is_spam']) {
            try {
                app(InboxForwarder::class)->dispatchForSubscriber($ownerId, $subscriber);
            } catch (\Throwable $e) {
                logger()->warning('Inbox forwarder (buzz submission) failed: ' . $e->getMessage());
            }
        }
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
     * For live-visitor counter notifications: deterministic per-30s plausible
     * number. If the campaign has any counter notifications in live_visitors
     * mode (or legacy visitor_count entries), derive the number from the
     * first one's min/max settings.
     */
    private function liveVisitorCountFor(array $notifications): int
    {
        $vc = null;
        foreach ($notifications as $n) {
            $type = $n['type'] ?? '';
            $isLive = $type === 'visitor_count'
                || ($type === 'counter' && (($n['settings']['mode'] ?? 'live_visitors') !== 'conversions'));
            if ($isLive) { $vc = $n; break; }
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
