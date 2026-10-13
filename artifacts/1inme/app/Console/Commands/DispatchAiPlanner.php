<?php

namespace App\Console\Commands;

use App\Modules\User\Models\NoteActionPlan;
use App\Services\Planner\PlannerEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DispatchAiPlanner extends Command
{
    protected $signature = 'planner:dispatch';

    protected $description = 'Queue due AI Planner reviews with per-plan budgets and schedules';

    public function handle(): int
    {
        Cache::store('database')->put('planner:scheduler-heartbeat', now()->toIso8601String(), 600);
        NoteActionPlan::withoutWorkspaceScope()->where('automation_enabled', true)->where('next_run_at', '<=', now())->orderBy('id')->chunkById(100, function ($plans) {
            foreach ($plans as $plan) {
                DB::transaction(function () use ($plan) {
                    $plan = NoteActionPlan::withoutWorkspaceScope()->whereKey($plan->id)->lockForUpdate()->first();
                    if (! $plan || ! $plan->automation_enabled || ! $plan->next_run_at || $plan->next_run_at->isFuture()) {
                        return;
                    }$key = $plan->next_run_at->toIso8601String();
                    $plan->update(['next_run_at' => app(PlannerEngine::class)->next($plan->planner_settings ?? [])]);
                    app(PlannerEngine::class)->enqueue($plan, $key, 'scheduled');
                });
            }
        });

        return self::SUCCESS;
    }
}
