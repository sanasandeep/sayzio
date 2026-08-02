<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\EventBroadcast;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\EventBroadcastService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile parity for the "Message guests" broadcast surface. Delegates
 * recipient resolution + fan-out + logging to EventBroadcastService.
 * Workspace-aware access mirrors EventTicketApiController.
 */
class EventBroadcastApiController extends Controller
{
    use ApiResponses;

    public function __construct(private EventBroadcastService $service) {}

    /** GET: past broadcasts + live per-audience recipient counts. */
    public function index(Request $request, int $linkId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = $this->findEventLink($request, $linkId);
        if (!$link) return $this->notFound();
        if (!$this->canAct($request, $link, 'links.view')) return $this->forbidden();

        return $this->ok([
            'counts'     => $this->service->audienceCounts($link),
            'broadcasts' => $this->service->history($link)->map(fn (EventBroadcast $b) => $this->shape($b))->all(),
        ]);
    }

    /** POST: send a broadcast to the chosen audience. */
    public function store(Request $request, int $linkId)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        $link = $this->findEventLink($request, $linkId);
        if (!$link) return $this->notFound();
        if (!$this->canAct($request, $link, 'links.edit')) return $this->forbidden();

        $data = $request->validate([
            'audience' => ['required', 'string', 'in:' . implode(',', EventBroadcastService::AUDIENCES)],
            'subject'  => ['required', 'string', 'max:200'],
            'message'  => ['required', 'string', 'max:5000'],
        ]);

        try {
            $broadcast = $this->service->send(
                $link,
                (int) $user->id,
                $data['audience'],
                $data['subject'],
                $data['message'],
            );
        } catch (\App\Modules\User\Services\EventBroadcastLimitException $e) {
            return $this->fail($e->getMessage(), 429, 'broadcast_rate_limited');
        }

        return $this->created($this->shape($broadcast));
    }

    protected function shape(EventBroadcast $b): array
    {
        return [
            'id'               => $b->id,
            'audience'         => $b->audience,
            'audience_label'   => EventBroadcast::AUDIENCES[$b->audience] ?? $b->audience,
            'subject'          => $b->subject,
            'message'          => $b->message,
            'recipients_count' => $b->recipients_count,
            'created_at'       => $b->created_at?->toIso8601String(),
        ];
    }

    // ─── Workspace-aware access (mirrors EventTicketApiController) ───

    protected function findEventLink(Request $request, int $id): ?Link
    {
        $user = $request->user();

        $link = Link::where('user_id', $user->id)->where('type', 'ics')->find($id);
        if ($link) return $link;

        $workspaceIds = $this->accessibleWorkspaceIds($user);
        if (empty($workspaceIds)) return null;

        return Link::where('type', 'ics')->whereIn('workspace_id', $workspaceIds)->find($id);
    }

    protected function accessibleWorkspaceIds($user): array
    {
        if (!Schema::hasColumn('links', 'workspace_id')) return [];

        return $user->accessibleWorkspaces()->pluck('id')->all();
    }

    protected function canAct(Request $request, Link $link, string $permission): bool
    {
        $user = $request->user();

        if ((int) $link->user_id === (int) $user->id) return true;
        if (empty($link->workspace_id)) return true;

        $workspace = Workspace::find($link->workspace_id);
        if (!$workspace) return true;

        return $user->canInWorkspace($workspace, $permission);
    }
}
