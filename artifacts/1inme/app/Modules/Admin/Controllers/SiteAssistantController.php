<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\SiteAssistantConversation;
use App\Modules\Common\Models\SiteAssistantMessage;
use App\Modules\Common\Models\SiteAssistantPageHint;
use App\Modules\Common\Models\SiteAssistantResponseTemplate;
use App\Services\AI\SiteAssistantSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteAssistantController extends Controller
{
    // ── Settings ──────────────────────────────────────────────
    public function edit()
    {
        // Build a small dropdown of admin-capable users to pick the
        // billing account for anonymous turns. Cap at 100 to keep the
        // form light; an empty selection means "auto-detect".
        $billingCandidates = \App\Modules\User\Models\User::query()
            ->whereHas('roles.permissions', fn ($q) => $q->where('key', 'settings.manage'))
            ->orderBy('id')
            ->limit(100)
            ->get(['id','name','email']);

        $chatModels = collect(\App\Services\AI\AiEngineSettings::models())
            ->filter(fn ($m) => ($m['kind'] ?? 'chat') === 'chat' && ($m['enabled'] ?? false))
            ->pluck('name')->values()->all();

        $platformMinds = \App\Modules\User\Models\AiMind::query()
            ->whereNull('user_id')
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id','name','is_default']);

        return view('admin.site-assistant.edit', [
            'cfg'                => SiteAssistantSettings::get(),
            'monthly_spend'      => SiteAssistantSettings::monthlySpend(),
            'totals'             => $this->totals(),
            'billingCandidates'  => $billingCandidates,
            'chatModels'         => $chatModels,
            'platformMinds'      => $platformMinds,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled_marketing'        => 'nullable|boolean',
            'enabled_app'              => 'nullable|boolean',
            'launcher_position'        => 'nullable|in:bottom-right,bottom-left',
            'accent_color'             => 'nullable|string|max:16',
            'avatar_url'               => 'nullable|string|max:500',
            'greeting'                 => 'nullable|string|max:500',
            'system_prompt'            => 'nullable|string|max:8000',
            'model'                    => 'nullable|string|max:64',
            'mind_ids'                 => 'nullable|array',
            'mind_ids.*'               => 'integer|min:1',
            'temperature'              => 'nullable|numeric|min:0|max:2',
            'max_tokens'               => 'nullable|integer|min:64|max:4000',
            'billing_user_id'          => [
                'nullable', 'integer', 'min:1',
                function ($attribute, $value, $fail) {
                    if (!$value) return;
                    $hasRole = \App\Modules\User\Models\User::query()
                        ->where('id', (int) $value)
                        ->whereHas('roles.permissions', fn ($q) => $q->where('key', 'settings.manage'))
                        ->exists();
                    if (!$hasRole) {
                        $fail('Selected billing user must hold the platform settings.manage permission.');
                    }
                },
            ],
            'monthly_budget_credits'   => 'nullable|integer|min:0|max:100000000',
            'session_rate_per_minute'  => 'nullable|integer|min:1|max:120',
            'handoff_enabled'          => 'nullable|boolean',
            'handoff_freeze_after'     => 'nullable|boolean',
            'low_balance_multiplier'         => 'nullable|integer|min:1|max:50',
            'low_balance_default_credits'    => 'nullable|integer|min:1|max:100000',
            'low_balance_message_signed_in'  => 'nullable|string|max:500',
            'low_balance_message_anonymous'  => 'nullable|string|max:500',
            'low_balance_message_locales'              => 'nullable|array|max:50',
            'low_balance_message_locales.*'            => 'array',
            'low_balance_message_locales.*.signed_in'  => 'nullable|string|max:500',
            'low_balance_message_locales.*.anonymous'  => 'nullable|string|max:500',
            'starter_prompts'          => 'nullable|array',
            'starter_prompts.*'        => 'nullable|string|max:200',
        ]);
        $payload = [
            'enabled_marketing'       => $request->boolean('enabled_marketing'),
            'enabled_app'             => $request->boolean('enabled_app'),
            'launcher_position'       => $data['launcher_position']    ?? 'bottom-right',
            'accent_color'            => $data['accent_color']         ?? '#7c3aed',
            'avatar_url'              => $data['avatar_url']           ?: null,
            'greeting'                => $data['greeting']             ?? '',
            'system_prompt'           => $data['system_prompt']        ?? SiteAssistantSettings::defaultSystemPrompt(),
            'model'                   => trim((string) ($data['model'] ?? '')),
            'mind_ids'                => array_values(array_unique(array_map('intval', (array) ($data['mind_ids'] ?? [])))),
            'temperature'             => (float) ($data['temperature'] ?? 0.4),
            'max_tokens'              => (int)   ($data['max_tokens']  ?? 800),
            'billing_user_id'         => isset($data['billing_user_id']) && (int) $data['billing_user_id'] > 0 ? (int) $data['billing_user_id'] : null,
            'monthly_budget_credits'  => (int)   ($data['monthly_budget_credits'] ?? 0),
            'session_rate_per_minute' => (int)   ($data['session_rate_per_minute'] ?? 12),
            'handoff_enabled'         => $request->boolean('handoff_enabled'),
            'handoff_freeze_after'    => $request->boolean('handoff_freeze_after'),
            'low_balance_multiplier'        => max(1, (int) ($data['low_balance_multiplier'] ?? 3)),
            'low_balance_default_credits'   => max(1, (int) ($data['low_balance_default_credits'] ?? 50)),
            'low_balance_message_signed_in' => trim((string) ($data['low_balance_message_signed_in'] ?? '')),
            'low_balance_message_anonymous' => trim((string) ($data['low_balance_message_anonymous'] ?? '')),
            'low_balance_message_locales'   => SiteAssistantSettings::normalizeLowBalanceLocales(
                (array) $request->input('low_balance_message_locales', [])
            ),
            'starter_prompts'         => array_values(array_filter(array_map(
                fn ($s) => trim((string) $s),
                (array) ($data['starter_prompts'] ?? [])
            ))),
        ];
        SiteAssistantSettings::update($payload);
        return redirect()->route('admin.site-assistant.edit')->with('success', 'Site Assistant settings saved.');
    }

    // ── Knowledge Base wrapper ────────────────────────────────
    /**
     * Lightweight knowledge base management surface for the site
     * assistant. Lists every platform Mind (the ones the assistant
     * may retrieve over) with chunk counts, source counts, and a
     * one-click re-index. Underlying CRUD lives in the AI Mind admin.
     */
    public function knowledge()
    {
        $minds = \App\Modules\User\Models\AiMind::query()
            ->whereNull('user_id')
            ->withCount(['sources', 'chunks'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
        $picked = array_map('intval', (array) (SiteAssistantSettings::get()['mind_ids'] ?? []));
        return view('admin.site-assistant.knowledge', compact('minds', 'picked'));
    }

    public function reindexKnowledge(\App\Modules\User\Models\AiMind $mind)
    {
        abort_unless(is_null($mind->user_id), 404);
        foreach ($mind->sources as $s) {
            $s->forceFill(['status' => \App\Modules\User\Models\AiMindSource::STATUS_QUEUED])->save();
            \App\Jobs\IngestAiMindSourceJob::dispatch($s->id);
        }
        return back()->with('success', "Re-index queued for \"{$mind->name}\".");
    }

    // ── Knowledge Sources (per-page custom content) ───────────
    /**
     * List the URLs / pasted text the admin has curated for the
     * site-wide assistant. These all live in a dedicated platform Mind
     * (auto-created on first use). Each source can optionally be
     * scoped to a route name pattern OR URL path so the runtime can
     * prefer it on the matching marketing page.
     */
    public function sources(Request $request)
    {
        $mind = SiteAssistantSettings::ensureAssistantMind();
        $perPage = 50;
        $baseQuery = \App\Modules\User\Models\AiMindSource::query()
            ->where('mind_id', $mind->id)
            ->whereIn('type', [
                \App\Modules\User\Models\AiMindSource::TYPE_LINK,
                \App\Modules\User\Models\AiMindSource::TYPE_TEXT,
                \App\Modules\User\Models\AiMindSource::TYPE_DOCUMENT,
            ])
            ->orderByDesc('id');

        // When the transcript viewer deep-links to a specific source
        // (?focus=…), jump to whichever page that row lives on so the
        // #source-{id} anchor actually resolves on screen.
        $focusId = (int) $request->get('focus', 0);
        if ($focusId > 0 && !$request->has('page')) {
            $focusRow = (clone $baseQuery)->where('id', $focusId)->first();
            if ($focusRow) {
                $position = (clone $baseQuery)->where('id', '>', $focusRow->id)->count();
                $page = intdiv($position, $perPage) + 1;
                if ($page > 1) {
                    $url = route('admin.site-assistant.sources', [
                        'page'  => $page,
                        'focus' => $focusId,
                    ]).'#source-'.$focusId;
                    return redirect()->to($url);
                }
            }
        }

        $sources = $baseQuery->paginate($perPage)->withQueryString();

        // Tally how many assistant messages cited each source since the
        // start of the current month. Citations are stored as a jsonb
        // array on each message — expand with jsonb_array_elements so we
        // can group by the source id without pulling rows into PHP.
        $monthStart = now()->startOfMonth();
        $usageRows = \Illuminate\Support\Facades\DB::select(
            "SELECT (cit->>'id')::bigint AS source_id, COUNT(DISTINCT m.id) AS uses
             FROM site_assistant_messages m
             CROSS JOIN LATERAL jsonb_array_elements(m.citations) AS cit
             WHERE m.role = 'assistant'
               AND m.created_at >= ?
               AND m.citations IS NOT NULL
               AND jsonb_typeof(m.citations) = 'array'
             GROUP BY source_id",
            [$monthStart]
        );
        $usageThisMonth = [];
        foreach ($usageRows as $row) {
            $usageThisMonth[(int) $row->source_id] = (int) $row->uses;
        }

        return view('admin.site-assistant.sources', compact('mind', 'sources', 'usageThisMonth'));
    }

    public function storeSource(Request $request)
    {
        $data = $request->validate([
            'kind'              => ['required', Rule::in(['url', 'text', 'document'])],
            'title'             => 'required|string|max:200',
            'url'               => 'nullable|url|max:2048',
            'body'              => 'nullable|string|max:50000',
            'file'              => 'nullable|file|max:25600|mimes:pdf,docx,doc,rtf,pptx,txt,md',
            'page_pattern'      => 'nullable|string|max:200',
            'assistant_surface' => ['nullable', Rule::in(['marketing', 'app', 'any'])],
            'refresh_minutes'   => 'nullable|integer|min:15|max:43200',
        ]);
        $mind = SiteAssistantSettings::ensureAssistantMind();

        if ($data['kind'] === 'url') {
            if (empty($data['url'])) {
                return back()->withErrors(['url' => 'A URL is required for link sources.'])->withInput();
            }
            $source = \App\Modules\User\Models\AiMindSource::create([
                'mind_id'           => $mind->id,
                'type'              => \App\Modules\User\Models\AiMindSource::TYPE_LINK,
                'title'             => $data['title'],
                'url'               => $data['url'],
                'page_pattern'      => $data['page_pattern'] ?: null,
                'assistant_surface' => $data['assistant_surface'] ?: null,
                'refresh_minutes'   => $data['refresh_minutes'] ?? (60 * 24),
                'status'            => \App\Modules\User\Models\AiMindSource::STATUS_QUEUED,
            ]);
        } elseif ($data['kind'] === 'text') {
            if (empty($data['body'])) {
                return back()->withErrors(['body' => 'Pasted content is required for text sources.'])->withInput();
            }
            $source = \App\Modules\User\Models\AiMindSource::create([
                'mind_id'           => $mind->id,
                'type'              => \App\Modules\User\Models\AiMindSource::TYPE_TEXT,
                'title'             => $data['title'],
                'body'              => $data['body'],
                'page_pattern'      => $data['page_pattern'] ?: null,
                'assistant_surface' => $data['assistant_surface'] ?: null,
                'status'            => \App\Modules\User\Models\AiMindSource::STATUS_QUEUED,
            ]);
        } else {
            if (!$request->hasFile('file')) {
                return back()->withErrors(['file' => 'A file is required for document sources.'])->withInput();
            }
            $file = $request->file('file');
            $disk = 'local';
            $path = $file->store('ai-minds/' . $mind->id, $disk);
            $source = \App\Modules\User\Models\AiMindSource::create([
                'mind_id'           => $mind->id,
                'type'              => \App\Modules\User\Models\AiMindSource::TYPE_DOCUMENT,
                'title'             => $data['title'],
                'storage_disk'      => $disk,
                'storage_path'      => $path,
                'mime'              => (string) $file->getMimeType(),
                'size_bytes'        => (int) $file->getSize(),
                'page_pattern'      => $data['page_pattern'] ?: null,
                'assistant_surface' => $data['assistant_surface'] ?: null,
                'status'            => \App\Modules\User\Models\AiMindSource::STATUS_QUEUED,
            ]);
        }
        \App\Jobs\IngestAiMindSourceJob::dispatch($source->id);
        return back()->with('success', 'Knowledge source added — ingestion queued.');
    }

    public function reingestSource(\App\Modules\User\Models\AiMindSource $source)
    {
        $mindId = (int) (SiteAssistantSettings::get()['assistant_mind_id'] ?? 0);
        abort_unless($mindId > 0 && (int) $source->mind_id === $mindId, 404);
        $source->forceFill(['status' => \App\Modules\User\Models\AiMindSource::STATUS_QUEUED])->save();
        \App\Jobs\IngestAiMindSourceJob::dispatch($source->id);
        return back()->with('success', 'Re-ingestion queued.');
    }

    public function destroySource(\App\Modules\User\Models\AiMindSource $source)
    {
        $mindId = (int) (SiteAssistantSettings::get()['assistant_mind_id'] ?? 0);
        abort_unless($mindId > 0 && (int) $source->mind_id === $mindId, 404);
        if ($source->type === \App\Modules\User\Models\AiMindSource::TYPE_DOCUMENT && $source->storage_path) {
            try {
                \Illuminate\Support\Facades\Storage::disk($source->storage_disk ?: 'local')->delete($source->storage_path);
            } catch (\Throwable $e) {
                // Best-effort cleanup; orphan file is acceptable.
            }
        }
        $source->delete();
        return back()->with('success', 'Knowledge source deleted.');
    }

    // ── Page hints ────────────────────────────────────────────
    public function hints()
    {
        $hints = SiteAssistantPageHint::orderBy('priority')->orderBy('id')->paginate(50);
        return view('admin.site-assistant.hints', compact('hints'));
    }

    public function storeHint(Request $request)
    {
        $data = $this->validateHint($request);
        SiteAssistantPageHint::create($data);
        return back()->with('success', 'Page hint created.');
    }

    public function updateHint(Request $request, SiteAssistantPageHint $hint)
    {
        $data = $this->validateHint($request);
        $hint->update($data);
        return back()->with('success', 'Page hint updated.');
    }

    public function destroyHint(SiteAssistantPageHint $hint)
    {
        $hint->delete();
        return back()->with('success', 'Page hint deleted.');
    }

    protected function validateHint(Request $request): array
    {
        $data = $request->validate([
            'label'         => 'required|string|max:120',
            'route_pattern' => 'required|string|max:200',
            'surface'       => ['required', Rule::in(['marketing', 'app', 'any'])],
            'description'   => 'nullable|string|max:2000',
            'suggested_actions_text' => 'nullable|string|max:2000',
            'priority'      => 'nullable|integer|min:0|max:1000',
            'is_active'     => 'nullable|boolean',
            'disable_widget'=> 'nullable|boolean',
        ]);
        $actions = [];
        foreach (preg_split('/\r?\n/', (string) ($data['suggested_actions_text'] ?? '')) as $line) {
            $line = trim($line);
            if ($line !== '') $actions[] = ['label' => $line];
        }
        return [
            'label'             => $data['label'],
            'route_pattern'     => $data['route_pattern'],
            'surface'           => $data['surface'],
            'description'       => $data['description'] ?? null,
            'suggested_actions' => $actions,
            'priority'          => (int) ($data['priority'] ?? 100),
            'is_active'         => (bool) $request->boolean('is_active', true),
            'disable_widget'    => (bool) $request->boolean('disable_widget', false),
        ];
    }

    // ── Response templates ────────────────────────────────────
    public function templates()
    {
        $templates = SiteAssistantResponseTemplate::orderBy('label')->paginate(50);
        return view('admin.site-assistant.templates', compact('templates'));
    }

    public function storeTemplate(Request $request)
    {
        $data = $this->validateTemplate($request);
        SiteAssistantResponseTemplate::create($data);
        return back()->with('success', 'Template created.');
    }

    public function updateTemplate(Request $request, SiteAssistantResponseTemplate $template)
    {
        $data = $this->validateTemplate($request, $template->id);
        $template->update($data);
        return back()->with('success', 'Template updated.');
    }

    public function destroyTemplate(SiteAssistantResponseTemplate $template)
    {
        $template->delete();
        return back()->with('success', 'Template deleted.');
    }

    protected function validateTemplate(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'key'        => ['required', 'string', 'max:64',
                Rule::unique('site_assistant_response_templates', 'key')->ignore($ignoreId)],
            'label'      => 'required|string|max:120',
            'kind'       => ['required', Rule::in(['buttons', 'list', 'form', 'image'])],
            'payload_json' => 'required|string|max:8000',
            'is_active'  => 'nullable|boolean',
        ]);
        $payload = json_decode($data['payload_json'], true);
        if (!is_array($payload)) {
            return abort(422, 'Payload must be valid JSON.');
        }
        return [
            'key'       => $data['key'],
            'label'     => $data['label'],
            'kind'      => $data['kind'],
            'payload'   => $payload,
            'is_active' => (bool) $request->boolean('is_active', true),
        ];
    }

    // ── Conversations browser ─────────────────────────────────
    /**
     * Lists conversations, with optional filters used by the analytics
     * deep-links so admins can jump from a flaky model/route row
     * straight to the cut-off transcripts behind it:
     *   - days=N         narrow last_message_at to the last N days
     *   - cutoffs=1      only convs containing a partial/failed
     *                    assistant message in the window
     *   - model=<label>  combined with cutoffs, requires the cut-off
     *                    message's meta.model to match (use the
     *                    literal "(unknown)" for missing model meta)
     *   - route=<label>  match the conversation's last_route exactly
     */
    public function conversations(Request $request)
    {
        $q = SiteAssistantConversation::query()->latest('last_message_at');
        if ($s = $request->get('search')) {
            $q->where(function ($qq) use ($s) {
                $qq->where('visitor_email', 'ilike', "%{$s}%")
                   ->orWhere('visitor_name', 'ilike', "%{$s}%")
                   ->orWhere('last_route', 'ilike', "%{$s}%");
            });
        }
        if ($request->boolean('handed_off')) $q->where('handed_off', true);
        if ($request->boolean('disabled'))   $q->where('is_disabled', true);

        $days = (int) $request->get('days', 0);
        $days = ($days >= 1 && $days <= 365) ? $days : 0;
        $since = $days > 0 ? now()->subDays($days - 1)->startOfDay() : null;
        if ($since) {
            $q->where('last_message_at', '>=', $since);
        }

        $route = trim((string) $request->get('route', ''));
        if ($route !== '') {
            $q->where('last_route', $route);
        }

        $model = $request->has('model') ? (string) $request->get('model') : null;
        $cutoffsOnly = $request->boolean('cutoffs') || $model !== null;
        if ($cutoffsOnly) {
            $q->whereExists(function ($sub) use ($model, $since) {
                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('site_assistant_messages as m')
                    ->whereColumn('m.conversation_id', 'site_assistant_conversations.id')
                    ->where('m.role', 'assistant')
                    ->whereRaw("m.meta->>'status' IN ('partial','failed')");
                if ($since) {
                    $sub->where('m.created_at', '>=', $since);
                }
                if ($model !== null) {
                    if ($model === '' || $model === '(unknown)') {
                        $sub->whereRaw("(m.meta->>'model' IS NULL OR m.meta->>'model' = '')");
                    } else {
                        $sub->whereRaw("m.meta->>'model' = ?", [$model]);
                    }
                }
            });
        }

        $conversations = $q->paginate(25)->withQueryString();
        $activeFilters = [
            'days'    => $days,
            'cutoffs' => $cutoffsOnly,
            'model'   => $model,
            'route'   => $route !== '' ? $route : null,
        ];
        return view('admin.site-assistant.conversations', compact('conversations', 'activeFilters'));
    }

    public function showConversation(SiteAssistantConversation $conversation)
    {
        $conversation->load(['messages' => fn ($q) => $q->orderBy('id')]);

        // Surface which Mind the dedicated assistant content lives in so
        // the transcript view can flag citations sourced from it.
        $assistantMindId = (int) (SiteAssistantSettings::get()['assistant_mind_id'] ?? 0);

        // Map of source_id => still-exists flag, so the badge only links
        // to the Knowledge Sources page when the row is still around.
        $citedIds = [];
        foreach ($conversation->messages as $m) {
            foreach ((array) $m->citations as $c) {
                $cid = (int) ($c['id'] ?? 0);
                if ($cid > 0) $citedIds[$cid] = true;
            }
        }
        $existingSourceIds = empty($citedIds)
            ? []
            : \App\Modules\User\Models\AiMindSource::query()
                ->whereIn('id', array_keys($citedIds))
                ->pluck('id')
                ->all();
        $existingSourceIds = array_flip(array_map('intval', $existingSourceIds));

        return view('admin.site-assistant.conversation-show', compact(
            'conversation', 'assistantMindId', 'existingSourceIds'
        ));
    }

    public function disableConversation(SiteAssistantConversation $conversation)
    {
        $conversation->update(['is_disabled' => true]);
        return back()->with('success', 'Conversation disabled.');
    }

    public function enableConversation(SiteAssistantConversation $conversation)
    {
        $conversation->update(['is_disabled' => false]);
        return back()->with('success', 'Conversation re-enabled.');
    }

    // ── Analytics ─────────────────────────────────────────────
    /**
     * Small analytics dashboard for site-assistant usage. Surfaces
     * messages/day, top routes the assistant is used on, average turns
     * before a handoff, deflection rate, and the most common questions
     * that triggered a handoff (so admins can feed them back into
     * page hints / response templates).
     */
    public function analytics(Request $request)
    {
        $days = (int) $request->get('days', 30);
        $days = max(7, min(90, $days));
        $since = now()->subDays($days - 1)->startOfDay();

        // Messages per day (user turns) — PostgreSQL date_trunc.
        $rows = SiteAssistantMessage::query()
            ->where('role', 'user')
            ->where('created_at', '>=', $since)
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') AS day, COUNT(*) AS c")
            ->groupBy('day')->orderBy('day')
            ->pluck('c', 'day')->all();
        $messagesPerDay = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $since->copy()->addDays($i)->format('Y-m-d');
            $messagesPerDay[] = [
                'day'   => $d,
                'count' => (int) ($rows[$d] ?? 0),
            ];
        }

        // Top routes the widget is being opened on.
        $topRoutes = SiteAssistantConversation::query()
            ->whereNotNull('last_route')
            ->where('last_message_at', '>=', $since)
            ->selectRaw('last_route, COUNT(*) AS c, SUM(turns_count) AS turns')
            ->groupBy('last_route')->orderByDesc('c')
            ->limit(10)->get();

        // Conversation-level signals over the window.
        $convQuery = SiteAssistantConversation::query()
            ->where('last_message_at', '>=', $since)
            ->where('turns_count', '>', 0);
        $totalConvs   = (int) (clone $convQuery)->count();
        $handedOff    = (int) (clone $convQuery)->where('handed_off', true)->count();
        $avgTurnsToHandoff = (float) (clone $convQuery)->where('handed_off', true)->avg('turns_count');
        $deflectionRate = $totalConvs > 0
            ? round((($totalConvs - $handedOff) / $totalConvs) * 100, 1)
            : null;

        // Most common questions that triggered a handoff. We grab the
        // user messages from handed-off conversations, normalize the
        // text, and count duplicates so admins can see what's slipping
        // through. Capped to keep memory bounded on busy installs.
        $handoffConvIds = SiteAssistantConversation::query()
            ->where('last_message_at', '>=', $since)
            ->where('handed_off', true)
            ->pluck('id');

        $suggestions = collect();
        if ($handoffConvIds->isNotEmpty()) {
            $userMsgs = SiteAssistantMessage::query()
                ->whereIn('conversation_id', $handoffConvIds)
                ->where('role', 'user')
                ->whereNotNull('content')
                ->orderByDesc('id')
                ->limit(2000)
                ->pluck('content');
            $buckets = [];
            foreach ($userMsgs as $raw) {
                $norm = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $raw)));
                if ($norm === '' || mb_strlen($norm) < 3) continue;
                $key = mb_substr($norm, 0, 160);
                if (!isset($buckets[$key])) {
                    $buckets[$key] = ['sample' => trim(mb_substr((string) $raw, 0, 200)), 'count' => 0];
                }
                $buckets[$key]['count']++;
            }
            $suggestions = collect($buckets)
                ->sortByDesc('count')
                ->take(15)
                ->values();
        }

        // Cut-off retry rate: of all partial/failed assistant streams in
        // the window, what fraction did the visitor click Retry on? A
        // retry is a later user message whose meta.retry_of points back
        // at the cut-off. A high *abandon* rate (low retry rate) is a
        // strong signal that an upstream call is flaking out and worth
        // investigating.
        $cutoffBase = SiteAssistantMessage::query()
            ->where('role', 'assistant')
            ->where('created_at', '>=', $since)
            ->whereRaw("meta->>'status' IN ('partial','failed')");
        $cutoffTotal = (int) (clone $cutoffBase)->count();
        $cutoffRetried = 0;
        $retriedIds = [];
        if ($cutoffTotal > 0) {
            // Bound by $since to keep the scan proportional to the
            // window, and require the retry_of value to be a non-empty
            // string of digits before the bigint cast so malformed
            // historical metadata can never raise a SQL cast error.
            $retriedIds = SiteAssistantMessage::query()
                ->where('role', 'user')
                ->where('created_at', '>=', $since)
                ->whereRaw("meta->>'retry_of' ~ '^[0-9]+$'")
                ->selectRaw("DISTINCT (meta->>'retry_of')::bigint AS rid")
                ->pluck('rid')
                ->filter()
                ->map(fn ($v) => (int) $v)
                ->all();
            if (!empty($retriedIds)) {
                $cutoffRetried = (int) (clone $cutoffBase)->whereIn('id', $retriedIds)->count();
            }
        }
        $cutoffRetryRate = $cutoffTotal > 0
            ? round(($cutoffRetried / $cutoffTotal) * 100, 1)
            : null;

        // Break the cut-offs down by chat model and by last_route so
        // admins can see *where* streams are flaking, not just whether.
        // Each row carries its own retry rate (retried / cutoffs) so a
        // chronically-abandoned upstream stands out from one that
        // visitors patiently click through.
        $retrySumExpr = empty($retriedIds)
            ? '0'
            : 'SUM(CASE WHEN m.id IN ('.implode(',', $retriedIds).') THEN 1 ELSE 0 END)';

        $cutoffByModel = collect();
        $cutoffByRoute = collect();
        if ($cutoffTotal > 0) {
            $cutoffByModel = \Illuminate\Support\Facades\DB::table('site_assistant_messages as m')
                ->where('m.role', 'assistant')
                ->where('m.created_at', '>=', $since)
                ->whereRaw("m.meta->>'status' IN ('partial','failed')")
                ->selectRaw("COALESCE(NULLIF(m.meta->>'model',''), '(unknown)') AS label,
                             COUNT(*) AS cutoffs,
                             {$retrySumExpr} AS retried")
                ->groupBy('label')
                ->orderByDesc('cutoffs')
                ->limit(8)
                ->get()
                ->map(fn ($r) => [
                    'label'   => (string) $r->label,
                    'cutoffs' => (int) $r->cutoffs,
                    'retried' => (int) $r->retried,
                    'rate'    => $r->cutoffs > 0
                        ? round(((int) $r->retried / (int) $r->cutoffs) * 100, 1)
                        : null,
                ]);

            $cutoffByRoute = \Illuminate\Support\Facades\DB::table('site_assistant_messages as m')
                ->join('site_assistant_conversations as c', 'c.id', '=', 'm.conversation_id')
                ->where('m.role', 'assistant')
                ->where('m.created_at', '>=', $since)
                ->whereRaw("m.meta->>'status' IN ('partial','failed')")
                ->whereNotNull('c.last_route')
                ->selectRaw("c.last_route AS label,
                             COUNT(*) AS cutoffs,
                             {$retrySumExpr} AS retried")
                ->groupBy('c.last_route')
                ->orderByDesc('cutoffs')
                ->limit(8)
                ->get()
                ->map(fn ($r) => [
                    'label'   => (string) $r->label,
                    'cutoffs' => (int) $r->cutoffs,
                    'retried' => (int) $r->retried,
                    'rate'    => $r->cutoffs > 0
                        ? round(((int) $r->retried / (int) $r->cutoffs) * 100, 1)
                        : null,
                ]);
        }

        return view('admin.site-assistant.analytics', [
            'days'              => $days,
            'messagesPerDay'    => $messagesPerDay,
            'topRoutes'         => $topRoutes,
            'totalConvs'        => $totalConvs,
            'handedOff'         => $handedOff,
            'avgTurnsToHandoff' => $avgTurnsToHandoff,
            'deflectionRate'    => $deflectionRate,
            'suggestions'       => $suggestions,
            'cutoffTotal'       => $cutoffTotal,
            'cutoffRetried'     => $cutoffRetried,
            'cutoffRetryRate'   => $cutoffRetryRate,
            'cutoffByModel'     => $cutoffByModel,
            'cutoffByRoute'     => $cutoffByRoute,
        ]);
    }

    protected function totals(): array
    {
        return [
            'conversations' => (int) SiteAssistantConversation::count(),
            'handoffs'      => (int) SiteAssistantConversation::where('handed_off', true)->count(),
            'turns_month'   => (int) SiteAssistantMessage::where('role', 'user')
                ->where('created_at', '>=', now()->startOfMonth())->count(),
            'page_hints'    => (int) SiteAssistantPageHint::count(),
            'templates'     => (int) SiteAssistantResponseTemplate::count(),
        ];
    }
}
