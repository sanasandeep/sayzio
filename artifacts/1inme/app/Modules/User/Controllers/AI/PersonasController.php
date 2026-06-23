<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\AiPersonaAgentVersion;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\AI\PersonaRuntime;
use App\Services\AI\PersonaSettings;
use App\Services\AI\PersonaTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Customer-facing AI Personas dashboard. Lists every Persona the user
 * owns and lets them create / duplicate / edit / delete / rollback
 * versions. The "Test this Persona" panel calls the same runtime that
 * widgets / inbox / Coach will use later, so what users see in the
 * builder is exactly what visitors will get.
 *
 * Every save writes a new ai_persona_agent_versions row and points
 * `active_version_id` at it. Rollback flips that pointer (and replays
 * the snapshot back onto the live row) so widgets always serve the
 * "active" config without joins.
 */
class PersonasController extends Controller
{
    public function __construct(
        protected PersonaRuntime $runtime,
        protected AiUsageCharger $credits,
    ) {}

    public function index(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) {
            return view('user.ai.disabled', ['title' => 'Personas']);
        }
        $this->ensureEnabled();
        $user = $request->user();

        $personas = AiPersonaAgent::where('user_id', $user->id)
            ->withCount('minds')
            ->latest('updated_at')
            ->get();
        $caps = PersonaSettings::caps();

        return view('user.ai-personas.index', [
            'personas' => $personas,
            'caps'     => $caps,
            'used'     => $personas->count(),
        ]);
    }

    public function create(Request $request)
    {
        $this->ensureEnabled();
        $caps = PersonaSettings::caps();
        $current = AiPersonaAgent::where('user_id', $request->user()->id)->count();
        if ($current >= $caps['max_personas_per_user']) {
            return redirect()->route('user.ai-personas.index')->with('error',
                "You have reached the {$caps['max_personas_per_user']}-persona limit.");
        }
        return view('user.ai-personas.create', [
            'templates' => PersonaTemplates::all(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureEnabled();
        $user = $request->user();
        $caps = PersonaSettings::caps();
        if (AiPersonaAgent::where('user_id', $user->id)->count() >= $caps['max_personas_per_user']) {
            return back()->with('error',
                "You have reached the {$caps['max_personas_per_user']}-persona limit.");
        }

        $data = $request->validate([
            'template' => 'nullable|string',
            'name'     => 'required|string|max:120',
        ]);
        $tpl = PersonaTemplates::get($data['template'] ?? 'blank') ?? PersonaTemplates::get('blank');
        $cfg = $tpl['config'];

        $persona = $this->createPersonaFromConfig($user, array_merge($cfg, [
            'name' => $data['name'],
        ]));

        return redirect()->route('user.ai-personas.edit', $persona)
            ->with('status', 'Persona created. Customize it below.');
    }

    public function edit(Request $request, AiPersonaAgent $persona)
    {
        $this->ensureEnabled();
        $this->authorize_($persona, $request->user());

        $persona->load(['minds:id,name,is_disabled,user_id,is_default', 'versions']);
        $myMinds = AiMind::where('user_id', $request->user()->id)
            ->where('is_disabled', false)
            ->orderBy('name')->get(['id','name']);
        $defaultMind = AiMind::whereNull('user_id')
            ->where('is_default', true)
            ->where('is_disabled', false)
            ->first();

        return view('user.ai-personas.edit', [
            'persona'     => $persona,
            'myMinds'     => $myMinds,
            'defaultMind' => $defaultMind,
            'attachedIds' => $persona->minds->pluck('id')->all(),
            'caps'        => PersonaSettings::caps(),
            'engineModels'=> array_values(array_filter(
                AiEngineSettings::models(),
                fn($m) => $m['enabled'] && $m['kind'] === 'chat'
            )),
            'tones'       => AiPersonaAgent::TONES,
            'fallbacks'   => AiPersonaAgent::FALLBACKS,
            'actionDefs'  => AiPersonaAgent::ACTIONS,
            'balance'     => $this->credits->getBalance($request->user()),
        ]);
    }

    public function update(Request $request, AiPersonaAgent $persona)
    {
        $this->ensureEnabled();
        $this->authorize_($persona, $request->user());
        $caps = PersonaSettings::caps();

        $data = $request->validate([
            'name'              => 'required|string|max:120',
            'description'       => 'nullable|string|max:500',
            'avatar_url'        => 'nullable|url|max:1024',
            'system_prompt'     => "required|string|max:{$caps['max_system_prompt_chars']}",
            'tone_preset'       => 'nullable|string|max:32',
            'style_guide'       => "nullable|string|max:{$caps['max_style_guide_chars']}",
            'model'             => 'required|string|max:64',
            'temperature_x100'  => 'required|integer|min:0|max:200',
            'max_tokens'        => 'required|integer|min:50|max:4000',
            'languages'         => 'nullable|array',
            'languages.*'       => 'string|max:8',
            'allowed_actions'   => 'nullable|array',
            'fallback_behavior' => 'required|in:' . implode(',', AiPersonaAgent::FALLBACKS),
            'greeting'          => 'nullable|string|max:1000',
            'starter_questions' => 'nullable|array|max:' . $caps['max_starter_questions'],
            'starter_questions.*' => 'nullable|string|max:200',
            'end_cta_label'     => 'nullable|string|max:120',
            'end_cta_url'       => 'nullable|url|max:1024',
            'use_default_mind'  => 'nullable|boolean',
            'mind_ids'          => 'nullable|array|max:' . $caps['max_minds_per_persona'],
            'mind_ids.*'        => 'integer',
            'summary'           => 'nullable|string|max:500',
        ]);

        $modelCfg = AiEngineSettings::model($data['model']);
        if (!$modelCfg || !$modelCfg['enabled'] || $modelCfg['kind'] !== 'chat') {
            return back()->withInput()->withErrors(['model' => 'Pick an admin-enabled chat model.']);
        }

        // Tone validation: silently ignore unknown values rather than
        // rejecting — admins may add custom presets later and we don't
        // want to break personas mid-edit.
        if (!empty($data['tone_preset']) && !in_array($data['tone_preset'], AiPersonaAgent::TONES, true)) {
            $data['tone_preset'] = null;
        }

        // Resolve attached minds — must be owned by the same user (or
        // the platform default, but that's controlled separately via
        // use_default_mind, not the pivot, so we filter it out here).
        $mindIds = collect($data['mind_ids'] ?? [])->filter()->values()->all();
        $valid   = AiMind::whereIn('id', $mindIds)
            ->where('user_id', $request->user()->id)
            ->pluck('id')->all();

        $allowed = collect(AiPersonaAgent::ACTIONS)->keys()
            ->mapWithKeys(fn($k) => [$k => (bool) ($data['allowed_actions'][$k] ?? false)])
            ->all();

        $starters = collect($data['starter_questions'] ?? [])
            ->map(fn($s) => trim((string) $s))
            ->filter()->values()->all();

        DB::transaction(function () use ($persona, $data, $valid, $allowed, $starters) {
            $persona->forceFill([
                'name'              => $data['name'],
                'description'       => $data['description'] ?? null,
                'avatar_url'        => $data['avatar_url'] ?? null,
                'system_prompt'     => $data['system_prompt'],
                'tone_preset'       => $data['tone_preset'] ?? null,
                'style_guide'       => $data['style_guide'] ?? null,
                'model'             => $data['model'],
                'temperature_x100'  => (int) $data['temperature_x100'],
                'max_tokens'        => (int) $data['max_tokens'],
                'languages'         => $data['languages'] ?? [],
                'allowed_actions'   => $allowed,
                'fallback_behavior' => $data['fallback_behavior'],
                'greeting'          => $data['greeting'] ?? null,
                'starter_questions' => $starters,
                'end_cta_label'     => $data['end_cta_label'] ?? null,
                'end_cta_url'       => $data['end_cta_url'] ?? null,
                'use_default_mind'  => (bool) ($data['use_default_mind'] ?? false),
            ])->save();

            $persona->minds()->sync($valid);

            $this->writeVersion($persona, $data['summary'] ?? null);
        });

        return back()->with('status', 'Persona saved as v' . $persona->fresh()->activeVersion?->revision . '.');
    }

    public function destroy(Request $request, AiPersonaAgent $persona)
    {
        $this->ensureEnabled();
        $this->authorize_($persona, $request->user());
        $persona->delete();
        return redirect()->route('user.ai-personas.index')->with('status', 'Persona deleted.');
    }

    public function duplicate(Request $request, AiPersonaAgent $persona)
    {
        $this->ensureEnabled();
        $this->authorize_($persona, $request->user());
        $caps = PersonaSettings::caps();
        $user = $request->user();
        if (AiPersonaAgent::where('user_id', $user->id)->count() >= $caps['max_personas_per_user']) {
            return back()->with('error',
                "You have reached the {$caps['max_personas_per_user']}-persona limit.");
        }

        $cfg = $persona->snapshotConfig();
        $cfg['name'] = Str::limit($cfg['name'] . ' (copy)', 120, '');
        $copy = $this->createPersonaFromConfig($user, $cfg);
        return redirect()->route('user.ai-personas.edit', $copy)
            ->with('status', 'Persona duplicated.');
    }

    public function rollback(Request $request, AiPersonaAgent $persona, AiPersonaAgentVersion $version)
    {
        $this->ensureEnabled();
        $this->authorize_($persona, $request->user());
        if ((int) $version->persona_id !== (int) $persona->id) abort(404);

        $cfg = (array) $version->config;
        DB::transaction(function () use ($persona, $cfg, $version) {
            $persona->forceFill([
                'name'              => $cfg['name'] ?? $persona->name,
                'description'       => $cfg['description'] ?? null,
                'avatar_url'        => $cfg['avatar_url'] ?? null,
                'system_prompt'     => $cfg['system_prompt'] ?? $persona->system_prompt,
                'tone_preset'       => $cfg['tone_preset'] ?? null,
                'style_guide'       => $cfg['style_guide'] ?? null,
                'model'             => $cfg['model'] ?? $persona->model,
                'temperature_x100'  => (int) ($cfg['temperature_x100'] ?? 50),
                'max_tokens'        => (int) ($cfg['max_tokens'] ?? 600),
                'languages'         => $cfg['languages'] ?? [],
                'allowed_actions'   => $cfg['allowed_actions'] ?? [],
                'fallback_behavior' => $cfg['fallback_behavior'] ?? 'clarify',
                'greeting'          => $cfg['greeting'] ?? null,
                'starter_questions' => $cfg['starter_questions'] ?? [],
                'end_cta_label'     => $cfg['end_cta_label'] ?? null,
                'end_cta_url'       => $cfg['end_cta_url'] ?? null,
                'use_default_mind'  => (bool) ($cfg['use_default_mind'] ?? false),
            ])->save();

            // Filter restored mind ids to ones the user still owns —
            // a Mind that was deleted between snapshot time and now
            // shouldn't crash the rollback.
            $valid = AiMind::whereIn('id', $cfg['mind_ids'] ?? [])
                ->where('user_id', $persona->user_id)
                ->pluck('id')->all();
            $persona->minds()->sync($valid);

            $this->writeVersion($persona, "Rolled back to v{$version->revision}");
        });

        return back()->with('status', "Rolled back to v{$version->revision}.");
    }

    public function test(Request $request, AiPersonaAgent $persona)
    {
        $this->ensureEnabled();
        $this->authorize_($persona, $request->user());
        if ($persona->is_disabled) {
            return response()->json(['error' => 'This Persona is disabled.'], 403);
        }

        $data = $request->validate([
            'message'       => 'required|string|max:2000',
            'history'       => 'nullable|array|max:20',
            'history.*.role'    => 'required|in:user,assistant',
            'history.*.content' => 'required|string|max:4000',
        ]);

        try {
            $result = $this->runtime->turn(
                $request->user(),
                $persona,
                (array) ($data['history'] ?? []),
                $data['message']
            );
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json([
                'error'  => "Need {$e->required} coins — only {$e->balance} available.",
                'top_up' => route('user.wallet.buy'),
            ], 402);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    /** Persona-only convenience: write a new version row and point at it. */
    protected function writeVersion(AiPersonaAgent $persona, ?string $summary): AiPersonaAgentVersion
    {
        $caps = PersonaSettings::caps();
        $next = (int) AiPersonaAgentVersion::where('persona_id', $persona->id)->max('revision') + 1;
        $v = AiPersonaAgentVersion::create([
            'persona_id'         => $persona->id,
            'revision'           => $next,
            'config'             => $persona->snapshotConfig(),
            'summary'            => $summary,
            'created_by_user_id' => $persona->user_id,
            'created_at'         => now(),
        ]);
        $persona->forceFill(['active_version_id' => $v->id])->save();

        // Trim old versions so a chatty editor doesn't bloat the table
        // (keep the most-recent N as configured by admin).
        $keep = max(1, (int) $caps['max_versions_per_persona']);
        $oldIds = AiPersonaAgentVersion::where('persona_id', $persona->id)
            ->orderByDesc('revision')->skip($keep)->take(1000)->pluck('id');
        if ($oldIds->isNotEmpty()) {
            AiPersonaAgentVersion::whereIn('id', $oldIds)->delete();
        }
        return $v;
    }

    protected function createPersonaFromConfig($user, array $cfg): AiPersonaAgent
    {
        $defaultModel = $this->pickDefaultModel();
        $allowed = collect(AiPersonaAgent::ACTIONS)->keys()
            ->mapWithKeys(fn($k) => [$k => (bool) (($cfg['allowed_actions'] ?? [])[$k] ?? false)])
            ->all();

        return DB::transaction(function () use ($user, $cfg, $defaultModel, $allowed) {
            $persona = AiPersonaAgent::create([
                'user_id'           => $user->id,
                'slug'              => null,
                'name'              => $cfg['name'] ?? 'New Persona',
                'description'       => $cfg['description'] ?? null,
                'avatar_url'        => $cfg['avatar_url'] ?? null,
                'system_prompt'     => $cfg['system_prompt'] ?? 'You are a helpful assistant.',
                'tone_preset'       => $cfg['tone_preset'] ?? 'friendly',
                'style_guide'       => $cfg['style_guide'] ?? null,
                'model'             => $cfg['model'] ?? $defaultModel,
                'temperature_x100'  => (int) ($cfg['temperature_x100'] ?? 50),
                'max_tokens'        => (int) ($cfg['max_tokens'] ?? 600),
                'languages'         => $cfg['languages'] ?? [],
                'allowed_actions'   => $allowed,
                'fallback_behavior' => in_array($cfg['fallback_behavior'] ?? 'clarify', AiPersonaAgent::FALLBACKS, true)
                    ? $cfg['fallback_behavior'] : 'clarify',
                'greeting'          => $cfg['greeting'] ?? null,
                'starter_questions' => array_values(array_filter((array) ($cfg['starter_questions'] ?? []))),
                'end_cta_label'     => $cfg['end_cta_label'] ?? null,
                'end_cta_url'       => $cfg['end_cta_url'] ?? null,
                'use_default_mind'  => (bool) ($cfg['use_default_mind'] ?? true),
            ]);
            $persona->slug = Str::slug($persona->name) . '-' . $persona->id;
            $persona->save();

            $valid = AiMind::whereIn('id', $cfg['mind_ids'] ?? [])
                ->where('user_id', $user->id)
                ->pluck('id')->all();
            if ($valid) $persona->minds()->sync($valid);

            $this->writeVersion($persona, 'Initial version');
            return $persona;
        });
    }

    /** Returns the first admin-enabled chat model, falling back to gpt-4o-mini. */
    protected function pickDefaultModel(): string
    {
        foreach (AiEngineSettings::models() as $m) {
            if (!empty($m['enabled']) && ($m['kind'] ?? '') === 'chat') return $m['name'];
        }
        return 'gpt-4o-mini';
    }

    protected function authorize_(AiPersonaAgent $persona, $user): void
    {
        if ((int) $persona->user_id !== (int) $user->id) abort(403);
    }

    protected function ensureEnabled(): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
    }
}
