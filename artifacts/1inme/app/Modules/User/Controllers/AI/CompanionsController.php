<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\AiCompanionConversation;
use App\Modules\Common\Models\AiCompanionMessage;
use App\Modules\User\Models\AiCompanion;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\Link;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\CompanionRuntime;
use App\Services\AI\CompanionSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customer-facing AI Companions dashboard. Lets the user wire any of
 * their existing AI Personas into one of three placements (biolink /
 * embed / inbox) and review the resulting conversations + analytics.
 *
 * The Companion is intentionally thin — all "brain" config lives on
 * the Persona, so the user can swap personas under the same widget
 * without breaking embedded snippets or biolink blocks.
 */
class CompanionsController extends Controller
{
    public function __construct(
        protected AiUsageCharger $credits,
    ) {}

    public function index(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) {
            return view('user.ai.disabled', ['title' => 'Companions']);
        }
        $this->ensureEnabled();
        $user = $request->user();

        $companions = AiCompanion::where('user_id', $user->id)
            ->with('persona:id,name,is_disabled')
            ->withCount('conversations')
            ->latest('updated_at')
            ->get();

        $caps = CompanionSettings::caps();
        // Effective per-plan cap (falls back to the global admin cap).
        $caps['max_companions_per_user'] = \App\Services\AI\AiPlanAccess::quantityCap($user, 'companions');

        return view('user.ai-companions.index', [
            'companions' => $companions,
            'caps'       => $caps,
            'used'       => $companions->count(),
            'placements' => AiCompanion::PLACEMENTS,
        ]);
    }

    public function create(Request $request)
    {
        $this->ensureEnabled();
        $user = $request->user();
        $caps = CompanionSettings::caps();
        $caps['max_companions_per_user'] = \App\Services\AI\AiPlanAccess::quantityCap($user, 'companions');

        $current = AiCompanion::where('user_id', $user->id)->count();
        if (!\App\Services\AI\AiPlanAccess::underQuantityCap($user, 'companions', $current)) {
            return redirect()->route('user.ai-companions.index')->with('error',
                \App\Services\AI\AiPlanAccess::quantityLimitMessage($user, 'companions', 'Companion', $current));
        }

        $personas = AiPersonaAgent::where('user_id', $user->id)
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('user.ai-companions.create', [
            'personas'   => $personas,
            'placements' => AiCompanion::PLACEMENTS,
            'caps'       => $caps,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureEnabled();
        $user = $request->user();
        $current = AiCompanion::where('user_id', $user->id)->count();
        if (!\App\Services\AI\AiPlanAccess::underQuantityCap($user, 'companions', $current)) {
            return back()->with('error',
                \App\Services\AI\AiPlanAccess::quantityLimitMessage($user, 'companions', 'Companion', $current));
        }

        $data = $request->validate([
            'name'       => 'required|string|max:120',
            'persona_id' => 'required|integer',
            'placement'  => 'required|in:' . implode(',', array_keys(AiCompanion::PLACEMENTS)),
        ]);

        $persona = AiPersonaAgent::where('id', $data['persona_id'])
            ->where('user_id', $user->id)
            ->first();
        if (!$persona) {
            return back()->withInput()->withErrors(['persona_id' => 'Pick one of your Personas.']);
        }

        $companion = AiCompanion::create([
            'user_id'              => $user->id,
            'persona_id'           => $persona->id,
            'public_id'            => AiCompanion::newPublicId(),
            'name'                 => $data['name'],
            'placement'            => $data['placement'],
            'config'               => AiCompanion::defaultConfig(),
            'allowed_domains'      => [],
            'free_turns_per_month' => $caps['default_free_turns_per_month'],
            'hard_cap_per_month'   => 2000,
        ]);

        return redirect()->route('user.ai-companions.edit', $companion)
            ->with('status', 'Companion created. Configure it below.');
    }

    public function edit(Request $request, AiCompanion $companion)
    {
        $this->ensureEnabled();
        $this->authorize_($companion, $request->user());

        $companion->load(['persona:id,name', 'links:id,alias,title']);
        $personas = AiPersonaAgent::where('user_id', $request->user()->id)
            ->where('is_disabled', false)
            ->orderBy('name')->get(['id','name']);
        $links = Link::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get(['id', 'alias', 'title']);

        $usage = CompanionRuntime::monthlyUsage($companion);

        return view('user.ai-companions.edit', [
            'companion'      => $companion,
            'personas'       => $personas,
            'links'          => $links,
            'attachedLinks'  => $companion->links->pluck('id')->all(),
            'caps'           => CompanionSettings::caps(),
            'placements'     => AiCompanion::PLACEMENTS,
            'config'         => $companion->effectiveConfig(),
            'usage'          => $usage,
            'embedScriptUrl' => url('/embed/companion.js'),
            'iframeUrl'      => route('public.companion.iframe', ['publicId' => $companion->public_id]),
            'balance'        => $this->credits->getBalance($request->user()),
        ]);
    }

    public function update(Request $request, AiCompanion $companion)
    {
        $this->ensureEnabled();
        $this->authorize_($companion, $request->user());
        $caps = CompanionSettings::caps();

        $data = $request->validate([
            'name'                 => 'required|string|max:120',
            'persona_id'           => 'required|integer',
            'placement'            => 'required|in:' . implode(',', array_keys(AiCompanion::PLACEMENTS)),
            'free_turns_per_month' => 'nullable|integer|min:0|max:1000000',
            'hard_cap_per_month'   => 'nullable|integer|min:0|max:1000000',
            'allowed_domains'      => 'nullable|array|max:' . $caps['max_allowed_domains'],
            'allowed_domains.*'    => 'nullable|string|max:200',
            'link_ids'             => 'nullable|array|max:100',
            'link_ids.*'           => 'integer',

            'config.theme'             => 'nullable|in:auto,light,dark',
            'config.accent'            => 'nullable|string|max:32',
            'config.launcher_icon'     => 'nullable|string|max:64',
            'config.launcher_label'    => 'nullable|string|max:60',
            'config.position'          => 'nullable|in:bottom-right,bottom-left',
            'config.greeting_bubble'   => 'nullable|string|max:280',
            'config.placeholder'       => 'nullable|string|max:120',
            'config.show_branding'     => 'nullable|boolean',
            'config.auto_open_after_ms'=> 'nullable|integer|min:0|max:60000',
            'config.inline'            => 'nullable|boolean',
            'config.auto_send_inbox'   => 'nullable|boolean',
        ]);

        $persona = AiPersonaAgent::where('id', $data['persona_id'])
            ->where('user_id', $request->user()->id)->first();
        if (!$persona) {
            return back()->withInput()->withErrors(['persona_id' => 'Pick one of your Personas.']);
        }

        // Normalize domain list — strip schemes, lowercase, dedupe.
        $domains = collect($data['allowed_domains'] ?? [])
            ->map(fn ($d) => strtolower(trim((string) $d)))
            ->map(function ($d) {
                if ($d === '') return '';
                $d = preg_replace('#^https?://#', '', $d);
                $d = preg_replace('#/.*$#', '', $d);
                return $d;
            })
            ->filter()->unique()->values()->all();

        $linkIds = AiCompanion::query()->getConnection()
            ->table('links')
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $data['link_ids'] ?? [])
            ->pluck('id')->all();

        $cfg = array_merge($companion->effectiveConfig(), array_filter(
            (array) ($data['config'] ?? []),
            fn ($v) => $v !== null,
        ));
        $cfg['show_branding']   = (bool) ($data['config']['show_branding']   ?? $cfg['show_branding']);
        $cfg['inline']          = (bool) ($data['config']['inline']          ?? false);
        $cfg['auto_send_inbox'] = (bool) ($data['config']['auto_send_inbox'] ?? false);

        DB::transaction(function () use ($companion, $data, $persona, $cfg, $domains, $linkIds, $caps) {
            $hardCap = (int) ($data['hard_cap_per_month'] ?? $companion->hard_cap_per_month);
            if ($caps['platform_hard_cap_per_month'] > 0) {
                $hardCap = min($hardCap ?: $caps['platform_hard_cap_per_month'], $caps['platform_hard_cap_per_month']);
            }
            $companion->forceFill([
                'name'                 => $data['name'],
                'persona_id'           => $persona->id,
                'placement'            => $data['placement'],
                'config'               => $cfg,
                'allowed_domains'      => $domains,
                'free_turns_per_month' => (int) ($data['free_turns_per_month'] ?? $companion->free_turns_per_month),
                'hard_cap_per_month'   => $hardCap,
            ])->save();
            $companion->links()->sync($linkIds);
        });

        return back()->with('status', 'Companion saved.');
    }

    public function destroy(Request $request, AiCompanion $companion)
    {
        $this->ensureEnabled();
        $this->authorize_($companion, $request->user());
        $companion->delete();
        return redirect()->route('user.ai-companions.index')->with('status', 'Companion deleted.');
    }

    public function conversations(Request $request, AiCompanion $companion)
    {
        $this->ensureEnabled();
        $this->authorize_($companion, $request->user());

        $convs = AiCompanionConversation::where('companion_id', $companion->id)
            ->orderByDesc('last_message_at')
            ->paginate(25);

        // ── Lightweight analytics ──────────────────────────────────
        // Top visitor questions (most-asked first 80 chars), last
        // 30 days, capped at 10 — useful for spotting recurring
        // intents that the persona's knowledge base should answer.
        $convIds = AiCompanionConversation::where('companion_id', $companion->id)->pluck('id');
        $since   = now()->subDays(30);
        $topQuestions = AiCompanionMessage::query()
            ->whereIn('conversation_id', $convIds)
            ->where('role', 'user')
            ->where('created_at', '>=', $since)
            ->select(\DB::raw('LOWER(SUBSTRING(content, 1, 80)) as q'), \DB::raw('COUNT(*) as n'))
            ->groupBy('q')
            ->orderByDesc('n')
            ->limit(10)
            ->get();

        // Avg per-message rating (1..5 thumbs) — null if untracked.
        $avgRating = (float) AiCompanionMessage::whereIn('conversation_id', $convIds)
            ->whereNotNull('rating')->avg('rating');
        // "Deflection rate" proxy: share of conversations that ended
        // without the visitor ever reaching out via inbox / link
        // click — we don't track those joins yet, so we expose the
        // simpler "answered turn" share instead (assistant turns /
        // user turns this month).
        $monthStart = now()->startOfMonth();
        $monthlyUserTurns = (int) AiCompanionMessage::whereIn('conversation_id', $convIds)
            ->where('role', 'user')->where('created_at', '>=', $monthStart)->count();
        $monthlyAiTurns = (int) AiCompanionMessage::whereIn('conversation_id', $convIds)
            ->where('role', 'assistant')->where('created_at', '>=', $monthStart)->count();
        $answeredRate = $monthlyUserTurns > 0
            ? round(min(1.0, $monthlyAiTurns / $monthlyUserTurns) * 100)
            : null;

        return view('user.ai-companions.conversations', [
            'companion'     => $companion,
            'conversations' => $convs,
            'topQuestions'  => $topQuestions,
            'avgRating'     => $avgRating,
            'answeredRate'  => $answeredRate,
        ]);
    }

    public function conversation(Request $request, AiCompanion $companion, AiCompanionConversation $conversation)
    {
        $this->ensureEnabled();
        $this->authorize_($companion, $request->user());
        if ((int) $conversation->companion_id !== (int) $companion->id) abort(404);

        $messages = $conversation->messages()->limit(500)->get();

        return view('user.ai-companions.conversation', [
            'companion'    => $companion,
            'conversation' => $conversation,
            'messages'     => $messages,
        ]);
    }

    protected function authorize_(AiCompanion $companion, $user): void
    {
        if ((int) $companion->user_id !== (int) $user->id) abort(403);
    }

    protected function ensureEnabled(): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
    }
}
