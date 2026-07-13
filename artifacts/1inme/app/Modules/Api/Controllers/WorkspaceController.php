<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WorkspaceController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $memberWorkspaceIds = WorkspaceMember::where('user_id', $userId)->pluck('workspace_id');
        $items = Workspace::whereIn('id', $memberWorkspaceIds)
            ->orWhere('owner_user_id', $userId)
            ->orderBy('id')
            ->get()
            ->unique('id')
            ->values()
            ->map(fn ($w) => [
                'id'         => $w->id,
                'name'       => $w->name,
                'slug'       => $w->slug ?? null,
                'is_personal'=> (bool) ($w->is_personal ?? false),
                'owner_user_id' => $w->owner_user_id,
                'is_owner'   => (int) $w->owner_user_id === (int) $userId,
                'icon'       => $w->iconKey(),
                'color'      => $w->iconColor(),
                'created_at' => optional($w->created_at)->toIso8601String(),
            ])->values()->all();
        return $this->ok(['items' => $items]);
    }

    public function members(Request $request, int $id)
    {
        $userId = $request->user()->id;
        $isMember = WorkspaceMember::where('workspace_id', $id)->where('user_id', $userId)->exists();
        $isOwner  = Workspace::where('id', $id)->where('owner_user_id', $userId)->exists();
        if (!$isMember && !$isOwner) return $this->forbidden('Not a workspace member');

        $items = WorkspaceMember::where('workspace_id', $id)
            ->with('user')
            ->get()
            ->map(fn ($m) => [
                'id'      => $m->id,
                'user_id' => $m->user_id,
                'role'    => $m->role,
                'name'    => $m->user?->name,
                'email'   => $m->user?->email,
                'avatar'  => $m->user?->avatar,
                'created_at' => optional($m->created_at)->toIso8601String(),
            ])->all();
        return $this->ok(['items' => $items]);
    }
}
