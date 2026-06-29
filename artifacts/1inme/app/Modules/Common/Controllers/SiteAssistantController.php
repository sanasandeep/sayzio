<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\SiteAssistantConversation;
use App\Modules\Common\Models\SiteAssistantLowBalanceClick;
use App\Modules\Common\Services\QuickContactService;
use App\Services\AI\SiteAssistantRuntime;
use App\Services\AI\SiteAssistantSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public chat endpoints for the site-wide AI assistant widget.
 *
 *   POST /assistant/session   — open / resume a conversation
 *   POST /assistant/message   — send a free-text message
 *   POST /assistant/choice    — submit a button/list/form selection
 *   POST /assistant/handoff   — escalate into the admin Contact Inbox
 *   GET  /assistant/bootstrap — initial config + page hint payload
 */
class SiteAssistantController extends Controller
{
    public function __construct(protected SiteAssistantRuntime $runtime) {}

    public function bootstrap(Request $request)
    {
        $surface = $this->detectSurface($request);
        if (!SiteAssistantSettings::isEnabledFor($surface)) {
            return response()->json(['enabled' => false]);
        }
        $cfg = SiteAssistantSettings::get();
        return response()->json([
            'enabled'           => true,
            'surface'           => $surface,
            'launcher_position' => $cfg['launcher_position'],
            'accent_color'      => $cfg['accent_color'],
            'avatar_url'        => SiteAssistantSettings::avatarUrlFor($cfg),
            'brand_name'        => SiteAssistantSettings::brandNameFor($cfg),
            'greeting'          => $cfg['greeting'],
            'starter_prompts'   => array_values((array) $cfg['starter_prompts']),
            'input_placeholder' => SiteAssistantSettings::inputPlaceholderFor($cfg),
            'send_label'        => SiteAssistantSettings::sendLabelFor($cfg),
            // Localized chrome strings consumed by the widget JS so
            // visitors with non-English Accept-Language headers don't
            // see English copy in the typing indicator, the cut-off
            // banner, the handoff disabled-input note, or the two
            // generic error toasts. Subheading is also rendered
            // server-side in the partial so it's correct before
            // bootstrap arrives.
            'subheading'             => SiteAssistantSettings::subheadingFor($cfg),
            'typing_indicator'       => SiteAssistantSettings::typingIndicatorFor($cfg),
            'handoff_note'           => SiteAssistantSettings::handoffNoteFor($cfg),
            'cutoff_notice'          => SiteAssistantSettings::cutoffNoticeFor($cfg),
            'cutoff_retry_label'     => SiteAssistantSettings::cutoffRetryLabelFor($cfg),
            'error_network'          => SiteAssistantSettings::errorNetworkFor($cfg),
            'error_generic'          => SiteAssistantSettings::errorGenericFor($cfg),
            // The assistant now requires a signed-in account on every
            // surface. The widget shows the localized note + a login CTA
            // (instead of the chat input) whenever auth_required is true.
            'auth_required'          => !$request->user(),
            'auth_required_note'     => SiteAssistantSettings::authRequiredNoteFor($cfg),
            'login_url'              => url('/login'),
            'handoff_enabled'   => (bool) $cfg['handoff_enabled'],
            'templates'         => $this->runtime->listTemplates(),
        ]);
    }

    /**
     * The assistant requires a signed-in account on every surface. This
     * returns the standard JSON rejection (consumed by both front-ends to
     * swap the input for a login CTA) when the caller is anonymous, or
     * null when the caller is authenticated and may proceed.
     */
    protected function authGate(Request $request): ?array
    {
        if ($request->user()) {
            return null;
        }
        $cfg = SiteAssistantSettings::get();
        return [
            'ok'            => false,
            'auth_required' => true,
            'login_url'     => url('/login'),
            'error'         => SiteAssistantSettings::authRequiredNoteFor($cfg),
        ];
    }

    public function session(Request $request)
    {
        $data = $request->validate([
            'visitor_token' => 'nullable|string|max:64',
            'surface'       => 'nullable|in:marketing,app',
            'page'          => 'array',
            'page.route'    => 'nullable|string|max:200',
            'page.path'     => 'nullable|string|max:240',
            'page.title'    => 'nullable|string|max:240',
            'page.url'      => 'nullable|string|max:500',
        ]);
        $surface = $this->detectSurface($request);
        $token = $this->resolveToken($data['visitor_token'] ?? null);
        $user  = $request->user();
        return response()->json($this->runtime->openSession(
            $token, $user, $surface, (array) ($data['page'] ?? []), $this->visitorMeta($request)
        ));
    }

    public function message(Request $request)
    {
        $data = $request->validate([
            'visitor_token' => 'required|string|max:64',
            'surface'       => 'nullable|in:marketing,app',
            'message'       => 'required|string|max:4000',
            'page'          => 'array',
        ]);
        if ($gate = $this->authGate($request)) {
            return response()->json($gate, 401);
        }
        $surface = $this->detectSurface($request);
        return response()->json($this->runtime->turn(
            $data['visitor_token'], $request->user(), $surface,
            (array) ($data['page'] ?? []), $data['message'], [], $this->visitorMeta($request)
        ));
    }

    /**
     * Streamed assistant reply over SSE. Emits `event: token` frames
     * for each delta the model produces, then a final `event: done`
     * frame carrying the persisted assistant message + handoff state.
     * On error, emits `event: error`.
     */
    public function stream(Request $request)
    {
        $data = $request->validate([
            'visitor_token'        => 'required|string|max:64',
            'surface'              => 'nullable|in:marketing,app',
            'message'              => 'required|string|max:4000',
            'page'                 => 'array',
            'retry_of_message_id'  => 'nullable|integer',
        ]);
        if ($gate = $this->authGate($request)) {
            return response()->json($gate, 401);
        }
        $surface = $this->detectSurface($request);
        $user = $request->user();
        $token = $data['visitor_token'];
        $page = (array) ($data['page'] ?? []);
        $message = $data['message'];
        $meta = $this->visitorMeta($request);
        $retryOf = isset($data['retry_of_message_id']) ? (int) $data['retry_of_message_id'] : null;

        $runtime = $this->runtime;
        $response = response()->stream(function () use ($runtime, $token, $user, $surface, $page, $message, $meta, $retryOf) {
            $emit = function (string $event, array $payload) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($payload) . "\n\n";
                @ob_flush();
                @flush();
            };
            try {
                $runtime->turnStream($token, $user, $surface, $page, $message, $meta, $emit, $retryOf);
            } catch (\Throwable $e) {
                report($e);
                $emit('error', ['error' => 'The assistant could not respond right now.']);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
        return $response;
    }

    public function choice(Request $request)
    {
        $data = $request->validate([
            'visitor_token' => 'required|string|max:64',
            'surface'       => 'nullable|in:marketing,app',
            'choice'        => 'required|array',
            'choice.label'  => 'nullable|string|max:240',
            'choice.value'  => 'nullable|string|max:240',
            'choice.template' => 'nullable|string|max:64',
            // Inline-form payload caps: keeps prompt+transcript size
            // bounded and prevents abusive submissions from inflating
            // model spend.
            'choice.values' => 'nullable|array|max:25',
            'choice.values.*' => 'nullable|string|max:1000',
            'page'          => 'array',
        ]);
        if ($gate = $this->authGate($request)) {
            return response()->json($gate, 401);
        }
        $surface = $this->detectSurface($request);
        $label = (string) ($data['choice']['label'] ?? $data['choice']['value'] ?? 'Selected option');
        return response()->json($this->runtime->turn(
            $data['visitor_token'], $request->user(), $surface,
            (array) ($data['page'] ?? []), $label, $data['choice'], $this->visitorMeta($request)
        ));
    }

    /**
     * Records a click on the low-balance CTA shown above the chat input
     * (Top up / See plans). Fires from the widget as a keepalive POST so
     * the navigation isn't blocked. We only record the surface, the
     * target URL, and the conversation/user the click happened in — no
     * payload from the page itself, so this stays cheap and safe.
     */
    public function lowBalanceClick(Request $request)
    {
        $data = $request->validate([
            'visitor_token' => 'nullable|string|max:64',
            'surface'       => 'nullable|in:marketing,app',
            'target_url'    => 'required|string|max:500',
        ]);
        $surface = $this->detectSurface($request);
        $user    = $request->user();

        $conversationId = null;
        $token = trim((string) ($data['visitor_token'] ?? ''));
        if ($token !== '' && preg_match('/^[A-Za-z0-9_\-]{8,64}$/', $token)) {
            $conv = SiteAssistantConversation::query()
                ->where('visitor_token', $token)
                ->orderByDesc('id')
                ->first();
            if ($conv) {
                // Defence in depth: only attribute the click to a
                // conversation if it actually belongs to the same
                // visitor (signed-in user, or anonymous with no user).
                if (!$user || (int) $conv->user_id === (int) $user->id || $conv->user_id === null) {
                    $conversationId = (int) $conv->id;
                }
            }
        }

        SiteAssistantLowBalanceClick::create([
            'conversation_id' => $conversationId,
            'user_id'         => $user?->id,
            'surface'         => $surface,
            'target_url'      => mb_substr((string) $data['target_url'], 0, 500),
            'ip_address'      => $request->ip(),
            'occurred_at'     => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function handoff(Request $request)
    {
        $data = $request->validate([
            'visitor_token' => 'required|string|max:64',
            'surface'       => 'nullable|in:marketing,app',
            'name'          => 'nullable|string|max:120',
            'email'         => 'nullable|email|max:200',
            'channel'       => 'required|in:callback,whatsapp,email',
            'phone'         => 'nullable|string|max:40',
            'message'       => 'nullable|string|max:2000',
            'page'          => 'array',
        ]);
        if ($gate = $this->authGate($request)) {
            return response()->json($gate, 401);
        }
        $user = $request->user();

        // Channel-specific validation (Indian phone for call back, country-
        // coded phone for WhatsApp, valid email for email). For the email
        // channel, fall back to the signed-in user's email when none given.
        $channel = (string) $data['channel'];
        $phone   = $data['phone'] ?? null;
        $email   = trim((string) ($data['email'] ?? $user?->email ?? ''));
        if ($error = QuickContactService::validate($channel, $phone, $email)) {
            return response()->json(['ok' => false, 'error' => $error], 422);
        }

        $surface = $this->detectSurface($request);
        return response()->json($this->runtime->handoff(
            $data['visitor_token'], $user, $surface,
            (array) ($data['page'] ?? []),
            [
                'name'    => $data['name'] ?? $user?->name ?? '',
                'email'   => $email,
                'message' => $data['message'] ?? '',
                'channel' => $channel,
                'phone'   => $phone,
            ],
            $this->visitorMeta($request)
        ));
    }

    /**
     * Standalone multi-channel quick-contact widget (NOT login-gated).
     * Anyone can leave a call back / WhatsApp / email request which lands
     * in the admin Contact Inbox and triggers an admin email.
     */
    public function quickContact(Request $request)
    {
        $data = $request->validate([
            'name'    => 'nullable|string|max:120',
            'email'   => 'nullable|email|max:200',
            'channel' => 'required|in:callback,whatsapp,email',
            'phone'   => 'nullable|string|max:40',
            'message' => 'nullable|string|max:2000',
            // Honeypot: a decoy field a real client leaves empty but blind
            // bots fill in. Kept nullable so an absent/empty value always
            // passes for legitimate web + mobile callers.
            'website' => 'nullable|string|max:200',
        ]);

        // The friendly confirmation we return on success — and also on a
        // silently-dropped submission (honeypot trip / duplicate) so an
        // abuser gets no signal that their attempt was rejected.
        $successMessage = "Thanks! We've got your request and will be in touch soon.";

        // Honeypot tripped: pretend success, persist nothing, notify no one.
        if (trim((string) ($data['website'] ?? '')) !== '') {
            return response()->json(['ok' => true, 'message' => $successMessage]);
        }

        $channel = (string) $data['channel'];
        $phone   = $data['phone'] ?? null;
        $email   = trim((string) ($data['email'] ?? $request->user()?->email ?? ''));
        if ($error = QuickContactService::validate($channel, $phone, $email)) {
            return response()->json(['ok' => false, 'error' => $error], 422);
        }

        $name = trim((string) ($data['name'] ?? $request->user()?->name ?? ''));
        $message = (string) ($data['message'] ?? '');

        // De-duplicate identical bursts from the same caller within a short
        // window (double-taps, retries, scripted floods). The claim is
        // atomic; a losing claim returns the same success copy WITHOUT a
        // second inbox row or admin email. Built from post-validation
        // (normalized) values so cosmetic differences still collapse.
        $fingerprint = QuickContactService::fingerprint([
            'ip'      => $request->ip(),
            'user_id' => $request->user()?->id,
            'channel' => $channel,
            'phone'   => $phone,
            'email'   => $email,
            'message' => $message,
        ]);
        if (!QuickContactService::claimSubmission($fingerprint)) {
            return response()->json(['ok' => true, 'message' => $successMessage]);
        }

        $label = QuickContactService::channelLabel($channel);
        $reach = $channel === 'email' ? $email : (string) $phone;

        $body  = "Quick-contact request from the website.\n\n";
        $body .= 'Preferred contact: ' . $label . "\n";
        $body .= 'Reach them at: ' . ($reach ?: '(none)') . "\n";
        if ($name !== '')  $body .= 'Name: ' . $name . "\n";
        if ($email !== '') $body .= 'Account email: ' . $email . "\n";
        if ($user = $request->user()) $body .= "Signed-in user: {$user->email} (#{$user->id})\n";
        if ($message !== '') $body .= "\nMessage:\n" . $message . "\n";

        QuickContactService::create([
            'name'    => $name,
            'email'   => $email,
            'subject' => 'Quick contact: ' . $label,
            'message' => $body,
            'channel' => $channel,
            'phone'   => $phone,
            'ip'      => $request->ip(),
        ]);

        return response()->json([
            'ok'      => true,
            'message' => $successMessage,
        ]);
    }

    /**
     * The widget mounts itself per layout (marketing vs logged-in app)
     * and sends an explicit `surface` field so backend behavior matches
     * what the visitor actually sees — e.g. a logged-in user browsing a
     * marketing page is `marketing`, not `app`. We trust only the
     * allow-listed values; everything else falls back to auth-state.
     */
    protected function detectSurface(Request $request): string
    {
        // Anonymous visitors are ALWAYS on the marketing surface,
        // regardless of any client-supplied value. This prevents an
        // unauthenticated caller from spoofing `surface=app` to bypass
        // the marketing-disabled toggle and consume billing credits.
        if (!$request->user()) {
            return 'marketing';
        }
        $explicit = (string) $request->input('surface', '');
        if (in_array($explicit, ['marketing', 'app'], true)) {
            return $explicit;
        }
        return 'app';
    }

    protected function resolveToken(?string $supplied): string
    {
        $tok = trim((string) $supplied);
        if ($tok !== '' && preg_match('/^[A-Za-z0-9_\-]{8,64}$/', $tok)) return $tok;
        return 'sa_' . Str::random(28);
    }

    protected function visitorMeta(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'ua' => Str::limit((string) $request->userAgent(), 240, ''),
        ];
    }
}
