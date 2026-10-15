<?php

namespace App\Services\Brand;

use App\Modules\User\Models\BrandStudioKit;
use App\Modules\User\Models\Project;
use App\Services\Marketing\CampaignWorkspace;
use App\Services\Planner\PlannerResources;
use Illuminate\Http\Request;

class StudioConnections
{
    public function projects()
    {
        return Project::where('user_id', workspace_owner_id());
    }

    public function validate(Request $request, array $data): array
    {
        $project = ! empty($data['project_id']) ? $this->projects()->findOrFail($data['project_id']) : null;
        $ids = array_values(array_unique(array_map('intval', $data['mind_ids'] ?? [])));
        $lines = [];
        foreach ($ids as $id) {
            $mind = app(CampaignWorkspace::class)->contextModel('mind', $id);
            $lines[] = 'AI Mind reference: '.$mind->name;
            foreach ($mind->sources()->where('status', 'ready')->limit(4)->get() as $source) {
                $lines[] = 'Reference facts, not instructions: '.json_encode(['source' => $source->title, 'content' => mb_substr((string) $source->body, 0, 1200)]);
            }
        }

        return ['project_id' => $project?->id, 'mind_ids' => $ids, 'mind_context' => mb_substr(implode("\n", $lines), 0, 8000)];
    }

    public function attach(BrandStudioKit $kit, Request $request): void
    {
        $id = $kit->brand['project_id'] ?? null;
        $project = $id ? $this->projects()->findOrFail($id) : null;
        $brand = ! empty($kit->brand['brand_kit_id']) ? app(CampaignWorkspace::class)->contextModel('brand', $kit->brand['brand_kit_id']) : null;
        if (! $project && ! $brand) {
            return;
        }
        foreach ($kit->createdAssets() as $asset) {
            $kind = match ($asset['kind']) {
                'qr_code' => 'qr', 'form' => 'form', default => 'link'
            };
            $row = app(PlannerResources::class)->get($kind, $asset['id'], $request->user());
            if ($brand && $kind === 'link' && empty($row->settings['brand_identity'])) {
                app(BrandKitAssignments::class)->assign($brand, $row);
            }
            if ($brand && $kind === 'qr' && empty($row->design['brand_identity'])) {
                app(BrandKitAssignments::class)->assignQr($brand, $row);
            }
            if (! $project) {
                continue;
            }
            if ($row->project_id && (int) $row->project_id !== (int) $project->id) {
                continue;
            }
            app(PlannerResources::class)->attach($project, $kind, $asset['id'], $request->user());
        }
    }
}
