<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\ConversationAction;
use App\Modules\User\Models\ConversationChoice;
use App\Modules\User\Models\ConversationFlow;
use App\Modules\User\Models\ConversationSession;
use App\Modules\User\Models\ConversationStep;
use App\Modules\User\Models\ConversationStepEvent;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationFlowController extends Controller
{
    /** Editor page (visual flow builder + live preview). */
    public function editor(Link $link)
    {
        $this->authorizeLink($link);
        $flow = $this->ensureFlow($link);
        $flow->load(['steps.choices', 'actions']);

        $blocks = $link->biolinkBlocks()->whereNull('parent_id')->get(['id', 'type', 'settings']);
        $blockOptions = $blocks->map(fn ($b) => [
            'id'    => $b->id,
            'type'  => $b->type,
            'label' => self::blockLabel($b),
        ])->values();

        // Mint a 24h signed preview URL so the iframe can render the
        // conversational view even when the flow is unpublished. The
        // RedirectController accepts a valid signed `?_preview=1` and,
        // for conversational mode, also stores a per-link session flag
        // that ConversationPublicController::start uses to authorise
        // /cv/{alias}/start against draft (unpublished) flows.
        $previewUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'redirect.handle',
            now()->addHours(24),
            ['alias' => $link->alias, '_preview' => 1, '_cv_preview' => 1],
            false
        );

        $flowPayload = [
            'actions' => $flow->actions->map(fn ($a) => [
                'client_id' => 'a' . $a->id,
                'kind'      => $a->kind,
                'label'     => $a->label,
                'payload'   => $a->payload,
            ])->values(),
            'steps'   => $flow->steps->map(fn ($s) => [
                'key'              => $s->key,
                'kind'             => $s->kind,
                'message_text'     => $s->message_text,
                'answer_field'     => $s->answer_field,
                'is_entry'         => (bool) $s->is_entry,
                'skip_if_known'    => (bool) $s->skip_if_known,
                'next_step_key'    => $s->next_step_key,
                'action_client_id' => $s->action_id ? 'a' . $s->action_id : null,
                'input_kind'       => $s->settings['input_kind']  ?? 'text',
                'placeholder'      => $s->settings['placeholder'] ?? null,
                'choices'          => $s->choices->map(fn ($c) => [
                    'label'            => $c->label,
                    'value'            => $c->value,
                    'next_step_key'    => $c->next_step_key,
                    'action_client_id' => $c->action_id ? 'a' . $c->action_id : null,
                ])->values(),
            ])->values(),
        ];

        return view('user.links.conversational.editor', [
            'link'         => $link,
            'flow'         => $flow,
            'flowPayload'  => $flowPayload,
            'stepKinds'    => ConversationStep::KINDS,
            'actionKinds'  => ConversationAction::KINDS,
            'blockOptions' => $blockOptions,
            'previewUrl'   => $previewUrl,
        ]);
    }

    /** Toggle conversational mode on/off. */
    public function toggleMode(Request $request, Link $link)
    {
        $this->authorizeLink($link);
        $on = (bool) $request->boolean('enabled');

        $settings = $link->settings ?? [];
        $settings['biolink'] = $settings['biolink'] ?? [];
        $settings['biolink']['mode'] = $on ? 'conversational' : 'list';
        $link->update(['settings' => $settings]);

        if ($on) {
            $this->ensureFlow($link);
        }
        return response()->json(['ok' => true, 'mode' => $settings['biolink']['mode']]);
    }

    /** Replace the flow definition (steps + choices + actions) wholesale. */
    public function save(Request $request, Link $link)
    {
        $this->authorizeLink($link);
        $flow = $this->ensureFlow($link);

        $data = $request->validate([
            'name'          => 'nullable|string|max:120',
            'intro_message' => 'nullable|string|max:2000',
            'is_published'  => 'nullable|boolean',
            'actions'              => 'nullable|array',
            'actions.*.client_id'  => 'required|string|max:60',
            'actions.*.kind'       => 'required|string|in:' . implode(',', array_keys(ConversationAction::KINDS)),
            'actions.*.label'      => 'nullable|string|max:160',
            'actions.*.payload'    => 'nullable|array',
            'steps'                => 'required|array|min:1|max:30',
            'steps.*.key'          => 'required|string|max:60|regex:/^[a-z0-9_]+$/',
            'steps.*.kind'         => 'required|string|in:' . implode(',', array_keys(ConversationStep::KINDS)),
            'steps.*.message_text' => 'required|string|max:1000',
            'steps.*.answer_field' => 'nullable|string|max:60',
            'steps.*.is_entry'     => 'nullable|boolean',
            'steps.*.skip_if_known'=> 'nullable|boolean',
            'steps.*.next_step_key'=> 'nullable|string|max:60',
            'steps.*.action_client_id' => 'nullable|string|max:60',
            'steps.*.input_kind'   => 'nullable|string|in:text,email',
            'steps.*.placeholder'  => 'nullable|string|max:120',
            'steps.*.choices'      => 'nullable|array|max:6',
            'steps.*.choices.*.label'         => 'required|string|max:120',
            'steps.*.choices.*.value'         => 'required|string|max:120',
            'steps.*.choices.*.next_step_key' => 'nullable|string|max:60',
            'steps.*.choices.*.action_client_id' => 'nullable|string|max:60',
        ]);

        // Step keys must be unique within the flow.
        $stepKeys = array_column($data['steps'], 'key');
        if (count($stepKeys) !== count(array_unique($stepKeys))) {
            return response()->json(['ok' => false, 'error' => 'Step keys must be unique'], 422);
        }

        // Exactly one entry step (default to first if none flagged).
        $entryCount = collect($data['steps'])->where('is_entry', true)->count();
        if ($entryCount === 0) {
            $data['steps'][0]['is_entry'] = true;
        } elseif ($entryCount > 1) {
            return response()->json(['ok' => false, 'error' => 'Only one step can be the entry point'], 422);
        }

        DB::transaction(function () use ($flow, $data) {
            // Bump version so in-flight visitor sessions still see the
            // version they started on (we copy the row but the simplest
            // safe approach is to keep version monotonic on the flow).
            $flow->update([
                'name'          => $data['name'] ?? $flow->name,
                'intro_message' => $data['intro_message'] ?? null,
                'is_published'  => (bool) ($data['is_published'] ?? $flow->is_published),
                'version'       => $flow->version + 1,
            ]);

            // Wipe + rebuild. Cascade deletes choices via FK.
            $flow->steps()->delete();
            $flow->actions()->delete();

            // Create actions first so steps/choices can reference them
            // by client_id -> action_id.
            $actionMap = [];
            foreach ($data['actions'] ?? [] as $a) {
                $action = $flow->actions()->create([
                    'kind'    => $a['kind'],
                    'label'   => $a['label'] ?? null,
                    'payload' => $a['payload'] ?? [],
                ]);
                $actionMap[$a['client_id']] = $action->id;
            }

            $sort = 0;
            foreach ($data['steps'] as $s) {
                $step = $flow->steps()->create([
                    'key'           => $s['key'],
                    'kind'          => $s['kind'],
                    'message_text'  => $s['message_text'],
                    'answer_field'  => $s['answer_field'] ?? null,
                    'is_entry'      => (bool) ($s['is_entry'] ?? false),
                    'skip_if_known' => (bool) ($s['skip_if_known'] ?? true),
                    'sort_order'    => $sort++,
                    'next_step_key' => $s['next_step_key'] ?? null,
                    'action_id'     => $actionMap[$s['action_client_id'] ?? null] ?? null,
                    'settings'      => [
                        'input_kind'  => $s['input_kind']  ?? 'text',
                        'placeholder' => $s['placeholder'] ?? null,
                    ],
                ]);
                foreach ($s['choices'] ?? [] as $cIdx => $c) {
                    $step->choices()->create([
                        'label'         => $c['label'],
                        'value'         => $c['value'],
                        'next_step_key' => $c['next_step_key'] ?? null,
                        'action_id'     => $actionMap[$c['action_client_id'] ?? null] ?? null,
                        'sort_order'    => $cIdx,
                    ]);
                }
            }
        });

        $flow->refresh()->load(['steps.choices', 'actions']);
        return response()->json([
            'ok'      => true,
            'version' => $flow->version,
            'flow'    => $this->flowToArray($flow),
        ]);
    }

    /** Funnel analytics endpoint (returns drop-off + choice distribution). */
    public function analytics(Link $link)
    {
        $this->authorizeLink($link);
        $flow = $this->ensureFlow($link);

        $entered = ConversationStepEvent::where('flow_id', $flow->id)
            ->where('event', ConversationStepEvent::EVENT_ENTERED)
            ->select('step_key', DB::raw('COUNT(*) as c'))
            ->groupBy('step_key')->pluck('c', 'step_key')->all();

        $answered = ConversationStepEvent::where('flow_id', $flow->id)
            ->where('event', ConversationStepEvent::EVENT_ANSWERED)
            ->select('step_key', DB::raw('COUNT(*) as c'))
            ->groupBy('step_key')->pluck('c', 'step_key')->all();

        $choiceDist = ConversationStepEvent::where('flow_id', $flow->id)
            ->where('event', ConversationStepEvent::EVENT_ANSWERED)
            ->whereNotNull('choice_value')
            ->select('step_key', 'choice_value', DB::raw('COUNT(*) as c'))
            ->groupBy('step_key', 'choice_value')->get();

        $totalSessions = ConversationSession::where('flow_id', $flow->id)->count();
        $completed     = ConversationSession::where('flow_id', $flow->id)->where('completed', true)->count();

        $steps = $flow->steps()->get(['key', 'message_text', 'kind', 'sort_order']);
        $funnel = [];
        foreach ($steps as $s) {
            $e = (int) ($entered[$s->key] ?? 0);
            $a = (int) ($answered[$s->key] ?? 0);
            $funnel[] = [
                'key'           => $s->key,
                'kind'          => $s->kind,
                'preview'       => Str::limit($s->message_text, 80),
                'entered'       => $e,
                'answered'      => $a,
                'drop_off_pct'  => $e > 0 ? round((($e - $a) / $e) * 100, 1) : 0,
                'choices'       => $choiceDist->where('step_key', $s->key)
                                    ->map(fn ($r) => ['value' => $r->choice_value, 'count' => (int) $r->c])
                                    ->values(),
            ];
        }

        return response()->json([
            'flow'           => ['id' => $flow->id, 'version' => $flow->version, 'name' => $flow->name],
            'total_sessions' => $totalSessions,
            'completed'      => $completed,
            'completion_pct' => $totalSessions > 0 ? round(($completed / $totalSessions) * 100, 1) : 0,
            'funnel'         => $funnel,
        ]);
    }

    /** Render the analytics page (HTML wrapper). */
    public function analyticsPage(Link $link)
    {
        $this->authorizeLink($link);
        $flow = $this->ensureFlow($link);
        return view('user.links.conversational.analytics', [
            'link' => $link,
            'flow' => $flow,
        ]);
    }

    // ───────────────────────── Internals ─────────────────────────

    protected function authorizeLink(Link $link): void
    {
        abort_if($link->user_id !== workspace_owner_id() || $link->type !== 'biolink', 403);
    }

    /** Lazily create a default flow + sample steps the first time. */
    protected function ensureFlow(Link $link): ConversationFlow
    {
        $flow = ConversationFlow::where('link_id', $link->id)->first();
        if ($flow) return $flow;

        $flow = ConversationFlow::create([
            'link_id'       => $link->id,
            'workspace_id'  => $link->workspace_id ?? null,
            'name'          => 'Conversational Flow',
            'intro_message' => "Hey 👋 quick question — what brings you here today?",
            'is_published'  => false,
        ]);

        // Sample 3-step starter so the editor isn't empty.
        $startAction = $flow->actions()->create([
            'kind'    => ConversationAction::KIND_MESSAGE,
            'label'   => 'Default ending',
            'payload' => ['text' => "Awesome — thanks for letting me know! Tap below to see what's next."],
        ]);

        $entry = $flow->steps()->create([
            'key'          => 'intent',
            'kind'         => ConversationStep::KIND_QUESTION,
            'message_text' => "What are you here for?",
            'is_entry'     => true,
            'sort_order'   => 0,
            'answer_field' => 'intent',
        ]);
        $entry->choices()->createMany([
            ['label' => '🛒 Buy something', 'value' => 'buy',    'next_step_key' => 'thanks', 'sort_order' => 0],
            ['label' => '👀 Just browsing',  'value' => 'browse', 'next_step_key' => 'thanks', 'sort_order' => 1],
            ['label' => '📩 Get in touch',   'value' => 'contact','next_step_key' => 'thanks', 'sort_order' => 2],
        ]);

        $flow->steps()->create([
            'key'          => 'thanks',
            'kind'         => ConversationStep::KIND_END,
            'message_text' => "Got it!",
            'sort_order'   => 1,
            'action_id'    => $startAction->id,
        ]);

        return $flow->fresh();
    }

    public static function blockLabel($block): string
    {
        $type = BiolinkBlock::TYPES[$block->type]['label'] ?? $block->type;
        $title = $block->settings['title'] ?? $block->settings['text'] ?? null;
        return $title ? trim(Str::limit($title, 32) . " ({$type})") : $type;
    }

    protected function flowToArray(ConversationFlow $flow): array
    {
        return [
            'id'            => $flow->id,
            'version'       => $flow->version,
            'name'          => $flow->name,
            'intro_message' => $flow->intro_message,
            'is_published'  => $flow->is_published,
            'actions'       => $flow->actions->map(fn ($a) => [
                'id' => $a->id, 'kind' => $a->kind, 'label' => $a->label, 'payload' => $a->payload,
            ])->values(),
            'steps'         => $flow->steps->map(fn ($s) => [
                'id'            => $s->id,
                'key'           => $s->key,
                'kind'          => $s->kind,
                'message_text'  => $s->message_text,
                'answer_field'  => $s->answer_field,
                'is_entry'      => $s->is_entry,
                'skip_if_known' => $s->skip_if_known,
                'next_step_key' => $s->next_step_key,
                'action_id'     => $s->action_id,
                'choices'       => $s->choices->map(fn ($c) => [
                    'label'         => $c->label,
                    'value'         => $c->value,
                    'next_step_key' => $c->next_step_key,
                    'action_id'     => $c->action_id,
                ])->values(),
            ])->values(),
        ];
    }
}
