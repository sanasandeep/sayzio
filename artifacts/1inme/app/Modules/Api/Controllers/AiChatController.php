<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\UserFile;
use App\Services\AI\AiChatPageManager;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\CompanionRuntime;
use App\Services\UploadPolicy;
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
        $caps  = $companion->brandingCapabilities();

        return $this->ok([
            'ai_chat' => [
                'link_id'    => (int) $link->id,
                'alias'      => $link->alias,
                'public_url' => url('/' . $link->alias),
                'name'       => $companion->name,
                'persona_id' => $companion->persona_id ? (int) $companion->persona_id : null,
                'config'     => [
                    'greeting'             => $config['greeting'] ?? null,
                    'placeholder'          => $config['placeholder'] ?? 'Ask me anything…',
                    'accent'               => $config['accent'] ?? '#7c3aed',
                    'theme'                => $config['theme'] ?? 'auto',
                    'show_branding'        => (bool) ($config['show_branding'] ?? true),
                    'ground_in_profile'    => (bool) ($config['ground_in_profile'] ?? true),
                    'avatar_url'           => $config['avatar_url'] ?? null,
                    'custom_branding_text' => $config['custom_branding_text'] ?? null,
                    'custom_branding_url'  => $config['custom_branding_url'] ?? null,
                ],
                'branding'   => [
                    'can_hide_branding'   => (bool) ($caps['can_hide_branding'] ?? false),
                    'can_custom_branding' => (bool) ($caps['can_custom_branding'] ?? false),
                    'can_avatar'          => (bool) ($caps['can_avatar'] ?? false),
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
            // Worst-case coins one visitor turn may debit from THIS owner's
            // wallet + their balance, following the shared coin_cost +
            // coin_balance affordability pattern (AskCoachController::threads).
            'coin_cost'    => $this->companionTurnCoins($request->user()),
            'coin_balance' => app(\App\Services\AI\AiUsageCharger::class)->getBalance($request->user()),
        ]);
    }

    /**
     * Worst-case coins one companion turn may cost the page owner. Never
     * fails the loader — a broken estimate just hides the hint (0).
     */
    protected function companionTurnCoins($user): int
    {
        try {
            return (int) (app(\App\Services\AI\AiCostEstimator::class)
                ->estimate($user, 'companion', str_repeat('x', 400))['coins'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
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
            'name'                        => ['required', 'string', 'max:120'],
            'persona_id'                  => ['required', 'integer'],
            'config.greeting'             => ['nullable', 'string', 'max:1000'],
            'config.placeholder'          => ['nullable', 'string', 'max:120'],
            'config.accent'               => ['nullable', 'string', 'max:32'],
            'config.theme'                => ['nullable', Rule::in(['auto', 'light', 'dark'])],
            'config.show_branding'        => ['nullable', 'boolean'],
            'config.ground_in_profile'    => ['nullable', 'boolean'],
            'config.avatar_url'           => ['nullable', 'string', 'max:2048'],
            'config.custom_branding_text' => ['nullable', 'string', 'max:60'],
            'config.custom_branding_url'  => ['nullable', 'string', 'max:300'],
            'avatar_remove'               => ['nullable', 'boolean'],
            'avatar_upload'               => ['nullable', UploadPolicy::rule('link.ai_avatar', $request->user())],
            'starters'                    => ['nullable', 'array', 'max:6'],
            'starters.*'                  => ['nullable', 'string', 'max:200'],
        ]);

        $persona = AiPersonaAgent::where('id', $data['persona_id'])
            ->where('user_id', $link->user_id)
            ->first();
        if (!$persona) {
            return $this->fail('Pick one of your personas.', 422, 'invalid_persona', [
                'persona_id' => ['Pick one of your personas.'],
            ]);
        }

        // Resolve the agent avatar before merging — an uploaded file lands
        // in the vault, otherwise we keep the posted URL. "Remove" wins.
        // mergeConfig still strips it for owners without a branding plan.
        $cfgInput = $data['config'] ?? [];
        if (!empty($data['avatar_remove'])) {
            $cfgInput['avatar_url'] = null;
        } elseif ($request->hasFile('avatar_upload')) {
            $cfgInput['avatar_url'] = UserFile::createFromUpload(
                $request->file('avatar_upload'),
                $request->user(),
                ['compress_image' => true, 'max_width' => 512, 'max_height' => 512, 'quality' => 88]
            )->url;
        }

        $cfg = $this->pages->mergeConfig($companion, $cfgInput, $data['starters'] ?? []);

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
