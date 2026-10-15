<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\MarketingCampaign;
use App\Modules\User\Models\MarketingPlanCalc;
use App\Modules\User\Models\NoteActionPlan;
use App\Services\Brand\StudioConnections;
use App\Services\BudgetForecast;
use App\Services\Marketing\CampaignWorkspace;
use App\Services\MarketingPlanDefaults;
use App\Services\Planner\PlannerResources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class BudgetForecastController extends Controller
{
    public function owned(Request $r, int $plan): MarketingPlanCalc
    {
        abort_unless($r->user()->planFeatureEnabled(MarketingPlanCalculatorController::FEATURE_KEY), 403);

        return MarketingPlanCalc::where('user_id', $r->user()->id)->where('workspace_id', app('current_workspace')->id)->findOrFail($plan);
    }

    public function versions(Request $r, int $plan)
    {
        $p = $this->owned($r, $plan);

        return response()->json(['versions' => DB::table('marketing_forecast_versions')->where('plan_id', $p->id)->where('shared', false)->latest('id')->limit(20)->get(['id', 'name', 'created_at', 'payload'])]);
    }

    public function restore(Request $r, int $plan, int $version)
    {
        $p = $this->owned($r, $plan);
        $v = DB::table('marketing_forecast_versions')->where('plan_id', $p->id)->where('id', $version)->first();
        abort_unless($v, 404);

        return DB::transaction(function () use ($p, $v, $r) {
            app(BudgetForecast::class)->version($p);
            $payload = array_replace(MarketingPlanDefaults::defaults($r->user()),json_decode($v->payload,true));
            if (MarketingPlanDefaults::toolCostsLocked()) {
                $payload = MarketingPlanDefaults::enforceLockedToolCosts($payload);
            }
            foreach (['actuals_log', 'share_epoch', 'campaign_id', 'planner_plan_id', 'project_id'] as $key) {
                if (array_key_exists($key, $p->payload)) {
                    $payload[$key] = $p->payload[$key];
                }
            }
            $p->update(['name' => $v->name, 'payload' => $payload]);

            return response()->json(['name' => $p->name, 'payload' => $p->payload]);
        });
    }

    public function connect(Request $r, int $plan)
    {
        $p = $this->owned($r, $plan);
        $d = $r->validate(['action' => 'required|in:studio,planner,project', 'project_id' => 'nullable|integer', 'campaign_id' => 'nullable|integer']);
        abort_if($d['action'] === 'project' && empty($d['project_id']), 422, 'Choose a folder or project first.');

        return DB::transaction(function () use ($p, $r, $d) {
            $p = MarketingPlanCalc::whereKey($p->id)->lockForUpdate()->firstOrFail();
            $b = $p->payload;
            $project = ! empty($d['project_id']) ? app(StudioConnections::class)->projects()->findOrFail($d['project_id']) : null;
            if ($project) {
                abort_unless($r->user()->canInWorkspace(app('current_workspace'), 'links.edit'), 403);
                app(PlannerResources::class)->attach($project, 'budget', $p->id, $r->user());
                $b['project_id'] = $project->id;
            }
            $brief = 'Budget & Forecast: '.$p->name."\nAssumptions (not guarantees): ".json_encode(collect($b)->only(['annual_budget', 'period_months', 'company', 'gross_margin', 'costs'])->all());
            if ($d['action'] === 'studio') {
                abort_unless($r->user()->canInWorkspace(app('current_workspace'), 'ai.view'), 403);
                $campaign = ! empty($d['campaign_id']) ? app(CampaignWorkspace::class)->owned($d['campaign_id'], $r) : (! empty($b['campaign_id']) ? app(CampaignWorkspace::class)->owned($b['campaign_id'], $r) : null);
                if (! $campaign) {
                    $campaign = MarketingCampaign::create(['user_id' => $r->user()->id, 'workspace_id' => $p->workspace_id, 'name' => mb_substr($p->name, 0, 160), 'path' => 'quick', 'project_id' => $b['project_id'] ?? null, 'brief' => ['goal' => (($b['company'] ?? '') ?: $p->name), 'content' => $brief, 'resources' => [['kind' => 'budget', 'id' => $p->id]]]]);
                } else {
                    $cb = $campaign->brief;
                    $refs = $cb['resources'] ?? [];
                    if (! collect($refs)->contains(fn ($x) => ($x['kind'] ?? '') === 'budget' && ($x['id'] ?? 0) === $p->id)) {
                        $refs[] = ['kind' => 'budget', 'id' => $p->id];
                    }
                    $cb['resources'] = $refs;
                    $campaign->update(['brief' => $cb]);
                }
                $b['campaign_id'] = $campaign->id;
                $redirect = route('user.marketing-studio.show', $campaign);
            } elseif ($d['action'] === 'planner') {
                abort_unless($r->user()->canInWorkspace(app('current_workspace'), 'planner.view'), 403);
                $connectedCampaign = null;
                if (! empty($b['campaign_id'])) {
                    $connectedCampaign = app(CampaignWorkspace::class)->owned($b['campaign_id'], $r);
                    $connectedCampaign = MarketingCampaign::whereKey($connectedCampaign->id)->lockForUpdate()->firstOrFail();
                }
                abort_if(! empty($b['planner_plan_id']) && $connectedCampaign?->plan_id && $connectedCampaign->plan_id !== (int) $b['planner_plan_id'], 422, 'This campaign is already connected to a different Planner plan. Choose a different campaign or open its existing plan.');
                $plannerId = $b['planner_plan_id'] ?? $connectedCampaign?->plan_id;
                $task = $plannerId ? app(PlannerResources::class)->get('plan', $plannerId, $r->user()) : NoteActionPlan::create(['user_id' => $r->user()->id, 'title' => mb_substr($p->name, 0, 160), 'notes' => $brief, 'purpose' => 'project', 'project_id' => $b['project_id'] ?? null, 'planner_settings' => ['goal' => 'Execute '.$p->name, 'resources' => [['kind' => 'budget', 'id' => $p->id]]], 'result' => ['summary' => $brief]]);
                $b['planner_plan_id'] = $task->id;
                if (! empty($b['campaign_id'])) {
                    $campaign = app(CampaignWorkspace::class)->owned($b['campaign_id'], $r);
                    if (! $campaign->plan_id) {
                        $campaign->update(['plan_id' => $task->id, 'project_id' => $task->project_id]);
                    }
                    if ($campaign->plan_id === $task->id) {
                        app(CampaignWorkspace::class)->returnToPlanner($campaign, $r);
                    }
                }
                $redirect = route('user.ai.mind.show', ['record' => $task->id]);
            } else {
                $redirect = route('user.marketing-plan.edit', $p->id);
            }
            $p->update(['payload' => $b]);

            return response()->json(['ok' => true, 'redirect' => $redirect]);
        });
    }

    public function share(Request $r, int $plan)
    {
        $p = $this->owned($r, $plan);
        $d = $r->validate(['days' => 'required|integer|min:1|max:30', 'brand_kit_id' => 'nullable|integer']);
        $brand = ! empty($d['brand_kit_id']) ? app(CampaignWorkspace::class)->contextModel('brand', $d['brand_kit_id']) : null;
        $payload = $p->payload;
        $payload['_subscription_inr'] = collect(MarketingPlanDefaults::planOptions())->firstWhere('slug', $payload['plan_slug'] ?? '')['inr'] ?? 0;
        $payload['report_brand'] = $brand ? ['name' => $brand->name, 'logo' => $brand->logo()] : null;
        $copy = clone $p;
        $copy->payload = $payload;
        $version = app(BudgetForecast::class)->version($copy, true);
        abort_unless($version, 503, 'Version storage is finishing deployment.');

        return response()->json(['url' => URL::temporarySignedRoute('forecast.share', now()->addDays($d['days']), ['plan' => $p->id, 'version' => $version, 'epoch' => $p->payload['share_epoch'] ?? 0])]);
    }

    public function revoke(Request $r, int $plan)
    {
        $p = $this->owned($r, $plan);
        $b = $p->payload;
        $b['share_epoch'] = ($b['share_epoch'] ?? 0) + 1;
        $p->update(['payload' => $b]);

        return response()->json(['ok' => true]);
    }

    public function publicReport(Request $r, int $plan, int $version)
    {
        abort_unless($r->hasValidSignature(), 403);
        $p = MarketingPlanCalc::findOrFail($plan);
        abort_unless((string) ($p->payload['share_epoch'] ?? 0) === (string) $r->query('epoch'), 403);
        $v = DB::table('marketing_forecast_versions')->where('plan_id', $p->id)->where('shared', true)->where('id', $version)->first();
        abort_unless($v, 404);
        $b = json_decode($v->payload, true);
        $sub = (float) ($b['_subscription_inr'] ?? 0);

        return response()->view('public.forecast-report', ['name' => $v->name, 'payload' => $b, 'forecast' => app(BudgetForecast::class)->calculate($b, $sub), 'created' => $v->created_at])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
