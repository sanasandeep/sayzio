<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CompetitorTeardown;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\CompetitorTeardownService;
use App\Services\AI\InsufficientCoinsForAiException;
use Illuminate\Http\Request;

/**
 * Creator-facing endpoints for the Competitor Biolink Teardown feature
 * (Task #3532).
 *
 * Routes:
 *   GET  /links-teardown/create        intake screen (paste a URL)
 *   POST /links-teardown                run fetch + AI teardown
 *   GET  /links-teardown/{teardown}     scored results
 *   POST /links-teardown/{teardown}/build  hand off to the AI biolink builder
 *
 * Every write is workspace-scoped via {@see workspace_owner()}; team
 * members can run/view teardowns, but the resulting record — and any
 * biolink built from it — lands on the workspace owner, matching the
 * Card & Brochure Scanner's convention.
 */
class CompetitorTeardownController extends Controller
{
    public function __construct(
        protected CompetitorTeardownService $teardown,
        protected AiUsageCharger $credits,
    ) {}

    public function create(Request $request)
    {
        if (!AiPlanAccess::featureAllowed($request->user(), 'competitor_teardown')) {
            return view('user.ai.disabled', [
                'title'       => 'Competitor Biolink Teardown',
                'upgradePlan' => AiPlanAccess::featureUpgradePlan($request->user(), 'competitor_teardown'),
            ]);
        }

        $owner  = workspace_owner();
        $recent = CompetitorTeardown::withoutGlobalScope('workspace')
            ->where('user_id', $owner->id)
            ->latest()
            ->limit(8)
            ->get();

        return view('user.links.teardown.create', [
            'recent'   => $recent,
            'engineOn' => AiEngineSettings::isEnabled() && (bool) AiEngineSettings::openAiKey(),
            'balance'  => $this->credits->getBalance($request->user()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        $owner = workspace_owner();
        $actor = $request->user();

        if (!AiPlanAccess::featureAllowed($actor, 'competitor_teardown')) {
            $plan = AiPlanAccess::featureUpgradePlan($actor, 'competitor_teardown');
            $msg  = 'The Competitor Biolink Teardown is not available on your current plan.';
            if ($plan) {
                $msg .= ' Upgrade to the ' . $plan->name . ' plan to use it.';
            }
            return back()->with('error', $msg)->withInput();
        }

        if (!AiEngineSettings::isEnabled() || !AiEngineSettings::openAiKey()) {
            return back()->with('error', 'AI analysis is currently unavailable. Please try again later.')->withInput();
        }

        try {
            $teardown = $this->teardown->analyze($owner, $actor, $data['url']);
        } catch (InsufficientCoinsForAiException $e) {
            return redirect()->route('user.wallet.buy')
                ->with('error', "You need {$e->required} coins to run a teardown (you have {$e->balance}).");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', "We couldn't analyze that page. Double-check the URL and try again.")->withInput();
        }

        return redirect()->route('user.links.teardown.show', $teardown);
    }

    public function show(Request $request, CompetitorTeardown $teardown)
    {
        $this->authorizeTeardown($teardown);

        return view('user.links.teardown.show', [
            'teardown' => $teardown,
            'analysis' => is_array($teardown->analysis) ? $teardown->analysis : [],
            'balance'  => $this->credits->getBalance($request->user()),
        ]);
    }

    public function build(Request $request, CompetitorTeardown $teardown)
    {
        $this->authorizeTeardown($teardown);

        if ($teardown->status !== 'completed') {
            return back()->with('error', "This teardown isn't ready yet.");
        }

        if (!AiEngineSettings::isEnabled() || !AiEngineSettings::openAiKey()) {
            return back()->with('error', 'AI building is currently unavailable. Please try again later.');
        }

        $owner = workspace_owner();
        $actor = $request->user();

        try {
            $link = $this->teardown->buildBetterVersion($owner, $actor, $teardown);
        } catch (InsufficientCoinsForAiException $e) {
            return redirect()->route('user.wallet.buy')
                ->with('error', "You need {$e->required} coins to build this page (you have {$e->balance}).");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', "We couldn't build that page. Please try again.");
        }

        return redirect()->route('user.links.blocks.editor', $link)
            ->with('success', 'Built a better version based on the teardown — review and publish when ready.');
    }

    private function authorizeTeardown(CompetitorTeardown $teardown): void
    {
        abort_if($teardown->user_id !== workspace_owner_id(), 403);
    }
}
