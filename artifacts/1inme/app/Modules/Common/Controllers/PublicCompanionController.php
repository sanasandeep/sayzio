<?php

namespace App\Modules\Common\Controllers;

use App\Modules\User\Models\AiCompanion;
use App\Services\AI\CompanionRuntime;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public, auth-free chat endpoint for AI Companions. Used by:
 *   - The biolink chatbot block (same-origin, no domain check).
 *   - The /embed/companion.js bundle on third-party sites
 *     (origin-allowlisted via AiCompanion::originAllowed()).
 *   - The /embed/companion/{publicId}/iframe fallback (same-origin).
 */
class PublicCompanionController
{
    public function __construct(
        protected CompanionRuntime $runtime,
    ) {}

    /** POST /companion/{publicId}/message — JSON */
    public function message(Request $request, string $publicId)
    {
        $companion = AiCompanion::where('public_id', $publicId)->first();
        if (!$companion) {
            return response()->json(['ok' => false, 'error' => 'Unknown chatbot.'], 404)
                ->withHeaders($this->corsHeaders($request, $companion));
        }
        if ($companion->is_disabled) {
            return response()->json(['ok' => false, 'error' => 'This chatbot is disabled.'], 403)
                ->withHeaders($this->corsHeaders($request, $companion));
        }

        // Origin gate — only enforced for `embed` placement. Biolink &
        // inbox always run from a 1INME-owned origin so we don't want
        // to second-guess them here (the biolink block + inbox UI are
        // the access controls in those flows).
        //
        // The iframe-fallback path posts same-origin (the iframe is
        // served by 1INME) so the browser-supplied Origin header is
        // 1INME, not the embedding site. To keep the allow-list
        // honest in that case we accept an HMAC-signed handshake
        // token (`iframe_token`) that the iframe controller stamps
        // after validating the parent Referer against the allow-list.
        if ($companion->placement === AiCompanion::PLACEMENT_EMBED) {
            $origin = $request->header('Origin');
            $tokenHost = null;
            // Either `session_token` (issued by /session for the
            // launcher embed) or `iframe_token` (issued by the
            // iframe controller) is sufficient — both are HMAC-signed
            // and bound to the companion + a verified origin host.
            $tok = (string) $request->input('session_token', '') ?: (string) $request->input('iframe_token', '');
            if ($tok) {
                $tokenHost = \App\Services\AI\CompanionRuntime::verifyIframeToken($companion, $tok);
            }
            $originOk = $origin && $companion->originAllowed($origin);
            if (!$originOk && !$tokenHost) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'This site is not allowed to embed this chatbot. Add it to the allow-list.',
                ], 403)->withHeaders($this->corsHeaders($request, $companion));
            }
        }

        $data = $request->validate([
            'message'        => 'required|string|max:4000',
            'visitor_token'  => 'nullable|string|max:64',
            'visitor_name'   => 'nullable|string|max:120',
            'visitor_email'  => 'nullable|email|max:200',
        ]);

        $token = trim((string) ($data['visitor_token'] ?? '')) ?: 'anon_' . Str::lower(Str::random(20));

        $result = $this->runtime->turn($companion, $token, $data['message'], [
            'name'   => $data['visitor_name']  ?? null,
            'email'  => $data['visitor_email'] ?? null,
            'ip'     => $request->ip(),
            'ua'     => Str::limit((string) $request->userAgent(), 255, ''),
            'origin' => $request->header('Origin'),
        ]);

        $payload = array_merge($result, ['visitor_token' => $token]);
        $status = ($result['ok'] ?? false) ? 200 : (($result['retry_after'] ?? 0) ? 429 : 422);

        return response()->json($payload, $status)
            ->withHeaders($this->corsHeaders($request, $companion));
    }

    /** OPTIONS /companion/{publicId}/message — CORS preflight */
    public function preflight(Request $request, string $publicId)
    {
        $companion = AiCompanion::where('public_id', $publicId)->first();
        return response('', 204)->withHeaders($this->corsHeaders($request, $companion));
    }

    /**
     * POST /companion/{publicId}/rate — visitor thumbs-up/down on a
     * specific assistant message. Lets the owner spot bad answers
     * without dragging open every conversation.
     */
    public function rate(Request $request, string $publicId)
    {
        $companion = AiCompanion::where('public_id', $publicId)->first();
        if (!$companion) {
            return response()->json(['ok' => false, 'error' => 'Unknown chatbot.'], 404)
                ->withHeaders($this->corsHeaders($request, $companion));
        }

        $data = $request->validate([
            'message_id'    => 'required|integer',
            'rating'        => 'required|integer|min:1|max:5',
            'visitor_token' => 'nullable|string|max:64',
            'flag'          => 'nullable|boolean',
        ]);

        // Ownership scope: only allow rating messages that belong to
        // a conversation under this companion. Prevents a malicious
        // visitor from rating arbitrary messages by id.
        $msg = \App\Modules\Common\Models\AiCompanionMessage::query()
            ->whereKey($data['message_id'])
            ->whereIn('conversation_id',
                \App\Modules\Common\Models\AiCompanionConversation::where('companion_id', $companion->id)->pluck('id'))
            ->first();
        if (!$msg) {
            return response()->json(['ok' => false, 'error' => 'Message not found.'], 404)
                ->withHeaders($this->corsHeaders($request, $companion));
        }
        $msg->forceFill([
            'rating'     => (int) $data['rating'],
            // A 1-star rating with `flag=true` queues the message into
            // the admin moderation queue.
            'is_flagged' => (bool) ($data['flag'] ?? false),
        ])->save();

        return response()->json(['ok' => true])->withHeaders($this->corsHeaders($request, $companion));
    }

    /**
     * POST /companion/{publicId}/session — issues an HMAC-signed
     * session token bound to (companion + verified embedding origin
     * + IP /24 + expiry). The embed JS calls this once on launcher
     * mount, then sends the token alongside every /message POST.
     *
     * The Origin allow-list is therefore enforced *once* (at session
     * mint), preventing curl-style spoofing of `Origin` per request
     * from cheaply burning credits — even if `public_id` leaks.
     */
    public function session(Request $request, string $publicId)
    {
        $companion = AiCompanion::where('public_id', $publicId)->first();
        if (!$companion) {
            return response()->json(['ok' => false, 'error' => 'Unknown chatbot.'], 404);
        }
        if ($companion->placement === AiCompanion::PLACEMENT_EMBED) {
            $origin = (string) $request->header('Origin', '');
            if (!$origin || !$companion->originAllowed($origin)) {
                return response()->json(['ok' => false, 'error' => 'Origin not allowed.'], 403)
                    ->withHeaders($this->corsHeaders($request, $companion));
            }
            $host = parse_url($origin, PHP_URL_HOST) ?: '';
            $token = CompanionRuntime::issueIframeToken($companion, $host, 3600);
            return response()->json(['ok' => true, 'session_token' => $token, 'expires_in' => 3600])
                ->withHeaders($this->corsHeaders($request, $companion));
        }
        // Biolink/inbox don't need a session — embed JS will skip the
        // token if `session_token` is null.
        return response()->json(['ok' => true, 'session_token' => null])
            ->withHeaders($this->corsHeaders($request, $companion));
    }

    /** GET /embed/companion/{publicId}/iframe — fallback chat UI */
    public function iframe(Request $request, string $publicId)
    {
        $companion = AiCompanion::where('public_id', $publicId)->firstOrFail();

        // For embed-placement companions we mint an HMAC-signed token
        // tied to the parent page's host; the iframe's JS sends it on
        // every /message POST. The token is only minted when the
        // parent (Referer) is on the allow-list, so an embedding site
        // that's not allowed gets a chat UI that simply fails 403.
        $iframeToken = null;
        if ($companion->placement === AiCompanion::PLACEMENT_EMBED) {
            $referer = (string) $request->header('Referer', '');
            $host    = $referer ? (parse_url($referer, PHP_URL_HOST) ?: '') : '';
            if ($host && $companion->originAllowed('https://' . $host)) {
                $iframeToken = \App\Services\AI\CompanionRuntime::issueIframeToken($companion, $host);
            }
        }

        return response()
            ->view('public.companion-iframe', [
                'companion'   => $companion,
                'config'      => $companion->effectiveConfig(),
                'postUrl'     => route('public.companion.message', ['publicId' => $publicId]),
                'iframeToken' => $iframeToken,
            ])
            ->header('X-Frame-Options', 'ALLOWALL')
            ->header('Content-Security-Policy', "frame-ancestors *");
    }

    /** GET /embed/companion.js — static bundle (served by controller so we can fingerprint cache headers). */
    public function bundle(Request $request)
    {
        $path = public_path('embed/companion.js');
        if (!is_file($path)) {
            abort(404);
        }
        return response()->file($path, [
            'Content-Type'  => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=600',
        ]);
    }

    protected function corsHeaders(Request $request, ?AiCompanion $companion): array
    {
        $origin = $request->header('Origin');
        $allow = '*';
        if ($companion && $companion->placement === AiCompanion::PLACEMENT_EMBED) {
            $allow = ($origin && $companion->originAllowed($origin)) ? $origin : 'null';
        } elseif ($origin) {
            $allow = $origin;
        }
        return [
            'Access-Control-Allow-Origin'      => $allow,
            'Access-Control-Allow-Methods'     => 'POST, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Content-Type, X-Requested-With, X-CSRF-TOKEN',
            'Access-Control-Allow-Credentials' => 'false',
            'Vary'                             => 'Origin',
        ];
    }
}
