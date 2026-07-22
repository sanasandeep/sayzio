<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Persona Agent" — a fully-configurable conversational agent.
 *
 * NOT to be confused with the simpler `AiPersona` model (Task #486),
 * which is the saved-output library for the brand-persona generator
 * tool. This one is the live agent: system prompt + tone + knobs +
 * attached Minds + version history. Lives at table
 * `ai_persona_agents` to avoid colliding with the generator's
 * `ai_personas` library.
 */
class AiPersonaAgent extends Model
{
    protected $table = 'ai_persona_agents';

    public const FALLBACKS = ['clarify', 'escalate', 'refuse'];
    public const TONES     = ['friendly', 'formal', 'witty', 'concise', 'playful', 'empathetic'];

    /** Allowed-action toggle keys exposed in the persona builder. */
    public const ACTIONS = [
        'quote_prices'      => 'May quote plan prices and coin costs',
        'share_biolinks'    => 'May share the user\'s public biolink URLs',
        'collect_email'     => 'May ask the visitor for an email address',
        'book_calls'        => 'May suggest booking a call / sending a calendar link',
        'refuse_offtopic'   => 'Must refuse questions outside the configured Minds',
        'cite_sources'      => 'Must cite the Mind sources used in each answer',
    ];

    protected static function booted(): void
    {
        // Drop any share rows pointing at this persona when it's deleted
        // (Task #2909). Access resolution already ignores orphans; this
        // keeps the table tidy.
        static::deleted(function (AiPersonaAgent $persona) {
            \App\Services\AI\AiResourceShareService::purgeForResource(
                AiResourceShare::RESOURCE_PERSONA, (int) $persona->id
            );
        });
    }

    protected $fillable = [
        'user_id', 'slug', 'name', 'description', 'avatar_url',
        'system_prompt', 'tone_preset', 'style_guide', 'use_brand_kit',
        'model', 'temperature_x100', 'max_tokens', 'languages',
        'allowed_actions', 'fallback_behavior',
        'greeting', 'starter_questions', 'end_cta_label', 'end_cta_url',
        'use_default_mind', 'is_disabled', 'disabled_reason',
        'last_used_at', 'active_version_id',
    ];

    protected function casts(): array
    {
        return [
            'languages'         => 'array',
            'allowed_actions'   => 'array',
            'starter_questions' => 'array',
            'use_default_mind'  => 'boolean',
            'use_brand_kit'     => 'boolean',
            'is_disabled'       => 'boolean',
            'temperature_x100'  => 'integer',
            'max_tokens'        => 'integer',
            'active_version_id' => 'integer',
            'last_used_at'      => 'datetime',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }

    public function minds(): BelongsToMany
    {
        return $this->belongsToMany(AiMind::class, 'ai_persona_agent_minds', 'persona_id', 'mind_id')
            ->withTimestamps();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AiPersonaAgentVersion::class, 'persona_id')->orderByDesc('revision');
    }

    public function activeVersion()
    {
        return $this->belongsTo(AiPersonaAgentVersion::class, 'active_version_id');
    }

    public function temperature(): float
    {
        return round(((int) $this->temperature_x100) / 100, 2);
    }

    /**
     * Snapshot of every editable field. Stored verbatim in
     * ai_persona_agent_versions.config so rollback can restore
     * everything in one transaction without re-querying live tables.
     */
    public function snapshotConfig(): array
    {
        return [
            'name'              => $this->name,
            'description'       => $this->description,
            'avatar_url'        => $this->avatar_url,
            'system_prompt'     => $this->system_prompt,
            'tone_preset'       => $this->tone_preset,
            'style_guide'       => $this->style_guide,
            'use_brand_kit'     => (bool) ($this->use_brand_kit ?? true),
            'model'             => $this->model,
            'temperature_x100'  => (int) $this->temperature_x100,
            'max_tokens'        => (int) $this->max_tokens,
            'languages'         => $this->languages ?? [],
            'allowed_actions'   => $this->allowed_actions ?? [],
            'fallback_behavior' => $this->fallback_behavior,
            'greeting'          => $this->greeting,
            'starter_questions' => $this->starter_questions ?? [],
            'end_cta_label'     => $this->end_cta_label,
            'end_cta_url'       => $this->end_cta_url,
            'use_default_mind'  => (bool) $this->use_default_mind,
            'mind_ids'          => $this->minds()->pluck('ai_minds.id')->all(),
        ];
    }
}
