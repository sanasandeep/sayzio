<?php

namespace App\Modules\User\Controllers;

use App\Modules\Common\Services\Carbon\CarbonSettingsResolver;
use App\Modules\User\Models\BiolinkCarbonSnapshot;
use App\Modules\User\Models\CarbonOffsetPurchase;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Sustainability dashboard + per-link / per-workspace settings
 * editor. Public methodology page is its own thin action so it
 * can be linked from the badge popover.
 */
class CarbonController extends Controller
{
    public function __construct(private CarbonSettingsResolver $settings) {}

    public function index(Request $request)
    {
        $workspace = $this->currentWorkspace($request);
        abort_unless($workspace, 403);

        $snapshots = BiolinkCarbonSnapshot::query()
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('period_start')
            ->limit(36)
            ->get();

        $purchases = CarbonOffsetPurchase::query()
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('purchased_at')
            ->limit(50)
            ->get();

        $byLink = $snapshots->groupBy('link_id');
        $links  = Link::query()->whereIn('id', $byLink->keys())->get()->keyBy('id');

        $perLink = $byLink->map(function ($rows, $linkId) use ($links) {
            $link = $links->get($linkId);
            return [
                'link'           => $link,
                'snapshots'      => $rows,
                'grams_total'    => round($rows->sum('grams_co2'), 2),
                'grams_offset'   => round($rows->sum('grams_offset'), 2),
                'last_snapshot'  => $rows->first(),
            ];
        })->values();

        $totals = [
            'grams_co2'    => round($snapshots->sum('grams_co2'), 2),
            'grams_offset' => round($snapshots->sum('grams_offset'), 2),
            'cost_minor'   => (int) $purchases->sum('cost_minor'),
            'currency'     => optional($purchases->first())->currency ?? 'USD',
            'certificates' => $purchases->whereNotNull('certificate_url')->count(),
        ];

        $workspaceDefaults = $this->settings->workspaceDefaults($workspace);

        return view('user.carbon.dashboard', compact(
            'workspace', 'perLink', 'totals', 'purchases', 'snapshots', 'workspaceDefaults'
        ));
    }

    public function methodology()
    {
        return view('user.carbon.methodology');
    }

    public function updateWorkspace(Request $request)
    {
        $workspace = $this->currentWorkspace($request);
        abort_unless($workspace, 403);
        abort_unless($this->canManage($request, $workspace), 403);

        $data = $this->validateSettings($request);
        $settings = (array) $workspace->settings;
        $settings['carbon'] = $data;
        $workspace->settings = $settings;
        $workspace->save();

        return back()->with('success', 'Workspace sustainability defaults saved.');
    }

    public function updateLink(Request $request, Link $link)
    {
        abort_unless($request->user() && (int) $request->user()->id === (int) $link->user_id, 403);

        $data = $this->validateSettings($request);
        $settings = (array) $link->settings;
        $settings['carbon'] = $data;
        $link->settings = $settings;
        $link->save();

        return back()->with('success', 'Carbon settings updated for this biolink.');
    }

    private function validateSettings(Request $request): array
    {
        $v = $request->validate([
            'enabled'          => ['nullable', 'boolean'],
            'monthly_budget'   => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'fallback'         => ['nullable', 'in:pause,partial'],
            'badge_visible'    => ['nullable', 'boolean'],
        ]);
        return [
            'enabled'              => (bool) ($v['enabled'] ?? false),
            'monthly_budget_minor' => (int) round(((float) ($v['monthly_budget'] ?? 0)) * 100),
            'fallback'             => $v['fallback'] ?? 'pause',
            'badge_visible'        => (bool) ($v['badge_visible'] ?? true),
            'currency'             => 'USD',
        ];
    }

    private function currentWorkspace(Request $request): ?Workspace
    {
        if (app()->bound('current_workspace')) {
            $ws = app('current_workspace');
            if ($ws) return $ws;
        }
        $user = $request->user();
        if (!$user) return null;
        return Workspace::where('owner_user_id', $user->id)->first();
    }

    private function canManage(Request $request, Workspace $workspace): bool
    {
        $user = $request->user();
        return $user && (int) $user->id === (int) $workspace->owner_user_id;
    }
}
