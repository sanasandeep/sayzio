<?php

namespace App\Modules\Common\Controllers;

use App\Modules\Common\Models\ViewerDmAttachment;
use App\Modules\Common\Models\ViewerDmConversation;
use App\Modules\Common\Models\ViewerDmUserBlock;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\User;
use App\Services\Dm\DmAccessPolicy;
use App\Services\Dm\DmDispatcher;
use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Public viewer-side endpoints for the new profile-scoped Direct
 * Messages feature (Task #1210). The biolink-scoped endpoints in
 * ViewerDirectMessageController stay untouched; this controller is
 * the entry point for the Message button on /@handle and for the
 * mobile app's per-creator DM screen.
 */
class ProfileDirectMessageController
{
    /**
     * Resolve the current viewer for both web and mobile.
     *
     *   - Web sessions ride on the cookie-backed ViewerSession.
     *   - The mobile app sends a Sanctum Bearer token (no cookies on
     *     native), so when the session is empty we fall back to the
     *     `sanctum` guard and promote that user into the viewer
     *     session for the rest of the request lifecycle. That keeps
     *     downstream services (DmDispatcher, hosted-checkout return
     *     URLs, follow triggers) identical across surfaces.
     */
    private function currentViewer(): ?User
    {
        $viewer = ViewerSession::user();
        if ($viewer) return $viewer;

        // Only attempt the Sanctum lookup when an Authorization header
        // is actually present. Touching the `sanctum` guard on every
        // anonymous web request both balloons memory (it pulls in the
        // full token + transient-token resolver chain) and pointlessly
        // hits storage for the 99% of profile-DM probes that come from
        // unauthenticated visitors looking at a creator page.
        $req = request();
        if ($req && $req->bearerToken()) {
            try {
                $bearer = auth('sanctum')->user();
                if ($bearer instanceof User) {
                    ViewerSession::login($bearer);
                    return $bearer;
                }
            } catch (\Throwable $_e) {
                // Bearer guard not configured / token rejected — fall
                // through to the unauthenticated branch.
            }
        }
        return null;
    }

    /**
     * GET /viewer/dm/profile/{handle}/access — JSON probe for "can I
     * DM this creator and at what price?" Used by the profile Message
     * button and by the mobile app to render the right CTA.
     */
    public function access(Request $request, string $handle): JsonResponse
    {
        $creator = User::where('handle', $handle)->first()
                ?? User::where('id', (int) $handle)->first();
        if (!$creator) return response()->json(['ok' => false, 'reason' => 'not_found'], 404);

        $viewer = $this->currentViewer();
        $conv   = $viewer ? $this->lookupConv($creator, $viewer) : null;
        $policy = app(DmAccessPolicy::class)->evaluate($creator, $viewer, $conv);

        return response()->json([
            'ok'              => true,
            'creator'         => [
                'id'     => $creator->id,
                'name'   => $creator->name,
                'handle' => $creator->handle,
                'avatar' => \App\Support\PublicStorageUrl::resolve($creator->avatar),
            ],
            'conversation_id' => $conv?->id,
            'policy'          => $policy,
            'limit'           => ViewerDmConversation::VIEWER_INITIAL_LIMIT,
        ]);
    }

    /**
     * GET /viewer/dm/profile/{handle}/thread — JSON thread payload
     * (creates the conversation lazily if the fan is allowed).
     */
    public function thread(Request $request, string $handle): JsonResponse
    {
        $creator = $this->resolveCreator($handle);
        if (!$creator) return response()->json(['ok' => false, 'reason' => 'not_found'], 404);

        $viewer = $this->currentViewer();
        if (!$viewer) return response()->json(['ok' => false, 'reason' => 'login_required'], 401);

        if ((int) $viewer->id === (int) $creator->id) {
            return response()->json(['ok' => false, 'reason' => 'self'], 422);
        }

        $conv = app(DmDispatcher::class)->findOrCreateProfileConversation($creator, $viewer);
        $messages = $conv->messages()->with('attachments')->limit(200)->get();

        // Mark owner-sent messages as read by the viewer (read receipts).
        if ($creator->dm_read_receipts_enabled !== false) {
            app(DmDispatcher::class)->markRead($conv, 'viewer');
        }

        $policy = app(DmAccessPolicy::class)->evaluate($creator, $viewer, $conv);

        return response()->json([
            'ok'              => true,
            'limit'           => ViewerDmConversation::VIEWER_INITIAL_LIMIT,
            'conversation_id' => $conv->id,
            'state'           => [
                'sent'           => $conv->viewer_msg_count,
                'owner_replied'  => (bool) $conv->owner_replied,
                'blocked'        => $conv->isBlocked(),
                'throttled'      => $conv->viewerIsThrottled(),
                'paid'           => (bool) $conv->paid_to_message,
            ],
            'policy'          => $policy,
            'messages'        => $messages->map(fn ($m) => $this->serializeMessage($m, $viewer))->values(),
        ]);
    }

    /**
     * POST /viewer/dm/profile/{handle}/send — viewer sends a message.
     * Returns 402 with a checkout URL when pay-to-message is required.
     */
    public function send(Request $request, string $handle): JsonResponse
    {
        $creator = $this->resolveCreator($handle);
        if (!$creator) return response()->json(['ok' => false, 'reason' => 'not_found'], 404);

        $viewer = $this->currentViewer();
        if (!$viewer) return response()->json(['ok' => false, 'reason' => 'login_required'], 401);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $body = trim((string) $data['body']);
        if ($body === '') return response()->json(['ok' => false, 'reason' => 'empty'], 422);

        $conv   = app(DmDispatcher::class)->findOrCreateProfileConversation($creator, $viewer);
        $policy = app(DmAccessPolicy::class)->evaluate($creator, $viewer, $conv);

        // Pay-to-message: hand back a checkout URL the client should redirect to.
        if (!$policy['can'] && $policy['reason'] === DmAccessPolicy::REASON_PAID_REQUIRED) {
            $r = app(MonetizationCheckout::class)->startDmPayToMessage(
                $viewer, $creator,
                (int) $policy['price_cents'],
                (string) $policy['currency'],
                (int) $conv->id,
                $request->input('return_url'),
            );
            return response()->json([
                'ok'           => false,
                'reason'       => 'paid_required',
                'checkout_url' => $r['url'],
                'price_cents'  => (int) $policy['price_cents'],
                'currency'     => (string) $policy['currency'],
            ], 402);
        }

        if (!$policy['can']) {
            return response()->json([
                'ok'     => false,
                'reason' => $policy['reason'],
                'policy' => $policy,
            ], 403);
        }

        $msg = app(DmDispatcher::class)->send($conv, $viewer, 'viewer', $body);
        $conv->refresh();

        return response()->json([
            'ok'      => true,
            'state'   => [
                'sent'          => $conv->viewer_msg_count,
                'owner_replied' => (bool) $conv->owner_replied,
                'throttled'     => $conv->viewerIsThrottled(),
            ],
            'message' => $this->serializeMessage($msg->load('attachments'), $viewer),
        ]);
    }

    /**
     * POST /viewer/dm/attachments/{attachment}/unlock — start a checkout
     * for a locked DM attachment. Returns the hosted-checkout URL.
     */
    public function unlockAttachment(Request $request, int $attachment): JsonResponse
    {
        $att = ViewerDmAttachment::find($attachment);
        if (!$att) return response()->json(['ok' => false, 'reason' => 'not_found'], 404);

        $viewer = $this->currentViewer();
        if (!$viewer) return response()->json(['ok' => false, 'reason' => 'login_required'], 401);

        // Authorization: the requesting viewer must actually be the
        // recipient of the conversation that owns this attachment.
        // Without this, any logged-in user could enumerate attachment
        // IDs and start a checkout — and on the "already unlocked"
        // branch leak the signed media URL of someone else's DM.
        $conv = ViewerDmConversation::find($att->conversation_id);
        if (!$conv || (int) $conv->viewer_user_id !== (int) $viewer->id) {
            return response()->json(['ok' => false, 'reason' => 'forbidden'], 403);
        }

        if (!$att->isLocked()) return response()->json(['ok' => false, 'reason' => 'not_locked'], 422);
        if ($att->isUnlockedFor($viewer->id)) {
            return response()->json(['ok' => true, 'already' => true, 'url' => $att->url]);
        }

        $creator = User::find($att->owner_user_id);
        if (!$creator) return response()->json(['ok' => false, 'reason' => 'not_found'], 404);

        $r = app(MonetizationCheckout::class)->startDmAttachmentUnlock(
            $viewer, $creator, $att->id,
            (int) $att->lock_price_cents,
            (string) $att->lock_currency,
            $request->input('return_url'),
        );

        return response()->json([
            'ok'           => true,
            'checkout_url' => $r['url'],
            'price_cents'  => (int) $att->lock_price_cents,
            'currency'     => (string) $att->lock_currency,
        ]);
    }

    /**
     * POST /viewer/dm/threads/{conversation}/tip — start a tip checkout
     * inside an existing DM thread. Returns the hosted-checkout URL.
     */
    public function tip(Request $request, int $conversation): JsonResponse
    {
        $conv = ViewerDmConversation::find($conversation);
        if (!$conv) return response()->json(['ok' => false, 'reason' => 'not_found'], 404);

        $viewer = $this->currentViewer();
        if (!$viewer) return response()->json(['ok' => false, 'reason' => 'login_required'], 401);
        if ((int) $conv->viewer_user_id !== (int) $viewer->id) {
            return response()->json(['ok' => false, 'reason' => 'forbidden'], 403);
        }

        $data = $request->validate([
            'amount_cents' => 'required|integer|min:100|max:100000',
            'currency'     => 'nullable|string|size:3',
            'note'         => 'nullable|string|max:280',
        ]);

        $creator = User::find($conv->creator_user_id ?: $conv->owner_user_id);
        if (!$creator) return response()->json(['ok' => false, 'reason' => 'not_found'], 404);

        $r = app(MonetizationCheckout::class)->startTip(
            $viewer, $creator,
            (int) $data['amount_cents'],
            strtoupper($data['currency'] ?? 'USD'),
            null,
            $data['note'] ?? null,
            false,
            URL::previous(),
        );

        return response()->json([
            'ok'           => true,
            'checkout_url' => $r['url'],
        ]);
    }

    protected function resolveCreator(string $handle): ?User
    {
        return User::where('handle', $handle)->first()
            ?? User::where('id', (int) $handle)->first();
    }

    protected function lookupConv(User $creator, User $viewer): ?ViewerDmConversation
    {
        return ViewerDmConversation::query()
            ->where('creator_user_id', $creator->id)
            ->where('viewer_user_id', $viewer->id)
            ->where('source', ViewerDmConversation::SOURCE_PROFILE)
            ->first();
    }

    protected function serializeMessage($m, ?User $viewer): array
    {
        $attachments = $m->attachments->map(function (ViewerDmAttachment $a) use ($viewer) {
            $unlocked = !$a->isLocked() || $a->isUnlockedFor($viewer?->id);
            return [
                'id'               => $a->id,
                'kind'             => $a->kind,
                'thumb_url'        => $unlocked ? ($a->thumb_url ?: $a->url) : ($a->blur_url ?: $a->thumb_url),
                'url'              => $unlocked ? $a->url : null,
                'duration_seconds' => $a->duration_seconds,
                'lock_price_cents' => (int) $a->lock_price_cents,
                'lock_currency'    => $a->lock_currency,
                'is_locked'        => $a->isLocked() && !$unlocked,
            ];
        })->values()->all();

        return [
            'id'          => $m->id,
            'side'        => $m->sender_type,
            'kind'        => $m->kind,
            'body'        => $m->body,
            'tip_id'      => $m->tip_id,
            'attachments' => $attachments,
            'sent_at'     => optional($m->created_at)->toIso8601String(),
            'read_at'     => optional($m->read_at)->toIso8601String(),
            'is_ai'       => (bool) $m->is_ai,
        ];
    }
}
