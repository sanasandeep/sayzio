<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Services\LeadAggregator;
use App\Modules\User\Services\LeadApprover;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class LeadApiController extends Controller
{
    use ApiResponses;

    private function owner(Request $request, bool $write = false)
    {
        $user = $request->user();
        $ws = app(WorkspaceContext::class)->resolve($user) ?? $user->ensureDefaultWorkspace();
        abort_unless((int) $ws->owner_user_id === (int) $user->id || $user->hasPermission('user.workspaces.access_any') || $user->membershipFor($ws)?->can($write ? 'contacts.edit' : 'contacts.view'), 403);

        return $ws->owner;
    }

    public function index(Request $request)
    {
        $owner = $this->owner($request);
        $data = $request->validate(['source' => ['nullable', Rule::in(LeadAggregator::SOURCES)], 'q' => 'nullable|string|max:200', 'page' => 'nullable|integer|min:1']);
        $aggregator = new LeadAggregator($owner->id);
        $page = $aggregator->paginate(['source' => $data['source'] ?? null, 'q' => $data['q'] ?? ''], 25, $data['page'] ?? 1);

        return $this->ok(['items' => $page->items(), 'sources' => LeadAggregator::sourceLabels(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function decide(Request $request)
    {
        $owner = $this->owner($request, true);
        $data = $request->validate(['source_type' => ['required', Rule::in(LeadAggregator::SOURCES)], 'source_id' => 'required|integer|min:1', 'decision' => 'required|in:approve,dismiss']);
        $item = (new LeadAggregator($owner->id))->find($data['source_type'], (int) $data['source_id']);
        if (! $item) {
            return $this->fail('This lead is no longer pending.', 409, 'lead_not_pending');
        }
        try {
            $service = app(LeadApprover::class);
            if ($data['decision'] === 'approve') {
                $service->approve($owner, $item, $request->user()->id);
            } else {
                $service->dismiss($owner, $item, $request->user()->id);
            }
        } catch (\RuntimeException $e) {
            return $this->fail(safe_error_message($e), 422, 'lead_update_failed');
        }

        return $this->ok(['message' => $data['decision'] === 'approve' ? 'Lead added to contacts.' : 'Lead dismissed.']);
    }
}
