<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\FanLeaderboardSetting;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\FanPointsEngine;
use Illuminate\Http\Request;

/**
 * Creator-side controller for the per-biolink fan leaderboard.
 * Lets the creator toggle the leaderboard, tweak point rules, and
 * define perks attached to top ranks. Public rendering uses the same
 * underlying settings via FanPointsEngine.
 */
class FanLeaderboardController extends Controller
{
    public function __construct(private FanPointsEngine $points) {}

    private function authorize(Request $request, Link $link): void
    {
        abort_if($link->user_id !== $request->user()->id, 403);
    }

    public function edit(Request $request, Link $link)
    {
        $this->authorize($request, $link);

        $settings = FanLeaderboardSetting::firstOrNew(
            ['link_id' => $link->id],
            [
                'user_id'      => $request->user()->id,
                'workspace_id' => $link->workspace_id ?? null,
                'is_enabled'   => false,
                'point_rules'  => FanLeaderboardSetting::defaultRules(),
                'perks'        => [],
                'top_n'        => 10,
            ]
        );

        $top = $this->points->topFans($link, $settings->top_n ?? 10);

        return view('user.leaderboard.edit', compact('link', 'settings', 'top'));
    }

    public function update(Request $request, Link $link)
    {
        $this->authorize($request, $link);

        $data = $request->validate([
            'is_enabled'            => ['nullable', 'boolean'],
            'show_anonymous_option' => ['nullable', 'boolean'],
            'top_n'                 => ['required', 'integer', 'min:3', 'max:100'],
            'point_rules'           => ['nullable', 'array'],
            'point_rules.*'         => ['integer', 'min:0', 'max:1000'],
            'perks'                 => ['nullable', 'array'],
            'perks.*.rank'          => ['required_with:perks', 'integer', 'min:1'],
            'perks.*.label'         => ['required_with:perks', 'string', 'max:255'],
        ]);

        $settings = FanLeaderboardSetting::firstOrNew(['link_id' => $link->id]);
        $settings->user_id      = $request->user()->id;
        $settings->workspace_id = $link->workspace_id ?? null;
        $settings->is_enabled   = (bool)($data['is_enabled'] ?? false);
        $settings->show_anonymous_option = (bool)($data['show_anonymous_option'] ?? true);
        $settings->top_n        = (int)$data['top_n'];
        $settings->point_rules  = array_merge(
            FanLeaderboardSetting::defaultRules(),
            $data['point_rules'] ?? []
        );
        $settings->perks        = array_values($data['perks'] ?? []);
        $settings->save();

        return back()->with('success', 'Leaderboard updated.');
    }
}
