<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiCompanion;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\Link;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\CompanionRuntime;
use App\Services\AI\CompanionSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        protected AiCreditService $credits,
    ) {}

    public function editor(Request $request, Link $link)
    {
        $this->ensureBiolinkFamily($link);
        $user = $request->user();

        $companion = $this->ensureCompanion($link, $user);
        $companion->load(['persona:id,name']);

        $personas = AiPersonaAgent::where('user_id', $link->user_id)
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('user.links.ai-chat.editor', [
            'link'      => $link,
            'companion' => $companion,
            'personas'  => $personas,
            'config'    => $companion->effectiveConfig(),
            'usage'     => CompanionRuntime::monthlyUsage($companion),
            'aiEnabled' => AiEngineSettings::isEnabled(),
            'publicUrl' => url('/' . $link->alias),
        ]);
    }

    public function save(Request $request, Link $link)
    {
        $this->ensureBiolinkFamily($link);
        $user = $request->user();
        $companion = $this->ensureCompanion($link, $user);

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
        ]);

        $persona = AiPersonaAgent::where('id', $data['persona_id'])
            ->where('user_id', $link->user_id)
            ->first();
        if (!$persona) {
            return back()->withInput()->withErrors(['persona_id' => 'Pick one of your personas.']);
        }

        $starters = collect($data['starters'] ?? [])
            ->map(fn ($s) => trim((string) $s))
            ->filter()->values()->all();

        $cfg = $companion->effectiveConfig();
        $cfg['greeting']          = $data['config']['greeting'] ?? null;
        $cfg['placeholder']       = $data['config']['placeholder'] ?? $cfg['placeholder'];
        $cfg['accent']            = $data['config']['accent'] ?? $cfg['accent'];
        $cfg['theme']             = $data['config']['theme'] ?? $cfg['theme'];
        $cfg['show_branding']     = (bool) ($data['config']['show_branding'] ?? false);
        $cfg['ground_in_profile'] = (bool) ($data['config']['ground_in_profile'] ?? false);
        $cfg['starters']          = $starters;

        DB::transaction(function () use ($companion, $data, $persona, $cfg, $link) {
            $companion->forceFill([
                'name'       => $data['name'],
                'persona_id' => $persona->id,
                'config'     => $cfg,
            ])->save();
            $companion->links()->syncWithoutDetaching([$link->id]);
        });

        return back()->with('status', 'AI chat saved.');
    }

    /**
     * Returns the AiCompanion bound to this link (placement = page),
     * creating one — together with a dedicated default persona when the
     * user has none — so the editor is never a dead end.
     */
    protected function ensureCompanion(Link $link, $user): AiCompanion
    {
        $companion = $link->aiCompanion();
        if ($companion) {
            return $companion;
        }

        $persona = AiPersonaAgent::where('user_id', $link->user_id)
            ->where('is_disabled', false)
            ->orderBy('id')
            ->first()
            ?: $this->createDefaultPersona($link);

        $caps = CompanionSettings::caps();

        $companion = AiCompanion::create([
            'user_id'              => $link->user_id,
            'persona_id'           => $persona->id,
            'public_id'            => AiCompanion::newPublicId(),
            'name'                 => $link->title ?: ($link->alias ?: 'AI Chat'),
            'placement'            => AiCompanion::PLACEMENT_PAGE,
            'config'               => array_merge(AiCompanion::defaultConfig(), [
                'ground_in_profile' => true,
                'show_branding'     => true,
                'starters'          => [],
            ]),
            'allowed_domains'      => [],
            'free_turns_per_month' => $caps['default_free_turns_per_month'],
            'hard_cap_per_month'   => 2000,
        ]);
        $companion->links()->syncWithoutDetaching([$link->id]);

        return $companion;
    }

    protected function createDefaultPersona(Link $link): AiPersonaAgent
    {
        $name = 'Assistant — ' . ($link->title ?: $link->alias);

        $persona = AiPersonaAgent::create([
            'user_id'           => $link->user_id,
            'slug'              => Str::slug(Str::limit($name, 60, '')) . '-' . Str::lower(Str::random(6)),
            'name'              => Str::limit($name, 120, ''),
            'description'       => 'Default assistant for the ' . ($link->title ?: $link->alias) . ' AI chat page.',
            'system_prompt'     => 'You are a friendly, helpful assistant for this page. Answer visitor questions clearly and concisely. If you are unsure, ask a clarifying question.',
            'model'             => AiEngineSettings::DEFAULT_FEATURE_MODEL,
            'temperature_x100'  => 70,
            'max_tokens'        => 600,
            'languages'         => [],
            'allowed_actions'   => [],
            'fallback_behavior' => 'clarify',
            'use_default_mind'  => true,
        ]);

        return $persona;
    }

    protected function ensureBiolinkFamily(Link $link): void
    {
        if (!$link->isBiolinkFamily()) {
            abort(404);
        }
    }
}
