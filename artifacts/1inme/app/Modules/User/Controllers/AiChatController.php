<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\AiPersonaAgentVersion;
use App\Modules\User\Models\Link;
use App\Services\AI\AiChatPageManager;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\CompanionRuntime;
use App\Services\AI\PersonaSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Editor for the full-page AI chat link type (`links.type = ai_chat`).
 *
 * An ai_chat link is a thin surface over the existing AI Companion
 * stack: each page binds exactly one AiCompanion (placement = `page`)
 * whose AiPersonaAgent supplies the brain. The public page is rendered
 * by RedirectController (view `common.ai-chat`) and talks to the same
 * PublicCompanionController@message endpoint the embed/biolink chatbots
 * use, so there is no separate AI runtime here.
 */
class AiChatController extends Controller
{
    public function __construct(
        protected AiUsageCharger $credits,
        protected AiChatPageManager $pages,
    ) {}

    public function editor(Request $request, Link $link)
    {
        $this->ensureBiolinkFamily($link);
        $user = $request->user();

        $companion = $this->pages->ensureCompanion($link);
        $companion->load(['persona.minds:id']);
        $persona = $companion->persona;

        $personas = AiPersonaAgent::where('user_id', $link->user_id)
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id', 'name']);

        $myMinds = AiMind::where('user_id', $user->id)
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id', 'name']);
        $defaultMind = AiMind::whereNull('user_id')
            ->where('is_default', true)
            ->where('is_disabled', false)
            ->first(['id', 'name']);

        return view('user.links.ai-chat.editor', [
            'link'            => $link,
            'companion'       => $companion,
            'persona'         => $persona,
            'personas'        => $personas,
            'config'          => $companion->effectiveConfig(),
            'usage'           => CompanionRuntime::monthlyUsage($companion),
            'aiEnabled'       => AiEngineSettings::isEnabled(),
            'publicUrl'       => url('/' . $link->alias),
            'tones'           => AiPersonaAgent::TONES,
            'fallbacks'       => AiPersonaAgent::FALLBACKS,
            'myMinds'         => $myMinds,
            'defaultMind'     => $defaultMind,
            'attachedMindIds' => $persona->minds->pluck('id')->all(),
            'caps'            => PersonaSettings::caps(),
        ]);
    }

    public function save(Request $request, Link $link)
    {
        $this->ensureBiolinkFamily($link);
        $user = $request->user();
        $companion = $this->pages->ensureCompanion($link);
        $caps = PersonaSettings::caps();

        $data = $request->validate([
            'name'              => 'required|string|max:120',
            'persona_id'        => 'required|integer',
            'config.greeting'   => 'nullable|string|max:1000',
            'config.placeholder'=> 'nullable|string|max:120',
            'config.accent'     => 'nullable|string|max:32',
            'config.theme'      => 'nullable|in:auto,light,dark',
            'config.show_branding'    => 'nullable|boolean',
            'config.ground_in_profile'=> 'nullable|boolean',
            'starters'          => 'nullable|array|max:6',
            'starters.*'        => 'nullable|string|max:200',
            // Inline persona ("the brain") editing. Only required when the
            // user is editing the persona currently bound to this page
            // (persona.apply = 1); switching to a different persona just
            // re-binds it and leaves its content untouched.
            'persona.apply'             => 'nullable|boolean',
            'persona.system_prompt'     => "required_if:persona.apply,1|nullable|string|max:{$caps['max_system_prompt_chars']}",
            'persona.tone_preset'       => 'nullable|string|max:32',
            'persona.fallback_behavior' => 'required_if:persona.apply,1|nullable|in:' . implode(',', AiPersonaAgent::FALLBACKS),
            'persona.use_default_mind'  => 'nullable|boolean',
            'persona.mind_ids'          => 'nullable|array|max:' . $caps['max_minds_per_persona'],
            'persona.mind_ids.*'        => 'integer',
        ]);

        $persona = AiPersonaAgent::where('id', $data['persona_id'])
            ->where('user_id', $link->user_id)
            ->first();
        if (!$persona) {
            return back()->withInput()->withErrors(['persona_id' => 'Pick one of your personas.']);
        }

        $cfg = $this->pages->mergeConfig($companion, $data['config'] ?? [], $data['starters'] ?? []);

        // Apply inline persona edits only when the user kept the originally
        // bound persona selected — guards against overwriting another
        // persona with the stale fields rendered for this page.
        $applyPersona = (bool) ($data['persona']['apply'] ?? false)
            && (int) $persona->id === (int) $companion->persona_id;

        $personaInput = $data['persona'] ?? [];
        $tone = $personaInput['tone_preset'] ?? null;
        if (!empty($tone) && !in_array($tone, AiPersonaAgent::TONES, true)) {
            $tone = null;
        }
        $validMindIds = AiMind::whereIn('id', collect($personaInput['mind_ids'] ?? [])->filter()->values())
            ->where('user_id', $user->id)
            ->pluck('id')->all();

        DB::transaction(function () use ($companion, $data, $persona, $cfg, $link, $applyPersona, $personaInput, $tone, $validMindIds) {
            $companion->forceFill([
                'name'       => $data['name'],
                'persona_id' => $persona->id,
                'config'     => $cfg,
            ])->save();
            $companion->links()->syncWithoutDetaching([$link->id]);

            if ($applyPersona) {
                $persona->forceFill([
                    'system_prompt'     => $personaInput['system_prompt'],
                    'tone_preset'       => $tone,
                    'fallback_behavior' => $personaInput['fallback_behavior'],
                    'use_default_mind'  => (bool) ($personaInput['use_default_mind'] ?? false),
                ])->save();
                $persona->minds()->sync($validMindIds);

                $this->writePersonaVersion($persona, 'Edited from AI Chat editor');
            }
        });

        return back()->with('status', 'AI chat saved.');
    }

    /**
     * Snapshot the persona into a new version row and point
     * active_version_id at it — mirrors PersonasController so the full
     * persona manager's version history / rollback stays accurate when
     * edits originate from the AI Chat editor. Trims old versions to the
     * admin-configured cap.
     */
    protected function writePersonaVersion(AiPersonaAgent $persona, ?string $summary): void
    {
        $next = (int) AiPersonaAgentVersion::where('persona_id', $persona->id)->max('revision') + 1;
        $version = AiPersonaAgentVersion::create([
            'persona_id'         => $persona->id,
            'revision'           => $next,
            'config'             => $persona->snapshotConfig(),
            'summary'            => $summary,
            'created_by_user_id' => $persona->user_id,
            'created_at'         => now(),
        ]);
        $persona->forceFill(['active_version_id' => $version->id])->save();

        $keep = max(1, (int) PersonaSettings::cap('max_versions_per_persona'));
        $oldIds = AiPersonaAgentVersion::where('persona_id', $persona->id)
            ->orderByDesc('revision')->skip($keep)->take(1000)->pluck('id');
        if ($oldIds->isNotEmpty()) {
            AiPersonaAgentVersion::whereIn('id', $oldIds)->delete();
        }
    }

    protected function ensureBiolinkFamily(Link $link): void
    {
        if (!$link->isBiolinkFamily()) {
            abort(404);
        }
    }
}
