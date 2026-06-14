<?php

namespace App\Modules\Common\Controllers;

use App\Modules\Common\Models\ViewerDmConversation;
use App\Modules\Common\Models\ViewerDmMessage;
use App\Modules\Common\Models\ViewerDmUserBlock;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Public, viewer-side endpoints powering the "Direct Message" biolink block.
 * - Requires an authenticated viewer (ViewerSession).
 * - Enforces the first-time anti-spam cap (2 messages until the owner replies).
 * - Honours owner-issued conversation/account blocks.
 */
class ViewerDirectMessageController
{
    /**
     * Resolve a biolink that is currently messageable.
     *
     * Returns the Link if it exists, is active, and has at least one
     * active `direct_message` block. Otherwise returns one of:
     *   ['reason' => 'not_found', 'http' => 404]   (no such biolink / inactive)
     *   ['reason' => 'dm_disabled', 'http' => 403] (link exists but DM block off)
     *
     * Centralising this keeps thread() and send() in lock-step so a
     * creator who toggles DM off is honoured by both endpoints.
     */
    private function resolveMessageableLink(int $linkId)
    {
        $link = Link::where('id', $linkId)->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)->first();
        if (! $link || ! ($link->is_active ?? true)) {
            return ['reason' => 'not_found', 'http' => 404];
        }

        $hasActiveDm = BiolinkBlock::where('link_id', $link->id)
            ->where('type', 'direct_message')
            ->where('is_active', true)
            ->exists();
        if (! $hasActiveDm) {
            return ['reason' => 'dm_disabled', 'http' => 403];
        }

        return $link;
    }

    public function thread(Request $request, int $linkId): JsonResponse
    {
        $viewer = ViewerSession::user();
        if (! $viewer) {
            return response()->json(['ok' => false, 'reason' => 'login_required'], 401);
        }

        $resolved = $this->resolveMessageableLink($linkId);
        if (is_array($resolved)) {
            return response()->json(['ok' => false, 'reason' => $resolved['reason']], $resolved['http']);
        }
        $link = $resolved;

        $conv = ViewerDmConversation::where('link_id', $link->id)
            ->where('viewer_user_id', $viewer->id)
            ->first();

        $messages = $conv
            ? $conv->messages()->limit(200)->get(['id', 'sender_type', 'body', 'created_at'])
            : collect();

        // Mark owner-sent messages as read by the viewer.
        if ($conv && $conv->viewer_unread_count > 0) {
            $conv->messages()->where('sender_type', 'owner')->whereNull('read_at')->update(['read_at' => now()]);
            $conv->viewer_unread_count = 0;
            $conv->save();
        }

        return response()->json([
            'ok'        => true,
            'limit'     => ViewerDmConversation::VIEWER_INITIAL_LIMIT,
            'state'     => [
                'sent'           => $conv?->viewer_msg_count ?? 0,
                'owner_replied'  => (bool) ($conv?->owner_replied ?? false),
                'blocked'        => (bool) ($conv?->isBlocked() ?? false),
                'throttled'      => (bool) ($conv?->viewerIsThrottled() ?? false),
            ],
            'messages'  => $messages->map(fn ($m) => [
                'id'        => $m->id,
                'side'      => $m->sender_type,
                'body'      => $m->body,
                'sent_at'   => optional($m->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    public function send(Request $request, int $linkId): JsonResponse
    {
        $viewer = ViewerSession::user();
        if (! $viewer) {
            return response()->json(['ok' => false, 'reason' => 'login_required'], 401);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $body = trim((string) $data['body']);
        if ($body === '') {
            return response()->json(['ok' => false, 'reason' => 'empty'], 422);
        }

        $resolved = $this->resolveMessageableLink($linkId);
        if (is_array($resolved)) {
            return response()->json(['ok' => false, 'reason' => $resolved['reason']], $resolved['http']);
        }
        $link = $resolved;

        $ownerId = (int) $link->user_id;

        // Cannot DM yourself.
        if ($ownerId === (int) $viewer->id) {
            return response()->json(['ok' => false, 'reason' => 'self'], 422);
        }

        // Account-level block check.
        $accountBlocked = ViewerDmUserBlock::where('owner_user_id', $ownerId)
            ->where('viewer_user_id', $viewer->id)->exists();
        if ($accountBlocked) {
            return response()->json(['ok' => false, 'reason' => 'blocked'], 403);
        }

        $preview = Str::limit(preg_replace('/\s+/', ' ', $body), 220, '…');

        // Race-safe creation: pre-create the conversation outside the
        // locking txn so two concurrent first-message requests do not collide
        // on the unique (link_id, viewer_user_id) index. firstOrCreate
        // tolerates the duplicate; the txn then locks the resulting row.
        try {
            ViewerDmConversation::firstOrCreate(
                ['link_id' => $link->id, 'viewer_user_id' => $viewer->id],
                ['owner_user_id' => $ownerId, 'status' => 'active']
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Race lost: the row exists now — proceed.
        }

        try {
            $result = DB::transaction(function () use ($link, $viewer, $body, $preview) {
                $conv = ViewerDmConversation::where('link_id', $link->id)
                    ->where('viewer_user_id', $viewer->id)
                    ->lockForUpdate()
                    ->first();

                if (! $conv) {
                    return ['error' => 'error', 'http' => 500];
                }

                if ($conv->isBlocked()) {
                    return ['error' => 'blocked', 'http' => 403];
                }

                if ($conv->viewerIsThrottled()) {
                    return ['error' => 'throttled', 'http' => 429];
                }

                ViewerDmMessage::create([
                    'conversation_id' => $conv->id,
                    'sender_type'     => 'viewer',
                    'sender_user_id'  => $viewer->id,
                    'body'            => $body,
                ]);

                $conv->viewer_msg_count    = $conv->viewer_msg_count + 1;
                $conv->owner_unread_count  = $conv->owner_unread_count + 1;
                $conv->last_message_at     = Carbon::now();
                $conv->last_message_preview = $preview;
                $conv->last_sender         = 'viewer';
                $conv->save();

                return ['conv' => $conv];
            });
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'reason' => 'error'], 500);
        }

        if (isset($result['error'])) {
            return response()->json([
                'ok'     => false,
                'reason' => $result['error'],
                'limit'  => ViewerDmConversation::VIEWER_INITIAL_LIMIT,
            ], $result['http']);
        }

        $conv = $result['conv'];

        // ── AI Companion auto-reply ──────────────────────────────────────
        // If this thread has an AI Companion bound (inbox placement),
        // and the companion's `auto_send_inbox` flag is on, draft +
        // post a reply on the owner's behalf. Failures are swallowed
        // — the viewer's send must still succeed.
        if ($conv->auto_reply_companion_id) {
            try {
                /** @var \App\Modules\User\Models\AiCompanion|null $cmp */
                $cmp = \App\Modules\User\Models\AiCompanion::query()
                    ->where('id', $conv->auto_reply_companion_id)
                    ->where('user_id', $ownerId)
                    ->where('placement', \App\Modules\User\Models\AiCompanion::PLACEMENT_INBOX)
                    ->where('is_disabled', false)
                    ->first();
                $cfg = $cmp?->effectiveConfig() ?? [];
                if ($cmp && !empty($cfg['auto_send_inbox'])) {
                    $runtime = app(\App\Services\AI\CompanionRuntime::class);
                    $token = 'inbox_v' . $viewer->id . '_c' . $conv->id;
                    $result = $runtime->turn($cmp, $token, $body, [
                        'name'   => $viewer->name ?? null,
                        'email'  => $viewer->email ?? null,
                        'ip'     => $request->ip(),
                        'ua'     => Str::limit((string) $request->userAgent(), 255, ''),
                        'origin' => 'inbox',
                    ]);
                    if (($result['ok'] ?? false) && !empty($result['answer'])) {
                        $aiBody = (string) $result['answer'];
                        $aiPreview = Str::limit(preg_replace('/\s+/', ' ', $aiBody), 220, '…');
                        DB::transaction(function () use ($conv, $ownerId, $aiBody, $aiPreview) {
                            ViewerDmMessage::create([
                                'conversation_id' => $conv->id,
                                'sender_type'     => 'owner',
                                'sender_user_id'  => $ownerId,
                                'body'            => $aiBody,
                                'is_ai'           => true,
                            ]);
                            $conv->owner_replied        = true;
                            $conv->owner_unread_count   = 0;
                            $conv->last_message_at      = Carbon::now();
                            $conv->last_message_preview = $aiPreview;
                            $conv->last_sender          = 'owner';
                            $conv->save();
                        });
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'ok'    => true,
            'limit' => ViewerDmConversation::VIEWER_INITIAL_LIMIT,
            'state' => [
                'sent'          => $conv->viewer_msg_count,
                'owner_replied' => (bool) $conv->owner_replied,
                'throttled'     => $conv->viewerIsThrottled(),
            ],
        ]);
    }
}
