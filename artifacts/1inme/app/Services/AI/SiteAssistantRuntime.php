<?php

namespace App\Services\AI;

use App\Modules\Common\Models\ContactMessage;
use App\Modules\Common\Models\SiteAssistantConversation;
use App\Modules\Common\Models\SiteAssistantMessage;
use App\Modules\Common\Models\SiteAssistantPageHint;
use App\Modules\Common\Models\SiteAssistantResponseTemplate;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Drives the site-wide AI assistant turn:
 *   - Resolves / creates the conversation.
 *   - Builds system prompt + page-context block.
 *   - Retrieves grounded chunks from the platform Mind (and the
 *     signed-in user's own Minds when applicable).
 *   - Calls OpenAiService::chat asking the model to return either
 *     prose or a small JSON envelope with rich blocks.
 *   - Persists user + assistant messages.
 *   - Enforces session rate limit + global monthly budget.
 *   - Provides the customer-care handoff path.
 */
class SiteAssistantRuntime
{
    public const HISTORY_TURNS = 12;
    public const MAX_USER_MESSAGE_CHARS = 2000;

    public function __construct(
        protected OpenAiService $openai,
        protected AiMindQueryService $minds,
    ) {}

    /**
     * Open or resume a conversation by visitor token.
     * Always returns a serializable summary used by the widget.
     */
    public function openSession(
        string $visitorToken,
        ?User $user,
        string $surface,
        array $page,
        array $visitorMeta = []
    ): array {
        $cfg = SiteAssistantSettings::get();
        // If the assistant is disabled for this surface, do not even
        // open a session — return a benign payload so the widget can
        // hide itself without leaking which surface was requested.
        if (!SiteAssistantSettings::isEnabledFor($surface)) {
            return [
                'ok'              => false,
                'is_disabled'     => true,
                'error'           => 'The assistant is not available here.',
                'visitor_token'   => $visitorToken,
                'messages'        => [],
                'starter_prompts' => [],
                'page_suggestions'=> [],
                'greeting'        => '',
            ];
        }
        $resolution = $this->resolveConversation($visitorToken, $user, $surface, $page, $visitorMeta);
        $conv = $resolution['conversation'];

        $messages = $conv->messages()->orderBy('id')->get()->map(
            fn ($m) => $this->serializeMessage($m)
        );

        $hint = SiteAssistantPageHint::resolve($page['route'] ?? null, $page['path'] ?? null, $surface);

        return [
            'ok'              => true,
            'conversation_id' => (int) $conv->id,
            'visitor_token'   => $conv->visitor_token,
            'rotated'         => $resolution['rotated'],
            'handed_off'      => (bool) $conv->handed_off,
            'is_disabled'     => (bool) $conv->is_disabled,
            'greeting'        => (string) $cfg['greeting'],
            'starter_prompts' => array_values((array) $cfg['starter_prompts']),
            'page_suggestions'=> $hint?->suggested_actions ?? [],
            'messages'        => $messages->values()->all(),
        ];
    }

    /**
     * Process one user turn. $message may be a free-text question OR a
     * structured choice payload (from a button/list/form template).
     *
     * @return array{ok:bool,error?:string,user_message?:array,assistant_message?:array,conversation_id?:int,handed_off?:bool}
     */
    public function turn(
        string $visitorToken,
        ?User $user,
        string $surface,
        array $page,
        string $message,
        array $choice = [],
        array $visitorMeta = []
    ): array {
        $cfg = SiteAssistantSettings::get();
        if (!SiteAssistantSettings::isEnabledFor($surface)) {
            return ['ok' => false, 'error' => 'The assistant is currently disabled.'];
        }
        if (SiteAssistantSettings::isOverBudget()) {
            return ['ok' => false, 'error' => 'The assistant is temporarily unavailable. Please try again later.'];
        }

        $message = trim($message);
        if ($message === '' && empty($choice)) {
            return ['ok' => false, 'error' => 'Message is required.'];
        }
        if (mb_strlen($message) > self::MAX_USER_MESSAGE_CHARS) {
            $message = mb_substr($message, 0, self::MAX_USER_MESSAGE_CHARS) . '…';
        }

        $resolution = $this->resolveConversation($visitorToken, $user, $surface, $page, $visitorMeta);
        $conv = $resolution['conversation'];

        if ($resolution['rotated']) {
            // Token was reassigned because it didn't belong to this user.
            // Reject the action and send back the new token so the client
            // can adopt it on the next turn.
            return [
                'ok'            => false,
                'error'         => 'Session expired. Please retry.',
                'visitor_token' => $conv->visitor_token,
                'rotated'       => true,
            ];
        }

        if ($conv->is_disabled) {
            return ['ok' => false, 'error' => 'This chat session has been disabled.'];
        }
        if ($conv->handed_off && (bool) ($cfg['handoff_freeze_after'] ?? true) && !$this->isHandoffResolved($conv)) {
            return ['ok' => false, 'error' => 'Your message is with our support team — they will reply by email.'];
        }

        // Per-session rate limit (cheap cache check).
        $rl = max(1, (int) ($cfg['session_rate_per_minute'] ?? 12));
        $rlKey = "siteasst-rl:{$conv->id}";
        $hits = (int) Cache::get($rlKey, 0);
        if ($hits >= $rl) {
            return ['ok' => false, 'error' => "You're sending messages too fast. Please wait a moment."];
        }
        Cache::put($rlKey, $hits + 1, now()->addMinute());

        $billingUser = $this->billingUser($user);
        if (!$billingUser) {
            return ['ok' => false, 'error' => 'The assistant is not yet configured. Please try again later.'];
        }

        // ── Persist user message first so it shows in transcript even if
        //    the model fails. For inline forms we serialize the field
        //    values into the message content so the model can actually
        //    use them on the next turn (raw payload still kept in meta).
        $content = $message !== '' ? $message : ($choice['label'] ?? '');
        if (!empty($choice['values']) && is_array($choice['values'])) {
            $lines = [];
            foreach ($choice['values'] as $k => $v) {
                if (is_scalar($v)) {
                    $lines[] = '- ' . $k . ': ' . trim((string) $v);
                } else {
                    $lines[] = '- ' . $k . ': ' . json_encode($v, JSON_UNESCAPED_UNICODE);
                }
            }
            $formLabel = trim((string) ($choice['label'] ?? 'Form submitted'));
            $content = $formLabel . "\n" . implode("\n", $lines);
        }
        $userMsg = SiteAssistantMessage::create([
            'conversation_id' => $conv->id,
            'role'            => 'user',
            'content'         => $content,
            'meta'            => array_filter([
                'choice' => $choice ?: null,
                'page'   => $page ?: null,
            ]),
        ]);

        // Build prompt
        $hint = SiteAssistantPageHint::resolve($page['route'] ?? null, $page['path'] ?? null, $surface);
        $systemPrompt = (string) $cfg['system_prompt'];

        $contextBlock = $this->buildPageContextBlock($surface, $page, $hint);

        $knowledgeBlock = '';
        $citations = [];
        try {
            $minds = $this->knowledgeMinds($cfg, $user, $billingUser);
            if ($minds) {
                $retrieved = $this->minds->retrieveContext(
                    $billingUser, $minds, $message ?: ($choice['label'] ?? ''),
                    [
                        'feature'    => 'site_assistant',
                        'related_id' => (int) $conv->id,
                        'reason'     => 'Site assistant retrieval',
                    ],
                    // contextUser is the authenticated visitor (or null
                    // for anonymous). NEVER pass $billingUser here —
                    // doing so would leak the billing admin's private
                    // feature data into anonymous transcripts.
                    $user
                );
                $knowledgeBlock = (string) ($retrieved['context'] ?? '');
                $citations = (array) ($retrieved['citations'] ?? []);
            }
        } catch (\Throwable $e) {
            report($e);
            // Retrieval failure is non-fatal — we still try to answer.
        }

        // Append a short, structured note about which response templates
        // the model may invoke by `template` key. The runtime hydrates
        // those templates from the admin-managed table so the model
        // cannot inject arbitrary UI.
        $tplKeys = SiteAssistantResponseTemplate::where('is_active', true)
            ->orderBy('label')->pluck('label', 'key')->take(20);
        $tplBlock = '';
        if ($tplKeys->isNotEmpty()) {
            $tplBlock  = "When a structured reply helps, return ONLY a fenced JSON block of the shape ```json {\"text\":\"…\",\"blocks\":[{...}]} ```.\n";
            $tplBlock .= "Available admin-defined templates (use `{\"template\":\"<key>\"}` inside `blocks` to invoke):\n";
            foreach ($tplKeys as $k => $label) $tplBlock .= " - {$k}: {$label}\n";
            $tplBlock .= "Allowed block types if not using a template: `buttons`, `list`, `image`, `form`. Otherwise reply in plain prose.";
        }

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        if ($tplBlock !== '') {
            $messages[] = ['role' => 'system', 'content' => $tplBlock];
        }
        if ($contextBlock !== '') {
            $messages[] = ['role' => 'system', 'content' => "Page context:\n" . $contextBlock];
        }
        if ($user) {
            $userCtx = $this->buildUserContextBlock($user);
            if ($userCtx !== '') {
                $messages[] = ['role' => 'system', 'content' => "Signed-in user context (private — never share with anyone else):\n" . $userCtx];
            }
        }
        if ($knowledgeBlock !== '') {
            $messages[] = ['role' => 'system', 'content' => "Knowledge:\n" . $knowledgeBlock];
        }

        // Replay history
        $history = $conv->messages()
            ->where('id', '<', $userMsg->id)
            ->orderByDesc('id')
            ->limit(self::HISTORY_TURNS)
            ->get(['role', 'content'])
            ->reverse()
            ->values();
        foreach ($history as $h) {
            $messages[] = [
                'role'    => $h->role === 'assistant' ? 'assistant' : 'user',
                'content' => (string) $h->content,
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $userMsg->content];

        $model = $this->modelFor($cfg);

        try {
            $result = $this->openai->chat($billingUser, $model, $messages, [
                'temperature' => (float) ($cfg['temperature'] ?? 0.4),
                'max_tokens'  => (int) ($cfg['max_tokens'] ?? 800),
                'feature'     => 'site_assistant',
                'related_id'  => (int) $conv->id,
                'reason'      => 'Site assistant turn',
            ]);
        } catch (InsufficientAiCreditsException $e) {
            return ['ok' => false, 'error' => 'The assistant is temporarily out of capacity. Please try again later.'];
        } catch (\Throwable $e) {
            report($e);
            return ['ok' => false, 'error' => 'The assistant could not respond right now. Please try again.'];
        }

        $answer = (string) $result['content'];
        $blocks = $this->extractBlocks($answer);
        // Strict server-side validation against the allow-list of block
        // shapes and active templates. Anything that doesn't fit is dropped
        // so the model can never return arbitrary JSON the widget will run.
        if (!empty($blocks['blocks'])) {
            $blocks['blocks'] = $this->sanitizeBlocks((array) $blocks['blocks']);
        }

        $aiMsg = null;
        DB::transaction(function () use ($conv, $answer, $blocks, $citations, $result, &$aiMsg) {
            $aiMsg = SiteAssistantMessage::create([
                'conversation_id' => $conv->id,
                'role'            => 'assistant',
                'content'         => $blocks['text'] ?? $answer,
                'blocks'          => $blocks['blocks'] ?? null,
                'citations'       => $citations,
                'credits_spent'   => (int) ($result['credits_spent'] ?? 0),
            ]);
            $conv->turns_count     = (int) $conv->turns_count + 1;
            $conv->credits_spent   = (int) $conv->credits_spent + (int) ($result['credits_spent'] ?? 0);
            $conv->last_message_at = now();
            $conv->save();
        });

        return [
            'ok'                => true,
            'conversation_id'   => (int) $conv->id,
            'user_message'      => $this->serializeMessage($userMsg->fresh()),
            'assistant_message' => $this->serializeMessage($aiMsg),
            'handed_off'        => (bool) $conv->handed_off,
        ];
    }

    /**
     * Hand the conversation off into the existing Contact Inbox.
     * Creates a ContactMessage carrying the whole transcript so support
     * staff can reply asynchronously.
     */
    public function handoff(
        string $visitorToken,
        ?User $user,
        string $surface,
        array $page,
        array $intake,
        array $visitorMeta = []
    ): array {
        $cfg = SiteAssistantSettings::get();
        // Surface-level enable check: if the assistant is disabled for
        // the requesting surface, handoff must also be blocked so a
        // disabled surface cannot create new Contact Inbox threads.
        if (!SiteAssistantSettings::isEnabledFor($surface)) {
            return ['ok' => false, 'error' => 'The assistant is not available here.'];
        }
        if (!($cfg['handoff_enabled'] ?? true)) {
            return ['ok' => false, 'error' => 'Live handoff is currently disabled.'];
        }

        $resolution = $this->resolveConversation($visitorToken, $user, $surface, $page, $visitorMeta);
        $conv = $resolution['conversation'];
        if ($resolution['rotated']) {
            return [
                'ok'            => false,
                'error'         => 'Session expired. Please retry.',
                'visitor_token' => $conv->visitor_token,
                'rotated'       => true,
            ];
        }

        $name    = trim((string) ($intake['name'] ?? $user?->name ?? ''));
        $email   = trim((string) ($intake['email'] ?? $user?->email ?? ''));
        $summary = trim((string) ($intake['message'] ?? ''));

        if ($name === '' || $email === '') {
            return ['ok' => false, 'error' => 'Please provide your name and email.'];
        }

        $transcript = $conv->messages()->orderBy('id')->get()
            ->map(fn ($m) => '[' . strtoupper($m->role) . '] ' . trim((string) $m->content))
            ->implode("\n\n");

        $body  = "Visitor message:\n" . ($summary ?: '(none)') . "\n\n";
        $body .= "Surface: {$surface}\n";
        $body .= 'Page: ' . ($page['url'] ?? $page['path'] ?? 'unknown') . "\n";
        if ($user) $body .= "Signed-in user: {$user->email} (#{$user->id})\n";
        $body .= "\n— Transcript —\n" . $transcript;

        $contact = ContactMessage::create([
            'name'    => Str::limit($name, 250, ''),
            'email'   => Str::limit($email, 250, ''),
            'subject' => Str::limit('Assistant handoff: ' . ($summary ?: 'support request'), 500, ''),
            'message' => $body,
            'ip'      => $visitorMeta['ip'] ?? null,
            'status'  => 'new',
        ]);

        $conv->forceFill([
            'handed_off'         => true,
            'contact_message_id' => $contact->id,
            'visitor_name'       => $name,
            'visitor_email'      => $email,
        ])->save();

        $confirm = SiteAssistantMessage::create([
            'conversation_id' => $conv->id,
            'role'            => 'assistant',
            'content'         => "Thanks {$name} — our team has your message and will reply at {$email} shortly.",
            'meta'            => ['handoff' => true, 'contact_message_id' => $contact->id],
        ]);

        return [
            'ok'                => true,
            'conversation_id'   => (int) $conv->id,
            'assistant_message' => $this->serializeMessage($confirm),
            'handed_off'        => true,
        ];
    }

    /**
     * Streamed variant of {@see turn()}. Invokes $emit('event', payload)
     * for each token and a final `done` event with the persisted message.
     */
    public function turnStream(
        string $visitorToken,
        ?User $user,
        string $surface,
        array $page,
        string $message,
        array $visitorMeta,
        callable $emit
    ): void {
        $cfg = SiteAssistantSettings::get();
        if (!SiteAssistantSettings::isEnabledFor($surface)) {
            $emit('error', ['error' => 'The assistant is currently disabled.']);
            return;
        }
        if (SiteAssistantSettings::isOverBudget()) {
            $emit('error', ['error' => 'The assistant is temporarily unavailable.']);
            return;
        }

        $message = trim($message);
        if ($message === '') { $emit('error', ['error' => 'Message is required.']); return; }
        if (mb_strlen($message) > self::MAX_USER_MESSAGE_CHARS) {
            $message = mb_substr($message, 0, self::MAX_USER_MESSAGE_CHARS) . '…';
        }

        $resolution = $this->resolveConversation($visitorToken, $user, $surface, $page, $visitorMeta);
        $conv = $resolution['conversation'];
        if ($resolution['rotated']) {
            $emit('error', ['error' => 'Session expired. Please retry.', 'visitor_token' => $conv->visitor_token, 'rotated' => true]);
            return;
        }
        if ($conv->is_disabled) { $emit('error', ['error' => 'This chat session has been disabled.']); return; }
        if ($conv->handed_off && (bool) ($cfg['handoff_freeze_after'] ?? true) && !$this->isHandoffResolved($conv)) {
            $emit('error', ['error' => 'Your message is with our support team — they will reply by email.']);
            return;
        }

        $rl = max(1, (int) ($cfg['session_rate_per_minute'] ?? 12));
        $rlKey = "siteasst-rl:{$conv->id}";
        $hits = (int) Cache::get($rlKey, 0);
        if ($hits >= $rl) { $emit('error', ['error' => "You're sending messages too fast. Please wait a moment."]); return; }
        Cache::put($rlKey, $hits + 1, now()->addMinute());

        $billingUser = $this->billingUser($user);
        if (!$billingUser) { $emit('error', ['error' => 'The assistant is not configured.']); return; }

        $userMsg = SiteAssistantMessage::create([
            'conversation_id' => $conv->id,
            'role'            => 'user',
            'content'         => $message,
            'meta'            => array_filter(['page' => $page ?: null]),
        ]);
        $emit('user', ['user_message' => $this->serializeMessage($userMsg->fresh())]);

        // Prompt assembly mirrors turn() so behavior stays consistent.
        $hint = SiteAssistantPageHint::resolve($page['route'] ?? null, $page['path'] ?? null, $surface);
        $contextBlock = $this->buildPageContextBlock($surface, $page, $hint);

        $knowledgeBlock = ''; $citations = [];
        try {
            $minds = $this->knowledgeMinds($cfg, $user, $billingUser);
            if ($minds) {
                $retrieved = $this->minds->retrieveContext(
                    $billingUser, $minds, $message,
                    [
                        'feature'    => 'site_assistant',
                        'related_id' => (int) $conv->id,
                        'reason'     => 'Site assistant retrieval (stream)',
                    ],
                    // See turn(): only the authenticated visitor can
                    // ground feature snapshots from platform Minds.
                    $user
                );
                $knowledgeBlock = (string) ($retrieved['context'] ?? '');
                $citations = (array) ($retrieved['citations'] ?? []);
            }
        } catch (\Throwable $e) { report($e); }

        $tplKeys = SiteAssistantResponseTemplate::where('is_active', true)->orderBy('label')->pluck('label', 'key')->take(20);
        $tplBlock = '';
        if ($tplKeys->isNotEmpty()) {
            $tplBlock = "When a structured reply helps, return ONLY a fenced JSON block of the shape ```json {\"text\":\"…\",\"blocks\":[{...}]} ```.\nAvailable templates:\n";
            foreach ($tplKeys as $k => $label) $tplBlock .= " - {$k}: {$label}\n";
        }

        $messages = [['role' => 'system', 'content' => (string) $cfg['system_prompt']]];
        if ($tplBlock !== '') $messages[] = ['role' => 'system', 'content' => $tplBlock];
        if ($contextBlock !== '') $messages[] = ['role' => 'system', 'content' => "Page context:\n" . $contextBlock];
        if ($user) {
            $userCtx = $this->buildUserContextBlock($user);
            if ($userCtx !== '') $messages[] = ['role' => 'system', 'content' => "Signed-in user context (private):\n" . $userCtx];
        }
        if ($knowledgeBlock !== '') $messages[] = ['role' => 'system', 'content' => "Knowledge:\n" . $knowledgeBlock];

        $history = $conv->messages()->where('id', '<', $userMsg->id)->orderByDesc('id')->limit(self::HISTORY_TURNS)->get(['role','content'])->reverse()->values();
        foreach ($history as $h) $messages[] = ['role' => $h->role === 'assistant' ? 'assistant' : 'user', 'content' => (string) $h->content];
        $messages[] = ['role' => 'user', 'content' => $userMsg->content];

        $model = $this->modelFor($cfg);

        try {
            $result = $this->openai->chatStream($billingUser, $model, $messages, [
                'temperature' => (float) ($cfg['temperature'] ?? 0.4),
                'max_tokens'  => (int) ($cfg['max_tokens'] ?? 800),
                'feature'     => 'site_assistant',
                'related_id'  => (int) $conv->id,
                'reason'      => 'Site assistant turn (stream)',
            ], function (string $delta) use ($emit) {
                $emit('token', ['delta' => $delta]);
            });
        } catch (InsufficientAiCreditsException $e) {
            $emit('error', ['error' => 'The assistant is temporarily out of capacity.']); return;
        } catch (\Throwable $e) {
            report($e);
            $emit('error', ['error' => 'The assistant could not respond right now.']); return;
        }

        $answer = (string) $result['content'];
        $blocks = $this->extractBlocks($answer);
        if (!empty($blocks['blocks'])) {
            $blocks['blocks'] = $this->sanitizeBlocks((array) $blocks['blocks']);
        }

        $aiMsg = null;
        DB::transaction(function () use ($conv, $blocks, $citations, $result, &$aiMsg) {
            $aiMsg = SiteAssistantMessage::create([
                'conversation_id' => $conv->id,
                'role'            => 'assistant',
                'content'         => $blocks['text'] ?? '',
                'blocks'          => $blocks['blocks'] ?? null,
                'citations'       => $citations,
                'credits_spent'   => (int) ($result['credits_spent'] ?? 0),
            ]);
            $conv->turns_count   = (int) $conv->turns_count + 1;
            $conv->credits_spent = (int) $conv->credits_spent + (int) ($result['credits_spent'] ?? 0);
            $conv->last_message_at = now();
            $conv->save();
        });

        $emit('done', [
            'assistant_message' => $this->serializeMessage($aiMsg),
            'handed_off'        => (bool) $conv->handed_off,
            'conversation_id'   => (int) $conv->id,
        ]);
    }

    /**
     * Pick the chat model the assistant should call: explicit admin
     * choice if set and enabled, otherwise the engine's mapped model
     * for the `companion` feature.
     */
    protected function modelFor(array $cfg): string
    {
        $configured = trim((string) ($cfg['model'] ?? ''));
        if ($configured !== '') {
            $entry = AiEngineSettings::model($configured);
            if ($entry && ($entry['enabled'] ?? false) && ($entry['kind'] ?? 'chat') === 'chat') {
                return $configured;
            }
        }
        return AiEngineSettings::featureModel('companion');
    }

    /**
     * Resolve which Minds the assistant should retrieve over for the
     * current visitor. Returns the merged set of:
     *   - admin-pinned platform Minds from settings (`mind_ids`)
     *   - the signed-in user's own Minds (when applicable)
     * Falls back to the platform default Mind when no admin pin exists.
     *
     * @return array<int,\App\Modules\User\Models\AiMind>
     */
    protected function knowledgeMinds(array $cfg, ?User $user, User $billingUser): array
    {
        $explicitIds = array_values(array_filter(array_map('intval', (array) ($cfg['mind_ids'] ?? []))));
        $userIds = $user
            ? array_column(\App\Modules\User\Models\AiMind::where('user_id', $user->id)->get(['id'])->toArray(), 'id')
            : [];
        $includePlatformDefault = empty($explicitIds);

        $merged = $this->minds->resolveMindsForUser(
            $billingUser,
            array_values(array_unique(array_merge($explicitIds, $userIds))),
            $includePlatformDefault
        );

        // If admin pinned specific platform Minds, add them explicitly
        // (resolveMindsForUser only returns user-owned Minds for the
        // given ids, so we need to fetch platform-owned ones manually).
        if ($explicitIds) {
            $platformPinned = \App\Modules\User\Models\AiMind::query()
                ->whereIn('id', $explicitIds)
                ->whereNull('user_id')
                ->where('is_disabled', false)
                ->get()
                ->all();
            foreach ($platformPinned as $m) {
                $merged[] = $m;
            }
        }
        $seen = []; $out = [];
        foreach ($merged as $m) {
            if (isset($seen[$m->id])) continue;
            $seen[$m->id] = true;
            $out[] = $m;
        }
        return $out;
    }

    public function listTemplates(): array
    {
        return SiteAssistantResponseTemplate::where('is_active', true)
            ->orderBy('label')->get()->map(fn ($t) => [
                'key'     => $t->key,
                'label'   => $t->label,
                'kind'    => $t->kind,
                'payload' => $t->payload,
            ])->all();
    }

    // ─────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────

    /**
     * Resolve (or mint) a conversation, enforcing per-user binding.
     *
     * Rules:
     *   - A token bound to user A may only be used by user A. If anyone
     *     else (including an anonymous visitor or a different signed-in
     *     user) presents that token, we mint a fresh token + conversation
     *     and signal `rotated=true` so the client adopts the new token
     *     and the old transcript stays sealed.
     *   - An anonymous conversation (bound_user_id NULL) may NOT be
     *     claimed by an authed user — instead they get a fresh, properly
     *     bound conversation.
     *   - When created for an authed visitor, bound_user_id is locked.
     *
     * @return array{conversation:SiteAssistantConversation,rotated:bool}
     */
    protected function resolveConversation(
        string $visitorToken,
        ?User $user,
        string $surface,
        array $page,
        array $visitorMeta
    ): array {
        $rotated = false;
        $conv = SiteAssistantConversation::where('visitor_token', $visitorToken)->first();

        if ($conv) {
            $bound = $conv->bound_user_id ? (int) $conv->bound_user_id : null;
            $authedId = $user?->id ? (int) $user->id : null;
            if (($bound !== null && $bound !== $authedId) || ($bound === null && $authedId !== null)) {
                // Token doesn't match the current visitor's auth state.
                $conv = null;
                $visitorToken = 'sa_' . Str::random(28);
                $rotated = true;
            }
        }

        if (!$conv) {
            $conv = new SiteAssistantConversation();
            $conv->visitor_token = $visitorToken;
            $conv->surface       = in_array($surface, ['marketing', 'app'], true) ? $surface : 'marketing';
            $conv->user_id       = $user?->id;
            $conv->bound_user_id = $user?->id;
            $conv->visitor_name  = $user?->name;
            $conv->visitor_email = $user?->email;
            $conv->visitor_ip    = $visitorMeta['ip'] ?? null;
            $conv->visitor_ua    = Str::limit((string) ($visitorMeta['ua'] ?? ''), 250, '');
        }

        $conv->last_route       = Str::limit((string) ($page['route'] ?? ''), 245, '');
        $conv->last_page_title  = Str::limit((string) ($page['title'] ?? ''), 245, '');
        $conv->save();

        return ['conversation' => $conv, 'rotated' => $rotated];
    }

    /**
     * Whether the linked ContactMessage has been resolved by support
     * staff (status replied/closed/resolved). When true, the bot can
     * resume answering after a handoff.
     */
    protected function isHandoffResolved(SiteAssistantConversation $conv): bool
    {
        if (!$conv->contact_message_id) return false;
        // Inbox lifecycle in this app is `new` → `read` → `archived`.
        // Admin archiving the message is the explicit "we're done" signal,
        // so the bot resumes answering at that point.
        $status = (string) ContactMessage::query()->whereKey($conv->contact_message_id)->value('status');
        return $status === 'archived';
    }

    /**
     * Validate model-returned blocks against an allow-list of types and
     * shapes; if a block carries a `template` key, hydrate it from the
     * matching active admin template (the template is authoritative).
     */
    protected function sanitizeBlocks(array $blocks): array
    {
        $out = [];
        $allowedTypes = ['buttons', 'list', 'form', 'image'];
        foreach ($blocks as $b) {
            if (!is_array($b)) continue;
            // Hydrate from a named template (admin-defined → trusted).
            if (!empty($b['template']) && is_string($b['template'])) {
                $tpl = SiteAssistantResponseTemplate::where('key', $b['template'])
                    ->where('is_active', true)->first();
                if ($tpl) {
                    $b = array_merge(['type' => $tpl->kind], (array) $tpl->payload, ['template' => $tpl->key]);
                }
            }
            $type = $b['type'] ?? null;
            if (!in_array($type, $allowedTypes, true)) continue;
            if ($type === 'buttons' || $type === 'list') {
                $opts = [];
                foreach ((array) ($b['options'] ?? []) as $o) {
                    if (!is_array($o)) continue;
                    $opts[] = array_intersect_key($o, array_flip(['label','value','title','description','thumbnail','action']));
                }
                if (!$opts) continue;
                $out[] = ['type' => $type, 'options' => $opts] + (isset($b['template']) ? ['template' => (string) $b['template']] : []);
            } elseif ($type === 'image') {
                $imgs = [];
                foreach ((array) ($b['images'] ?? [$b]) as $im) {
                    if (!is_array($im) || empty($im['src']) || !is_string($im['src'])) continue;
                    if (!preg_match('#^https?://#i', $im['src'])) continue;
                    $imgs[] = ['src' => $im['src'], 'alt' => (string) ($im['alt'] ?? '')];
                }
                if (!$imgs) continue;
                $out[] = ['type' => 'image', 'images' => $imgs];
            } elseif ($type === 'form') {
                $fields = [];
                foreach ((array) ($b['fields'] ?? []) as $f) {
                    if (!is_array($f) || empty($f['name'])) continue;
                    $fields[] = [
                        'name'     => (string) $f['name'],
                        'label'    => (string) ($f['label'] ?? $f['name']),
                        'type'     => in_array($f['type'] ?? '', ['text','email','tel','textarea'], true) ? $f['type'] : 'text',
                        'required' => !empty($f['required']),
                    ];
                }
                if (!$fields) continue;
                $out[] = [
                    'type'         => 'form',
                    'fields'       => $fields,
                    'submit_label' => (string) ($b['submit_label'] ?? 'Submit'),
                    'action'       => in_array($b['action'] ?? '', ['handoff', 'submit'], true) ? $b['action'] : 'submit',
                ] + (isset($b['template']) ? ['template' => (string) $b['template']] : []);
            }
        }
        return $out;
    }

    /**
     * Lightweight per-user grounding: gives the assistant a concise
     * snapshot of the visitor's account so answers like "how many links
     * do I have?" are accurate. We avoid pulling sensitive data — just
     * counts and the most recent few item names.
     */
    protected function buildUserContextBlock(User $user): string
    {
        try {
            $links = \App\Modules\User\Models\Link::query()->where('user_id', $user->id);
            $linkCount = (int) (clone $links)->count();
            $recentLinks = (clone $links)->latest('id')->limit(5)->pluck('title')->filter()->all();

            $projectCount = (int) \App\Modules\User\Models\Project::query()->where('user_id', $user->id)->count();
            $formCount    = (int) \App\Modules\User\Models\Form::query()->where('user_id', $user->id)->count();
            $mindCount    = (int) \App\Modules\User\Models\AiMind::query()->where('user_id', $user->id)->count();

            $lines = [];
            $lines[] = 'Email: ' . $user->email;
            if ($user->name) $lines[] = 'Name: ' . $user->name;
            $lines[] = "Counts — links: {$linkCount}, projects: {$projectCount}, forms: {$formCount}, knowledge minds: {$mindCount}.";
            if ($recentLinks) {
                $lines[] = 'Recent links: ' . implode(', ', array_map(fn ($t) => '"' . Str::limit((string) $t, 40, '…') . '"', $recentLinks));
            }
            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function buildPageContextBlock(string $surface, array $page, ?SiteAssistantPageHint $hint): string
    {
        $lines = [];
        $lines[] = 'Surface: ' . $surface;
        if (!empty($page['title']))  $lines[] = 'Page title: ' . (string) $page['title'];
        if (!empty($page['route']))  $lines[] = 'Route: '      . (string) $page['route'];
        if (!empty($page['path']))   $lines[] = 'Path: '       . (string) $page['path'];
        if ($hint) {
            if ($hint->description) {
                $lines[] = 'Description: ' . (string) $hint->description;
            }
            $actions = (array) $hint->suggested_actions;
            if ($actions) {
                $lines[] = 'Suggested actions visitors can take here:';
                foreach ($actions as $a) {
                    $label = is_array($a) ? ($a['label'] ?? '') : (string) $a;
                    if ($label !== '') $lines[] = ' - ' . $label;
                }
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Look for a fenced JSON block of the form ```json {"text":"...","blocks":[...]} ```
     * and split it from the prose. If absent, return the raw text.
     *
     * @return array{text:string,blocks:?array}
     */
    protected function extractBlocks(string $answer): array
    {
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $answer, $m)) {
            $json = json_decode($m[1], true);
            if (is_array($json)) {
                $text = isset($json['text']) ? (string) $json['text'] : trim(str_replace($m[0], '', $answer));
                $blocks = isset($json['blocks']) && is_array($json['blocks']) ? $json['blocks'] : null;
                return ['text' => $text, 'blocks' => $blocks];
            }
        }
        return ['text' => $answer, 'blocks' => null];
    }

    protected function serializeMessage(SiteAssistantMessage $m): array
    {
        return [
            'id'         => (int) $m->id,
            'role'       => (string) $m->role,
            'content'    => (string) $m->content,
            'blocks'     => $m->blocks,
            'citations'  => $m->citations,
            'meta'       => $m->meta,
            'created_at' => optional($m->created_at)->toIso8601String(),
        ];
    }

    /**
     * Determine which user account credits should be charged against.
     * For signed-in visitors that's their own account. For anonymous
     * marketing visitors we use the explicitly admin-configured platform
     * billing user. If unset, we look for the first super-admin (a user
     * with the global `settings.manage` role) — never an arbitrary user.
     */
    protected function billingUser(?User $user): ?User
    {
        if ($user) return $user;
        $cfg = SiteAssistantSettings::get();
        $configuredId = (int) ($cfg['billing_user_id'] ?? 0);
        if ($configuredId > 0) {
            // Defense-in-depth: re-validate the configured user still
            // holds the platform settings.manage permission. This
            // protects against stale settings if an admin's role was
            // revoked after the value was saved.
            $u = User::query()
                ->where('id', $configuredId)
                ->whereHas('roles.permissions', fn ($q) => $q->where('key', 'settings.manage'))
                ->first();
            if ($u) return $u;
        }
        // Fallback: first user that holds the platform-admin role used
        // to manage settings. Avoids picking a random regular account.
        try {
            return User::query()
                ->whereHas('roles.permissions', fn ($q) => $q->where('key', 'settings.manage'))
                ->orderBy('id')->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
