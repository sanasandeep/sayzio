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
        $plans = DB::table('plans')->get();
        foreach ($plans as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?: [];
            $tierDefaults = $this->tierDefaultsFor($plan->slug);
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
        // Per CONTRIBUTING.md "Backfill / seed migration down() policy":
        // only strip the keys we added if their value still equals the
        // seeded default for that plan tier. Any drift means a curator
        // edited the limit and we must preserve their edit.
        $plans = DB::table('plans')->get();
        foreach ($plans as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?: [];
            $tierDefaults = $this->tierDefaultsFor($plan->slug);
            $changed = false;
            foreach ($tierDefaults as $k => $v) {
                if (array_key_exists($k, $features) && $features[$k] === $v) {
                    unset($features[$k]);
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

    /**
     * Per-tier defaults. Unknown slugs get the same defaults as `free`.
     */
    private function tierDefaultsFor(?string $slug): array
    {
        $defaults = [
            'free'       => ['max_workspaces' => 1, 'max_seats_per_workspace' => 1],
            'starter'    => ['max_workspaces' => 1, 'max_seats_per_workspace' => 2],
            'pro'        => ['max_workspaces' => 2, 'max_seats_per_workspace' => 3],
            'business'   => ['max_workspaces' => 5, 'max_seats_per_workspace' => 10],
            'enterprise' => ['max_workspaces' => -1, 'max_seats_per_workspace' => -1],
        ];
        return $defaults[$slug] ?? $defaults['free'];
    }
};
