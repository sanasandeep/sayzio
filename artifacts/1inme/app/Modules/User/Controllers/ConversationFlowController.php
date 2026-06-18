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
    /** Allowed input kinds — `input` step covers free-text and typed captures. */
    public const INPUT_KINDS = ['text', 'email', 'phone', 'url', 'number'];

    /** Comparison operators usable in branching conditions and choice conditions. */
    public const CONDITION_OPS = ['eq','neq','contains','not_contains','in','gt','lt','exists','empty'];

    /** Editor page (visual flow builder + live preview). */
    public function editor(Link $link)
    {
        $this->authorizeLink($link);
        $flow = self::ensureFlow($link);
        $flow->load(['steps.choices', 'actions']);

        $blocks = $link->biolinkBlocks()->whereNull('parent_id')->get(['id', 'type', 'settings']);
        $blockOptions = $blocks->map(fn ($b) => [
            'id'    => $b->id,
            'type'  => $b->type,
            'label' => self::blockLabel($b),
        ])->values();

        $previewUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'redirect.handle',
            now()->addHours(24),
            ['alias' => $link->alias, '_preview' => 1, '_cv_preview' => 1],
            false
        );

        $flowPayload = self::flowPayload($flow);

        return view('user.links.conversational.editor', [
            'link'         => $link,
            'flow'         => $flow,
            'flowPayload'  => $flowPayload,
            'stepKinds'    => ConversationStep::KINDS,
            'actionKinds'  => ConversationAction::KINDS,
            'blockOptions' => $blockOptions,
            'previewUrl'   => $previewUrl,
            'inputKinds'   => self::INPUT_KINDS,
            'conditionOps' => self::CONDITION_OPS,
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
            self::ensureFlow($link);
        }
        return response()->json(['ok' => true, 'mode' => $settings['biolink']['mode']]);
    }

    /** Replace the flow definition (steps + choices + actions) wholesale. */
    public function save(Request $request, Link $link)
    {
        $this->authorizeLink($link);
        $flow = self::ensureFlow($link);

        $data = $request->validate(self::saveRules());

        if ($err = self::validateFlowData($data)) {
            return response()->json(['ok' => false, 'error' => $err], 422);
        }

        $version = self::persistFlow($flow, $data);

        return response()->json([
            'ok'      => true,
            'version' => $version,
        ]);
    }

    /**
     * Validation rules for a full flow save. Shared by the web editor and
     * the mobile REST API so the two surfaces never drift.
     */
    public static function saveRules(): array
    {
        return [
            'name'          => 'nullable|string|max:120',
            'intro_message' => 'nullable|string|max:2000',
            'is_published'  => 'nullable|boolean',
            'settings'      => 'nullable|array',
            'settings.default_typing_ms' => 'nullable|integer|min:0|max:5000',
            'actions'              => 'nullable|array',
            'actions.*.client_id'  => 'required|string|max:60',
            'actions.*.kind'       => 'required|string|in:' . implode(',', array_keys(ConversationAction::KINDS)),
            'actions.*.label'      => 'nullable|string|max:160',
            'actions.*.payload'    => 'nullable|array',
            'steps'                => 'required|array|min:1|max:30',
            'steps.*.key'          => 'required|string|max:60|regex:/^[a-z0-9_]+$/',
            'steps.*.kind'         => 'required|string|in:' . implode(',', array_keys(ConversationStep::KINDS)),
            'steps.*.message_text' => 'required|string|max:2000',
            'steps.*.answer_field' => 'nullable|string|max:60',
            'steps.*.is_entry'     => 'nullable|boolean',
            'steps.*.skip_if_known'=> 'nullable|boolean',
            'steps.*.next_step_key'=> 'nullable|string|max:60',
            'steps.*.action_client_id' => 'nullable|string|max:60',
            'steps.*.settings'     => 'nullable|array',
            'steps.*.choices'      => 'nullable|array|max:8',
            'steps.*.choices.*.label'         => 'required|string|max:120',
            'steps.*.choices.*.value'         => 'required|string|max:120',
            'steps.*.choices.*.next_step_key' => 'nullable|string|max:60',
            'steps.*.choices.*.action_client_id' => 'nullable|string|max:60',
            'steps.*.choices.*.settings'         => 'nullable|array',
        ];
    }

    /**
     * Post-validation flow checks (unique step keys, single entry, per-step
     * deep validation, dangling references, merge-tag well-formedness).
     * Mutates $data to default the entry flag. Returns an error string when
     * invalid, or null when the flow is safe to persist.
     */
    public static function validateFlowData(array &$data): ?string
    {
        // Step keys must be unique within the flow.
        $stepKeys = array_column($data['steps'], 'key');
        if (count($stepKeys) !== count(array_unique($stepKeys))) {
            return 'Step keys must be unique';
        }
        $stepKeySet = array_flip($stepKeys);

        // Exactly one entry step (default to first if none flagged).
        $entryCount = collect($data['steps'])->where('is_entry', true)->count();
        if ($entryCount === 0) {
            $data['steps'][0]['is_entry'] = true;
        } elseif ($entryCount > 1) {
            return 'Only one step can be the entry point';
        }

        // Per-step + per-choice deep validation. Catches bad regex,
        // empty AI intents, dangling step references, malformed merge
        // tags, and out-of-range constraints — anything that would
        // either crash the runtime or silently break the flow.
        foreach ($data['steps'] as $step) {
            $err = self::validateStepSettings($step, $stepKeySet);
            if ($err) {
                return "Step '{$step['key']}': {$err}";
            }
            // Validate dangling next_step_key
            if (!empty($step['next_step_key']) && !isset($stepKeySet[$step['next_step_key']])) {
                return "Step '{$step['key']}' next step references missing key '{$step['next_step_key']}'";
            }
            foreach ($step['choices'] ?? [] as $c) {
                if (!empty($c['next_step_key']) && !isset($stepKeySet[$c['next_step_key']])) {
                    return "Step '{$step['key']}' choice '{$c['value']}' references missing step '{$c['next_step_key']}'";
                }
                $cerr = self::validateChoiceCondition($c, $stepKeySet);
                if ($cerr) {
                    return "Step '{$step['key']}' choice '{$c['value']}': {$cerr}";
                }
            }
            // Merge-tag well-formedness on bot text.
            if ($mtErr = self::validateMergeTags((string) $step['message_text'])) {
                return "Step '{$step['key']}' message: {$mtErr}";
            }
        }

        return null;
    }

    /** Persist a validated flow definition; returns the new version. */
    public static function persistFlow(ConversationFlow $flow, array $data): int
    {
        DB::transaction(function () use ($flow, $data) {
            $flow->update([
                'name'          => $data['name'] ?? $flow->name,
                'intro_message' => $data['intro_message'] ?? null,
                'is_published'  => (bool) ($data['is_published'] ?? $flow->is_published),
                'settings'      => $data['settings'] ?? $flow->settings,
                'version'       => $flow->version + 1,
            ]);

            $flow->steps()->delete();
            $flow->actions()->delete();

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
                $stepSettings = is_array($s['settings'] ?? null) ? $s['settings'] : [];
                // Normalise legacy keys (input_kind / placeholder used to
                // live as siblings of `settings` in the UI payload).
                if (isset($s['input_kind']))  $stepSettings['input_kind']  = $s['input_kind'];
                if (isset($s['placeholder'])) $stepSettings['placeholder'] = $s['placeholder'];

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
                    'settings'      => $stepSettings,
                ]);
                foreach ($s['choices'] ?? [] as $cIdx => $c) {
                    $step->choices()->create([
                        'label'         => $c['label'],
                        'value'         => $c['value'],
                        'next_step_key' => $c['next_step_key'] ?? null,
                        'action_id'     => $actionMap[$c['action_client_id'] ?? null] ?? null,
                        'sort_order'    => $cIdx,
                        'settings'      => is_array($c['settings'] ?? null) ? $c['settings'] : null,
                    ]);
                }
            }
        });

        return (int) $flow->fresh()->version;
    }

    /** Serialise a flow (steps + choices + actions) for the editor UIs. */
    public static function flowPayload(ConversationFlow $flow): array
    {
        $flow->loadMissing(['steps.choices', 'actions']);

        return [
            'name'           => $flow->name,
            'intro_message'  => $flow->intro_message,
            'is_published'   => (bool) $flow->is_published,
            'settings'       => is_array($flow->settings) ? $flow->settings : [],
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
                'settings'         => is_array($s->settings) ? $s->settings : [],
                'choices'          => $s->choices->map(fn ($c) => [
                    'label'            => $c->label,
                    'value'            => $c->value,
                    'next_step_key'    => $c->next_step_key,
                    'action_client_id' => $c->action_id ? 'a' . $c->action_id : null,
                    'settings'         => is_array($c->settings) ? $c->settings : [],
                ])->values(),
            ])->values(),
        ];
    }

    /**
     * Per-step shape check beyond plain validation rules. Returns an
     * error string when the step is invalid, or null when ok.
     */
    public static function validateStepSettings(array $step, array $stepKeySet): ?string
    {
        $kind = $step['kind'];
        $s = is_array($step['settings'] ?? null) ? $step['settings'] : [];

        // Branching conditions (per step) — array of rules with goto step.
        if (!empty($s['conditions']) && is_array($s['conditions'])) {
            foreach ($s['conditions'] as $cond) {
                if (!is_array($cond)) return 'malformed condition rule';
                $op = (string) ($cond['op'] ?? '');
                if (!in_array($op, self::CONDITION_OPS, true)) {
                    return "unknown condition operator '{$op}'";
                }
                if (empty($cond['field']) || !is_string($cond['field'])) {
                    return 'condition missing field';
                }
                $goto = (string) ($cond['goto'] ?? '');
                if ($goto !== '' && !isset($stepKeySet[$goto])) {
                    return "condition goto references missing step '{$goto}'";
                }
            }
        }

        // Per-step typing delay must be reasonable.
        if (isset($s['typing_delay_ms'])) {
            $d = (int) $s['typing_delay_ms'];
            if ($d < 0 || $d > 8000) return 'typing delay must be between 0 and 8000ms';
        }

        switch ($kind) {
            case ConversationStep::KIND_INPUT:
                $ik = $s['input_kind'] ?? 'text';
                if (!in_array($ik, self::INPUT_KINDS, true)) {
                    return "unsupported input kind '{$ik}'";
                }
                if (!empty($s['validation']) && is_array($s['validation'])) {
                    $v = $s['validation'];
                    if (isset($v['regex']) && $v['regex'] !== '') {
                        // Wrap in `~` delimiters to allow author-friendly raw patterns.
                        if (@preg_match('~' . str_replace('~', '\~', $v['regex']) . '~u', '') === false) {
                            return 'invalid regex pattern';
                        }
                    }
                    if (isset($v['min_length']) && (int) $v['min_length'] < 0) return 'min_length cannot be negative';
                    if (isset($v['max_length']) && (int) $v['max_length'] < 1) return 'max_length must be >= 1';
                    if (isset($v['min'], $v['max']) && (float) $v['min'] > (float) $v['max']) return 'min must be <= max';
                }
                break;

            case ConversationStep::KIND_QUESTION:
                if (!empty($s['multi_select'])) {
                    $min = (int) ($s['min_choices'] ?? 1);
                    $max = (int) ($s['max_choices'] ?? max(1, count($step['choices'] ?? [])));
                    if ($min < 1) return 'multi-select min must be >= 1';
                    if ($max < $min) return 'multi-select max must be >= min';
                    if (empty($step['choices'])) return 'multi-select needs at least one choice';
                }
                break;

            case ConversationStep::KIND_MEDIA:
                $m = $s['media'] ?? null;
                if (!is_array($m) || empty($m['url']) || !is_string($m['url'])) return 'media URL is required';
                $mk = $m['kind'] ?? 'image';
                if (!in_array($mk, ['image','gif','video','audio'], true)) return "unknown media kind '{$mk}'";
                if (!filter_var($m['url'], FILTER_VALIDATE_URL)) return 'media URL is not valid';
                break;

            case ConversationStep::KIND_FILE_UPLOAD:
                $f = $s['file'] ?? [];
                $maxMb = (int) ($f['max_mb'] ?? 10);
                if ($maxMb < 1 || $maxMb > 50) return 'file max_mb must be between 1 and 50';
                break;

            case ConversationStep::KIND_RATING:
                $r = $s['rating'] ?? [];
                $scale = $r['scale'] ?? 'star';
                if (!in_array($scale, ['star','nps','emoji'], true)) return "unsupported rating scale '{$scale}'";
                break;

            case ConversationStep::KIND_DATETIME:
                $d = $s['datetime'] ?? [];
                $mode = $d['mode'] ?? 'datetime';
                if (!in_array($mode, ['date','time','datetime'], true)) return "unsupported datetime mode '{$mode}'";
                break;

            case ConversationStep::KIND_AI_FREETEXT:
                $ai = $s['ai'] ?? [];
                $intents = $ai['intents'] ?? [];
                if (!is_array($intents) || count($intents) < 1) {
                    return 'AI step needs at least one intent';
                }
                foreach ($intents as $intent) {
                    if (!is_array($intent) || empty($intent['value']) || empty($intent['label'])) {
                        return 'each AI intent needs a value + label';
                    }
                    $g = (string) ($intent['next_step_key'] ?? '');
                    if ($g !== '' && !isset($stepKeySet[$g])) {
                        return "AI intent '{$intent['value']}' routes to missing step '{$g}'";
                    }
                }
                $fb = (string) ($ai['fallback_step_key'] ?? '');
                if ($fb === '') return 'AI step needs a fallback step';
                if (!isset($stepKeySet[$fb])) return "AI fallback references missing step '{$fb}'";
                break;
        }

        return null;
    }

    public static function validateChoiceCondition(array $c, array $stepKeySet): ?string
    {
        $cs = is_array($c['settings'] ?? null) ? $c['settings'] : [];
        if (empty($cs['condition'])) return null;
        $cond = $cs['condition'];
        if (!is_array($cond)) return 'malformed choice condition';
        $op = (string) ($cond['op'] ?? '');
        if (!in_array($op, self::CONDITION_OPS, true)) return "unknown condition operator '{$op}'";
        if (empty($cond['field'])) return 'choice condition missing field';
        return null;
    }

    /**
     * Reject `{{...}}` tags with no closing braces or that contain
     * obvious syntax problems. We only support `{{name}}`, `{{step:key}}`
     * and `{{answer:field}}` — anything else we'll treat as literal but
     * still warn on unbalanced braces so the visitor never sees raw `{{`.
     */
    public static function validateMergeTags(string $text): ?string
    {
        $opens  = substr_count($text, '{{');
        $closes = substr_count($text, '}}');
        if ($opens !== $closes) return 'unbalanced merge tag braces (`{{` / `}}`)';
        if (preg_match_all('/\{\{\s*([^}]*?)\s*\}\}/u', $text, $m)) {
            foreach ($m[1] as $tag) {
                $tag = trim($tag);
                if ($tag === '') return 'empty merge tag `{{}}`';
                if (!preg_match('/^[a-z0-9_]+(:[a-z0-9_]+)?$/i', $tag)) {
                    return "invalid merge tag `{{{$tag}}}`";
                }
            }
        }
        return null;
    }

    /** Funnel analytics endpoint (returns drop-off + choice distribution + new step kinds). */
    public function analytics(Link $link)
    {
        $this->authorizeLink($link);
        $flow = self::ensureFlow($link);

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

        $validationFails = ConversationStepEvent::where('flow_id', $flow->id)
            ->where('event', ConversationStepEvent::EVENT_VALIDATION_FAIL)
            ->select('step_key', DB::raw('COUNT(*) as c'))
            ->groupBy('step_key')->pluck('c', 'step_key')->all();

        $aiClassified = ConversationStepEvent::where('flow_id', $flow->id)
            ->where('event', ConversationStepEvent::EVENT_AI_CLASSIFIED)
            ->select('step_key', 'choice_value', DB::raw('COUNT(*) as c'))
            ->groupBy('step_key', 'choice_value')->get();

        $totalSessions = ConversationSession::where('flow_id', $flow->id)->count();
        $completed     = ConversationSession::where('flow_id', $flow->id)->where('completed', true)->count();

        $steps = $flow->steps()->get(['key', 'message_text', 'kind', 'sort_order', 'settings']);
        $funnel = [];
        foreach ($steps as $s) {
            $e = (int) ($entered[$s->key] ?? 0);
            $a = (int) ($answered[$s->key] ?? 0);

            // Multi-select selections come in as comma-joined strings on
            // the choice_value column (set by the runtime). Split them
            // into individual choices so the histogram is meaningful.
            $rawDist = $choiceDist->where('step_key', $s->key);
            $isMulti = !empty($s->settings['multi_select']);
            if ($isMulti) {
                $explode = [];
                foreach ($rawDist as $row) {
                    foreach (array_filter(array_map('trim', explode(',', (string) $row->choice_value))) as $v) {
                        $explode[$v] = ($explode[$v] ?? 0) + (int) $row->c;
                    }
                }
                $choices = collect($explode)->map(fn ($c, $v) => ['value' => (string) $v, 'count' => $c])->values();
            } else {
                $choices = $rawDist->map(fn ($r) => ['value' => $r->choice_value, 'count' => (int) $r->c])->values();
            }

            $row = [
                'key'           => $s->key,
                'kind'          => $s->kind,
                'preview'       => Str::limit($s->message_text, 80),
                'entered'       => $e,
                'answered'      => $a,
                'drop_off_pct'  => $e > 0 ? round((($e - $a) / $e) * 100, 1) : 0,
                'choices'       => $choices,
                'validation_failures' => (int) ($validationFails[$s->key] ?? 0),
            ];

            // Rating distribution + average for rating steps. Ratings
            // are stored as the choice_value (so they slot into the same
            // EVENT_ANSWERED rows used for everything else).
            if ($s->kind === ConversationStep::KIND_RATING) {
                $sum = 0; $n = 0; $hist = [];
                foreach ($rawDist as $r) {
                    $val = is_numeric($r->choice_value) ? (float) $r->choice_value : null;
                    if ($val === null) continue;
                    $hist[(string) $r->choice_value] = (int) $r->c;
                    $sum += $val * (int) $r->c;
                    $n   += (int) $r->c;
                }
                $row['rating'] = [
                    'scale'    => $s->settings['rating']['scale'] ?? 'star',
                    'avg'      => $n > 0 ? round($sum / $n, 2) : null,
                    'count'    => $n,
                    'hist'     => $hist,
                ];
            }

            // AI intent distribution + fallback rate for ai_freetext steps.
            if ($s->kind === ConversationStep::KIND_AI_FREETEXT) {
                $byIntent = [];
                foreach ($aiClassified->where('step_key', $s->key) as $r) {
                    $byIntent[(string) $r->choice_value] = (int) $r->c;
                }
                $total = array_sum($byIntent);
                $fallback = (int) ($byIntent['__fallback__'] ?? 0);
                $row['ai'] = [
                    'intents'        => collect($byIntent)
                        ->map(fn ($c, $v) => ['value' => (string) $v, 'count' => $c])
                        ->values(),
                    'total'          => $total,
                    'fallback'       => $fallback,
                    'fallback_pct'   => $total > 0 ? round(($fallback / $total) * 100, 1) : 0,
                ];
            }

            $funnel[] = $row;
        }

        return response()->json([
            'flow'           => ['id' => $flow->id, 'version' => $flow->version, 'name' => $flow->name],
            'total_sessions' => $totalSessions,
            'completed'      => $completed,
            'completion_pct' => $totalSessions > 0 ? round(($completed / $totalSessions) * 100, 1) : 0,
            'funnel'         => $funnel,
        ]);
    }

    public function analyticsPage(Link $link)
    {
        $this->authorizeLink($link);
        $flow = self::ensureFlow($link);
        return view('user.links.conversational.analytics', [
            'link' => $link,
            'flow' => $flow,
        ]);
    }

    // ───────────────────────── Internals ─────────────────────────

    protected function authorizeLink(Link $link): void
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);
    }

    public static function ensureFlow(Link $link): ConversationFlow
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
}
