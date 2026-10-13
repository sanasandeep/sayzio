<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Jobs\RunAiPlanner;
use App\Modules\User\Controllers\AiTypeBuilderController;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\NoteActionPlan;
use App\Modules\User\Models\PlannerHandoff;
use App\Modules\User\Models\PlannerNote;
use App\Modules\User\Models\PlannerRun;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\WorkspaceMember;
use App\Services\AI\AiMindSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\Planner\PlannerEngine;
use App\Services\Planner\PlannerResources;
use App\Services\Planner\PlannerTasks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlannerController extends Controller
{
    public function allow(Request $r, NoteActionPlan $plan, bool $author = false): void
    {
        abort_unless($plan->workspace_id === app('current_workspace')->id && ($plan->user_id === $r->user()->id || (! $author && $plan->shared)), 404);
    }

    public function settings(Request $r, NoteActionPlan $plan)
    {
        $this->allow($r, $plan, true);
        $v = $r->validate(['goal' => 'required|string|max:2000', 'project_id' => 'nullable|integer', 'shared' => 'nullable|boolean', 'resources' => 'nullable|array|max:20', 'resources.*' => ['string', 'regex:/^[a-z]+:[1-9][0-9]*$/'], 'staff_id' => 'nullable|integer', 'board_id' => 'nullable|integer', 'trigger' => 'required|in:schedule,form_submission', 'cadence' => 'required|in:daily,weekly', 'timezone' => 'required|timezone', 'time' => 'required|date_format:H:i', 'weekday' => 'required|integer|min:0|max:6', 'run_budget' => 'required|integer|min:1|max:100000', 'daily_budget' => 'required|integer|min:1|max:1000000', 'monthly_budget' => 'required|integer|min:1|max:10000000', 'automation_enabled' => 'nullable|boolean', 'auto_tasks' => 'nullable|boolean']);
        $refs = [];
        foreach ($v['resources'] ?? [] as $value) {
            [$kind,$id] = array_pad(explode(':', $value, 2), 2, null);
            app(PlannerResources::class)->get($kind, (int) $id, $r->user());
            $refs[] = ['kind' => $kind, 'id' => (int) $id];
        }
        if (! empty($v['staff_id'])) {
            app(PlannerResources::class)->get('staff', $v['staff_id'], $r->user());
        }
        if (! empty($v['board_id'])) {
            app(PlannerResources::class)->get('board', $v['board_id'], $r->user());
        }
        if (! empty($v['project_id'])) {
            $project = Project::where('user_id', workspace_owner_id())->findOrFail($v['project_id']);
        } else {
            $project = null;
        }
        abort_if($r->boolean('automation_enabled') && $v['trigger'] === 'form_submission' && ! collect($refs)->contains(fn ($ref) => $ref['kind'] === 'form'), 422, 'Select at least one form for the submission trigger.');
        abort_if($v['daily_budget'] < $v['run_budget'] || $v['monthly_budget'] < $v['daily_budget'], 422, 'Daily budget must cover one run; monthly budget must cover the daily budget.');
        $s = array_intersect_key($v, array_flip(['goal', 'staff_id', 'board_id', 'trigger', 'cadence', 'timezone', 'time', 'weekday', 'run_budget', 'daily_budget', 'monthly_budget']));
        foreach (['staff_id', 'board_id', 'weekday', 'run_budget', 'daily_budget', 'monthly_budget'] as $key) {
            if (isset($s[$key])) {
                $s[$key] = (int) $s[$key];
            }
        }
        $s['resources'] = $refs;
        $s['auto_tasks'] = $r->boolean('auto_tasks');
        $plan->update(['project_id' => $project?->id, 'shared' => $r->boolean('shared'), 'planner_settings' => $s, 'automation_enabled' => $r->boolean('automation_enabled'), 'next_run_at' => $r->boolean('automation_enabled') && $v['trigger'] === 'schedule' ? app(PlannerEngine::class)->next($s) : null]);
        if ($project) {
            app(PlannerResources::class)->attach($project, 'plan', $plan->id, $r->user());
        }

        return back()->with('success', 'Planner settings saved.');
    }

    public function pause(Request $r, NoteActionPlan $plan)
    {
        $this->allow($r, $plan, true);
        $plan->update(['automation_enabled' => false]);

        return back()->with('success', 'Background work paused. A run already executing may finish.');
    }

    public function estimate(Request $r, NoteActionPlan $plan)
    {
        $this->allow($r, $plan, true);
        $q = app(PlannerEngine::class)->prepare($plan, $r->user());
        $token = (string) Str::uuid();
        Cache::store('database')->put('planner-quote:'.$r->user()->id.':'.$plan->id.':'.$token, ['coins' => $q['coins'], 'hash' => hash('sha256', json_encode([$plan->result, $plan->planner_settings]))], 600);

        return response()->json(['token' => $token, 'estimated_coins' => $q['coins'], 'balance' => app(AiUsageCharger::class)->getBalance($r->user()), 'budget_allowed' => $q['coins'] <= (int) ($plan->planner_settings['run_budget'] ?? PHP_INT_MAX)]);
    }

    public function run(Request $r, NoteActionPlan $plan)
    {
        $this->allow($r, $plan, true);
        $token = $r->validate(['token' => 'required|uuid'])['token'];
        $key = 'planner-quote:'.$r->user()->id.':'.$plan->id.':'.$token;
        $lock = Cache::store('database')->lock($key.':lock', 30);
        abort_unless($lock->get(), 409, 'This review is already being queued.');
        try {
            $q = Cache::store('database')->pull($key);
            abort_unless($q && hash_equals($q['hash'], hash('sha256', json_encode([$plan->result, $plan->planner_settings]))), 422, 'Estimate expired or plan changed. Estimate again.');
            DB::transaction(function () use ($q, $plan, $token) {
                $run = PlannerRun::create(['plan_id' => $plan->id, 'run_key' => $plan->id.':manual:'.$token, 'trigger' => 'manual', 'reserved_coins' => $q['coins']]);
                RunAiPlanner::dispatch($run->id)->afterCommit();
            });

            return response()->json(['message' => 'Run queued. Its result will appear in Activities and notifications.']);
        } finally {
            $lock->release();
        }
    }

    public function applyRun(Request $r, NoteActionPlan $plan, PlannerRun $run)
    {
        $this->allow($r, $plan, true);
        abort_unless($run->plan_id === $plan->id && $run->status === 'needs_review' && $run->result, 422, 'No pending review is available.');
        DB::transaction(function () use ($plan, $run) {
            $run = PlannerRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            abort_unless($run->status === 'needs_review', 409);
            $plan = NoteActionPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $snapshot = $run->result;
            $snapshot['_previous_plan'] = ['title' => $plan->title, 'result' => $plan->result, 'actions' => $plan->actions];
            $actions = $plan->actions ?? [];
            foreach (['form', 'page'] as $key) {
                if (isset($actions[$key])) {
                    $actions['previous_drafts'][] = ['kind' => $key, 'id' => $actions[$key]];
                }unset($actions[$key]);
            }
            $plan->update(['result' => $run->result, 'title' => $run->result['title'], 'actions' => $actions]);
            $run->update(['status' => 'applied', 'result' => $snapshot]);
        });

        return back()->with('success', 'Reviewed plan applied. Existing tasks and resources were kept.');
    }

    public function revert(Request $r, NoteActionPlan $plan, PlannerRun $run)
    {
        $this->allow($r, $plan, true);
        abort_unless($run->plan_id === $plan->id && $run->status === 'applied' && isset($run->result['_previous_plan']), 422, 'This revision cannot be restored.');
        DB::transaction(function () use ($plan, $run) {
            $plan = NoteActionPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $run = PlannerRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            $current = $run->result;
            unset($current['_previous_plan']);
            abort_unless($run->status === 'applied' && $plan->result === $current, 409, 'A later revision exists. Review it first.');
            $old = $run->result['_previous_plan'];
            $plan->update(['title' => $old['title'], 'result' => $old['result']]);
            $run->update(['status' => 'reverted']);
        });

        return back()->with('success', 'Previous plan restored. Tasks and created resources were kept.');
    }

    public function tasks(Request $r, NoteActionPlan $plan)
    {
        $this->allow($r, $plan);
        $v = $r->validate(['indices' => 'required|array|min:1|max:15', 'indices.*' => 'integer|min:0|max:14', 'board_id' => 'nullable|integer', 'due_date' => 'nullable|date', 'assignee_id' => 'nullable|integer']);
        if (! empty($v['assignee_id'])) {
            abort_unless((int) $v['assignee_id'] === app('current_workspace')->owner_user_id || WorkspaceMember::where('workspace_id', app('current_workspace')->id)->where('user_id', $v['assignee_id'])->exists(), 422, 'Assignee must belong to this workspace.');
        }$board = app(PlannerTasks::class)->create($plan, $r->user(), $v['indices'], $v['board_id'] ?? null, $v['due_date'] ?? null, $v['assignee_id'] ?? null);

        return redirect()->route('user.tasks.show', $board)->with('success', 'Tasks created. Existing matching tasks were kept.');
    }

    public function folder(Request $r, Project $project)
    {
        abort_unless($project->user_id === workspace_owner_id(), 404);
        $resources = app(PlannerResources::class)->folder($project, $r->user());
        $notes = PlannerNote::where('project_id', $project->id)->where(fn ($q) => $q->where('user_id', $r->user()->id)->orWhere('shared', true))->whereNull('completed_at')->get();
        $ids = collect($resources)->where('kind', 'plan')->pluck('row.id');

        return view('user.planner.folder', compact('project', 'resources', 'notes') + ['runs' => PlannerRun::whereIn('plan_id', $ids)->latest()->limit(20)->get(), 'choices' => app(PlannerResources::class)->choices($r->user())]);
    }

    public function attach(Request $r, Project $project)
    {
        abort_unless($project->user_id === workspace_owner_id(), 404);
        $v = $r->validate(['items' => 'required|array|max:50', 'items.*' => ['string', 'regex:/^[a-z]+:[1-9][0-9]*$/']]);
        foreach ($v['items'] as $value) {
            [$kind,$id] = array_pad(explode(':', $value, 2), 2, null);
            app(PlannerResources::class)->get($kind, (int) $id, $r->user());
        }DB::transaction(function () use ($v, $project, $r) {
            foreach ($v['items'] as $value) {
                [$kind,$id] = explode(':', $value, 2);
                app(PlannerResources::class)->attach($project, $kind, (int) $id, $r->user());
            }
        });

        return back()->with('success', 'Items moved into this folder.');
    }

    public function note(Request $r)
    {
        $v = $r->validate(['body' => 'required|string|max:3000', 'project_id' => 'nullable|integer', 'plan_id' => 'nullable|integer', 'resource' => ['nullable', 'regex:/^[a-z]+:[1-9][0-9]*$/'], 'shared' => 'nullable|boolean', 'pinned' => 'nullable|boolean']);
        $kind = null;
        $id = null;
        if (! empty($v['project_id'])) {
            Project::where('user_id', workspace_owner_id())->findOrFail($v['project_id']);
        }
        if (! empty($v['plan_id'])) {
            $this->allow($r, NoteActionPlan::findOrFail($v['plan_id']));
        }
        if (! empty($v['resource'])) {
            [$kind,$id] = array_pad(explode(':', $v['resource'], 2), 2, null);
            app(PlannerResources::class)->get($kind, (int) $id, $r->user());
        }
        PlannerNote::create(['user_id' => $r->user()->id, 'workspace_id' => app('current_workspace')->id, 'body' => $v['body'], 'project_id' => $v['project_id'] ?? null, 'plan_id' => $v['plan_id'] ?? null, 'resource_kind' => $kind, 'resource_id' => $id, 'shared' => $r->boolean('shared'), 'pinned' => $r->boolean('pinned')]);

        return back()->with('success', 'Reminder saved.');
    }

    public function updateNote(Request $r, PlannerNote $note)
    {
        abort_unless($note->user_id === $r->user()->id, 404);
        $v = $r->validate(['action' => 'required|in:done,restore,snooze,pin,unpin', 'until' => 'nullable|date|after:now']);
        $attrs = match ($v['action']) {
            'done' => ['completed_at' => now()],'restore' => ['completed_at' => null, 'snoozed_until' => null],'snooze' => ['snoozed_until' => $v['until'] ?? now()->addDay()],'pin' => ['pinned' => true],default => ['pinned' => false]
        };
        $note->update($attrs);

        return back()->with('success', 'Reminder updated.');
    }

    public function tools(): array
    {
        return ['staff' => ['AI Staff', 'user.ai.staff.index'], 'chat' => ['AI Chat', 'user.ai.companion.show'], 'coach' => ['Ask Coach', 'user.ai.ask-coach.show'], 'minds' => ['AI Minds', 'user.minds.index'], 'persona' => ['AI Persona Generator', 'user.ai.persona.show'], 'agents' => ['AI Agents', 'user.ai-personas.index'], 'companions' => ['AI Companions', 'user.ai-companions.index'], 'brand' => ['AI Brand Kit', 'user.brand-kits.index'], 'studio' => ['AI Brand Studio', 'user.brand-studio.index'], 'optimizer' => ['AI Link Optimizer', 'user.ai.coach.show'], 'growth' => ['Growth Insights', 'user.links.index'], 'inbox' => ['AI Inbox Agent', 'user.inbox.unified.index'], 'builders' => ['Specialised page builders', 'user.links.create']];
    }

    public function handoff(Request $r, NoteActionPlan $plan)
    {
        $this->allow($r, $plan);
        $v = $r->validate(['tool' => ['required', Rule::in(array_keys($this->tools()))], 'task_index' => 'nullable|integer|min:0|max:14']);
        $brief = $plan->result['summary'];
        if (isset($v['task_index'])) {
            $task = $plan->result['tasks'][$v['task_index']] ?? null;
            abort_unless($task, 422);
            $brief = $task['title']."\n".$task['details'];
        }$refs = $plan->planner_settings['resources'] ?? [];
        $brief .= "\nGoal: ".($plan->planner_settings['goal'] ?? $plan->title)."\nLinked resources: ".json_encode(array_map(function ($ref) use ($r) {
            $row = app(PlannerResources::class)->get($ref['kind'], $ref['id'], $r->user());

            return ['type' => $ref['kind'], 'name' => app(PlannerResources::class)->label($row), 'url' => app(PlannerResources::class)->url($ref['kind'], $row)];
        }, $refs));
        $handoff = PlannerHandoff::create(['user_id' => $r->user()->id, 'workspace_id' => $plan->workspace_id, 'plan_id' => $plan->id, 'tool' => $v['tool'], 'brief' => mb_substr($brief, 0, 12000)]);
        $route = $this->tools()[$v['tool']][1];
        abort_unless(Route::has($route), 422, 'This tool is not available in this installation.');
        $r->session()->put('planner_handoff', $handoff->id);
        foreach ($refs as $ref) {
            if ($ref['kind'] !== 'link') {
                continue;
            }$link = app(PlannerResources::class)->get('link', $ref['id'], $r->user());
            if ($v['tool'] === 'builders' && isset(AiTypeBuilderController::SERVICES[$link->type])) {
                return redirect()->route('user.links.ai-type-builder', $link);
            }
            if ($v['tool'] === 'builders' && in_array($link->type, Link::BIOLINK_FAMILY)) {
                return redirect()->route('user.links.blocks.editor', $link);
            }
            if ($v['tool'] === 'growth') {
                return redirect()->route('user.links.show', $link);
            }
        }

        return redirect()->route($route);
    }

    public function memory(Request $r, NoteActionPlan $plan)
    {
        $this->allow($r, $plan);
        $v = $r->validate(['mind_id' => 'required|integer']);
        $mind = app(PlannerResources::class)->get('mind', $v['mind_id'], $r->user());
        $caps = AiMindSettings::caps();
        abort_if($mind->sources()->count() >= $caps['max_sources_per_mind'], 422, 'This Mind has reached its source limit.');
        $body = $plan->result['summary']."\nDecisions:\n".implode("\n", $plan->result['decisions'] ?? []);
        AiMindSource::firstOrCreate(['mind_id' => $mind->id, 'title' => mb_substr('Planner decisions: '.$plan->title, 0, 200), 'body' => mb_substr($body, 0, $caps['max_text_chars'])], ['type' => AiMindSource::TYPE_TEXT, 'status' => AiMindSource::STATUS_DISABLED, 'status_message' => 'Saved from AI Planner. Refresh this source to learn it; normal learning charges apply.']);

        return redirect()->route('user.minds.edit', $mind)->with('status', 'Decisions saved as an unlearned source. Refresh when ready; learning can use coins.');
    }

    public function dismiss(Request $r)
    {
        $handoff = PlannerHandoff::where('user_id', $r->user()->id)->find($r->session()->get('planner_handoff'));
        if ($handoff) {
            $handoff->update(['closed_at' => now()]);
        }
        $r->session()->forget('planner_handoff');

        return back()->with('status', 'Planner brief disconnected.');
    }

    public function capture(Request $r)
    {
        $v = $r->validate(['handoff_id' => 'nullable|required_without:plan_id|integer', 'plan_id' => 'nullable|required_without:handoff_id|integer', 'result' => 'required|string|max:20000']);
        if (! empty($v['handoff_id'])) {
            $handoff = PlannerHandoff::where('user_id', $r->user()->id)->findOrFail($v['handoff_id']);
            $plan = NoteActionPlan::findOrFail($handoff->plan_id);
            $this->allow($r, $plan);
            $handoff->update(['result' => $v['result'], 'closed_at' => now()]);
        } else {
            $plan = NoteActionPlan::findOrFail($v['plan_id']);
            $this->allow($r, $plan);
            PlannerHandoff::create(['user_id' => $r->user()->id, 'workspace_id' => $plan->workspace_id, 'plan_id' => $plan->id, 'tool' => 'captured', 'brief' => 'Result added from an AI feature', 'result' => $v['result']]);
        }

        $r->session()->forget('planner_handoff');

        return redirect()->route('user.ai.mind.show', ['record' => $plan->id]);
    }
}
