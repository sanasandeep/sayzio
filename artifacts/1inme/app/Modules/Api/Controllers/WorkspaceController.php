<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    use ApiResponses;

    /** Serialize a workspace into the same shape the index() list uses. */
    protected function present(Workspace $w, int $userId): array
    {
        return [
            'id'         => $w->id,
            'name'       => $w->name,
            'slug'       => $w->slug ?? null,
            'is_personal'=> (bool) ($w->is_personal ?? false),
            'owner_user_id' => $w->owner_user_id,
            'is_owner'   => (int) $w->owner_user_id === (int) $userId,
            'icon'       => $w->iconKey(),
            'color'      => $w->iconColor(),
            'created_at' => optional($w->created_at)->toIso8601String(),
        ];
    }

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

    /**
     * Owner-only: rename a workspace and/or update its appearance (icon +
     * colour). Mirrors the web {@see \App\Modules\User\Controllers\WorkspaceController::update()}
     * — same validation, same `settings.appearance` persistence and activity
     * log — so the native mobile edit surface stays in lockstep with the web
     * settings page. Returns the re-serialized workspace so the caller can
     * refresh the switcher immediately.
     */
    public function update(Request $request, int $id)
    {
        $userId    = $request->user()->id;
        $workspace = Workspace::find($id);
        if (!$workspace) return $this->notFound('Workspace not found');
        if ((int) $workspace->owner_user_id !== (int) $userId) {
            return $this->forbidden('Only the workspace owner can edit it');
        }

        $data = $request->validate([
            'name'  => 'required|string|max:120',
            'icon'  => ['nullable', 'string', Rule::in(array_keys(Workspace::ICON_CHOICES))],
            'color' => ['nullable', 'string', Rule::in(Workspace::COLOR_CHOICES)],
        ]);

        $previousName = $workspace->name;

        $settings   = $workspace->settings ?? [];
        $appearance = $settings['appearance'] ?? [];
        if (!empty($data['icon'])) {
            $appearance['icon'] = $data['icon'];
        }
        if (!empty($data['color'])) {
            $appearance['color'] = $data['color'];
        }
        $settings['appearance'] = $appearance;

        $workspace->update([
            'name'     => $data['name'],
            'settings' => $settings,
        ]);

        WorkspaceActivityRecorder::record($workspace, 'workspace.update', 'workspace', $workspace->id, $workspace->name, null, [
            'from_name' => $previousName, 'to_name' => $data['name'],
        ]);

        return $this->ok(['item' => $this->present($workspace->fresh(), (int) $userId)]);
    }
}
