<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Controllers\AI\MarketingStrategistController;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersona;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\BrandStudioKit;
use App\Modules\User\Models\MarketingCampaign;
use App\Modules\User\Models\MarketingStrategy;
use App\Modules\User\Models\NoteActionPlan;
use App\Services\Marketing\CampaignWorkspace;
use App\Services\Planner\PlannerResources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MarketingStudioController extends Controller
{
    public function __construct(private CampaignWorkspace $studio) {}

    public function index(Request $r)
    {
        if (! Schema::hasTable('marketing_campaigns')) {
            return response('Marketing Studio is finishing deployment. Please reload shortly.', 503);
        }

        $connected = MarketingCampaign::withoutGlobalScope('workspace')->get(['studio_ids', 'strategy_ids']);
        $studioIds = $connected->flatMap(fn ($item) => $item->studio_ids ?? [])->unique()->all();
        $strategyIds = $connected->flatMap(fn ($item) => $item->strategy_ids ?? [])->unique()->all();

        return view('user.marketing-studio.index', ['campaigns' => MarketingCampaign::where('user_id', $r->user()->id)->latest()->get(), 'legacyStrategies' => MarketingStrategy::whereNotIn('id', $strategyIds)->where('user_id', $r->user()->id)->where('workspace_id', app('current_workspace')->id)->latest()->limit(20)->get(), 'legacyKits' => BrandStudioKit::whereNotIn('id', $studioIds)->where('user_id', workspace_owner_id())->latest()->limit(20)->get()]);
    }

    public function create(Request $r)
    {
        return view('user.marketing-studio.create', $this->options() + ['campaign' => null, 'folderContext' => $r->filled('project_id') ? \App\Modules\User\Models\Project::where('user_id', workspace_owner_id())->findOrFail($r->integer('project_id')) : null]);
    }

    private function options(): array
    {
        $can = fn ($permission) => auth()->user()->canInWorkspace(app('current_workspace'), $permission);

        return ['brandKits' => $can('links.view') ? BrandKit::where('user_id', workspace_owner_id())->get() : collect(), 'minds' => $can('minds.view') ? AiMind::where('user_id', workspace_owner_id())->where('is_disabled', false)->get() : collect(), 'personas' => $can('ai-personas.view') ? AiPersona::where('user_id', workspace_owner_id())->get() : collect(), 'agents' => $can('ai-agents.view') ? AiPersonaAgent::where('user_id', workspace_owner_id())->get() : collect()];
    }

    private function input(Request $r): array
    {
        $d = $r->validate(['project_id' => 'nullable|integer', 'name' => 'required|string|max:160', 'path' => 'required|in:quick,strategy', 'goal' => 'required|string|max:2000', 'offer' => 'nullable|string|max:1000', 'audience' => 'nullable|string|max:300', 'content' => 'nullable|string|max:5000', 'language' => 'nullable|string|max:100', 'channels' => 'nullable|string|max:300', 'budget' => 'nullable|string|max:120', 'constraints' => 'nullable|string|max:1000', 'output' => 'nullable|in:biolink,form,short_link,qr_code,vcard', 'brand_kit_id' => 'nullable|integer', 'persona_id' => 'nullable|integer', 'agent_id' => 'nullable|integer', 'mind_ids' => 'nullable|array|max:8', 'mind_ids.*' => 'integer']);
        if (!empty($d['project_id'])) \App\Modules\User\Models\Project::where('user_id', workspace_owner_id())->findOrFail($d['project_id']);
        foreach (['brand_kit_id' => 'brand', 'persona_id' => 'persona', 'agent_id' => 'agent'] as $key => $kind) {
            if (! empty($d[$key])) {
                $this->studio->contextModel($kind, (int) $d[$key]);
            }
        }
        foreach ($d['mind_ids'] ?? [] as $id) {
            $this->studio->contextModel('mind', (int) $id);
        }

        return $d;
    }

    public function store(Request $r)
    {
        $d = $this->input($r);
        $campaign = MarketingCampaign::create(['user_id' => $r->user()->id, 'workspace_id' => app('current_workspace')->id, 'name' => $d['name'], 'path' => $d['path'], 'project_id' => $d['project_id'] ?? null, 'brief' => collect($d)->except(['name', 'path'])->all(), 'strategy_ids' => [], 'studio_ids' => []]);
        if (! empty($d['output']) && $d['path'] === 'quick') {
            return redirect()->route('user.marketing-studio.build', [$campaign, 'output' => $d['output']]);
        }

        return redirect()->route('user.marketing-studio.show', [$campaign, 'tab' => $d['path'] === 'quick' ? 'create' : 'strategy']);
    }

    public function show(Request $r, int $campaign)
    {
        $campaign = $this->studio->owned($campaign, $r);

        return view('user.marketing-studio.show', $this->options() + ['campaign' => $campaign, 'materials' => $this->studio->materials($campaign), 'strategies' => MarketingStrategy::where('user_id', $r->user()->id)->where('workspace_id', app('current_workspace')->id)->whereIn('id', $campaign->strategy_ids ?? [])->latest()->get(), 'kits' => BrandStudioKit::where('user_id', workspace_owner_id())->whereIn('id', $campaign->studio_ids ?? [])->latest()->get(), 'plans' => app(PlannerResources::class)->query('plan', $r->user())->latest()->limit(50)->get(),
            'tab' => in_array($r->query('tab'), ['overview', 'strategy', 'create', 'results']) ? $r->query('tab') : 'overview']);
    }

    public function update(Request $r, int $campaign)
    {
        $campaign = $this->studio->owned($campaign, $r);
        $d = $this->input($r);
        $d['resources'] = $campaign->brief['resources'] ?? [];
        $history = $campaign->history ?? [];
        $history[] = ['name' => $campaign->name, 'path' => $campaign->path, 'brief' => $campaign->brief, 'saved_at' => now()->toIso8601String()];
        $campaign->update(['name' => $d['name'], 'path' => $d['path'], 'brief' => collect($d)->except(['name', 'path'])->all(), 'history' => array_slice($history, -20)]);

        return back()->with('status', 'Brief saved. Existing materials remain unchanged.');
    }

    public function connectPlan(Request $r, int $campaign)
    {
        $campaign = $this->studio->owned($campaign, $r);
        $data = $r->validate(['plan_id' => 'required|integer']);
        $plan = app(PlannerResources::class)->get('plan', (int) $data['plan_id'], $r->user());
        abort_unless(! $campaign->plan_id || $campaign->plan_id === $plan->id, 422, 'This campaign is already connected to a Planner plan.');
        $campaign->update(['plan_id' => $plan->id, 'project_id' => $plan->project_id]);
        $this->studio->returnToPlanner($campaign, $r);

        return back()->with('status', 'Campaign materials connected to Planner.');
    }

    public function restore(Request $r, int $campaign, int $version)
    {
        $campaign = $this->studio->owned($campaign, $r);
        $snapshot = $campaign->history[$version] ?? null;
        abort_unless($snapshot, 404);
        $history = $campaign->history ?? [];
        $history[] = ['name' => $campaign->name, 'path' => $campaign->path, 'brief' => $campaign->brief, 'saved_at' => now()->toIso8601String()];
        $campaign->update(['name' => $snapshot['name'], 'path' => $snapshot['path'], 'brief' => $snapshot['brief'], 'history' => array_slice($history, -20)]);

        return back()->with('status', 'Brief restored. Existing materials remain unchanged.');
    }

    public function fromPlan(Request $r, NoteActionPlan $plan)
    {
        $campaign = $this->studio->fromPlan($plan, $r);

        return redirect()->route('user.marketing-studio.show', $campaign);
    }

    public function build(Request $r, int $campaign)
    {
        $campaign = $this->studio->owned($campaign, $r);
        $r->merge(['campaign_id' => $campaign->id]);

        return app(BrandStudioController::class)->index($r);
    }

    public function strategy(Request $r, int $campaign)
    {
        $campaign = $this->studio->owned($campaign, $r);
        $r->merge(['campaign_id' => $campaign->id]);

        return app(MarketingStrategistController::class)->create($r);
    }
}
