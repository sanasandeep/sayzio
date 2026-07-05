<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\CompetitorTeardown;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\CompetitorTeardownService;
use App\Services\AI\InsufficientCoinsForAiException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile (Sanctum) parity for the web Competitor Biolink Teardown flow
 * (App\Modules\User\Controllers\CompetitorTeardownController). Every write
 * lands on the workspace owner, matching the web controller's convention
 * (the Sanctum API path never binds an active workspace, so we resolve the
 * "owner" the same way the rest of the API module does: the caller's own
 * account — team-scoped teardown handoff on mobile is a web-only nuance
 * left for a future task, same as the Card & Brochure Scanner's mobile
 * parity today).
 *
 *   GET    /links-teardown            → recent teardowns (max 8) + engine/balance
 *   POST   /links-teardown            → fetch + AI-score a competitor URL
 *   GET    /links-teardown/{teardown} → scored results
 *   POST   /links-teardown/{teardown}/build → hand off to the AI biolink builder
 */
class CompetitorTeardownController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected CompetitorTeardownService $teardown,
        protected AiUsageCharger $credits,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $recent = CompetitorTeardown::withoutGlobalScope('workspace')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(8)
            ->get();

        return $this->ok([
            'ai_enabled' => AiEngineSettings::isEnabled() && (bool) AiEngineSettings::openAiKey(),
            'allowed'    => AiPlanAccess::featureAllowed($user, 'competitor_teardown'),
            'balance'    => $this->credits->getBalance($user),
            'items'      => $recent->map(fn (CompetitorTeardown $t) => $this->transform($t))->all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        $user = $request->user();

        if (!AiPlanAccess::featureAllowed($user, 'competitor_teardown')) {
            return $this->planGate(
                'The Competitor Biolink Teardown is not available on your current plan.',
                'competitor_teardown',
                $user,
            );
        }

        if (!AiEngineSettings::isEnabled() || !AiEngineSettings::openAiKey()) {
            return $this->fail('AI analysis is currently unavailable. Please try again later.', 503, 'ai_unavailable');
        }

        try {
            $teardown = $this->teardown->analyze($user, $user, $data['url']);
        } catch (InsufficientCoinsForAiException $e) {
            return $this->fail('Not enough AI credits to run a teardown.', 402, 'insufficient_credits', [
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'analysis_failed');
        } catch (\Throwable $e) {
            report($e);
            return $this->fail("We couldn't analyze that page. Double-check the URL and try again.", 422, 'analysis_failed');
        }

        return $this->created($this->transform($teardown));
    }

    public function show(Request $request, int $id)
    {
        $teardown = $this->ownedTeardown($request, $id);
        if (!$teardown) {
            return $this->notFound('Teardown not found');
        }

        return $this->ok($this->transform($teardown));
    }

    public function build(Request $request, int $id)
    {
        $teardown = $this->ownedTeardown($request, $id);
        if (!$teardown) {
            return $this->notFound('Teardown not found');
        }

        if ($teardown->status !== 'completed') {
            return $this->fail("This teardown isn't ready yet.", 422, 'not_ready');
        }

        if (!AiEngineSettings::isEnabled() || !AiEngineSettings::openAiKey()) {
            return $this->fail('AI building is currently unavailable. Please try again later.', 503, 'ai_unavailable');
        }

        $user = $request->user();

        try {
            $link = $this->teardown->buildBetterVersion($user, $user, $teardown);
        } catch (InsufficientCoinsForAiException $e) {
            return $this->fail('Not enough AI credits to build this page.', 402, 'insufficient_credits', [
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'build_failed');
        } catch (\Throwable $e) {
            report($e);
            return $this->fail("We couldn't build that page. Please try again.", 422, 'build_failed');
        }

        return $this->ok([
            'link_id' => $link->id,
            'alias'   => $link->alias,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function transform(CompetitorTeardown $t): array
    {
        return [
            'id'             => $t->id,
            'competitor_url' => $t->competitor_url,
            'status'         => $t->status,
            'analysis'       => is_array($t->analysis) ? $t->analysis : null,
            'error'          => $t->error,
            'credits_spent'  => (int) $t->credits_spent,
            'built_link_id'  => $t->built_link_id,
            'created_at'     => optional($t->created_at)->toIso8601String(),
        ];
    }

    private function ownedTeardown(Request $request, int $id): ?CompetitorTeardown
    {
        return CompetitorTeardown::withoutGlobalScope('workspace')
            ->where('user_id', $request->user()->id)
            ->find($id);
    }
}
