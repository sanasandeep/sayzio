<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Plan;
use App\Modules\Api\Support\SessionTokenIssuer;
use App\Modules\Common\Models\SiteAssistantConversation;
use App\Modules\Common\Models\SiteAssistantLowBalanceClick;
use App\Modules\Common\Services\OtpService;
use App\Modules\Common\Services\QuickContactService;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\User\Controllers\AcceptInviteController;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\User\Services\TwoFactorPolicy;
use App\Services\AI\SiteAssistantRuntime;
use App\Services\AI\SiteAssistantSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

    /** Memoized resolved viewer, or null when fully unresolved. */
    protected ?User $resolvedUser = null;
    protected bool $userResolved = false;

    /**
     * Resolve the current viewer across BOTH front-ends: the same-origin
     * blade widget authenticates via the web session, while the cross-origin
     * marketing widget sends a Sanctum bearer token (no cookies). Resolving
     * both here means a visitor who just logged in *inside the chat* (session
     * on blade, bearer token on marketing) unlocks the chat in place without
     * a reload.
     */
    protected function currentUser(Request $request): ?User
    {
        if ($this->userResolved) {
            return $this->resolvedUser;
        }
        $this->userResolved = true;
        $this->resolvedUser = $request->user() ?: $request->user('sanctum');
        return $this->resolvedUser;
    }

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
            'auth_required'          => !$this->currentUser($request),
            'auth_required_note'     => SiteAssistantSettings::authRequiredNoteFor($cfg),
            'login_url'              => url('/login'),
            // Drives the in-chat passwordless login form: which OTP methods
            // the front-ends may offer. When BOTH are off the widgets fall
            // back to the full-page login CTA.
            'email_otp_enabled'      => AuthMethods::emailOtpEnabled(),
            'mobile_login_enabled'   => AuthMethods::mobileLoginEnabled(),
            'registration_paused'    => AuthMethods::registrationPaused(),
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
        if ($this->currentUser($request)) {
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
        $user  = $this->currentUser($request);
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
            // Optional page screenshot (data URL) for the vision tier.
            // ~1.5MB decoded ≈ 2MB base64; the runtime enforces the
            // decoded-size cap + mime allow-list + plan gating.
            'screenshot'    => 'nullable|string|max:2200000',
        ]);
        if ($gate = $this->authGate($request)) {
            return response()->json($gate, 401);
        }
        $surface = $this->detectSurface($request);
        return response()->json($this->runtime->turn(
            $data['visitor_token'], $this->currentUser($request), $surface,
            (array) ($data['page'] ?? []), $data['message'], [], $this->visitorMeta($request),
            isset($data['screenshot']) ? (string) $data['screenshot'] : null
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
            // See message(): optional vision-tier page screenshot.
            'screenshot'           => 'nullable|string|max:2200000',
        ]);
        if ($gate = $this->authGate($request)) {
            return response()->json($gate, 401);
        }
        $surface = $this->detectSurface($request);
        $user = $this->currentUser($request);
        $token = $data['visitor_token'];
        $page = (array) ($data['page'] ?? []);
        $message = $data['message'];
        $meta = $this->visitorMeta($request);
        $retryOf = isset($data['retry_of_message_id']) ? (int) $data['retry_of_message_id'] : null;
        $screenshot = isset($data['screenshot']) ? (string) $data['screenshot'] : null;

        $runtime = $this->runtime;
        $response = response()->stream(function () use ($runtime, $token, $user, $surface, $page, $message, $meta, $retryOf, $screenshot) {
            $emit = function (string $event, array $payload) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($payload) . "\n\n";
                @ob_flush();
                @flush();
            };
            try {
                $runtime->turnStream($token, $user, $surface, $page, $message, $meta, $emit, $retryOf, $screenshot);
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
            $data['visitor_token'], $this->currentUser($request), $surface,
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
        $user    = $this->currentUser($request);

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
        $user = $this->currentUser($request);

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
            // Time-trap: ms elapsed between the widget form opening and submit,
            // as measured on the client (a same-clock delta, immune to clock
            // skew). A human never clears the form in under MIN_FILL_MS; a
            // sub-floor value quarantines the lead. Nullable + bounded so older
            // clients that omit it (and odd timers) are never penalized.
            'elapsed_ms' => 'nullable|integer|min:0|max:86400000',
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
        $email   = trim((string) ($data['email'] ?? $this->currentUser($request)?->email ?? ''));
        if ($error = QuickContactService::validate($channel, $phone, $email)) {
            return response()->json(['ok' => false, 'error' => $error], 422);
        }

        $name = trim((string) ($data['name'] ?? $this->currentUser($request)?->name ?? ''));
        $message = (string) ($data['message'] ?? '');

        // De-duplicate identical bursts from the same caller within a short
        // window (double-taps, retries, scripted floods). The claim is
        // atomic; a losing claim returns the same success copy WITHOUT a
        // second inbox row or admin email. Built from post-validation
        // (normalized) values so cosmetic differences still collapse.
        $fingerprint = QuickContactService::fingerprint([
            'ip'      => $request->ip(),
            'user_id' => $this->currentUser($request)?->id,
            'channel' => $channel,
            'phone'   => $phone,
            'email'   => $email,
            'message' => $message,
        ]);
        if (!QuickContactService::claimSubmission($fingerprint)) {
            return response()->json(['ok' => true, 'message' => $successMessage]);
        }

        // Spam guard for distributed bots that rotate IPs + vary the message
        // (so the per-caller dedupe can't catch them). A clearly spammy body
        // (links / known spam patterns), an implausible global submission
        // burst, OR a form filled+posted faster than a human plausibly could
        // (the time-trap) quarantines the lead: it is still persisted for
        // review but is kept out of the default inbox and never emails the
        // admin. Real single leads from new visitors trip none of these
        // checks, so they flow through normally.
        $isBurst = QuickContactService::registerSubmissionAndDetectBurst();
        $tooFast = QuickContactService::tooFastSubmission($data['elapsed_ms'] ?? null);
        $quarantine = $isBurst || $tooFast || QuickContactService::looksSpammy($message);
        $status = $quarantine ? QuickContactService::SPAM_STATUS : 'new';

        $label = QuickContactService::channelLabel($channel);
        $reach = $channel === 'email' ? $email : (string) $phone;

        $body  = "Quick-contact request from the website.\n\n";
        $body .= 'Preferred contact: ' . $label . "\n";
        $body .= 'Reach them at: ' . ($reach ?: '(none)') . "\n";
        if ($name !== '')  $body .= 'Name: ' . $name . "\n";
        if ($email !== '') $body .= 'Account email: ' . $email . "\n";
        if ($user = $this->currentUser($request)) $body .= "Signed-in user: {$user->email} (#{$user->id})\n";
        if ($message !== '') $body .= "\nMessage:\n" . $message . "\n";

        QuickContactService::create([
            'name'    => $name,
            'email'   => $email,
            'subject' => 'Quick contact: ' . $label,
            'message' => $body,
            'channel' => $channel,
            'phone'   => $phone,
            'ip'      => $request->ip(),
            'status'  => $status,
        ]);

        return response()->json([
            'ok'      => true,
            'message' => $successMessage,
        ]);
    }

    /**
     * In-chat passwordless login/signup — step 1: issue an OTP.
     *
     * Login == signup (no password): the same code works whether or not an
     * account already exists; verifyCode() creates the account on first
     * successful entry. We always return a generic success so the response
     * never reveals whether the identifier already had an account
     * (enumeration guard), and apply the same honeypot + time-trap defences
     * as the quick-contact form. Works for BOTH front-ends: the route lives
     * under the already-CORS-/CSRF-exempt `assistant/*` group.
     */
    public function sendCode(Request $request, OtpService $otp)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:190'],
            'type'       => ['required', Rule::in(['email', 'mobile'])],
            // Honeypot: decoy field a human never fills. Nullable so absent
            // values always pass for legitimate callers.
            'website'    => ['nullable', 'string', 'max:200'],
            // Time-trap: ms between the form opening and submit (same-clock
            // delta, immune to skew). A sub-floor value is a bot signal.
            'elapsed_ms' => ['nullable', 'integer', 'min:0', 'max:86400000'],
        ]);

        // Generic confirmation returned on success AND on a silently-dropped
        // submission (honeypot / time-trap) so a bot gets no signal.
        $generic = [
            'ok'      => true,
            'sent'    => true,
            'message' => 'If that account can receive a code, we just sent one.',
        ];

        // Honeypot tripped or implausibly-fast submit: pretend success,
        // issue nothing, create nothing.
        if (trim((string) ($data['website'] ?? '')) !== ''
            || QuickContactService::tooFastSubmission($data['elapsed_ms'] ?? null)) {
            return response()->json($generic);
        }

        // Enforce the email-only-by-default / allowed-country-code policy.
        if ($denied = $this->guardAuthMethod($data['type'], $data['identifier'])) {
            return $denied;
        }

        $user = $this->resolveAuthUser($data['identifier'], $data['type']);

        // The OTP path doubles as sign-up. When registrations are paused and
        // no account exists, issue no code and create nothing.
        if (!$user && AuthMethods::registrationPaused()) {
            return response()->json([
                'ok'    => false,
                'error' => AuthMethods::registrationPausedMessage(),
                'code'  => AuthMethods::ERROR_REGISTRATION_PAUSED,
            ], 403);
        }

        // Always issue + try to send a code (login == signup). A code keyed
        // to the identifier verifies whether or not the account exists yet.
        $code = $otp->generate($data['identifier'], $data['type'], 'login', 'web', $request->ip());
        try {
            $data['type'] === 'email'
                ? $otp->sendEmail($data['identifier'], $code)
                : $otp->sendWhatsApp($data['identifier'], $code);
        } catch (\Throwable $e) {
            Log::warning('Assistant OTP send failed: ' . $e->getMessage());
        }

        return response()->json($generic + [
            'demo_reveal' => AuthMethods::demoRevealMessage($code),
        ]);
    }

    /**
     * In-chat passwordless login/signup — step 2: verify the OTP and sign
     * in (creating the account first if this is a sign-up). On the blade
     * widget we establish a normal web session in place; on the marketing
     * widget (no cookies) we mint a Sanctum bearer token it sends on every
     * subsequent /assistant/* call. Two-factor accounts are bounced to the
     * full login page (2FA in chat is out of scope).
     */
    public function verifyCode(Request $request, OtpService $otp)
    {
        $data = $request->validate([
            'identifier'  => ['required', 'string', 'max:190'],
            'type'        => ['required', Rule::in(['email', 'mobile'])],
            'code'        => ['required', 'string', 'size:6'],
            // Marketing (cross-origin) widget asks for a bearer token; the
            // blade widget omits this and gets a session login instead.
            'issue_token' => ['nullable', 'boolean'],
            'device'      => ['nullable', 'string', 'max:60'],
            'website'     => ['nullable', 'string', 'max:200'],
            'elapsed_ms'  => ['nullable', 'integer', 'min:0', 'max:86400000'],
        ]);

        $invalid = ['ok' => false, 'error' => 'Invalid or expired code.', 'code' => 'invalid_otp'];

        // Honeypot / time-trap: a real login can't be faked here, so reject
        // generically (same shape as a bad code).
        if (trim((string) ($data['website'] ?? '')) !== ''
            || QuickContactService::tooFastSubmission($data['elapsed_ms'] ?? null)) {
            return response()->json($invalid, 422);
        }

        if ($denied = $this->guardAuthMethod($data['type'], $data['identifier'])) {
            return $denied;
        }

        if (!$otp->verify($data['identifier'], $data['code'], $data['type'], 'login', 'web')) {
            return response()->json($invalid, 422);
        }

        $user = $this->resolveAuthUser($data['identifier'], $data['type']);

        // Login == signup: first successful verification of an unknown
        // identifier creates the account (unless registrations are paused).
        if (!$user) {
            if (AuthMethods::registrationPaused()) {
                return response()->json([
                    'ok'    => false,
                    'error' => AuthMethods::registrationPausedMessage(),
                    'code'  => AuthMethods::ERROR_REGISTRATION_PAUSED,
                ], 403);
            }
            $user = $this->createAccountFromIdentifier($data['identifier'], $data['type']);
        }

        // Admin holds can never sign in (auto-lifts expired holds).
        if ($msg = $this->suspensionMessage($user)) {
            return response()->json(['ok' => false, 'error' => $msg, 'code' => 'account_suspended'], 403);
        }

        // Two-factor is out of scope for in-chat login: bounce enrolled
        // accounts to the full login page rather than bypassing the factor.
        if (app(TwoFactorPolicy::class)->userHasEnrolledTotp($user)) {
            return response()->json([
                'ok'        => false,
                'twofactor' => true,
                'login_url' => url('/login'),
                'error'     => 'Please finish signing in on the login page to complete two-factor verification.',
            ], 409);
        }

        $user->ensureDefaultWorkspace();

        // Cross-origin marketing widget: hand back a bearer token (no
        // cookies cross the origin boundary).
        if ($request->boolean('issue_token')) {
            $newToken = SessionTokenIssuer::issue(
                $user, $request, $data['device'] ?? null, 'web', 'web'
            );
            \App\Jobs\RecordLoginEventJob::dispatch(
                $user->id,
                'assistant_otp_' . $data['type'],
                (string) ($request->ip() ?? ''),
                (string) ($request->userAgent() ?? ''),
                ['personal_access_token_id' => $newToken->accessToken->id ?? null],
                true,
                now(),
            );
            return response()->json(['ok' => true, 'token' => $newToken->plainTextToken]);
        }

        // Same-origin blade widget: establish a normal web session in place.
        Auth::login($user, true);
        $request->session()->regenerate();
        \App\Jobs\RecordLoginEventJob::dispatch(
            $user->id,
            'assistant_otp_' . $data['type'],
            (string) ($request->ip() ?? ''),
            (string) ($request->userAgent() ?? ''),
            ['session_id' => $request->session()->getId()],
            true,
            now(),
        );
        AcceptInviteController::attachPendingInvite($user);

        return response()->json(['ok' => true]);
    }

    /**
     * Email-only-by-default / allowed-country-code policy. Returns a JSON
     * error response when the chosen method isn't permitted; null otherwise.
     */
    protected function guardAuthMethod(string $type, string $identifier)
    {
        if ($type === 'email') {
            if (!AuthMethods::emailOtpEnabled()) {
                return response()->json([
                    'ok' => false, 'error' => 'Email code login is not available right now.',
                    'code' => 'email_otp_disabled',
                ], 422);
            }
            return null;
        }
        // mobile
        if (!AuthMethods::mobileLoginEnabled()) {
            return response()->json([
                'ok' => false, 'error' => 'Mobile login is not available. Use your email instead.',
                'code' => 'mobile_login_disabled',
            ], 422);
        }
        if (!AuthMethods::isAllowedMobile($identifier)) {
            return response()->json([
                'ok' => false,
                'error' => "That country code isn't supported. Allowed codes: " . AuthMethods::allowedCountryCodesLabel() . '.',
                'code' => 'country_code_not_allowed',
            ], 422);
        }
        return null;
    }

    /**
     * Resolve any verified linked identifier (email/phone) to its owning
     * user, falling back to the legacy users.email / users.mobile columns.
     */
    protected function resolveAuthUser(string $identifier, string $type): ?User
    {
        $kind = $type === 'mobile' ? 'phone' : 'email';
        if ($u = LinkedIdentifier::resolveUser($kind, $identifier)) {
            return $u;
        }
        return $type === 'email'
            ? User::where('email', strtolower($identifier))->first()
            : User::where('mobile', $identifier)->first();
    }

    /**
     * Create a brand-new account from a verified identifier (login==signup).
     * Mirrors the JSON OTP register path: a random password, the default
     * plan, an active status, and a default workspace. The display name is
     * derived from the email local-part when available.
     */
    protected function createAccountFromIdentifier(string $identifier, string $type): User
    {
        $name = 'Member';
        if ($type === 'email') {
            $derived = trim(Str::title(str_replace(['.', '_', '-', '+'], ' ', Str::before($identifier, '@'))));
            if ($derived !== '') {
                $name = $derived;
            }
        }

        $user = User::create([
            'name'     => $name,
            'email'    => $type === 'email'  ? strtolower($identifier) : null,
            'mobile'   => $type === 'mobile' ? $identifier : null,
            'password' => Hash::make(Str::random(48)),
            'plan_id'  => Plan::defaultPlan()?->id,
            'status'   => 'active',
        ]);
        if (method_exists($user, 'ensureDefaultWorkspace')) {
            $user->ensureDefaultWorkspace();
        }
        return $user;
    }

    /**
     * Login gate for admin temporary holds. Returns a user-facing message
     * when the account is suspended, or null when it may sign in. Holds whose
     * `reactivate_at` has already passed are auto-lifted here (mirrors the
     * web AuthController) so a user isn't locked out past their date.
     */
    protected function suspensionMessage(?User $user): ?string
    {
        if (!$user || !$user->isSuspended()) {
            return null;
        }
        if ($user->reactivate_at && $user->reactivate_at->isPast()) {
            $user->forceFill([
                'suspended_at'      => null,
                'suspension_reason' => null,
                'suspended_by'      => null,
                'reactivate_at'     => null,
            ])->save();
            return null;
        }
        $reason = trim((string) $user->suspension_reason);
        $msg = 'Your account has been suspended.';
        if ($reason !== '') {
            $msg .= ' Reason: ' . $reason;
        }
        if ($user->reactivate_at) {
            $msg .= ' It is scheduled to be reactivated on ' . $user->reactivate_at->format('M j, Y') . '.';
        }
        return $msg;
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
        if (!$this->currentUser($request)) {
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
