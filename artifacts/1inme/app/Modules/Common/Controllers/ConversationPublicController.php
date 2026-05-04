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
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConversationPublicController extends Controller
{
    /** Boot a visitor session for the published flow. */
    public function start(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link) abort(404);

        $previewExpires = (int) session('cv_preview_link_'.$link->id, 0);
        $allowDraft = $previewExpires > now()->getTimestamp();

        $flowQ = ConversationFlow::where('link_id', $link->id);
        if (!$allowDraft) $flowQ->where('is_published', true);
        $flow = $flowQ->orderByDesc('version')->first();
        if (!$flow) {
            return response()->json(['ok' => false, 'error' => 'No published flow'], 404);
        }

        $data = $request->validate([
            'page_session_id' => 'nullable|string|max:64|regex:/^pg_[a-z0-9]{6,60}$/i',
        ]);
        $pageSessionId = $data['page_session_id'] ?? null;

        $known = $this->knownAnswers($flow, $pageSessionId);

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
                'name'              => $flow->name,
                'intro_message'     => $this->renderTemplate((string) $flow->intro_message, $known),
                'version'           => $flow->version,
                'default_typing_ms' => (int) ($snapshot['settings']['default_typing_ms'] ?? 600),
            ],
            'step'    => $this->stepPayloadArr($first, $snapshot, $known),
            'known'   => $known,
        ]);
    }

    /** Visitor answered the current step — branch and return the next step. */
    public function answer(Request $request, string $publicId)
    {
        $session = ConversationSession::where('public_id', $publicId)->firstOrFail();
        $flow = ConversationFlow::findOrFail($session->flow_id);

        $snapshot = $this->ensureSnapshot($session, $flow);
        $current = $snapshot['steps'][$session->current_step_key] ?? null;

        if (!$current) {
            $this->logEvent($session, $flow, (string) $session->current_step_key, ConversationStepEvent::EVENT_COMPLETED);
            $session->update(['completed' => true, 'completed_at' => now()]);
            return response()->json([
                'ok' => true, 'done' => true, 'action' => null,
                'answers' => $session->answers ?? [],
                'note'    => 'This conversation was updated. Thanks for visiting!',
            ]);
        }

        $data = $request->validate([
            'choice_value'   => 'nullable|string|max:120',
            'choice_values'  => 'nullable|array|max:20',
            'choice_values.*'=> 'string|max:120',
            'input_value'    => 'nullable|string|max:5000',
            'rating_value'   => 'nullable|numeric',
            'datetime_value' => 'nullable|string|max:60',
        ]);

        $answers = $session->answers ?? [];
        $path    = $session->path ?? [];
        $field   = $current['answer_field'] ?: $current['key'];

        $nextKey   = $current['next_step_key'] ?? null;
        $actionId  = $current['action_id'] ?? null;
        $logValue  = null;
        $kind      = $current['kind'];

        if ($kind === ConversationStep::KIND_QUESTION) {
            $isMulti = !empty($current['settings']['multi_select']);
            if ($isMulti) {
                $picks = array_values(array_unique(array_filter((array) ($data['choice_values'] ?? []))));
                $min = (int) ($current['settings']['min_choices'] ?? 1);
                $max = (int) ($current['settings']['max_choices'] ?? max(1, count($current['choices'] ?? [])));
                if (count($picks) < $min || count($picks) > $max) {
                    $this->logEvent($session, $flow, $current['key'], ConversationStepEvent::EVENT_VALIDATION_FAIL);
                    return response()->json(['ok' => false, 'error' => "Pick between {$min} and {$max} options"], 422);
                }
                $valid = collect($current['choices'] ?? [])->pluck('value')->all();
                foreach ($picks as $p) {
                    if (!in_array($p, $valid, true)) {
                        return response()->json(['ok' => false, 'error' => 'Unknown choice'], 422);
                    }
                }
                $answers[$field] = $picks;
                $logValue = implode(',', $picks);
                // Multi-select uses the step's `next_step_key` (or
                // conditions evaluated below) — individual choices'
                // routes don't apply when more than one was picked.
            } else {
                if (empty($data['choice_value'])) {
                    return response()->json(['ok' => false, 'error' => 'Choose an option'], 422);
                }
                $choice = collect($current['choices'] ?? [])->firstWhere('value', $data['choice_value']);
                if (!$choice) return response()->json(['ok' => false, 'error' => 'Unknown choice'], 422);
                $answers[$field] = $choice['value'];
                $logValue = $choice['value'];
                // Choice-level condition can override the route. Handy
                // for "if budget = high go premium, else demo".
                $cs = $choice['settings'] ?? [];
                $routedByCondition = false;
                if (!empty($cs['condition']) && is_array($cs['condition'])) {
                    if ($this->evalCondition($cs['condition'], $answers)
                        && !empty($cs['condition']['goto'])) {
                        $nextKey = $cs['condition']['goto'];
                        $routedByCondition = true;
                    }
                }
                if (!$routedByCondition) {
                    $nextKey  = $choice['next_step_key'] ?: $nextKey;
                    $actionId = $choice['action_id'] ?: $actionId;
                }
            }
        } elseif ($kind === ConversationStep::KIND_INPUT) {
            $val = trim((string) ($data['input_value'] ?? ''));
            if ($val === '') {
                return response()->json(['ok' => false, 'error' => 'Please enter a value'], 422);
            }
            $err = $this->validateInput($current, $val);
            if ($err) {
                $this->logEvent($session, $flow, $current['key'], ConversationStepEvent::EVENT_VALIDATION_FAIL);
                return response()->json(['ok' => false, 'error' => $err], 422);
            }
            $answers[$field] = $val;
            $logValue = mb_substr($val, 0, 60);
        } elseif ($kind === ConversationStep::KIND_RATING) {
            if (!isset($data['rating_value']) || !is_numeric($data['rating_value'])) {
                return response()->json(['ok' => false, 'error' => 'Pick a rating'], 422);
            }
            $r = $current['settings']['rating'] ?? [];
            $scale = $r['scale'] ?? 'star';
            $defaultRange = $scale === 'nps' ? [0, 10] : ($scale === 'emoji' ? [1, 5] : [1, 5]);
            $min = (int) ($r['min'] ?? $defaultRange[0]);
            $max = (int) ($r['max'] ?? $defaultRange[1]);
            $v = (float) $data['rating_value'];
            if ($v < $min || $v > $max) {
                $this->logEvent($session, $flow, $current['key'], ConversationStepEvent::EVENT_VALIDATION_FAIL);
                return response()->json(['ok' => false, 'error' => "Rating must be {$min}–{$max}"], 422);
            }
            $answers[$field] = $v;
            $logValue = (string) $v;
        } elseif ($kind === ConversationStep::KIND_DATETIME) {
            $raw = trim((string) ($data['datetime_value'] ?? ''));
            if ($raw === '') {
                return response()->json(['ok' => false, 'error' => 'Pick a date / time'], 422);
            }
            $d = $current['settings']['datetime'] ?? [];
            $mode = $d['mode'] ?? 'datetime';
            $err = $this->validateDateTime($mode, $raw, $d);
            if ($err) {
                $this->logEvent($session, $flow, $current['key'], ConversationStepEvent::EVENT_VALIDATION_FAIL);
                return response()->json(['ok' => false, 'error' => $err], 422);
            }
            $answers[$field] = $raw;
            $logValue = $raw;
        } elseif ($kind === ConversationStep::KIND_AI_FREETEXT) {
            $val = trim((string) ($data['input_value'] ?? ''));
            if ($val === '') {
                return response()->json(['ok' => false, 'error' => 'Type a reply'], 422);
            }
            [$intent, $confidence] = $this->classifyAi($flow, $current, $val);
            $ai = $current['settings']['ai'] ?? [];
            $intents = $ai['intents'] ?? [];
            $matched = collect($intents)->firstWhere('value', $intent);
            if (!$matched || $confidence < (float) ($ai['min_confidence'] ?? 0.4)) {
                $nextKey = $ai['fallback_step_key'] ?? $nextKey;
                $logValue = '__fallback__';
                $answers[$field] = $val;
                $answers[$field . '_intent'] = '__fallback__';
            } else {
                $nextKey = $matched['next_step_key'] ?? $nextKey;
                $logValue = (string) $matched['value'];
                $answers[$field] = $val;
                $answers[$field . '_intent'] = $matched['value'];
            }
            // Log ai classification distinctly so analytics can split
            // intent distribution from regular `answered` rows.
            $this->logEvent($session, $flow, $current['key'], ConversationStepEvent::EVENT_AI_CLASSIFIED, $logValue);
        } elseif ($kind === ConversationStep::KIND_FILE_UPLOAD) {
            // File upload comes via captureFile() — treat any direct
            // POST here without a stored file pointer as missing.
            if (empty($answers[$field . '_url'])) {
                return response()->json(['ok' => false, 'error' => 'Upload a file first'], 422);
            }
            $logValue = mb_substr((string) $answers[$field . '_url'], 0, 60);
        }
        // KIND_MEDIA / KIND_MESSAGE / KIND_END auto-advance.

        $this->logEvent($session, $flow, $current['key'], ConversationStepEvent::EVENT_ANSWERED, $logValue);

        // Step-level conditions evaluated after the answer is captured.
        // First match wins; falls through to the previously resolved
        // nextKey / choice-level route otherwise.
        $stepConds = $current['settings']['conditions'] ?? [];
        if (is_array($stepConds)) {
            foreach ($stepConds as $cond) {
                if (!is_array($cond)) continue;
                if ($this->evalCondition($cond, $answers) && !empty($cond['goto'])) {
                    $nextKey = $cond['goto'];
                    break;
                }
            }
        }

        $merged = $this->persistMemory($session, $flow, $current, $answers);
        if ($merged) {
            $answers = array_merge($merged, $answers);
        }

        $next = $nextKey ? ($snapshot['steps'][$nextKey] ?? null) : null;
        if ($next) {
            $next = $this->advanceWhileKnownArr($snapshot, $next, $answers);
        }

        if (!$next || $current['kind'] === ConversationStep::KIND_END) {
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
                'action'   => $action ? $this->actionPayloadArr($action, (int) $flow->link_id, $answers) : null,
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
            'step'    => $this->stepPayloadArr($next, $snapshot, $answers),
            'answers' => $answers,
        ]);
    }

    /**
     * File-upload step: visitor POSTs a multipart file. We enforce the
     * creator's max-size + accepted-types constraints, store the file
     * on the public disk, and stash the resolved URL on the session
     * answers under `<field>_url` so the next answer() call can
     * advance the flow as if the upload were the answer.
     */
    public function captureFile(Request $request, string $publicId)
    {
        $session = ConversationSession::where('public_id', $publicId)->firstOrFail();
        $flow = ConversationFlow::findOrFail($session->flow_id);
        $snapshot = $this->ensureSnapshot($session, $flow);
        $current = $snapshot['steps'][$session->current_step_key] ?? null;
        if (!$current || $current['kind'] !== ConversationStep::KIND_FILE_UPLOAD) {
            return response()->json(['ok' => false, 'error' => 'No file step'], 422);
        }

        $cfg   = $current['settings']['file'] ?? [];
        $maxKb = (int) ($cfg['max_mb'] ?? 10) * 1024;
        $accept = trim((string) ($cfg['accept'] ?? ''));

        $rules = ['file' => "required|file|max:{$maxKb}"];
        if ($accept !== '') {
            // Author-friendly comma-list of extensions: "pdf,jpg,png"
            $exts = collect(explode(',', $accept))
                ->map(fn ($e) => ltrim(trim($e), '.'))
                ->filter()->values()->all();
            if ($exts) $rules['file'] .= '|mimes:' . implode(',', $exts);
        }

        try {
            $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->logEvent($session, $flow, $current['key'], ConversationStepEvent::EVENT_VALIDATION_FAIL);
            $first = collect($e->errors())->flatten()->first();
            return response()->json(['ok' => false, 'error' => $first ?: 'File rejected'], 422);
        }

        $file = $request->file('file');
        $name = Str::random(20) . '.' . strtolower($file->getClientOriginalExtension() ?: 'bin');
        try {
            $path = $file->storeAs('cv_uploads/' . date('Y/m'), $name, 'public');
            $url  = Storage::disk('public')->url($path);
        } catch (\Throwable $ex) {
            return response()->json(['ok' => false, 'error' => 'Upload failed'], 500);
        }

        $field = $current['answer_field'] ?: $current['key'];
        $answers = $session->answers ?? [];

        // Re-upload on the same step: drop the previous file so it doesn't
        // become an orphan. The nightly cv-uploads:prune-abandoned sweep
        // would catch it eventually, but cleaning up at write time keeps
        // disk usage tight and avoids leaking the prior file for `--days`.
        $prevPath = $this->resolveStoredPath($answers[$field . '_path'] ?? null, $answers[$field . '_url'] ?? null);
        if ($prevPath && $prevPath !== $path) {
            try {
                Storage::disk('public')->delete($prevPath);
            } catch (\Throwable $ex) {
                logger()->warning('Failed deleting replaced cv_upload ' . $prevPath . ': ' . $ex->getMessage());
            }
        }

        $answers[$field]           = $file->getClientOriginalName();
        $answers[$field . '_url']  = $url;
        $answers[$field . '_path'] = $path;
        $answers[$field . '_size'] = $file->getSize();
        $session->update(['answers' => $answers]);

        return response()->json(['ok' => true, 'url' => $url, 'name' => $file->getClientOriginalName()]);
    }

    /**
     * Recover a public-disk relative path (e.g. `cv_uploads/2026/05/foo.bin`)
     * from a stored `_path` answer, or fall back to parsing it out of the
     * `_url` we wrote earlier. Only paths inside `cv_uploads/` are returned
     * so a stray value can never trigger a delete elsewhere on the disk.
     */
    protected function resolveStoredPath(mixed $storedPath, mixed $storedUrl): ?string
    {
        if (is_string($storedPath) && str_starts_with($storedPath, 'cv_uploads/')) {
            return $storedPath;
        }
        if (is_string($storedUrl) && $storedUrl !== '') {
            $urlPath = parse_url($storedUrl, PHP_URL_PATH) ?: $storedUrl;
            if (preg_match('#(cv_uploads/[A-Za-z0-9_/\-.]+)#', $urlPath, $m)) {
                return $m[1];
            }
        }
        return null;
    }

    public function captureEmail(Request $request, string $publicId)
    {
        $session = ConversationSession::where('public_id', $publicId)->first();
        if (!$session) abort(404);

        $data = $request->validate(['email' => 'required|email|max:190']);
        $email = mb_strtolower(trim($data['email']));

        $flow = ConversationFlow::find($session->flow_id);
        if (!$flow) return response()->json(['ok' => false, 'error' => 'No flow'], 422);

        $answers = is_array($session->answers) ? $session->answers : [];
        $answers['email'] = $email;

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

    public function drop(Request $request, string $publicId)
    {
        $session = ConversationSession::where('public_id', $publicId)->first();
        if (!$session || $session->completed) return response()->json(['ok' => true]);
        $this->logEvent($session, $session->flow, (string) $session->current_step_key, ConversationStepEvent::EVENT_DROPPED);
        return response()->json(['ok' => true]);
    }

    // ─────────────────────── Helpers ───────────────────────

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
                    'settings'      => $c->settings ?? [],
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
        return [
            'entry_key' => $entryKey,
            'steps'     => $steps,
            'actions'   => $actions,
            'settings'  => is_array($flow->settings) ? $flow->settings : [],
        ];
    }

    protected function renderBlockHtml(int $blockId, int $linkId): ?string
    {
        try {
            $block = \App\Modules\User\Models\BiolinkBlock::withoutGlobalScope('workspace')
                ->where('id', $blockId)
                ->where('link_id', $linkId)
                ->first();
            if (!$block) return null;
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

    protected function stepPayloadArr(array $step, array $snapshot, array $answers): array
    {
        $defaultDelay = (int) ($snapshot['settings']['default_typing_ms'] ?? 600);
        $stepDelay = (int) ($step['settings']['typing_delay_ms'] ?? $defaultDelay);
        // Render merge tags against the live answers so previous
        // captures show up in the next bot bubble.
        $message = $this->renderTemplate((string) $step['message_text'], $answers);

        $payload = [
            'key'          => $step['key'],
            'kind'         => $step['kind'],
            'message_text' => $message,
            'typing_ms'    => max(0, $stepDelay),
            'choices'      => array_map(
                fn ($c) => [
                    'label' => $this->renderTemplate((string) $c['label'], $answers),
                    'value' => $c['value'],
                ],
                $step['choices'] ?? []
            ),
            'input_kind'   => $step['settings']['input_kind'] ?? 'text',
            'placeholder'  => $step['settings']['placeholder'] ?? null,
            'multi_select' => !empty($step['settings']['multi_select']),
            'min_choices'  => (int) ($step['settings']['min_choices'] ?? 1),
            'max_choices'  => (int) ($step['settings']['max_choices'] ?? max(1, count($step['choices'] ?? []))),
            'validation'   => $step['settings']['validation'] ?? null,
        ];

        if ($step['kind'] === ConversationStep::KIND_MEDIA) {
            $payload['media'] = $step['settings']['media'] ?? null;
        }
        if ($step['kind'] === ConversationStep::KIND_FILE_UPLOAD) {
            $payload['file']   = $step['settings']['file'] ?? ['max_mb' => 10, 'accept' => ''];
        }
        if ($step['kind'] === ConversationStep::KIND_RATING) {
            $payload['rating'] = $step['settings']['rating'] ?? ['scale' => 'star', 'min' => 1, 'max' => 5];
        }
        if ($step['kind'] === ConversationStep::KIND_DATETIME) {
            $payload['datetime'] = $step['settings']['datetime'] ?? ['mode' => 'datetime'];
        }
        if ($step['kind'] === ConversationStep::KIND_AI_FREETEXT) {
            $payload['placeholder'] = $payload['placeholder'] ?: 'Type your reply…';
            $payload['input_kind']  = 'text';
        }

        return $payload;
    }

    protected function actionPayloadArr(array $action, int $linkId = 0, array $answers = []): array
    {
        $payload = $action['payload'] ?? [];
        $resolved = ['kind' => $action['kind'], 'label' => $this->renderTemplate((string) $action['label'], $answers)];
        switch ($action['kind']) {
            case ConversationAction::KIND_OPEN_LINK:
                $resolved['url'] = $this->renderTemplate((string) ($payload['url'] ?? ''), $answers);
                break;
            case ConversationAction::KIND_SHOW_BLOCK:
                $blockId = $payload['block_id'] ?? null;
                $resolved['block_id'] = $blockId;
                $resolved['html']     = ($blockId && $linkId) ? $this->renderBlockHtml((int) $blockId, $linkId) : null;
                break;
            case ConversationAction::KIND_BOOK_CALENDAR:
                $resolved['url'] = $this->renderTemplate((string) ($payload['booking_url'] ?? ''), $answers);
                break;
            case ConversationAction::KIND_MESSAGE:
                $resolved['text'] = $this->renderTemplate((string) ($payload['text'] ?? ''), $answers);
                break;
            case ConversationAction::KIND_CAPTURE_EMAIL:
                $resolved['cta'] = $payload['cta'] ?? 'Subscribe';
                break;
        }
        return $resolved;
    }

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
            // Step-level conditions also apply when fast-forwarding so
            // a returning visitor lands on the same branch they would
            // have walked the first time.
            foreach ($step['settings']['conditions'] ?? [] as $cond) {
                if (is_array($cond) && $this->evalCondition($cond, $answers) && !empty($cond['goto'])) {
                    $nextKey = $cond['goto'];
                    break;
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
     * Resolve `{{name}}`, `{{step:key}}`, `{{answer:field}}` against the
     * answers map. Unknown / missing tags fall back to an empty string
     * (so visitors never see literal `{{...}}` text).
     */
    protected function renderTemplate(string $tpl, array $answers): string
    {
        if ($tpl === '' || strpos($tpl, '{{') === false) return $tpl;
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)(?::([a-z0-9_]+))?\s*\}\}/i', function ($m) use ($answers) {
            $ns = strtolower($m[1]);
            $key = $m[2] ?? null;
            $lookupKey = $key ?: $ns;
            if ($ns === 'step' || $ns === 'answer') {
                $v = $answers[$key] ?? '';
            } else {
                $v = $answers[$lookupKey] ?? '';
            }
            if (is_array($v)) $v = implode(', ', $v);
            return (string) $v;
        }, $tpl);
    }

    /** Evaluate a single condition rule against the current answers. */
    protected function evalCondition(array $cond, array $answers): bool
    {
        $field = $cond['field'] ?? null;
        if (!$field) return false;
        $actual = $answers[$field] ?? null;
        $expected = $cond['value'] ?? null;
        switch ($cond['op'] ?? 'eq') {
            case 'eq':  return is_array($actual) ? in_array($expected, $actual, false) : (string) $actual === (string) $expected;
            case 'neq': return is_array($actual) ? !in_array($expected, $actual, false) : (string) $actual !== (string) $expected;
            case 'contains':
                return is_array($actual)
                    ? in_array($expected, $actual, false)
                    : (is_string($actual) && stripos($actual, (string) $expected) !== false);
            case 'not_contains':
                return !(is_array($actual)
                    ? in_array($expected, $actual, false)
                    : (is_string($actual) && stripos($actual, (string) $expected) !== false));
            case 'in':
                $arr = is_array($expected) ? $expected : array_map('trim', explode(',', (string) $expected));
                return in_array((string) $actual, array_map('strval', $arr), true);
            case 'gt': return is_numeric($actual) && (float) $actual >  (float) $expected;
            case 'lt': return is_numeric($actual) && (float) $actual <  (float) $expected;
            case 'exists': return $actual !== null && $actual !== '';
            case 'empty':  return $actual === null || $actual === '' || (is_array($actual) && empty($actual));
        }
        return false;
    }

    /** Per-step input validation. Returns error string or null. */
    protected function validateInput(array $step, string $value): ?string
    {
        $kind = $step['settings']['input_kind'] ?? 'text';
        $v    = $step['settings']['validation'] ?? [];
        $msg  = $v['error_message'] ?? null;

        switch ($kind) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) return $msg ?: 'Please enter a valid email';
                break;
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) return $msg ?: 'Please enter a valid URL';
                break;
            case 'phone':
                // Loose international phone match — digits, spaces, +, -, parens, min 7 digits.
                if (!preg_match('/^[\d\s+\-()]{7,}$/', $value)) return $msg ?: 'Please enter a valid phone number';
                break;
            case 'number':
                if (!is_numeric($value)) return $msg ?: 'Please enter a number';
                if (isset($v['min']) && (float) $value < (float) $v['min']) return $msg ?: "Must be at least {$v['min']}";
                if (isset($v['max']) && (float) $value > (float) $v['max']) return $msg ?: "Must be at most {$v['max']}";
                break;
        }
        if (isset($v['min_length']) && mb_strlen($value) < (int) $v['min_length']) {
            return $msg ?: "Must be at least {$v['min_length']} characters";
        }
        if (isset($v['max_length']) && mb_strlen($value) > (int) $v['max_length']) {
            return $msg ?: "Must be at most {$v['max_length']} characters";
        }
        if (!empty($v['regex'])) {
            $pattern = '~' . str_replace('~', '\~', $v['regex']) . '~u';
            // @ to suppress runtime warnings if a creator slipped past validation.
            if (@preg_match($pattern, $value) !== 1) return $msg ?: 'That doesn\'t look right';
        }
        return null;
    }

    protected function validateDateTime(string $mode, string $raw, array $cfg): ?string
    {
        try {
            $tz = $cfg['timezone'] ?? null;
            $dt = $tz ? new \DateTimeImmutable($raw, new \DateTimeZone($tz)) : new \DateTimeImmutable($raw);
        } catch (\Throwable $e) {
            return 'Pick a valid date / time';
        }
        if (!empty($cfg['min'])) {
            try {
                $min = new \DateTimeImmutable($cfg['min']);
                if ($dt < $min) return 'Pick a later date / time';
            } catch (\Throwable $e) {}
        }
        if (!empty($cfg['max'])) {
            try {
                $max = new \DateTimeImmutable($cfg['max']);
                if ($dt > $max) return 'Pick an earlier date / time';
            } catch (\Throwable $e) {}
        }
        return null;
    }

    /**
     * Classify a free-text reply into one of the configured intents.
     * Returns [intentValue, confidence(0..1)]. Falls back to '__none__'
     * with confidence 0 when the AI is unavailable or unconfigured —
     * the caller routes those to the configured fallback step.
     */
    protected function classifyAi(ConversationFlow $flow, array $step, string $text): array
    {
        $ai = $step['settings']['ai'] ?? [];
        $intents = $ai['intents'] ?? [];
        if (empty($intents)) return ['__none__', 0.0];

        // Without an OpenAI key we can't classify — fall back gracefully.
        if (!AiEngineSettings::openAiKey()) return ['__none__', 0.0];

        // Charge the *flow owner's* AI credits, mirroring how the
        // Companion runtime works. If that user is missing or has no
        // balance we degrade to fallback rather than error the visitor.
        try {
            $link = Link::find($flow->link_id);
            $owner = $link ? User::find($link->user_id) : null;
            if (!$owner) return ['__none__', 0.0];

            $intentLines = [];
            foreach ($intents as $i) {
                $val   = (string) ($i['value'] ?? '');
                $label = (string) ($i['label'] ?? $val);
                $ex    = trim((string) ($i['examples'] ?? ''));
                $intentLines[] = "- {$val} ({$label})" . ($ex !== '' ? " — examples: {$ex}" : '');
            }
            $catalog = implode("\n", $intentLines);

            $system = "You classify a user's chat reply into exactly ONE of the listed intents. "
                    . "Respond with strict JSON: {\"intent\":\"<value>\",\"confidence\":0.0-1.0}. "
                    . "If none of the intents fit, return intent='__none__' with confidence 0.";
            $user   = "Intents:\n{$catalog}\n\nReply: " . mb_substr($text, 0, 800);

            $service = app(OpenAiService::class);
            $model   = $ai['model'] ?? AiEngineSettings::DEFAULT_FEATURE_MODEL;
            $resp = $service->chat($owner, $model, [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ], [
                'temperature'     => 0,
                'max_tokens'      => 60,
                'response_format' => ['type' => 'json_object'],
                'feature'         => 'cv_intent_classify',
                'reason'          => 'Conversational AI intent routing',
            ]);

            $content = trim((string) ($resp['content'] ?? ''));
            $parsed = json_decode($content, true);
            if (!is_array($parsed)) return ['__none__', 0.0];
            $intent = (string) ($parsed['intent'] ?? '__none__');
            $conf   = max(0.0, min(1.0, (float) ($parsed['confidence'] ?? 0.0)));
            return [$intent, $conf];
        } catch (\Throwable $e) {
            logger()->warning('Conversational AI classify failed: ' . $e->getMessage());
            return ['__none__', 0.0];
        }
    }

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

    protected function persistMemory(ConversationSession $session, ConversationFlow $flow, array $step, array $answers): array
    {
        $field = $step['answer_field'] ?: $step['key'];
        if ($step['kind'] !== ConversationStep::KIND_INPUT) return [];
        $value = $answers[$field] ?? null;
        if (!$value || !is_string($value)) return [];

        $isEmail = (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
        if (!$isEmail) return [];

        $prior = [];
        try {
            $userId      = $session->link->user_id;
            $workspaceId = $session->link->workspace_id ?? $userId;

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
