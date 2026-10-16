<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Support\PlanVariantResolver;
use Database\Seeders\PlansAndAddonsSeeder;
use Illuminate\Console\Command;

class AuditPublicPlans extends Command
{
    protected $signature = 'plans:audit-public';
    protected $description = 'Read-only audit of saved public plan limits, pricing groups and parent domain switches';

    public function handle(): int
    {
        $issues = [];
        $plans = Plan::query()->get()->keyBy('slug');
        foreach (PlansAndAddonsSeeder::publicPlanLadder() as $slug => $expected) {
            $plan = $plans->get($slug);
            if (!$plan) {
                $issues[] = [$slug, 'plan', 'missing'];
                continue;
            }
            foreach ($expected as $key => $value) {
                $actual = ($plan->features ?? [])[$key] ?? null;
                if ($actual !== $value) {
                    $issues[] = [$slug, $key, json_encode($actual).' (expected '.json_encode($value).')'];
                }
            }
        }
        // Category siblings count as grouped: a variant belongs to whatever
        // band its anchor does. Checking raw slugs reported every generated
        // variant as missing, which is noise that hides a real omission.
        $grouped = collect(PlanVariantResolver::expandGroups(PlansAndAddonsSeeder::publicPlanGroups()))
            ->pluck('slugs')->flatten()->all();
        foreach ($plans as $plan) {
            if (!$plan->is_internal && !$plan->is_archived && $plan->status === 'active'
                && !in_array($plan->slug, $grouped, true)) {
                $issues[] = [$plan->slug, 'pricing group', 'missing'];
            }
        }
        if ($issues) {
            $this->table(['Plan', 'Setting', 'Saved value'], $issues);
            $this->error('Public plan settings differ from the approved configuration. No settings were changed.');
            return self::FAILURE;
        }
        $this->info('Public plan settings match the approved configuration. No settings were changed.');
        return self::SUCCESS;
    }
}
