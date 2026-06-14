<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\Link;
use App\Services\AI\AiChatPageManager;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\CompanionRuntime;
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
        protected AiCreditService $credits,
        protected AiChatPageManager $pages,
    ) {}

    public function editor(Request $request, Link $link)
    {
        $this->ensureBiolinkFamily($link);

        $companion = $this->pages->ensureCompanion($link);
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
        $companion = $this->pages->ensureCompanion($link);

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

        $cfg = $this->pages->mergeConfig($companion, $data['config'] ?? [], $data['starters'] ?? []);

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

    protected function ensureBiolinkFamily(Link $link): void
    {
        if (!$link->isBiolinkFamily()) {
            abort(404);
        }
    }
}
