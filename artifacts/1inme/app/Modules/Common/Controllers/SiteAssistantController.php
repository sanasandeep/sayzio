<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
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
            'avatar_url'        => $cfg['avatar_url'],
            'greeting'          => $cfg['greeting'],
            'starter_prompts'   => array_values((array) $cfg['starter_prompts']),
            'handoff_enabled'   => (bool) $cfg['handoff_enabled'],
            'templates'         => $this->runtime->listTemplates(),
        ]);
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
        $surface = $this->detectSurface($request);
        $label = (string) ($data['choice']['label'] ?? $data['choice']['value'] ?? 'Selected option');
        return response()->json($this->runtime->turn(
            $data['visitor_token'], $request->user(), $surface,
            (array) ($data['page'] ?? []), $label, $data['choice'], $this->visitorMeta($request)
        ));
    }

    public function handoff(Request $request)
    {
        $data = $request->validate([
            'visitor_token' => 'required|string|max:64',
            'surface'       => 'nullable|in:marketing,app',
            'name'          => 'required|string|max:120',
            'email'         => 'required|email|max:200',
            'message'       => 'nullable|string|max:2000',
            'page'          => 'array',
        ]);
        $surface = $this->detectSurface($request);
        return response()->json($this->runtime->handoff(
            $data['visitor_token'], $request->user(), $surface,
            (array) ($data['page'] ?? []),
            ['name' => $data['name'], 'email' => $data['email'], 'message' => $data['message'] ?? ''],
            $this->visitorMeta($request)
        ));
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
