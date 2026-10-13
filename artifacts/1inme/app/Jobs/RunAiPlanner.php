<?php

namespace App\Jobs;

use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Controllers\AI\MindController;
use App\Modules\User\Models\NoteActionPlan;
use App\Modules\User\Models\PlannerRun;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Modules\User\Models\Workspace;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use App\Services\Planner\PlannerEngine;
use App\Services\Planner\PlannerTasks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class RunAiPlanner implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public $failOnTimeout = true;

    public $timeout = 180;

    public function __construct(public int $runId) {}

    public function handle(): void
    {
        $run = PlannerRun::find($this->runId);
        if (! $run || $run->status !== 'queued') {
            return;
        }
        $plan = NoteActionPlan::withoutWorkspaceScope()->find($run->plan_id);
        if (! $plan) {
            return;
        }
        $actor = User::find($plan->user_id);
        $ws = Workspace::find($plan->workspace_id);
        $bindings = [];
        foreach (['current_workspace', 'workspace_owner'] as $key) {
            $bindings[$key] = app()->bound($key) ? app($key) : null;
        }$previous = Auth::user();
        $out = null;
        try {
            abort_unless($actor && $ws && $actor->belongsToWorkspace($ws) && $actor->canInWorkspace($ws, 'create'), 403, 'Planner author no longer has permission.');
            app()->instance('current_workspace', $ws);
            app()->instance('workspace_owner', User::findOrFail($ws->owner_user_id));
            Auth::setUser($actor);
            abort_unless($run->trigger === 'manual' || $plan->automation_enabled, 422, 'Automation is paused.');
            $quote = app(PlannerEngine::class)->prepare($plan, $actor);
            $s = $plan->planner_settings ?? [];
            $max = $run->trigger === 'manual' ? min(max(1, $run->reserved_coins), (int) ($s['run_budget'] ?? max(1, $run->reserved_coins))) : (int) ($s['run_budget'] ?? 0);
            DB::transaction(function () use ($run, $plan, $quote, $actor, $s, $max) {
                $locked = NoteActionPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
                $claim = PlannerRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
                abort_unless($claim->status === 'queued', 409, 'Run already claimed.');
                $tz = $s['timezone'] ?? 'UTC';
                $others = PlannerRun::where('plan_id', $plan->id)->where('id', '!=', $run->id);
                $daily = (clone $others)->where('created_at', '>=', now($tz)->startOfDay()->utc())->get()->sum(fn ($r) => $r->status === 'running' ? $r->reserved_coins : $r->coins_spent);
                $monthly = (clone $others)->where('created_at', '>=', now($tz)->startOfMonth()->utc())->get()->sum(fn ($r) => $r->status === 'running' ? $r->reserved_coins : $r->coins_spent);
                abort_if($quote['coins'] > $max || $daily + $quote['coins'] > (int) ($s['daily_budget'] ?? 10000) || $monthly + $quote['coins'] > (int) ($s['monthly_budget'] ?? 100000), 422, 'Coin budget reached. Increase the budget and explicitly resume.');
                abort_if(app(AiUsageCharger::class)->getBalance($actor) < $quote['coins'], 422, 'Insufficient coins.');
                $run->update(['status' => 'running', 'reserved_coins' => $quote['coins'], 'started_at' => now(), 'feature' => $quote['feature']]);
            });
            $out = app(OpenAiService::class)->chat($actor, $quote['model'], $quote['messages'], ['feature' => $quote['feature'], 'related_id' => $quote['staff']?->id ?? $plan->id, 'meta' => ['planner_run_id' => $run->id, 'planner_plan_id' => $plan->id], 'max_tokens' => 3500, 'temperature' => .2, 'response_format' => ['type' => 'json_object'], 'reason' => 'AI Planner background review']);
            abort_if(($out['credits_spent'] ?? 0) > $quote['coins'], 422, 'Provider exceeded the estimate. Charged coins were refunded.');
            $result = app(MindController::class)->normalize(json_decode($out['content'], true, 512, JSON_THROW_ON_ERROR));
            $result['_metrics'] = $quote['metrics'];
            $run->update(['status' => 'needs_review', 'coins_spent' => $out['credits_spent'] ?? 0, 'result' => $result, 'finished_at' => now(), 'message' => 'Review the generated plan before applying changes.']);
            if ($quote['staff']) {
                $quote['staff']->markUsed();
            }
            // Only internal tasks are eligible for opt-in automatic application. Content remains a draft.
            if (! empty($s['auto_tasks'])) {
                try {
                    app(PlannerTasks::class)->create($plan, $actor, array_keys($plan->result['tasks'] ?? []), $s['board_id'] ?? null);
                } catch (\Throwable $e) {
                    $run->update(['message' => 'Review saved. Approved task creation could not complete; check the selected board and permissions.']);
                    report($e);
                }
            }
        } catch (\Throwable $e) {
            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 409) {
                return;
            }
            if ($out && ($out['credits_spent'] ?? 0) > 0 && $run->fresh()->status !== 'needs_review') {
                app(AiUsageCharger::class)->refund($actor, $out['credits_spent'], ['feature' => $run->feature, 'related_id' => $plan->id, 'meta' => ['planner_run_id' => $run->id, 'planner_plan_id' => $plan->id], 'reason' => 'Failed Planner run refund', 'idempotency_key' => 'planner-run-refund:'.$run->id]);
            }
            $run->refresh();
            if ($run->status !== 'needs_review') {
                $run->update(['status' => 'failed', 'coins_spent' => 0, 'finished_at' => now(), 'message' => mb_substr($e instanceof HttpExceptionInterface ? $e->getMessage() : 'The AI run failed. No generated output was applied. Retry after checking configuration.', 0, 1000)]);
            }
            if ($run->trigger !== 'manual') {
                $plan->update(['automation_enabled' => false]);
            }report($e);
        } finally {
            if ($actor && $run->fresh()?->finished_at) {
                try {
                    app(NotificationService::class)->notify($actor, 'planner_run', ['title' => 'AI Planner: '.$plan->title, 'message' => $run->fresh()->message, 'url' => route('user.ai.mind.show', ['record' => $plan->id])]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            foreach ($bindings as $key => $value) {
                if ($value) {
                    app()->instance($key, $value);
                } else {
                    app()->forgetInstance($key);
                }
            }if ($previous) {
                Auth::setUser($previous);
            } else {
                Auth::forgetGuards();
            }
        }
    }

    public function failed(?\Throwable $e): void
    {
        DB::transaction(function () {
            $run = PlannerRun::whereKey($this->runId)->lockForUpdate()->first();
            if (! $run || ! in_array($run->status, ['queued', 'running'])) {
                return;
            }
            $plan = NoteActionPlan::withoutWorkspaceScope()->find($run->plan_id);
            $actor = $plan ? User::find($plan->user_id) : null;
            if ($actor) {
                $spent = -(int) WalletTransaction::where('user_id', $actor->id)->where('type', 'spend')->where('meta->planner_run_id', $run->id)->sum('delta_coins');
                if ($spent > 0) {
                    app(AiUsageCharger::class)->refund($actor, $spent, ['feature' => $run->feature, 'meta' => ['planner_run_id' => $run->id], 'reason' => 'Interrupted Planner run refund', 'idempotency_key' => 'planner-run-refund:'.$run->id]);
                }
            }
            $run->update(['status' => 'failed', 'coins_spent' => 0, 'finished_at' => now(), 'message' => 'Worker stopped before completion. Any recorded run charges were refunded. Review before retrying.']);
            if ($plan && $run->trigger !== 'manual') {
                $plan->update(['automation_enabled' => false]);
            }
        });
    }
}
