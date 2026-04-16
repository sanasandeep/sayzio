<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Services\LinkPerformanceCoach;
use Illuminate\Http\Request;

/**
 * Admin settings for the workspace-wide Performance Coach defaults.
 *
 * Controls which preset new / untuned links start from, lets the admin
 * fine-tune threshold values, and lets them publish custom presets that
 * appear alongside the built-in ones in the per-link picker.
 */
class CoachDefaultsController extends Controller
{
    public function edit()
    {
        $admin = LinkPerformanceCoach::adminSettings();
        $preset = $admin['preset'] ?? 'creator';
        $overrides = is_array($admin['overrides'] ?? null) ? $admin['overrides'] : [];
        $customPresets = LinkPerformanceCoach::adminCustomPresets();

        // Effective values for the custom-threshold row: start from the
        // creator baseline and apply whatever overrides are stored so fields
        // are pre-filled with sensible numbers on first load.
        $effective = LinkPerformanceCoach::PRESETS['creator']['values'];
        foreach (LinkPerformanceCoach::TUNABLE_KEYS as $k) {
            if (isset($overrides[$k]) && is_numeric($overrides[$k])) {
                $effective[$k] = (float) $overrides[$k];
            }
        }

        return view('admin.coach-defaults.edit', [
            'preset'        => $preset,
            'effective'     => $effective,
            'builtinPresets'=> LinkPerformanceCoach::PRESETS,
            'customPresets' => $customPresets,
            'tunableKeys'   => LinkPerformanceCoach::TUNABLE_KEYS,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'preset' => 'required|string|max:64',
            'overrides' => 'nullable|array',
            'overrides.ctr_critical' => 'nullable|numeric|between:0,1',
            'overrides.ctr_warning' => 'nullable|numeric|between:0,1',
            'overrides.ctr_excellent' => 'nullable|numeric|between:0,1',
            'overrides.bounce_critical' => 'nullable|numeric|between:0,100',
            'overrides.bounce_warning' => 'nullable|numeric|between:0,100',
            'overrides.bounce_excellent' => 'nullable|numeric|between:0,100',
            'overrides.engagement_low_seconds' => 'nullable|numeric|between:1,600',
            'overrides.engagement_excellent_seconds' => 'nullable|numeric|between:1,600',
            'overrides.momentum_drop_critical' => 'nullable|numeric|between:-1,0',
            'overrides.momentum_drop_warning' => 'nullable|numeric|between:-1,0',
            'overrides.momentum_win_threshold' => 'nullable|numeric|between:0,5',
            'custom_presets' => 'nullable|array',
            'custom_presets.*.key' => 'nullable|string|max:32',
            'custom_presets.*.label' => 'nullable|string|max:64',
            'custom_presets.*.description' => 'nullable|string|max:160',
            'custom_presets.*.values' => 'nullable|array',
        ]);

        // Drop rows where the admin left both key and label blank (empty
        // "add another preset" placeholders in the form).
        $customs = [];
        foreach (($validated['custom_presets'] ?? []) as $row) {
            $k = trim((string) ($row['key'] ?? ''));
            $l = trim((string) ($row['label'] ?? ''));
            if ($k === '' && $l === '') continue;
            $customs[] = $row;
        }

        LinkPerformanceCoach::saveAdminSettings(
            $validated['preset'],
            $validated['overrides'] ?? [],
            $customs,
        );

        return redirect()
            ->route('admin.coach-defaults.edit')
            ->with('success', 'Performance Coach defaults saved.');
    }
}
