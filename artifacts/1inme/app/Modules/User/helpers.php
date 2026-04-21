<?php

if (!function_exists('workspace_owner')) {
    /**
     * Return the User model that owns the current workspace, or the
     * signed-in user when no workspace is bound (CLI, public flows, etc.).
     *
     * Use this in resource controllers in place of `$request->user()` when
     * the call is loading workspace-owned relations like `->forms()` or
     * `->links()` — a team member's own user has no forms/links of its own.
     */
    function workspace_owner(): ?\App\Modules\User\Models\User
    {
        if (app()->bound('workspace_owner')) {
            return app('workspace_owner');
        }
        return auth()->user();
    }
}

if (!function_exists('workspace_owner_id')) {
    /**
     * Return the user id that "owns" the resources visible in the current
     * request — i.e. the active workspace's owner_user_id when one is bound,
     * or the signed-in user's id otherwise.
     *
     * Resource controllers use this in place of `auth()->id()` /
     * `$request->user()->id` so that team members acting inside an owner's
     * workspace see and operate on the owner's data, not their own.
     */
    function workspace_owner_id(): ?int
    {
        if (app()->bound('workspace_owner')) {
            return app('workspace_owner')->id;
        }
        return auth()->id();
    }
}
