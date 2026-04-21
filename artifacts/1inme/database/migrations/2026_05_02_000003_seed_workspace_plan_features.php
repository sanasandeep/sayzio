<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add `max_workspaces` and `max_seats_per_workspace` to every plan's
     * `features` JSON, with sensible defaults that scale with tier. Curator
     * edits are preserved — we only fill in keys that are missing.
     */
    public function up(): void
    {
        $defaults = [
            'free'       => ['max_workspaces' => 1, 'max_seats_per_workspace' => 1],
            'starter'    => ['max_workspaces' => 1, 'max_seats_per_workspace' => 2],
            'pro'        => ['max_workspaces' => 2, 'max_seats_per_workspace' => 3],
            'business'   => ['max_workspaces' => 5, 'max_seats_per_workspace' => 10],
            'enterprise' => ['max_workspaces' => -1, 'max_seats_per_workspace' => -1],
        ];

        $plans = DB::table('plans')->get();
        foreach ($plans as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?: [];
            $tierDefaults = $defaults[$plan->slug] ?? ['max_workspaces' => 1, 'max_seats_per_workspace' => 1];
            $changed = false;
            foreach ($tierDefaults as $k => $v) {
                if (!array_key_exists($k, $features)) {
                    $features[$k] = $v;
                    $changed = true;
                }
            }
            if ($changed) {
                DB::table('plans')->where('id', $plan->id)->update([
                    'features' => json_encode($features),
                ]);
            }
        }
    }

    public function down(): void
    {
        $plans = DB::table('plans')->get();
        foreach ($plans as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?: [];
            unset($features['max_workspaces'], $features['max_seats_per_workspace']);
            DB::table('plans')->where('id', $plan->id)->update([
                'features' => json_encode($features),
            ]);
        }
    }
};
