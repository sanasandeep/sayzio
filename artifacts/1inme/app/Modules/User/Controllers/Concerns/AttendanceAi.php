<?php

namespace App\Modules\User\Controllers\Concerns;

use App\Modules\User\Models\AiStaff;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiStaffRuntime;
use App\Services\AI\PeopleAiContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait AttendanceAi
{
    protected function peopleAiData(Request $request, $ws): array
    {
        if ($this->actorGuard() !== 'web') {
            return ['staff' => collect(), 'drafts' => collect()];
        }

        return ['staff' => AiStaff::where('user_id', $this->actor($request)->id)->where('is_disabled', false)->get(['id', 'name', 'config']), 'drafts' => DB::table('attendance_ai_drafts')->where('workspace_id', $ws->id)->where('actor_user_id', $this->actor($request)->id)->latest()->limit(30)->get()];
    }

    protected function draftPayload(string $kind, array $data): array
    {
        $rules = $kind === 'roster'
            ? ['staff_ids' => 'required|array|min:1|max:100', 'staff_ids.*' => 'integer|distinct', 'kind' => 'required|in:work,off', 'shift_id' => 'nullable|required_if:kind,work|integer', 'starts_on' => 'required|date_format:Y-m-d', 'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on', 'note' => 'required|string|min:3|max:2000']
            : ['leave_type_id' => 'required|integer', 'starts_on' => 'required|date_format:Y-m-d', 'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on', 'reason' => 'required|string|min:3|max:2000'];

        return Validator::make($data, $rules)->validate();
    }

    public function preparePeopleDraft(Request $request)
    {
        $ws = $this->workspace($request);
        abort_unless($this->actorGuard() === 'web' && $this->attendance->policy($ws)['ai_people_enabled'], 403);
        $data = $request->validate(['ai_staff_id' => 'required|integer', 'draft_kind' => 'required|in:roster,leave', 'prompt' => 'required|string|min:5|max:4000']);
        $this->workspace($request, $data['draft_kind'] === 'roster' ? 'manage' : 'create');
        $staff = AiStaff::where('user_id', $this->actor($request)->id)->where('is_disabled', false)->findOrFail($data['ai_staff_id']);
        abort_unless($this->actor($request)->canInWorkspace($ws, 'ai-agents.run'), 403, 'Your workspace role cannot run AI Staff.');
        abort_unless(AiEngineSettings::isEnabled(), 404);
        abort_unless(AiPlanAccess::featureAllowed($this->actor($request), $staff->featureKey()), 403, 'This AI Staff domain is not available on your plan.');
        abort_if(($staff->config['connections']['mode'] ?? 'draft') === 'read', 403, 'Choose an AI Staff member with draft access.');
        $context = app(PeopleAiContext::class)->snapshot($this->actor($request));
        if ($data['draft_kind'] === 'roster') {
            $context .= ' Allowed workspace employees: '.json_encode($this->directory($ws)->map->only(['id', 'name'])->all()).' Shifts: '.DB::table('attendance_shifts')->where('workspace_id', $ws->id)->where('active', true)->get(['id', 'name', 'starts_at', 'ends_at'])->toJson();
        }
        try {
            $payload = app(AiStaffRuntime::class)->draftPeople($this->actor($request), $staff, $data['draft_kind'], $data['prompt'], $context);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['prompt' => 'The AI could not prepare a complete draft. Specify the dates and leave type or shift, then try again.']);
        }
        $payload = $this->draftPayload($data['draft_kind'], $payload);
        DB::table('attendance_ai_drafts')->insert(['workspace_id' => $ws->id, 'actor_user_id' => $this->actor($request)->id, 'ai_staff_id' => $staff->id, 'kind' => $data['draft_kind'], 'status' => 'pending', 'payload' => json_encode($payload), 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'AI draft prepared. Review every field before approving.');
    }

    public function decidePeopleDraft(Request $request, int $draft)
    {
        $ws = $this->workspace($request);
        abort_unless($this->actorGuard() === 'web', 403);
        $decision = $request->validate(['decision' => 'required|in:apply,dismiss'])['decision'];
        DB::transaction(function () use ($request, $ws, $draft, $decision) {
            $row = DB::table('attendance_ai_drafts')->where('workspace_id', $ws->id)->where('actor_user_id', $this->actor($request)->id)->where('id', $draft)->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_unless($row->status === 'pending', 409);
            if ($decision === 'apply') {
                abort_unless($this->attendance->policy($ws)['ai_people_enabled'], 403);
                $staff = AiStaff::where('user_id', $this->actor($request)->id)->where('is_disabled', false)->findOrFail($row->ai_staff_id);
                abort_if(($staff->config['connections']['mode'] ?? 'draft') === 'read', 403);
                $payload = $this->draftPayload($row->kind, json_decode($row->payload, true));
                if ($row->kind === 'leave') {
                    $payload['type'] = 'leave';
                }
                $forward = Request::create($request->url(), 'POST', $payload, [], [], $request->server->all());
                $forward->setUserResolver(fn () => $this->actor($request));
                if ($row->kind === 'roster') {
                    $this->saveRoster($forward);
                } else {
                    $this->storeRequest($forward);
                }
            }
            DB::table('attendance_ai_drafts')->where('id', $row->id)->update(['status' => $decision === 'apply' ? 'applied' : 'dismissed', 'updated_at' => now()]);
            $this->audit($ws, $request, 'ai_draft', $row->id, ['decision' => $decision, 'note' => 'Human reviewed the AI people draft.']);
        });

        return back()->with('success', $decision === 'apply' ? 'Draft applied. Leave requests still need a separate reviewer.' : 'Draft dismissed.');
    }
}
