<?php

namespace App\Services\AI;

use App\Modules\User\Models\AiCompanion;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\Link;
use Illuminate\Support\Str;

/**
 * Shared resolver for the full-page AI chat link type (`links.type =
 * ai_chat`). An ai_chat link is a thin surface over the existing AI
 * Companion stack: each page binds exactly one AiCompanion (placement =
 * `page`) whose AiPersonaAgent supplies the brain.
 *
 * This lives in a service so the web editor (AiChatController) and the
 * mobile/REST editor (Api\AiChatController) stay in lockstep — both must
 * create the same companion shape and never leave the editor a dead end.
 */
class AiChatPageManager
{
    /**
     * Returns the AiCompanion bound to this link (placement = page),
     * creating one — together with a dedicated default persona when the
     * user has none — so the editor is never a dead end.
     */
    public function ensureCompanion(Link $link): AiCompanion
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

        return AiPersonaAgent::create([
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
    }

    /**
     * Merge validated editor input into the companion's effective config,
     * returning the new config array. Shared by web + API so both editors
     * persist the identical shape.
     */
    public function mergeConfig(AiCompanion $companion, array $config, array $starters): array
    {
        $cfg = $companion->effectiveConfig();
        $cfg['greeting']          = $config['greeting'] ?? null;
        $cfg['placeholder']       = $config['placeholder'] ?? $cfg['placeholder'];
        $cfg['accent']            = $config['accent'] ?? $cfg['accent'];
        $cfg['theme']             = $config['theme'] ?? $cfg['theme'];
        $cfg['show_branding']     = (bool) ($config['show_branding'] ?? false);
        $cfg['ground_in_profile'] = (bool) ($config['ground_in_profile'] ?? false);
        $cfg['starters']          = collect($starters)
            ->map(fn ($s) => trim((string) $s))
            ->filter()->values()->all();

        return $cfg;
    }
}
