<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\Link;
use App\Services\AI\AiChatPageManager;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\CompanionRuntime;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Mobile/REST editor for the full-page AI chat link type
 * (`links.type = ai_chat`). Mirrors the web AiChatController: each page
 * binds exactly one AiCompanion (placement = page) whose AiPersonaAgent
 * supplies the brain. Companion resolution + config merging live in the
 * shared AiChatPageManager so web and mobile stay in lockstep, and the
 * public page keeps talking to the same PublicCompanionController@message
 * runtime — there is no separate AI runtime here.
 */
class AiChatController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected AiChatPageManager $pages,
    ) {}

    /**
     * Load the AI chat page's editable basics + the user's personas so
     * the mobile editor can render the form. Lazily creates the bound
     * companion (and a default persona when the user has none) exactly
     * like the web editor, so the screen is never a dead end.
     */
    public function show(Request $request, int $id)
    {
        $link = $this->ownedBiolinkFamilyLink($request, $id);
        if (!$link) return $this->notFound('AI chat page not found');

        $companion = $this->pages->ensureCompanion($link);
        $companion->load(['persona:id,name']);
        $config = $companion->effectiveConfig();

        $personas = AiPersonaAgent::where('user_id', $link->user_id)
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'name' => $p->name])
            ->values()->all();

        $usage = CompanionRuntime::monthlyUsage($companion);

        return $this->ok([
            'ai_chat' => [
                'link_id'    => (int) $link->id,
                'alias'      => $link->alias,
                'public_url' => url('/' . $link->alias),
                'name'       => $companion->name,
                'persona_id' => $companion->persona_id ? (int) $companion->persona_id : null,
                'config'     => [
                    'greeting'          => $config['greeting'] ?? null,
                    'placeholder'       => $config['placeholder'] ?? 'Ask me anything…',
                    'accent'            => $config['accent'] ?? '#7c3aed',
                    'theme'             => $config['theme'] ?? 'auto',
                    'show_branding'     => (bool) ($config['show_branding'] ?? true),
                    'ground_in_profile' => (bool) ($config['ground_in_profile'] ?? true),
                ],
                'starters'   => array_values((array) ($config['starters'] ?? [])),
                'usage'      => [
                    'turns'                => (int) ($usage['turns'] ?? 0),
                    'free_turns_per_month' => (int) $companion->free_turns_per_month,
                    'hard_cap_per_month'   => (int) $companion->hard_cap_per_month,
                ],
                'ai_enabled' => AiEngineSettings::isEnabled(),
            ],
            'personas' => $personas,
        ]);
    }

    /**
     * Save the AI chat page basics. Same fields + validation as the web
     * save() handler, routed through the shared config merge.
     */
    public function save(Request $request, int $id)
    {
        $link = $this->ownedBiolinkFamilyLink($request, $id);
        if (!$link) return $this->notFound('AI chat page not found');

        $companion = $this->pages->ensureCompanion($link);

        $data = $request->validate([
            'name'                     => ['required', 'string', 'max:120'],
            'persona_id'               => ['required', 'integer'],
            'config.greeting'          => ['nullable', 'string', 'max:1000'],
            'config.placeholder'       => ['nullable', 'string', 'max:120'],
            'config.accent'            => ['nullable', 'string', 'max:32'],
            'config.theme'             => ['nullable', Rule::in(['auto', 'light', 'dark'])],
            'config.show_branding'     => ['nullable', 'boolean'],
            'config.ground_in_profile' => ['nullable', 'boolean'],
            'starters'                 => ['nullable', 'array', 'max:6'],
            'starters.*'               => ['nullable', 'string', 'max:200'],
        ]);

        $persona = AiPersonaAgent::where('id', $data['persona_id'])
            ->where('user_id', $link->user_id)
            ->first();
        if (!$persona) {
            return $this->fail('Pick one of your personas.', 422, 'invalid_persona', [
                'persona_id' => ['Pick one of your personas.'],
            ]);
        }

        $cfg = $this->pages->mergeConfig($companion, $data['config'] ?? [], $data['starters'] ?? []);

        DB::transaction(function () use ($companion, $data, $persona, $cfg, $link) {
            $companion->forceFill([
                'name'       => $data['name'],
                'persona_id' => $persona->id,
                'config'     => $cfg,
            ])->save();
            $companion->links()->syncWithoutDetaching([$link->id]);
        });

        return $this->show($request, $id);
    }

    /** Resolve a biolink-family link the caller owns, or null. */
    protected function ownedBiolinkFamilyLink(Request $request, int $id): ?Link
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link || !$link->isBiolinkFamily()) {
            return null;
        }
        return $link;
    }
}
