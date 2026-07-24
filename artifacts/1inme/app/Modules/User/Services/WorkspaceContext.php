<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;

/**
 * Per-request "active workspace" resolver. Stores the active workspace id
 * on the session and exposes lookup helpers for controllers, views and
 * permission middleware.
 */
class WorkspaceContext
{
    public const SESSION_KEY = 'active_workspace_id';

    protected ?Workspace $cached = null;

    public function set(Workspace $ws): void
    {
        session([self::SESSION_KEY => $ws->id]);
        $this->cached = $ws;
        app()->instance('current_workspace', $ws);
        // Persist the choice on the user row so the stateless Sanctum API
        // (mobile app) resolves the SAME active workspace as the web session
        // instead of silently falling back to "first accessible" — the source
        // of web/app links-list desync.
        $this->persist($ws);
    }

    /** Best-effort stamp of users.active_workspace_id (column may lag on old DBs). */
    protected function persist(Workspace $ws): void
    {
        try {
            $user = auth()->user();
            if ($user && (int) ($user->active_workspace_id ?? 0) !== (int) $ws->id) {
                \DB::table('users')->where('id', $user->id)
                    ->update(['active_workspace_id' => $ws->id]);
                $user->active_workspace_id = $ws->id;
            }
        } catch (\Throwable) {
            // Column not migrated yet — session-only behaviour, as before.
        }
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->cached = null;
        if (app()->bound('current_workspace')) {
            app()->forgetInstance('current_workspace');
        }
    }

    /**
     * Resolve the active workspace for the signed-in user, falling back to
     * (a) their oldest owned workspace, then (b) any workspace they're a
     * member of, then (c) lazy-creating a default workspace. Returns null
     * if no user is signed in.
     */
    public function resolve(?User $user): ?Workspace
    {
        if (!$user) return null;
        if ($this->cached) return $this->cached;

        $stored = session(self::SESSION_KEY);
        if ($stored) {
            $ws = Workspace::find($stored);
            if ($ws && $user->belongsToWorkspace($ws)) {
                $this->cached = $ws;
                return $ws;
            }
            // Stored workspace is no longer accessible (member removed,
            // workspace deleted) — fall through to default resolution and
            // overwrite the session pointer below.
        }

        // No session pointer (fresh session) — honour the persisted
        // active workspace (set by web or the mobile app) before falling
        // back to the first accessible workspace.
        $persisted = (int) ($user->active_workspace_id ?? 0);
        if ($persisted) {
            $ws = Workspace::find($persisted);
            if ($ws && $user->belongsToWorkspace($ws)) {
                $this->set($ws);
                return $ws;
            }
        }

        $accessible = $user->accessibleWorkspaces();
        $ws = $accessible->first();

        if (!$ws) {
            $ws = $user->ensureDefaultWorkspace();
        }

        $this->set($ws);
        return $ws;
    }

    /** Get the cached current workspace, or null if none resolved yet. */
    public function current(): ?Workspace
    {
        return $this->cached;
    }
}
