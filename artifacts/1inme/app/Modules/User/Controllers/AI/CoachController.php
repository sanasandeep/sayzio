<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
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
 * Spend is tagged `feature => 'coach'`.
 */
class CoachController extends Controller
{
    protected const MODEL = 'gpt-4o-mini';

    public function __construct(
        protected OpenAiService $ai,
        protected AiCreditService $credits,
    ) {}

    public function show(Request $request)
    {
        $this->ensureEnabled();
        $owner = app('workspace_owner', fn() => $request->user());
        // Surface the active workspace's most recent links so the user
        // can pick the one they want coached. Cap at 25 to keep the
        // <select> usable.
        $links = Link::query()
            ->where('user_id', $owner->id)
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'title', 'alias', 'long_url', 'type']);

        return view('user.ai.coach', [
            'balance' => $this->credits->getBalance($request->user()),
            'links'   => $links,
            'result'  => session('ai.coach.result'),
            'pickedId'=> session('ai.coach.link_id'),
        ]);
    }

    public function suggest(Request $request)
    {
        $this->ensureEnabled();
        $owner = app('workspace_owner', fn() => $request->user());
        $data = $request->validate([
            'link_id' => 'required|integer',
            'goal'    => 'nullable|string|max:200',
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

        $messages = [
            ['role' => 'system', 'content' =>
                "You are Coach, a growth advisor for a link-in-bio creator. "
                . "Given a link snapshot and an optional goal, return: "
                . "a one-sentence diagnosis, then 3 numbered, specific, "
                . "low-effort experiments to try this week. No fluff."],
            ['role' => 'user', 'content' =>
                "Goal: " . ($data['goal'] ?? 'increase engagement') . "\n\n"
                . "Link snapshot:\n" . json_encode($snapshot, JSON_PRETTY_PRINT)],
        ];

        try {
            $out = $this->ai->chat($request->user(), self::MODEL, $messages, [
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
        session()->flash('ai.coach.result', [
            'content'       => $out['content'],
            'credits_spent' => $out['credits_spent'],
            'model'         => $out['model'],
            'link_title'    => $link->title,
        ]);
        return redirect()->route('user.ai.coach.show');
    }

    protected function ensureEnabled(): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
    }
}
