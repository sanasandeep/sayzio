<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceInvite;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Public landing page for a workspace invite link. Handles both flows:
 *   - Existing user: log in, then click "Accept" to join.
 *   - New user: bounced through OTP signup (using the invited email),
 *     then auto-joined on completion via the cookie attribution helper.
 */
class AcceptInviteController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invite = WorkspaceInvite::where('token', $token)->first();
        if (!$invite || !$invite->isPending()) {
            return view('user.workspaces.invite-invalid');
        }

        $invite->load('workspace.owner', 'inviter');
        $user = $request->user();
        $loggedInWithRightEmail = $user && strcasecmp($user->email, $invite->email) === 0;

        return view('user.workspaces.invite-show', compact('invite', 'user', 'loggedInWithRightEmail'));
    }

    public function accept(Request $request, string $token, WorkspaceContext $ctx)
    {
        $invite = WorkspaceInvite::where('token', $token)->firstOrFail();
        abort_unless($invite->isPending(), 410, 'This invite is no longer valid.');

        $user = $request->user();
        if (!$user) {
            // Stash invite token so OTP signup can attribute on completion.
            session(['pending_workspace_invite' => $token]);
            return redirect()->route('user.register', ['email' => $invite->email])
                ->with('info', 'Sign up with ' . $invite->email . ' to accept your invite.');
        }

        if (strcasecmp($user->email, $invite->email) !== 0) {
            return back()->with('error', "This invite is for {$invite->email} — please sign in with that email.");
        }

        // Already a member? Just mark the invite accepted and switch.
        $existing = WorkspaceMember::where('workspace_id', $invite->workspace_id)
            ->where('user_id', $user->id)->first();
        if (!$existing) {
            WorkspaceMember::create([
                'workspace_id' => $invite->workspace_id,
                'user_id'      => $user->id,
                'role'         => $invite->role,
                'permissions'  => $invite->permissions,
            ]);
        }
        $invite->update(['accepted_at' => now()]);
        session()->forget('pending_workspace_invite');

        $ws = $invite->workspace;
        if ($ws) $ctx->set($ws);

        // Notify the inviter (best-effort).
        try {
            $inviterEmail = optional($invite->inviter)->email;
            if ($inviterEmail) {
                \App\Modules\Common\Services\Emailer::send('workspace.invite_accepted', $inviterEmail, [
                    'user_name'      => $user->name,
                    'user_email'     => $user->email,
                    'workspace_name' => $ws->name,
                ], ['user' => optional($invite->inviter)->id, 'related' => $ws]);
            }
        } catch (\Throwable $e) {}

        return redirect()->route('user.dashboard')->with('success', "Welcome to {$ws->name}.");
    }

    /**
     * Hook called from the auth controller right after a successful
     * registration/OTP-verify to auto-join any pending invite the user had
     * stashed in session. Safe to call when no invite is pending.
     */
    public static function attachPendingInvite(?User $user): void
    {
        if (!$user) return;
        $token = session('pending_workspace_invite');
        if (!$token) return;
        $invite = WorkspaceInvite::where('token', $token)->first();
        if (!$invite || !$invite->isPending()) {
            session()->forget('pending_workspace_invite');
            return;
        }
        if (strcasecmp($user->email, $invite->email) !== 0) return;

        if (!WorkspaceMember::where('workspace_id', $invite->workspace_id)->where('user_id', $user->id)->exists()) {
            WorkspaceMember::create([
                'workspace_id' => $invite->workspace_id,
                'user_id'      => $user->id,
                'role'         => $invite->role,
                'permissions'  => $invite->permissions,
            ]);
        }
        $invite->update(['accepted_at' => now()]);
        session()->forget('pending_workspace_invite');
        session([\App\Modules\User\Services\WorkspaceContext::SESSION_KEY => $invite->workspace_id]);
    }
}
