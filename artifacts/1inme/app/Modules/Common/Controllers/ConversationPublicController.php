<?php

namespace App\Modules\Common\Controllers;

use App\Modules\User\Models\ConversationAction;
use App\Modules\User\Models\ConversationFlow;
use App\Modules\User\Models\ConversationSession;
use App\Modules\User\Models\ConversationStep;
use App\Modules\User\Models\ConversationStepEvent;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ConversationPublicController extends Controller
{
    /** Boot a visitor session for the published flow. */
    public function start(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link) abort(404);

        // Owner preview: when the editor's signed `_preview=1` request
        // rendered the chat view, RedirectController stamped a short-
        // lived per-link session flag. If it's still valid, allow the
        // latest draft flow so creators can iterate without publishing.
        $previewExpires = (int) session('cv_preview_link_'.$link->id, 0);
        $allowDraft = $previewExpires > now()->getTimestamp();

        $flowQ = ConversationFlow::where('link_id', $link->id);
        if (!$allowDraft) $flowQ->where('is_published', true);
        $flow = $flowQ->orderByDesc('version')->first();
        if (!$flow) {
            return response()->json(['ok' => false, 'error' => 'No published flow'], 404);
        }

        $data = $request->validate([
            // Stable per-browser id minted client-side and stored in
            // localStorage. Format: `pg_` + 32 hex chars (or short
            // fallback when crypto is unavailable). We only use it as
            // an opaque key — never echoed back to other visitors.
            'page_session_id' => 'nullable|string|max:64|regex:/^pg_[a-z0-9]{6,60}$/i',
        ]);
        $pageSessionId = $data['page_session_id'] ?? null;

        // Returning-visitor memory: pull prior answers from the most
        // recent completed session that *this same browser* created.
        // Strictly scoped by page_session_id — never by link/workspace —
        // so one visitor can never see another visitor's answers.
        $known = $this->knownAnswers($flow, $pageSessionId);

        // Freeze the flow graph the visitor begins on. The answer
        // endpoint resolves steps/choices/actions from this snapshot,
        // so even if the creator publishes edits mid-conversation the
        // visitor finishes on the version they started.
        $snapshot = $this->buildSnapshot($flow);
        if (empty($snapshot['steps']) || empty($snapshot['entry_key'])) {
            return response()->json(['ok' => false, 'error' => 'Flow has no steps'], 422);
        }

        $session = ConversationSession::create([
            'flow_id'         => $flow->id,
            'link_id'         => $link->id,
            'flow_version'    => $flow->version,
            'flow_snapshot'   => $snapshot,
            'page_session_id' => $pageSessionId,
            'answers'         => $known,
            'path'            => [],
        ]);

        $first = $this->advanceWhileKnownArr($snapshot, $snapshot['steps'][$snapshot['entry_key']], $known);
        $session->update(['current_step_key' => $first['key'], 'path' => [$first['key']]]);
        $this->logEvent($session, $flow, $first['key'], ConversationStepEvent::EVENT_ENTERED);

        return response()->json([
            'ok'      => true,
            'session' => ['id' => $session->public_id],
            'flow'    => [
                'name'          => $flow->name,
                'intro_message' => $flow->intro_message,
                'version'       => $flow->version,
            ],
            'step'    => $this->stepPayloadArr($first),
            'known'   => $known,
        ]);
    }

    /** Visitor answered the current step — branch and return the next step. */
    public function answer(Request $request, string $publicId)
    {
        $session = ConversationSession::where('public_id', $publicId)->firstOrFail();
        $flow = ConversationFlow::findOrFail($session->flow_id);

        // Resolve graph from the session's frozen snapshot (taken at
        // /start). This isolates in-flight visitors from creator edits
        // — `current_step_key` always exists in the snapshot the
        // visitor began on, even if the live flow has been republished.
        $snapshot = $this->ensureSnapshot($session, $flow);
        $current = $snapshot['steps'][$session->current_step_key] ?? null;

        if (!$current) {
            // Snapshot is also missing this key (e.g. corrupt legacy
            // session pre-snapshot). End gracefully rather than crash.
            $this->logEvent($session, $flow, (string) $session->current_step_key, ConversationStepEvent::EVENT_COMPLETED);
            $session->update(['completed' => true, 'completed_at' => now()]);
            return response()->json([
                'ok' => true, 'done' => true, 'action' => null,
                'answers' => $session->answers ?? [],
                'note'    => 'This conversation was updated. Thanks for visiting!',
            ]);
        }

        $data = $request->validate([
            'choice_value' => 'nullable|string|max:120',
            'input_value'  => 'nullable|string|max:1000',
        ]);

        $answers = $session->answers ?? [];
        $path    = $session->path ?? [];
        $field   = $current['answer_field'] ?: $current['key'];

        $nextKey   = $current['next_step_key'] ?? null;
        $actionId  = $current['action_id'] ?? null;
        $logValue  = null;

        if ($current['kind'] === ConversationStep::KIND_QUESTION) {
            if (empty($data['choice_value'])) {
                return response()->json(['ok' => false, 'error' => 'Choose an option'], 422);
            }
            $choice = collect($current['choices'] ?? [])
                ->firstWhere('value', $data['choice_value']);
            if (!$choice) {
                return response()->json(['ok' => false, 'error' => 'Unknown choice'], 422);
            }
            $answers[$field] = $choice['value'];
            $logValue = $choice['value'];
            $nextKey  = $choice['next_step_key'] ?: $nextKey;
            $actionId = $choice['action_id'] ?: $actionId;
        } elseif ($current['kind'] === ConversationStep::KIND_INPUT) {
            $answers[$field] = trim((string) ($data['input_value'] ?? ''));
            if ($answers[$field] === '') {
                return response()->json(['ok' => false, 'error' => 'Please enter a value'], 422);
            }
            $logValue = mb_substr($answers[$field], 0, 60);
        }
        // KIND_MESSAGE / KIND_END auto-advance — no answer captured.

        $this->logEvent($session, $flow, $current['key'], ConversationStepEvent::EVENT_ANSWERED, $logValue);

        // If this answer is an email, write it to Contact + Subscriber
        // (workspace-scoped) and merge any prior answers stored on that
        // Contact/Subscriber back into this session. That gives us
        // cross-browser memory once the visitor identifies themselves.
        $merged = $this->persistMemory($session, $flow, $current, $answers);
        if ($merged) {
            $answers = array_merge($merged, $answers);
        }

        // Find next step, skipping any whose answer is already known.
        $next = $nextKey ? ($snapshot['steps'][$nextKey] ?? null) : null;
        if ($next) {
            $next = $this->advanceWhileKnownArr($snapshot, $next, $answers);
        }

        if (!$next || $current['kind'] === ConversationStep::KIND_END) {
            // Flow complete — fire the end action from the snapshot.
            $action = $actionId ? ($snapshot['actions'][$actionId] ?? null) : null;
            $session->update([
                'answers'             => $answers,
                'completed'           => true,
                'completed_action_id' => $action['id'] ?? null,
                'completed_at'        => now(),
            ]);
            $this->logEvent($session, $flow, $current['key'], ConversationStepEvent::EVENT_COMPLETED);
            return response()->json([
                'ok'       => true,
                'done'     => true,
                'action'   => $action ? $this->actionPayloadArr($action, (int) $flow->link_id) : null,
                'answers'  => $answers,
            ]);
        }

        $path[] = $next['key'];
        $session->update([
            'answers'          => $answers,
            'current_step_key' => $next['key'],
            'path'             => $path,
        ]);
        $this->logEvent($session, $flow, $next['key'], ConversationStepEvent::EVENT_ENTERED);

        return response()->json([
            'ok'      => true,
            'done'    => false,
            'step'    => $this->stepPayloadArr($next),
            'answers' => $answers,
        ]);
    }

    /**
     * End-action email capture: visitor submits an email from the
     * inline form rendered for a `capture_email` end action. Persists
     * to Subscriber + workspace Contact via the same memory pipeline
     * used by input-step emails, and stores the email on the session
     * answers so funnel analytics keep a single source of truth.
     */
    public function captureEmail(Request $request, string $publicId)
    {
        $session = ConversationSession::where('public_id', $publicId)->first();
        if (!$session) abort(404);

        $data = $request->validate([
            'email' => 'required|email|max:190',
        ]);
        $email = mb_strtolower(trim($data['email']));

        $flow = ConversationFlow::find($session->flow_id);
        if (!$flow) return response()->json(['ok' => false, 'error' => 'No flow'], 422);

        $answers = is_array($session->answers) ? $session->answers : [];
        $answers['email'] = $email;

        // Reuse the input-step persistence path: it handles Subscriber
        // upsert, Contact + ContactEmail dedupe by lowercased email,
        // and links subscriber_id / contact_id back to the session.
        $syntheticStep = [
            'kind'         => ConversationStep::KIND_INPUT,
            'key'          => 'capture_email_action',
            'answer_field' => 'email',
        ];
        $this->persistMemory($session, $flow, $syntheticStep, $answers);

        $session->update([
            'answers'      => $answers,
            'completed'    => true,
            'completed_at' => $session->completed_at ?? now(),
        ]);

        return response()->json(['ok' => true, 'email' => $email]);
    }

    /** Best-effort drop-off ping when the visitor closes the chat early. */
    public function drop(Request $request, string $publicId)
    {
        $session = ConversationSession::where('public_id', $publicId)->first();
        if (!$session || $session->completed) return response()->json(['ok' => true]);
        $this->logEvent($session, $session->flow, (string) $session->current_step_key, ConversationStepEvent::EVENT_DROPPED);
        return response()->json(['ok' => true]);
    }

    // ─────────────────────── Helpers ───────────────────────

    /**
     * Build a self-contained snapshot of every step, choice, and
     * action in the flow keyed for O(1) lookup. Stored on the session
     * so creator edits can never strand an in-flight visitor.
     */
    protected function buildSnapshot(ConversationFlow $flow): array
    {
        $steps = [];
        $entryKey = null;
        foreach ($flow->steps()->with('choices')->orderBy('sort_order')->get() as $step) {
            if ($step->is_entry && !$entryKey) $entryKey = $step->key;
            $steps[$step->key] = [
                'key'           => $step->key,
                'kind'          => $step->kind,
                'message_text'  => $step->message_text,
                'answer_field'  => $step->answer_field,
                'skip_if_known' => (bool) $step->skip_if_known,
                'next_step_key' => $step->next_step_key,
                'action_id'     => $step->action_id,
                'settings'      => $step->settings ?? [],
                'choices'       => $step->choices->map(fn ($c) => [
                    'label'         => $c->label,
                    'value'         => $c->value,
                    'next_step_key' => $c->next_step_key,
                    'action_id'     => $c->action_id,
                ])->values()->all(),
            ];
        }
        if (!$entryKey && !empty($steps)) {
            $entryKey = array_key_first($steps);
        }
        $actions = [];
        foreach ($flow->actions()->get() as $a) {
            $actions[$a->id] = [
                'id'      => $a->id,
                'kind'    => $a->kind,
                'label'   => $a->label,
                'payload' => $a->payload ?? [],
            ];
        }
        return ['entry_key' => $entryKey, 'steps' => $steps, 'actions' => $actions];
    }

    /**
     * Server-render a single biolink block via the same partial the
     * static list view uses, so a `show_block` end action can drop
     * the live block (CTA, embed, calendar, etc.) into the chat
     * stream instead of just showing placeholder text.
     */
    protected function renderBlockHtml(int $blockId, int $linkId): ?string
    {
        try {
            $block = \App\Modules\User\Models\BiolinkBlock::withoutGlobalScope('workspace')
                ->where('id', $blockId)
                ->where('link_id', $linkId)
                ->first();
            if (!$block) {
                logger()->warning('Conversational show_block ownership mismatch', [
                    'block_id' => $blockId, 'link_id' => $linkId,
                ]);
                return null;
            }
            $s = is_array($block->settings) ? $block->settings : [];
            return view('common.partials.biolink-block-render', [
                'block'     => $block,
                's'         => $s,
                'fontColor' => '#ffffff',
            ])->render();
        } catch (\Throwable $e) {
            logger()->warning('Conversational show_block render failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Return the session's snapshot, lazily building one for legacy rows. */
    protected function ensureSnapshot(ConversationSession $session, ConversationFlow $flow): array
    {
        $snap = $session->flow_snapshot;
        if (is_array($snap) && !empty($snap['steps'])) return $snap;
        $snap = $this->buildSnapshot($flow);
        $session->update([
            'flow_snapshot' => $snap,
            'flow_version'  => $session->flow_version ?? $flow->version,
        ]);
        return $snap;
    }

    protected function stepPayloadArr(array $step): array
    {
        return [
            'key'          => $step['key'],
            'kind'         => $step['kind'],
            'message_text' => $step['message_text'],
            'choices'      => array_map(
                fn ($c) => ['label' => $c['label'], 'value' => $c['value']],
                $step['choices'] ?? []
            ),
            'input_kind'   => $step['settings']['input_kind'] ?? 'text',
            'placeholder'  => $step['settings']['placeholder'] ?? null,
        ];
    }

    protected function actionPayloadArr(array $action, int $linkId = 0): array
    {
        $payload = $action['payload'] ?? [];
        $resolved = ['kind' => $action['kind'], 'label' => $action['label']];
        switch ($action['kind']) {
            case ConversationAction::KIND_OPEN_LINK:
                $resolved['url'] = $payload['url'] ?? null; break;
            case ConversationAction::KIND_SHOW_BLOCK:
                $blockId = $payload['block_id'] ?? null;
                $resolved['block_id'] = $blockId;
                $resolved['html']     = ($blockId && $linkId)
                    ? $this->renderBlockHtml((int) $blockId, $linkId)
                    : null;
                break;
            case ConversationAction::KIND_BOOK_CALENDAR:
                $resolved['url'] = $payload['booking_url'] ?? null; break;
            case ConversationAction::KIND_MESSAGE:
                $resolved['text'] = $payload['text'] ?? ''; break;
            case ConversationAction::KIND_CAPTURE_EMAIL:
                $resolved['cta'] = $payload['cta'] ?? 'Subscribe'; break;
        }
        return $resolved;
    }

    /**
     * Skip steps whose `answer_field` is already in $answers (memory).
     * Bounded loop so a corrupt graph can't infinitely cycle.
     */
    protected function advanceWhileKnownArr(array $snapshot, array $step, array $answers): array
    {
        $seen = [];
        for ($i = 0; $i < 30; $i++) {
            $field = $step['answer_field'] ?: $step['key'];
            if (empty($step['skip_if_known'])) return $step;
            if (!array_key_exists($field, $answers)) return $step;
            if ($step['kind'] === ConversationStep::KIND_END) return $step;

            $nextKey = $step['next_step_key'] ?? null;
            if ($step['kind'] === ConversationStep::KIND_QUESTION) {
                foreach ($step['choices'] ?? [] as $c) {
                    if ($c['value'] === ($answers[$field] ?? null) && !empty($c['next_step_key'])) {
                        $nextKey = $c['next_step_key'];
                        break;
                    }
                }
            }
            if (!$nextKey || isset($seen[$nextKey])) return $step;
            $seen[$nextKey] = true;
            $next = $snapshot['steps'][$nextKey] ?? null;
            if (!$next) return $step;
            $step = $next;
        }
        return $step;
    }

    /**
     * Pull cached answers from THIS browser's prior completed session
     * for the same flow. Scoped strictly by `page_session_id` so a
     * visitor can never inherit another visitor's answers.
     *
     * If the prior session also produced a Subscriber (email capture),
     * we additionally merge any answers stored on that Subscriber's
     * metadata — this keeps memory across browsers when the same email
     * is re-entered, but only after explicit identification.
     */
    protected function knownAnswers(ConversationFlow $flow, ?string $pageSessionId): array
    {
        if (!$pageSessionId) return [];

        $prior = ConversationSession::where('flow_id', $flow->id)
            ->where('completed', true)
            ->where('page_session_id', $pageSessionId)
            ->orderByDesc('completed_at')
            ->first();

        return $prior ? (array) ($prior->answers ?? []) : [];
    }

    /**
     * When the visitor types an email, persist the conversation memory
     * onto a workspace Contact + Subscriber keyed by that email. Returns
     * any *prior* answers we recovered from those records so the caller
     * can merge them into the current session — this is what gives
     * cross-browser memory once the visitor identifies themselves.
     */
    protected function persistMemory(ConversationSession $session, ConversationFlow $flow, array $step, array $answers): array
    {
        $field = $step['answer_field'] ?: $step['key'];
        if ($step['kind'] !== ConversationStep::KIND_INPUT) return [];
        $value = $answers[$field] ?? null;
        if (!$value) return [];

        $isEmail = (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
        if (!$isEmail) return [];

        $prior = [];
        try {
            $userId      = $session->link->user_id;
            $workspaceId = $session->link->workspace_id ?? $userId;

            // 1. Subscriber: per-link mailing-list membership.
            $sub = Subscriber::withoutGlobalScope('workspace')
                ->where('user_id', $userId)
                ->where('link_id', $session->link_id)
                ->where('type', 'conversational')
                ->where('email', $value)
                ->first();
            if ($sub && is_array($sub->metadata)) {
                $prior = array_merge($prior, (array) ($sub->metadata['answers'] ?? []));
            }
            $merged = array_merge($prior, $answers);
            if (!$sub) {
                $sub = Subscriber::create([
                    'user_id'       => $userId,
                    'link_id'       => $session->link_id,
                    'type'          => 'conversational',
                    'email'         => $value,
                    'status'        => 'active',
                    'source'        => 'conversational_flow',
                    'metadata'      => ['answers' => $merged, 'flow_id' => $flow->id],
                    'subscribed_at' => now(),
                ]);
            } else {
                $sub->update(['metadata' => ['answers' => $merged, 'flow_id' => $flow->id]]);
            }

            // 2. Contact: workspace-wide CRM record. Look up by an
            // existing ContactEmail row and reuse if present, else
            // create a new contact + email row in the same workspace.
            $contact = null;
            try {
                $existingEmail = \App\Modules\User\Models\ContactEmail::query()
                    ->whereRaw('LOWER(value) = ?', [mb_strtolower($value)])
                    ->whereHas('contact', function ($q) use ($workspaceId) {
                        $q->withoutGlobalScope('workspace')
                          ->where('workspace_id', $workspaceId);
                    })
                    ->first();
                if ($existingEmail) {
                    $contact = Contact::withoutGlobalScope('workspace')->find($existingEmail->contact_id);
                }
                if (!$contact) {
                    $contact = Contact::create([
                        'workspace_id' => $workspaceId,
                        'user_id'      => $userId,
                        'display_name' => $value,
                    ]);
                    \App\Modules\User\Models\ContactEmail::create([
                        'contact_id' => $contact->id,
                        'label'      => 'biolink',
                        'value'      => $value,
                        'is_primary' => true,
                    ]);
                }
                if ($contact) {
                    $notes = (string) ($contact->notes ?? '');
                    $tag = '[conversational:flow=' . $flow->id . ']';
                    if (!str_contains($notes, $tag)) {
                        $contact->notes = trim($notes . "\n" . $tag . ' ' . json_encode($merged));
                        $contact->save();
                    }
                }
            } catch (\Throwable $e) {
                logger()->warning('Conversational Contact persist failed: ' . $e->getMessage());
            }

            $update = [];
            if ($sub && empty($session->subscriber_id)) $update['subscriber_id'] = $sub->id;
            if ($contact && empty($session->contact_id)) $update['contact_id'] = $contact->id;
            if ($update) $session->update($update);
        } catch (\Throwable $e) {
            logger()->warning('Conversational memory persist failed: ' . $e->getMessage());
        }
        return $prior;
    }

    protected function logEvent(ConversationSession $session, ConversationFlow $flow, string $stepKey, string $event, ?string $value = null): void
    {
        try {
            ConversationStepEvent::create([
                'session_id'   => $session->id,
                'flow_id'      => $flow->id,
                'step_key'     => $stepKey,
                'event'        => $event,
                'choice_value' => $value,
                'occurred_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Analytics must not break the flow.
        }
    }
}
