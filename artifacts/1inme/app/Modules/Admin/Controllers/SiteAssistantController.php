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
        $conversations = $q->paginate(25)->withQueryString();
        return view('admin.site-assistant.conversations', compact('conversations'));
    }

    public function showConversation(SiteAssistantConversation $conversation)
    {
        $conversation->load(['messages' => fn ($q) => $q->orderBy('id')]);
        return view('admin.site-assistant.conversation-show', compact('conversation'));
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
