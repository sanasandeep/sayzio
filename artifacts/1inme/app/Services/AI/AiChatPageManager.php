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
        $caps = $companion->brandingCapabilities();

        $cfg = $companion->effectiveConfig();
        $cfg['greeting']          = $config['greeting'] ?? null;
        $cfg['placeholder']       = $config['placeholder'] ?? $cfg['placeholder'];
        $cfg['accent']            = $config['accent'] ?? $cfg['accent'];
        $cfg['theme']             = $config['theme'] ?? $cfg['theme'];
        $cfg['ground_in_profile'] = (bool) ($config['ground_in_profile'] ?? false);
        $cfg['starters']          = collect($starters)
            ->map(fn ($s) => trim((string) $s))
            ->filter()->values()->all();

        // Branding visibility — hiding "Powered by Sayzio" requires the
        // remove_branding feature. Ungated owners always show it.
        $wantShow = (bool) ($config['show_branding'] ?? false);
        $cfg['show_branding'] = $caps['can_hide_branding'] ? $wantShow : true;

        // Custom branding text + URL — gated by custom_branding; stripped
        // (nulled) for anyone without it so a downgrade can't keep them.
        if ($caps['can_custom_branding']) {
            $cfg['custom_branding_text'] = $this->cleanText($config['custom_branding_text'] ?? null, 60);
            $cfg['custom_branding_url']  = $this->cleanUrl($config['custom_branding_url'] ?? null);
        } else {
            $cfg['custom_branding_text'] = null;
            $cfg['custom_branding_url']  = null;
        }

        // Custom agent avatar — gated by either branding feature. The
        // controller resolves an uploaded file (or vault pick) into a URL
        // string in $config['avatar_url'] before calling this. We only
        // overwrite when the key is explicitly present so a normal save that
        // doesn't touch the avatar (web posts only avatar_upload/avatar_remove)
        // preserves the existing one instead of wiping it. Ungated owners are
        // always stripped regardless of what was stored.
        if (!$caps['can_avatar']) {
            $cfg['avatar_url'] = null;
        } elseif (array_key_exists('avatar_url', $config)) {
            $cfg['avatar_url'] = $this->cleanUrl($config['avatar_url'] ?? null);
        }
        // else: keep $cfg['avatar_url'] from effectiveConfig() unchanged.

        return $cfg;
    }

    /** Trim + length-cap a free-text branding value, or null when empty. */
    private function cleanText($value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') return null;
        return Str::limit($value, $max, '');
    }

    /** Accept only absolute http(s) (or root-relative) URLs, else null. */
    private function cleanUrl($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') return null;
        if (str_starts_with($value, '/')) return $value;
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) return $value;
        return null;
    }
}
