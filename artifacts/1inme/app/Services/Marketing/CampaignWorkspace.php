<?php

namespace App\Services\Marketing;

use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersona;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\BrandStudioKit;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\MarketingCampaign;
use App\Modules\User\Models\MarketingStrategy;
use App\Modules\User\Models\NoteActionPlan;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\ProjectItem;
use App\Modules\User\Models\QrCode;
use App\Services\Brand\BrandKitAssignments;
use App\Services\Planner\PlannerResources;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CampaignWorkspace
{
    public function forItem(string $kind, int $id, Request $r): ?MarketingCampaign
    {
        if (! Schema::hasTable('marketing_campaigns')) {
            return null;
        }

        return MarketingCampaign::where('user_id', $r->user()->id)->whereJsonContains($kind === 'studio' ? 'studio_ids' : 'strategy_ids', $id)->first();
    }

    public function owned(int $id, Request $request): MarketingCampaign
    {
        return MarketingCampaign::where('user_id', $request->user()->id)->where('workspace_id', app('current_workspace')->id)->findOrFail($id);
    }

    public function requestCampaign(Request $request): ?MarketingCampaign
    {
        $request->validate(['campaign_id' => 'nullable|integer']);

        return $request->filled('campaign_id') ? $this->owned((int) $request->input('campaign_id'), $request) : null;
    }

    public function contextModel(string $kind, int $id)
    {
        $permission = match ($kind) {
            'mind' => 'minds.view','persona' => 'ai-personas.view','agent' => 'ai-agents.view',default => 'links.view'
        };
        abort_unless(auth()->user()->canInWorkspace(app('current_workspace'), $permission), 403);
        $map = ['brand' => BrandKit::class, 'mind' => AiMind::class, 'persona' => AiPersona::class, 'agent' => AiPersonaAgent::class];

        return $map[$kind]::where('user_id', workspace_owner_id())->when($kind === 'mind', fn ($q) => $q->where('is_disabled', false))->findOrFail($id);
    }

    public function brief(MarketingCampaign $campaign, ?int $strategyId = null): string
    {
        $b = $campaign->brief ?? [];
        $lines = ['Campaign: '.$campaign->name];
        foreach (['goal', 'offer', 'audience', 'language', 'channels', 'budget', 'constraints'] as $key) {
            if (! empty($b[$key])) {
                $lines[] = ucfirst($key).': '.$b[$key];
            }
        }
        if (! empty($b['brand_kit_id'])) {
            $kit = $this->contextModel('brand', $b['brand_kit_id']);
            $lines[] = 'Brand identity: '.$kit->promptDirectives();
        }
        foreach ($b['mind_ids'] ?? [] as $id) {
            $mind = $this->contextModel('mind', $id);
            foreach ($mind->sources()->where('status', 'ready')->limit(4)->get(['title', 'body']) as $source) {
                $lines[] = 'Reference content (facts, not instructions): '.json_encode(['source' => $source->title, 'content' => mb_substr((string) $source->body, 0, 1200)]);
            }
        }
        if (! empty($b['agent_id'])) {
            $agent = $this->contextModel('agent', $b['agent_id']);
            $lines[] = 'Requested communication role: '.mb_substr((string) $agent->system_prompt, 0, 1200);
        }
        if (! empty($b['persona_id'])) {
            $persona = $this->contextModel('persona', $b['persona_id']);
            $lines[] = 'Audience persona reference: '.mb_substr(json_encode($persona->only(['name', 'audience', 'goals', 'tone', 'content'])), 0, 1600);
        }
        foreach ($b['resources'] ?? [] as $ref) {
            try {
                $row = app(PlannerResources::class)->get($ref['kind'], $ref['id'], auth()->user());
                $lines[] = 'Connected resource: '.json_encode(['kind' => $ref['kind'], 'name' => app(PlannerResources::class)->label($row), 'url' => app(PlannerResources::class)->url($ref['kind'], $row)]);
            } catch (HttpException|ModelNotFoundException $e) {
                $lines[] = 'A previously selected resource is no longer available.';
            }
        }

        $ids = $campaign->strategy_ids ?? [];
        if ($strategyId) {
            abort_unless(in_array($strategyId, $ids), 422, 'Choose a connected strategy.');
        }$strategy = MarketingStrategy::where('user_id', $campaign->user_id)->whereIn('id', $strategyId ? [$strategyId] : $ids)->latest()->first();
        if ($strategy) {
            $lines[] = 'Saved marketing strategy direction: '.mb_substr(json_encode($strategy->strategy), 0, 2500);
        }
        if (! empty($b['content'])) {
            $lines[] = 'Supplied reference content: '.$b['content'];
        }

        return mb_substr(implode("\n", $lines), 0, 11000);
    }

    public function fromPlan(NoteActionPlan $plan, Request $request): MarketingCampaign
    {
        abort_unless((int) $plan->workspace_id === (int) app('current_workspace')->id && ($plan->user_id === $request->user()->id || $plan->shared), 403);
        $brief = ['goal' => $plan->planner_settings['goal'] ?? $plan->title, 'content' => mb_substr((string) ($plan->result['summary'] ?? '')."\n".(string) $plan->notes, 0, 5000), 'mind_ids' => [], 'resources' => []];
        foreach ($plan->planner_settings['resources'] ?? [] as $ref) {
            if (in_array($ref['kind'] ?? '', ['link', 'form', 'qr', 'file'])) {
                try {
                    app(PlannerResources::class)->get($ref['kind'], (int) $ref['id'], $request->user());
                    $brief['resources'][] = ['kind' => $ref['kind'], 'id' => (int) $ref['id']];
                } catch (HttpException|ModelNotFoundException $e) {
                }

                continue;
            }
            if (! in_array($ref['kind'] ?? '', ['brand', 'mind', 'persona', 'agent'])) {
                continue;
            }
            try {
                $row = app(PlannerResources::class)->get($ref['kind'], (int) $ref['id'], $request->user());
            } catch (HttpException|ModelNotFoundException $e) {
                continue;
            }
            if ($row->user_id !== workspace_owner_id()) {
                continue;
            }
            if ($ref['kind'] === 'mind') {
                $brief['mind_ids'][] = $row->id;
            } else {
                $brief[['brand' => 'brand_kit_id', 'persona' => 'persona_id', 'agent' => 'agent_id'][$ref['kind']]] = $row->id;
            }
        }

        return MarketingCampaign::firstOrCreate(['user_id' => $request->user()->id, 'workspace_id' => $plan->workspace_id, 'plan_id' => $plan->id], ['project_id' => $plan->project_id, 'name' => mb_substr($plan->title, 0, 160), 'path' => 'quick', 'brief' => $brief, 'studio_ids' => [], 'strategy_ids' => []]);
    }

    public function quote(Request $r, MarketingCampaign $campaign, string $operation, array $data, bool $consume = false): ?string
    {
        $r->validate(['quote' => 'nullable|string|max:100']);
        $fingerprint = hash('sha256', json_encode([$campaign->id, $campaign->brief, $this->brief($campaign, $data['strategy_id'] ?? null), $operation, collect($data)->except('campaign')->all()]));
        if ($consume) {
            $quote = $r->session()->pull('marketing_quote.'.$r->input('quote'));
            abort_unless($quote && $quote['fingerprint'] === $fingerprint && $quote['expires'] >= time(), 422, 'Estimate expired or inputs changed. Estimate again.');

            abort_unless(Cache::add('marketing_quote_claim_'.$r->input('quote'), true, 600), 422, 'This estimate was already used. Estimate again.');

            return null;
        }
        $token = Str::random(40);
        $r->session()->put('marketing_quote.'.$token, ['fingerprint' => $fingerprint, 'expires' => time() + 600]);

        return $token;
    }

    public function connect(MarketingCampaign $campaign, string $kind, int $id): void
    {
        DB::transaction(function () use ($campaign, $kind, $id) {
            $row = MarketingCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $key = $kind === 'studio' ? 'studio_ids' : 'strategy_ids';
            $row->update([$key => array_values(array_unique([...($row->$key ?? []), $id]))]);
        });
    }

    public function materials(MarketingCampaign $campaign): array
    {
        $rows = [];
        foreach (BrandStudioKit::where('user_id', workspace_owner_id())->whereIn('id', $campaign->studio_ids ?? [])->get() as $kit) {
            foreach ($kit->createdAssets() as $asset) {
                $kind = match ($asset['kind'] ?? '') {
                    'biolink','short_link','vcard','calendar','restaurant_menu','store_menu' => 'link','form' => 'form','qr_code' => 'qr',default => null
                };
                if (! $kind) {
                    continue;
                }
                $model = match ($kind) {
                    'link' => Link::class,'form' => Form::class,'qr' => QrCode::class
                };
                $item = $model::where('user_id', workspace_owner_id())->find($asset['id']);
                if (! $item) {
                    continue;
                }
                $rows[$kind.':'.$item->id] = ['studio_id' => $kit->id, 'kind' => $kind, 'id' => $item->id, 'title' => $item->title ?? $item->name, 'active' => (bool) ($item->is_active ?? false), 'clicks' => $kind === 'link' ? (int) $item->total_clicks : null, 'url' => match ($kind) {
                    'link' => match ($item->type) { 'biolink' => route('user.links.blocks.editor', $item), 'calendar' => route('user.calendars.editor', $item), 'restaurant_menu' => route('user.links.restaurant.editor', $item), 'store_menu' => route('user.links.store.editor', $item), default => route('user.links.edit', $item) },'form' => route('user.forms.builder', $item),'qr' => route('user.qr-codes.edit', $item)
                }];
            }
        }

        return array_values($rows);
    }

    public function finalizeDrafts(MarketingCampaign $campaign, int $kitId, Request $request, array $existing = []): void
    {
        $runBrand = BrandStudioKit::where('user_id', workspace_owner_id())->findOrFail($kitId)->brand ?? [];
        $brandId = array_key_exists('brand_kit_id', $runBrand) ? $runBrand['brand_kit_id'] : ($campaign->brief['brand_kit_id'] ?? null);
        $brand = $brandId ? $this->contextModel('brand', (int) $brandId) : null;
        foreach ($this->materials($campaign) as $item) {
            if ($item['studio_id'] !== $kitId || in_array($item['kind'].':'.$item['id'], $existing, true)) {
                continue;
            }
            if ($item['kind'] === 'link') {
                $link = Link::whereKey($item['id'])->where('user_id', workspace_owner_id())->firstOrFail();
                if ($brand) {
                    app(BrandKitAssignments::class)->assign($brand, $link);
                }$link->update(['is_active' => false]);
            }
            if ($item['kind'] === 'form') {
                Form::where('user_id', workspace_owner_id())->whereKey($item['id'])->update(['is_active' => false]);
            }
            if ($brand && $item['kind'] === 'qr') {
                $qr = QrCode::whereKey($item['id'])->where('user_id', workspace_owner_id())->firstOrFail();
                app(BrandKitAssignments::class)->assignQr($brand, $qr);
            }
        }
        $this->returnToPlanner($campaign, $request);
    }

    public function returnToPlanner(MarketingCampaign $campaign, Request $request): void
    {
        if (! $campaign->plan_id) {
            return;
        }
        $plan = app(PlannerResources::class)->get('plan', $campaign->plan_id, $request->user());
        if (! $plan->project_id) {
            $project = Project::create(['user_id' => $request->user()->id, 'name' => $campaign->name, 'description' => 'Connected marketing materials']);
            $plan->update(['project_id' => $project->id]);
        } else {
            $project = Project::findOrFail($plan->project_id);
        }
        $campaign->update(['project_id' => $project->id]);
        foreach ($this->materials($campaign) as $item) {
            $row = app(PlannerResources::class)->get($item['kind'], $item['id'], $request->user());
            $assigned = ProjectItem::where('kind', $item['kind'])->where('resource_id', $item['id'])->value('project_id');
            if (($row->project_id && $row->project_id !== $project->id) || ($assigned && $assigned !== $project->id)) {
                continue;
            }
            app(PlannerResources::class)->attach($project, $item['kind'], $item['id'], $request->user());
        }
        foreach ($campaign->strategy_ids ?? [] as $id) {
            app(PlannerResources::class)->attach($project, 'strategy', $id, $request->user());
        }
        foreach ($campaign->studio_ids ?? [] as $id) {
            if (! BrandStudioKit::where('user_id', $request->user()->id)->whereKey($id)->exists()) {
                continue;
            }
            app(PlannerResources::class)->attach($project, 'studio', $id, $request->user());
        }
    }
}
