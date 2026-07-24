<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
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

    /**
     * Create a new (team) workspace — owner-only, capped by the plan's
     * `max_workspaces` feature. Mirrors the web {@see \App\Modules\User\Controllers\WorkspaceController::store()}:
     * same validation, same `settings.appearance` persistence, and the same
     * plan-limit guard. Over the cap we return a plan-gated 402 carrying the
     * cheapest plan that raises the limit, so the mobile app can route the
     * owner straight to the recommended plan on its /upgrade screen (the
     * native billing/entitlement path that replaces the web team-billing
     * modal). Returns the serialized workspace so the switcher can refresh.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => 'required|string|max:120',
            'icon'  => ['nullable', 'string', Rule::in(array_keys(Workspace::ICON_CHOICES))],
            'color' => ['nullable', 'string', Rule::in(Workspace::COLOR_CHOICES)],
        ]);

        $max   = (int) $user->getPlanFeature('max_workspaces', 1);
        $owned = $user->ownedWorkspaces()->count();
        if ($max !== -1 && $owned >= $max) {
            return $this->planGate(
                "Your plan allows at most {$max} workspace(s). Upgrade to add more.",
                'max_workspaces',
                $user,
                402,
                'plan_upgrade_required',
                $owned,
            );
        }

        // Persist the chosen symbol + colour into the generic settings JSON
        // (no dedicated columns), only when the owner actually picked one;
        // otherwise the workspace falls back to its automatic icon.
        $settings = [];
        if (!empty($data['icon']) || !empty($data['color'])) {
            $settings['appearance'] = array_filter([
                'icon'  => $data['icon'] ?? null,
                'color' => $data['color'] ?? null,
            ]);
        }

        // Workspaces created from the switcher are team workspaces; the
        // personal one is auto-created at registration and is the only
        // is_personal=true row the user owns.
        $ws = $user->ownedWorkspaces()->create([
            'name'        => $data['name'],
            'slug'        => Str::slug($data['name']) . '-' . Str::random(4),
            'is_personal' => false,
            'settings'    => $settings ?: null,
        ]);

        return $this->created(['item' => $this->present($ws->fresh(), (int) $user->id)]);
    }

    /**
     * Make the given workspace the caller's active workspace. The mobile
     * switcher has always POSTed here; the route simply didn't exist, so the
     * switch silently no-oped and the app stayed pinned to the fallback
     * workspace while the web session moved on — the web/app links desync.
     *
     * Persists `users.active_workspace_id`, which the web session resolver
     * (WorkspaceContext) also reads and writes, so switching on either
     * surface keeps the other in sync.
     */
    public function activate(Request $request, int $id)
    {
        $user = $request->user();
        $ws = Workspace::find($id);
        if (!$ws || !$user->belongsToWorkspace($ws)) {
            return $this->fail('Workspace not found.', 404, 'not_found');
        }

        try {
            \Illuminate\Support\Facades\DB::table('users')
                ->where('id', $user->id)
                ->update(['active_workspace_id' => $ws->id]);
        } catch (\Throwable) {
            // Column not migrated yet — treat as a no-op rather than a 500.
        }

        return $this->ok(['item' => $this->present($ws, (int) $user->id)]);
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
                'avatar'  => \App\Support\PublicStorageUrl::resolve($m->user?->avatar),
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

    /**
     * Owner-only: delete a workspace they own. Mirrors the web
     * {@see \App\Modules\User\Controllers\WorkspaceController::destroy()} —
     * the personal workspace can never be deleted, and an owner must always
     * keep at least one workspace, so their last one is protected too.
     * Members and pending invites are cleared first. Returns the refreshed
     * workspace list so the switcher can drop the deleted entry and pick a
     * new active workspace immediately.
     */
    public function destroy(Request $request, int $id)
    {
        $user      = $request->user();
        $workspace = Workspace::find($id);
        if (!$workspace) return $this->notFound('Workspace not found');
        if ((int) $workspace->owner_user_id !== (int) $user->id) {
            return $this->forbidden('Only the workspace owner can delete it');
        }
        if ($workspace->is_personal) {
            return $this->fail('Your personal workspace cannot be deleted.', 422);
        }
        if ($user->ownedWorkspaces()->count() <= 1) {
            return $this->fail('You cannot delete your only workspace.', 422);
        }

        $workspace->members()->delete();
        $workspace->invites()->delete();
        $workspace->delete();

        $memberWorkspaceIds = WorkspaceMember::where('user_id', $user->id)->pluck('workspace_id');
        $items = Workspace::whereIn('id', $memberWorkspaceIds)
            ->orWhere('owner_user_id', $user->id)
            ->orderBy('id')
            ->get()
            ->unique('id')
            ->values()
            ->map(fn ($w) => $this->present($w, (int) $user->id))
            ->values()
            ->all();

        return $this->ok(['items' => $items]);
    }
}
