<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\Link;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindProvisioner;
use App\Services\AI\AiMindQueryService;
use App\Services\AI\InsufficientAiCreditsException;
use App\Services\AI\OpenAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Coach — looks at the active workspace's links and suggests concrete
 * tweaks (titles, calls-to-action, ordering) to lift engagement.
 *
 *   GET  /user/ai/coach              picker + last suggestions
 *   POST /user/ai/coach/suggest      runs the chat call against a link snapshot
 *
 * The form lets the user pick which of their Minds (knowledge bases)
 * Coach should reference, with a separate opt-in toggle for the
 * platform default Mind. When minds are selected, Coach pulls the most
 * relevant chunks and grounds its experiments in that context, and
 * returns the cited sources alongside the suggestions.
 *
 * Spend is tagged `feature => 'coach'`.
 */
class CoachController extends Controller
{
    public function __construct(
        protected OpenAiService $ai,
        protected AiCreditService $credits,
        protected AiMindQueryService $minds,
    ) {}

    public function show(Request $request)
    {
        $this->ensureEnabled();
        $user = $request->user();
        AiMindProvisioner::ensureForUser($user);

        $owner = app('workspace_owner', fn() => $user);
        // Surface the active workspace's most recent links so the user
        // can pick the one they want coached. Cap at 25 to keep the
        // <select> usable.
        $links = Link::query()
            ->where('user_id', $owner->id)
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'title', 'alias', 'long_url', 'type']);

        return view('user.ai.coach', [
            'balance'      => $this->credits->getBalance($user),
            'links'        => $links,
            'result'       => session('ai.coach.result'),
            'pickedId'     => session('ai.coach.link_id'),
            'input'        => session('ai.coach.input', []),
            'mineMinds'    => $this->userMinds($user),
            'platformMind' => $this->platformMind(),
        ]);
    }

    public function suggest(Request $request)
    {
        $this->ensureEnabled();
        $user = $request->user();
        $owner = app('workspace_owner', fn() => $user);
        $data = $request->validate([
            'link_id'          => 'required|integer',
            'goal'             => 'nullable|string|max:200',
            'mind_ids'         => 'nullable|array',
            'mind_ids.*'       => 'integer',
            'include_platform' => 'nullable|boolean',
        ]);
        $link = Link::query()
            ->where('user_id', $owner->id)
            ->whereKey($data['link_id'])
            ->first();
        if (!$link) {
            return back()->with('error', 'Link not found in this workspace.');
        }

        // Snapshot just the bits the model needs — never send raw user
        // PII or settings blobs into the prompt.
        $snapshot = [
            'title'       => $link->title,
            'short_alias' => $link->alias,
            'destination' => $link->long_url,
            'type'        => $link->type,
            'clicks_30d'  => (int) $link->clicks()
                ->where('created_at', '>=', now()->subDays(30))->count(),
        ];

        $goal = $data['goal'] ?? 'increase engagement';
        $userPrompt = "Goal: {$goal}\n\nLink snapshot:\n" . json_encode($snapshot, JSON_PRETTY_PRINT);

        $mindIds = $data['mind_ids'] ?? [];
        $includePlatform = (bool) ($data['include_platform'] ?? false);
        // Coach is owned by the asking user (their credits, their KBs);
        // we deliberately do not let a workspace member query another
        // member's Minds here.
        $selectedMinds = $this->minds->resolveMindsForUser($user, $mindIds, $includePlatform);

        $kbCreditsSpent = 0;
        $citations      = [];
        $kbContext      = '';
        if ($selectedMinds) {
            // Use goal + link title as the retrieval query — captures
            // both the strategic intent and the topic of the link.
            $retrievalQuery = trim($goal . ' ' . ($link->title ?? '') . ' ' . ($link->long_url ?? ''));
            try {
                $retrieved = $this->minds->retrieveContext($user, $selectedMinds, $retrievalQuery);
                $kbContext      = $retrieved['context'];
                $citations      = $retrieved['citations'];
                $kbCreditsSpent = (int) $retrieved['credits_spent'];
            } catch (InsufficientAiCreditsException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('Coach Mind retrieval failed: ' . $e->getMessage());
            }
        }

        $systemPrompt = "You are Coach, a growth advisor for a link-in-bio creator. "
            . "Given a link snapshot and an optional goal, return: "
            . "a one-sentence diagnosis, then 3 numbered, specific, "
            . "low-effort experiments to try this week. No fluff.";
        if ($kbContext !== '') {
            $systemPrompt .= "\n\nWhen relevant, ground your experiments in the Knowledge Base context "
                . "below — reuse its terminology, products and audience details. Do not invent "
                . "facts that are not in the context.\n\n"
                . "Knowledge Base context:\n" . $kbContext;
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ];

        try {
            $out = $this->ai->chat($request->user(), AiEngineSettings::featureModel('coach'), $messages, [
                'feature'     => 'coach.suggest',
                'related_id'  => $link->id,
                'temperature' => 0.5,
                'max_tokens'  => 500,
                'reason'      => "Coach: suggestions for link #{$link->id}",
            ]);
        } catch (\RuntimeException $e) {
            if ($e instanceof InsufficientAiCreditsException) throw $e;
            Log::warning('Coach AI call failed: ' . $e->getMessage());
            return back()->with('error',
                'Coach could not respond right now. Please try again.');
        }

        session()->flash('ai.coach.link_id', $link->id);
        session()->flash('ai.coach.input', [
            'goal'             => $data['goal'] ?? null,
            'mind_ids'         => array_map('intval', $mindIds),
            'include_platform' => $includePlatform,
        ]);
        session()->flash('ai.coach.result', [
            'content'        => $out['content'],
            'credits_spent'  => (int) $out['credits_spent'] + $kbCreditsSpent,
            'model'          => $out['model'],
            'link_title'     => $link->title,
            'citations'      => $citations,
            'minds_used'     => array_map(
                fn(AiMind $m) => ['id' => (int) $m->id, 'name' => (string) $m->name, 'is_platform' => $m->isPlatform()],
                $selectedMinds,
            ),
        ]);
        return redirect()->route('user.ai.coach.show');
    }

    /** @return \Illuminate\Support\Collection<int,AiMind> */
    protected function userMinds($user)
    {
        return AiMind::where('user_id', $user->id)
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function platformMind(): ?AiMind
    {
        return AiMind::whereNull('user_id')
            ->where('is_default', true)
            ->where('is_disabled', false)
            ->first(['id', 'name']);
    }

    protected function ensureEnabled(): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
    }
}
